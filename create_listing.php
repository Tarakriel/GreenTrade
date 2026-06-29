<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/includes/db.php";

$message = "";
$messageType = "";

function columnExists($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (!tableExists($conn, "listings")) {
    $conn->query("
        CREATE TABLE listings (
            listing_id INT AUTO_INCREMENT PRIMARY KEY,
            seller_id INT NOT NULL,
            title VARCHAR(150) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            category VARCHAR(100),
            item_condition VARCHAR(100),
            location VARCHAR(150),
            image VARCHAR(255),
            status VARCHAR(50) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

$uploadDir = __DIR__ . "/uploads/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $sellerId = $_SESSION["user_id"];
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price = trim($_POST["price"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $condition = trim($_POST["item_condition"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $imageName = "";

    if ($title === "" || $price === "") {
        $message = "Please enter the item title and price.";
        $messageType = "error";
    } elseif (!is_numeric($price)) {
        $message = "Please enter a valid price.";
        $messageType = "error";
    } else {
        if (!empty($_FILES["image"]["name"])) {
            $allowedTypes = ["jpg", "jpeg", "png", "gif", "webp"];
            $originalName = $_FILES["image"]["name"];
            $fileTmp = $_FILES["image"]["tmp_name"];
            $fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($fileExt, $allowedTypes)) {
                $message = "Only JPG, JPEG, PNG, GIF and WEBP images are allowed.";
                $messageType = "error";
            } else {
                $imageName = time() . "_" . preg_replace("/[^a-zA-Z0-9._-]/", "_", $originalName);
                $targetFile = $uploadDir . $imageName;

                if (!move_uploaded_file($fileTmp, $targetFile)) {
                    $message = "Image upload failed. Please try again.";
                    $messageType = "error";
                }
            }
        }

        if ($message === "") {
            $hasSellerId = columnExists($conn, "listings", "seller_id");
            $hasTitle = columnExists($conn, "listings", "title");
            $hasDescription = columnExists($conn, "listings", "description");
            $hasPrice = columnExists($conn, "listings", "price");
            $hasCategory = columnExists($conn, "listings", "category");
            $hasCondition = columnExists($conn, "listings", "item_condition");
            $hasLocation = columnExists($conn, "listings", "location");
            $hasImage = columnExists($conn, "listings", "image");
            $hasStatus = columnExists($conn, "listings", "status");

            $columns = [];
            $placeholders = [];
            $values = [];
            $types = "";

            if ($hasSellerId) {
                $columns[] = "seller_id";
                $placeholders[] = "?";
                $values[] = $sellerId;
                $types .= "i";
            }

            if ($hasTitle) {
                $columns[] = "title";
                $placeholders[] = "?";
                $values[] = $title;
                $types .= "s";
            }

            if ($hasDescription) {
                $columns[] = "description";
                $placeholders[] = "?";
                $values[] = $description;
                $types .= "s";
            }

            if ($hasPrice) {
                $columns[] = "price";
                $placeholders[] = "?";
                $values[] = $price;
                $types .= "d";
            }

            if ($hasCategory) {
                $columns[] = "category";
                $placeholders[] = "?";
                $values[] = $category;
                $types .= "s";
            }

            if ($hasCondition) {
                $columns[] = "item_condition";
                $placeholders[] = "?";
                $values[] = $condition;
                $types .= "s";
            }

            if ($hasLocation) {
                $columns[] = "location";
                $placeholders[] = "?";
                $values[] = $location;
                $types .= "s";
            }

            if ($hasImage) {
                $columns[] = "image";
                $placeholders[] = "?";
                $values[] = $imageName;
                $types .= "s";
            }

            if ($hasStatus) {
                $status = "pending";
                $columns[] = "status";
                $placeholders[] = "?";
                $values[] = $status;
                $types .= "s";
            }

            $sql = "INSERT INTO listings (`" . implode("`, `", $columns) . "`) VALUES (" . implode(", ", $placeholders) . ")";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param($types, ...$values);

                if ($stmt->execute()) {
                    header("Location: my_listings.php?success=1");
                    exit;
                } else {
                    $message = "Failed to add listing: " . $stmt->error;
                    $messageType = "error";
                }
            } else {
                $message = "Database error: " . $conn->error;
                $messageType = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Item - GreenTrade</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f8f4;
            color: #1f2933;
        }

        .navbar {
            background: #0f5a1f;
            color: white;
            padding: 18px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar h2 {
            margin: 0;
            font-size: 24px;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 18px;
            font-size: 15px;
        }

        .navbar a:hover {
            text-decoration: underline;
        }

        .container {
            max-width: 720px;
            margin: 45px auto;
            background: white;
            padding: 35px;
            border-radius: 14px;
            border: 1px solid #d5e6d5;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }

        h1 {
            margin-top: 0;
            color: #0f5a1f;
            text-align: center;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #cfd8cf;
            border-radius: 8px;
            font-size: 15px;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #0f5a1f;
        }

        .btn {
            width: 100%;
            background: #0f5a1f;
            color: white;
            border: none;
            padding: 13px;
            border-radius: 9px;
            font-weight: bold;
            font-size: 15px;
            cursor: pointer;
        }

        .btn:hover {
            background: #0b4418;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 18px;
            text-align: center;
        }

        .success {
            background: #dff3e3;
            color: #0f5a1f;
        }

        .error {
            background: #fdecea;
            color: #8a1c1c;
        }

        .links {
            text-align: center;
            margin-top: 18px;
        }

        .links a {
            color: #0f5a1f;
            font-weight: bold;
            text-decoration: none;
        }

        @media (max-width: 600px) {
            .navbar {
                flex-direction: column;
                gap: 10px;
            }

            .container {
                margin: 25px 15px;
                padding: 25px;
            }
        }
    </style>
</head>
<body>

<div class="navbar">
    <h2>GreenTrade</h2>
    <div>
        <a href="index.php">Home</a>
        <a href="listing.php">Items</a>
        <a href="my_listings.php">My Items</a>
        <a href="orders.php">Orders</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h1>Add New Item</h1>

    <?php if (!empty($message)): ?>
        <div class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="create_listing.php" enctype="multipart/form-data">
        <div class="form-group">
            <label for="title">Item Title</label>
            <input type="text" name="title" id="title" required>
        </div>

        <div class="form-group">
            <label for="description">Item Description</label>
            <textarea name="description" id="description"></textarea>
        </div>

        <div class="form-group">
            <label for="price">Price</label>
            <input type="number" name="price" id="price" step="0.01" min="0" required>
        </div>

        <div class="form-group">
            <label for="category">Category</label>
            <input type="text" name="category" id="category" placeholder="Example: Beauty, Baby, Clothing">
        </div>

        <div class="form-group">
            <label for="item_condition">Condition</label>
            <select name="item_condition" id="item_condition">
                <option value="">Select condition</option>
                <option value="New">New</option>
                <option value="Pre-loved">Pre-loved</option>
                <option value="Recycled">Recycled</option>
                <option value="Eco-friendly">Eco-friendly</option>
            </select>
        </div>

        <div class="form-group">
            <label for="location">Location</label>
            <input type="text" name="location" id="location" placeholder="Example: Cape Town">
        </div>

        <div class="form-group">
            <label for="image">Item Image</label>
            <input type="file" name="image" id="image" accept="image/*">
        </div>

        <button type="submit" class="btn">Add Item</button>
    </form>

    <div class="links">
        <a href="my_listings.php">Back to My Items</a>
    </div>
</div>

</body>
</html>

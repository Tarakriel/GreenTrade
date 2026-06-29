<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/includes/db.php";
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

function columnExists($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

function firstExistingColumn($conn, $table, $columns) {
    foreach ($columns as $column) {
        if (columnExists($conn, $table, $column)) {
            return $column;
        }
    }

    return null;
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];

if (!tableExists($conn, "listings")) {
    die("Listings table does not exist.");
}

if (!tableExists($conn, "orders")) {
    $conn->query("
        CREATE TABLE orders (
            order_id INT AUTO_INCREMENT PRIMARY KEY,
            buyer_id INT NOT NULL,
            listing_id INT NOT NULL,
            total_amount DECIMAL(10,2) DEFAULT 0,
            status VARCHAR(50) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/*
|--------------------------------------------------------------------------
| Detect listing columns
|--------------------------------------------------------------------------
*/
$listingIdCol = firstExistingColumn($conn, "listings", [
    "listing_id",
    "id"
]);

$listingTitleCol = firstExistingColumn($conn, "listings", [
    "title",
    "name",
    "listing_title"
]);

$listingPriceCol = firstExistingColumn($conn, "listings", [
    "price",
    "amount",
    "selling_price"
]);

$listingImageCol = firstExistingColumn($conn, "listings", [
    "image",
    "image_path",
    "photo",
    "picture"
]);

$listingSellerCol = firstExistingColumn($conn, "listings", [
    "seller_id",
    "user_id"
]);

if (!$listingIdCol) {
    die("No listing ID column found in listings table.");
}

if (!$listingTitleCol) {
    die("No listing title/name column found in listings table.");
}

/*
|--------------------------------------------------------------------------
| Get listing ID from URL
|--------------------------------------------------------------------------
*/
$listingId = 0;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $listingId = (int) $_GET['id'];
} elseif (isset($_GET['listing_id']) && is_numeric($_GET['listing_id'])) {
    $listingId = (int) $_GET['listing_id'];
}

if ($listingId <= 0) {
    header("Location: listing.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch listing
|--------------------------------------------------------------------------
*/
$selectParts = [];
$selectParts[] = "`$listingIdCol` AS listing_id";
$selectParts[] = "`$listingTitleCol` AS title";

if ($listingPriceCol) {
    $selectParts[] = "`$listingPriceCol` AS price";
} else {
    $selectParts[] = "0 AS price";
}

if ($listingImageCol) {
    $selectParts[] = "`$listingImageCol` AS image";
} else {
    $selectParts[] = "'' AS image";
}

if ($listingSellerCol) {
    $selectParts[] = "`$listingSellerCol` AS seller_id";
} else {
    $selectParts[] = "0 AS seller_id";
}

$sql = "
    SELECT " . implode(", ", $selectParts) . "
    FROM listings
    WHERE `$listingIdCol` = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Listing query prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $listingId);
$stmt->execute();

$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Invalid Listing - GreenTrade</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body style="background:#f4f8f5;">
        <div class="container mt-5">
            <div class="alert alert-danger">
                Invalid listing selected.
            </div>
            <a href="listing.php" class="btn btn-success">Back to Listings</a>
            <a href="index.php" class="btn btn-secondary">Back to Main Site</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$item = $result->fetch_assoc();

$itemSellerId = (int)($item['seller_id'] ?? 0);

if ($itemSellerId > 0 && $itemSellerId === $userId) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Checkout - GreenTrade</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body style="background:#f4f8f5;">
        <div class="container mt-5">
            <div class="alert alert-warning">
                You cannot buy your own listing.
            </div>
            <a href="listing.php?id=<?= e($listingId) ?>" class="btn btn-success">Back to Listing</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/*
|--------------------------------------------------------------------------
| Detect orders columns
|--------------------------------------------------------------------------
*/
$orderUserCol = firstExistingColumn($conn, "orders", [
    "user_id",
    "buyer_id",
    "customer_id",
    "client_id",
    "orders_user_id"
]);

$orderListingCol = firstExistingColumn($conn, "orders", [
    "listing_id",
    "product_id",
    "item_id"
]);

$orderTotalCol = firstExistingColumn($conn, "orders", [
    "total_amount",
    "total",
    "grand_total",
    "amount",
    "price"
]);

$orderStatusCol = firstExistingColumn($conn, "orders", [
    "status",
    "order_status",
    "payment_status"
]);

$orderDateCol = firstExistingColumn($conn, "orders", [
    "created_at",
    "order_date",
    "date",
    "created_on"
]);

if (!$orderUserCol) {
    $conn->query("ALTER TABLE orders ADD COLUMN buyer_id INT NOT NULL DEFAULT 0");
    $orderUserCol = "buyer_id";
}

if (!$orderListingCol) {
    $conn->query("ALTER TABLE orders ADD COLUMN listing_id INT NOT NULL DEFAULT 0");
    $orderListingCol = "listing_id";
}

if (!$orderTotalCol) {
    $conn->query("ALTER TABLE orders ADD COLUMN total_amount DECIMAL(10,2) DEFAULT 0");
    $orderTotalCol = "total_amount";
}

if (!$orderStatusCol) {
    $conn->query("ALTER TABLE orders ADD COLUMN status VARCHAR(50) DEFAULT 'pending'");
    $orderStatusCol = "status";
}

if (!$orderDateCol) {
    $conn->query("ALTER TABLE orders ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    $orderDateCol = "created_at";
}

$title = $item['title'] ?? 'Listing';
$price = (float)($item['price'] ?? 0);
$image = $item['image'] ?? '';

/*
|--------------------------------------------------------------------------
| Place order
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $status = "pending";

    $insertColumns = [];
    $placeholders = [];
    $types = "";
    $values = [];

    $insertColumns[] = "`$orderUserCol`";
    $placeholders[] = "?";
    $types .= "i";
    $values[] = $userId;

    $insertColumns[] = "`$orderListingCol`";
    $placeholders[] = "?";
    $types .= "i";
    $values[] = $listingId;

    $insertColumns[] = "`$orderTotalCol`";
    $placeholders[] = "?";
    $types .= "d";
    $values[] = $price;

    $insertColumns[] = "`$orderStatusCol`";
    $placeholders[] = "?";
    $types .= "s";
    $values[] = $status;

    $insertSql = "
        INSERT INTO orders 
        (" . implode(", ", $insertColumns) . ")
        VALUES 
        (" . implode(", ", $placeholders) . ")
    ";

    $insertStmt = $conn->prepare($insertSql);

    if (!$insertStmt) {
        die("Order insert prepare failed: " . $conn->error . "<br><br>SQL: " . e($insertSql));
    }

    $insertStmt->bind_param($types, ...$values);

    if (!$insertStmt->execute()) {
        die("Order could not be placed: " . $insertStmt->error);
    }

    header("Location: orders.php?success=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout - GreenTrade</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link 
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" 
        rel="stylesheet"
    >

    <style>
        body {
            margin: 0;
            background: #f4f8f5;
            font-family: Arial, sans-serif;
            color: #1f2933;
        }

        .navbar-custom {
            background: #1b5e20;
            padding: 14px 0;
            box-shadow: 0 2px 12px rgba(0,0,0,.12);
        }

        .navbar-custom a {
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
        }

        .page-wrap {
            max-width: 950px;
            margin: 40px auto;
            padding: 0 18px;
        }

        .page-header {
            background: linear-gradient(135deg, #1b5e20, #43a047);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 26px;
            box-shadow: 0 4px 16px rgba(0,0,0,.12);
        }

        .checkout-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 3px 16px rgba(0,0,0,.08);
        }

        .item-img {
            width: 160px;
            height: 160px;
            object-fit: cover;
            border-radius: 14px;
            background: #e9f3eb;
            border: 1px solid #d7e8da;
        }

        .price {
            color: #1b5e20;
            font-size: 26px;
            font-weight: 800;
        }

        .btn-green {
            background: #1b5e20;
            color: white;
            border: none;
            border-radius: 9px;
            padding: 11px 16px;
            font-weight: 700;
            text-decoration: none;
        }

        .btn-green:hover {
            background: #124116;
            color: white;
        }

        .btn-outline-green {
            background: transparent;
            color: #1b5e20;
            border: 1px solid #1b5e20;
            border-radius: 9px;
            padding: 10px 15px;
            font-weight: 700;
            text-decoration: none;
        }

        .btn-outline-green:hover {
            background: #1b5e20;
            color: white;
        }

        .summary-box {
            background: #f7fbf8;
            border-radius: 14px;
            padding: 18px;
            border: 1px solid #d7e8da;
        }
    </style>
</head>
<body>

<nav class="navbar-custom">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="index.php" class="fs-5">GreenTrade</a>

        <div>
            <a href="index.php" class="me-3">Home</a>
            <a href="listing.php" class="me-3">Listings</a>
            <a href="orders.php" class="me-3">Orders</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="page-wrap">

    <div class="page-header">
        <h1 class="mb-1">Checkout</h1>
        <p class="mb-0">Review your order before confirming.</p>
    </div>

    <div class="checkout-card">
        <div class="row g-4 align-items-center">
            <div class="col-md-3">
                <?php if (!empty($image)): ?>
                    <img src="uploads/<?= e($image) ?>" class="item-img" alt="<?= e($title) ?>">
                <?php else: ?>
                    <div class="item-img d-flex align-items-center justify-content-center text-muted">
                        No image
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-md-9">
                <h3><?= e($title) ?></h3>

                <div class="price mb-3">
                    R<?= number_format($price, 2) ?>
                </div>

                <div class="summary-box mb-4">
                    <p class="mb-2">
                        <strong>Listing ID:</strong> <?= e($listingId) ?>
                    </p>

                    <p class="mb-2">
                        <strong>Total:</strong> R<?= number_format($price, 2) ?>
                    </p>

                    <p class="mb-0">
                        <strong>Status:</strong> Pending
                    </p>
                </div>

                <form method="post">
                    <button type="submit" name="place_order" class="btn-green">
                        Place Order
                    </button>

                    <a href="listing.php?id=<?= e($listingId) ?>" class="btn-outline-green ms-2">
                        Back to Listing
                    </a>

                    <a href="listing.php" class="btn-outline-green ms-2">
                        Browse Listings
                    </a>
                </form>
            </div>
        </div>
    </div>

</div>

<script 
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>
</html>

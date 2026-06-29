<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/includes/db.php";

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

function getImagePath($image) {
    $image = trim((string)$image);

    if ($image === "") {
        return "";
    }

    if (
        strpos($image, "http://") === 0 ||
        strpos($image, "https://") === 0 ||
        strpos($image, "uploads/") === 0 ||
        strpos($image, "/uploads/") === 0
    ) {
        return $image;
    }

    return "uploads/" . $image;
}

$listings = [];
$idColumn = "listing_id";
$titleColumn = null;
$priceColumn = null;
$imageColumn = null;

if (tableExists($conn, "listings")) {
    $idColumn = firstExistingColumn($conn, "listings", ["listing_id", "id"]) ?? "listing_id";
    $titleColumn = firstExistingColumn($conn, "listings", ["title", "item_name", "name"]);
    $priceColumn = firstExistingColumn($conn, "listings", ["price", "amount", "item_price"]);
    $imageColumn = firstExistingColumn($conn, "listings", ["image", "image_url", "photo", "picture"]);

    $query = "SELECT * FROM listings ORDER BY `$idColumn` DESC LIMIT 6";
    $result = $conn->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $listings[] = $row;
        }
    }
}

$isLoggedIn = isset($_SESSION["user_id"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GreenTrade</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f8f4;
            color: #1f2933;
        }

        .navbar {
            background: #0f5a1f;
            color: #ffffff;
            padding: 18px 45px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar h2 {
            margin: 0;
            font-size: 25px;
            letter-spacing: 0.4px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .nav-links a {
            color: #ffffff;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
        }

        .nav-links a:hover {
            text-decoration: underline;
        }

        .page-wrap {
            max-width: 1200px;
            margin: 0 auto;
            padding: 35px 22px 70px;
        }

        .hero {
            background: #166322;
            color: #ffffff;
            border-radius: 18px;
            padding: 42px 36px;
            margin-bottom: 38px;
            box-shadow: 0 5px 16px rgba(0,0,0,0.08);
        }

        .hero h1 {
            margin: 0 0 15px;
            font-size: 36px;
            line-height: 1.2;
        }

        .hero p {
            margin: 0;
            font-size: 19px;
            line-height: 1.5;
            max-width: 720px;
        }

        .hero-buttons {
            margin-top: 28px;
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            background: #0f5a1f;
            color: #ffffff;
            padding: 13px 22px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            border: none;
            cursor: pointer;
            text-align: center;
        }

        .btn:hover {
            background: #0b4418;
        }

        .btn-light {
            background: #ffffff;
            color: #0f5a1f;
        }

        .btn-light:hover {
            background: #ecf7ed;
        }

        .section-title {
            font-size: 30px;
            margin: 0 0 24px;
            color: #1f2933;
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }

        .item-card {
            background: #ffffff;
            border: 1px solid #d5e6d5;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .image-box {
            height: 210px;
            background: #dff0e2;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #607460;
            font-size: 15px;
            overflow: hidden;
        }

        .image-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .item-body {
            padding: 18px;
        }

        .item-body h3 {
            margin: 0 0 10px;
            font-size: 20px;
            color: #1f2933;
        }

        .price {
            color: #0f5a1f;
            font-weight: bold;
            font-size: 22px;
            margin-bottom: 16px;
        }

        .view-btn {
            width: 100%;
        }

        .empty-box {
            background: #ffffff;
            border: 1px solid #d5e6d5;
            padding: 25px;
            border-radius: 14px;
            color: #4b5563;
            font-size: 16px;
        }

        footer {
            background: #0f5a1f;
            color: #ffffff;
            text-align: center;
            padding: 18px;
            margin-top: 50px;
        }

        @media (max-width: 950px) {
            .items-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .navbar {
                padding: 18px 25px;
                align-items: flex-start;
                flex-direction: column;
                gap: 12px;
            }

            .nav-links {
                gap: 12px;
            }
        }

        @media (max-width: 620px) {
            .items-grid {
                grid-template-columns: 1fr;
            }

            .page-wrap {
                padding: 25px 15px 50px;
            }

            .hero {
                padding: 32px 24px;
            }

            .hero h1 {
                font-size: 29px;
            }

            .hero p {
                font-size: 17px;
            }

            .navbar h2 {
                font-size: 23px;
            }
        }
    </style>
</head>
<body>

<header class="navbar">
    <h2>GreenTrade</h2>

    <nav class="nav-links">
        <a href="index.php">Home</a>
        <a href="listing.php">Items</a>
        <a href="my_listings.php">My Items</a>
        <a href="orders.php">Orders</a>

        <?php if ($isLoggedIn): ?>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>

        <a href="admin/dashboard.php">Admin</a>
    </nav>
</header>

<main class="page-wrap">

    <section class="hero">
        <h1>Welcome to GreenTrade</h1>
        <p>Buy and sell eco-friendly, recycled, pre-loved and new items.</p>

        <div class="hero-buttons">
            <?php if ($isLoggedIn): ?>
                <a class="btn btn-light" href="create_listing.php">Add New Item</a>
            <?php else: ?>
                <a class="btn btn-light" href="register.php">Join GreenTrade</a>
            <?php endif; ?>

            <a class="btn" href="listing.php">Browse Items</a>
        </div>
    </section>

    <h2 class="section-title">Latest Items</h2>

    <?php if (!empty($listings)): ?>
        <section class="items-grid">
            <?php foreach ($listings as $item): ?>
                <?php
                    $itemId = $item[$idColumn] ?? 0;
                    $title = $titleColumn && isset($item[$titleColumn]) ? $item[$titleColumn] : "Item";
                    $price = $priceColumn && isset($item[$priceColumn]) ? $item[$priceColumn] : 0;
                    $image = $imageColumn && isset($item[$imageColumn]) ? $item[$imageColumn] : "";
                    $imagePath = getImagePath($image);
                ?>

                <article class="item-card">
                    <div class="image-box">
                        <?php if (!empty($imagePath)): ?>
                            <img 
                                src="<?php echo htmlspecialchars($imagePath); ?>" 
                                alt="<?php echo htmlspecialchars($title); ?>"
                                onerror="this.style.display='none'; this.parentElement.innerHTML='Image';"
                            >
                        <?php else: ?>
                            Image
                        <?php endif; ?>
                    </div>

                    <div class="item-body">
                        <h3><?php echo htmlspecialchars($title); ?></h3>
                        <div class="price">R<?php echo number_format((float)$price, 2); ?></div>
                        <a class="btn view-btn" href="listing.php?id=<?php echo urlencode($itemId); ?>">View Item</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php else: ?>
        <div class="empty-box">
            No listings have been added yet.
        </div>
    <?php endif; ?>

</main>

<footer>
    &copy; <?php echo date("Y"); ?> GreenTrade. All rights reserved.
</footer>

</body>
</html>

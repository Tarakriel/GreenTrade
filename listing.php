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

if (!tableExists($conn, "listings")) {
    die("Listings table does not exist.");
}

$idColumn = columnExists($conn, "listings", "listing_id") ? "listing_id" : "id";

$requestedId = 0;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $requestedId = (int) $_GET['id'];
} elseif (isset($_GET['listing_id']) && is_numeric($_GET['listing_id'])) {
    $requestedId = (int) $_GET['listing_id'];
}

$isLoggedIn = isset($_SESSION['user_id']);
$currentUserId = $isLoggedIn ? (int) $_SESSION['user_id'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Listings - GreenTrade</title>
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
            max-width: 1180px;
            margin: 38px auto;
            padding: 0 18px;
        }

        .page-header {
            background: linear-gradient(135deg, #1b5e20, #43a047);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 26px;
            box-shadow: 0 4px 16px rgba(0,0,0,.12);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .page-header h1 {
            margin: 0;
            font-weight: 700;
        }

        .page-header p {
            margin: 6px 0 0;
            opacity: .95;
        }

        .btn-green {
            background: #1b5e20;
            color: white;
            border: none;
            border-radius: 9px;
            padding: 10px 14px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }

        .btn-green:hover {
            background: #124116;
            color: white;
        }

        .btn-light-green {
            background: white;
            color: #1b5e20;
            border: none;
            border-radius: 9px;
            padding: 10px 14px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }

        .btn-light-green:hover {
            background: #e8f5e9;
            color: #124116;
        }

        .btn-outline-green {
            background: transparent;
            color: #1b5e20;
            border: 1px solid #1b5e20;
            border-radius: 9px;
            padding: 9px 13px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }

        .btn-outline-green:hover {
            background: #1b5e20;
            color: white;
        }

        .listing-card {
            background: white;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 3px 16px rgba(0,0,0,.08);
            height: 100%;
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .listing-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,.12);
        }

        .listing-img {
            width: 100%;
            height: 230px;
            object-fit: cover;
            background: #e9f3eb;
        }

        .listing-body {
            padding: 18px;
        }

        .listing-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1f2933;
        }

        .price {
            color: #1b5e20;
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .badge-green {
            background: #e8f5e9;
            color: #1b5e20;
            padding: 6px 10px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 13px;
            display: inline-block;
        }

        .detail-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 3px 16px rgba(0,0,0,.08);
        }

        .detail-img {
            width: 100%;
            max-height: 520px;
            object-fit: cover;
            border-radius: 18px;
            background: #e9f3eb;
            box-shadow: 0 3px 16px rgba(0,0,0,.08);
        }

        .meta-row {
            padding: 10px 0;
            border-bottom: 1px solid #edf2ef;
        }

        .meta-row strong {
            color: #1b5e20;
        }

        .empty-box {
            background: white;
            border-radius: 18px;
            padding: 35px;
            text-align: center;
            box-shadow: 0 3px 16px rgba(0,0,0,.08);
        }

        .footer {
            text-align: center;
            color: #6b7280;
            margin: 35px 0 20px;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .page-wrap {
                margin: 22px auto;
            }

            .page-header {
                padding: 22px;
            }

            .page-header h1 {
                font-size: 26px;
            }
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

            <?php if ($isLoggedIn): ?>
                <a href="my_listings.php" class="me-3">My Listings</a>
                <a href="orders.php" class="me-3">Orders</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php" class="me-3">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<?php if ($requestedId <= 0): ?>

    <?php
    $result = $conn->query("
        SELECT *
        FROM listings
        ORDER BY `$idColumn` DESC
        LIMIT 100
    ");

    if (!$result) {
        die("Listings query failed: " . $conn->error);
    }
    ?>

    <div class="page-wrap">
        <div class="page-header">
            <div>
                <h1>Listings</h1>
                <p>Browse available GreenTrade items.</p>
            </div>

            <a href="index.php" class="btn-light-green">
                Back to Main Site
            </a>
        </div>

        <?php if ($result->num_rows === 0): ?>
            <div class="empty-box">
                <h4>No listings found</h4>
                <p>There are no listings available yet.</p>
                <a href="index.php" class="btn-green">Back to Main Site</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                        $listingId = (int)($row[$idColumn] ?? 0);
                        $title = $row['title'] ?? $row['name'] ?? 'Untitled listing';
                        $description = $row['description'] ?? '';
                        $price = $row['price'] ?? 0;
                        $category = $row['category'] ?? 'Uncategorized';
                        $image = $row['image'] ?? '';
                    ?>

                    <div class="col-md-4 mb-4">
                        <div class="listing-card">
                            <?php if (!empty($image)): ?>
                                <img 
                                    src="uploads/<?php echo e($image); ?>" 
                                    class="listing-img"
                                    alt="<?php echo e($title); ?>"
                                >
                            <?php else: ?>
                                <div class="listing-img d-flex align-items-center justify-content-center text-muted">
                                    No image
                                </div>
                            <?php endif; ?>

                            <div class="listing-body">
                                <div class="listing-title">
                                    <?php echo e($title); ?>
                                </div>

                                <p class="text-muted">
                                    <?php echo e(substr($description, 0, 90)); ?>
                                    <?php if (strlen($description) > 90): ?>...<?php endif; ?>
                                </p>

                                <div class="price">
                                    R<?php echo number_format((float)$price, 2); ?>
                                </div>

                                <p>
                                    <span class="badge-green">
                                        <?php echo e($category); ?>
                                    </span>
                                </p>

                                <?php if ($listingId > 0): ?>
                                    <a 
                                        href="listing.php?id=<?php echo e($listingId); ?>" 
                                        class="btn-green w-100 text-center"
                                    >
                                        View Listing
                                    </a>
                                <?php else: ?>
                                    <div class="alert alert-warning p-2">
                                        Missing listing ID
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>

    <?php
    $joinUsers = tableExists($conn, "users");

    if ($joinUsers) {
        $sql = "
            SELECT 
                listings.*,
                users.full_name,
                users.verified,
                users.user_id AS seller_user_id
            FROM listings
            LEFT JOIN users ON listings.seller_id = users.user_id
            WHERE listings.`$idColumn` = ?
            LIMIT 1
        ";
    } else {
        $sql = "
            SELECT *
            FROM listings
            WHERE `$idColumn` = ?
            LIMIT 1
        ";
    }

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Listing query prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $requestedId);
    $stmt->execute();

    $single = $stmt->get_result();

    if (!$single || $single->num_rows === 0):
    ?>

        <div class="page-wrap">
            <div class="empty-box">
                <h4>Listing not found</h4>
                <p>This listing may have been removed.</p>
                <a href="listing.php" class="btn-green">View All Listings</a>
                <a href="index.php" class="btn-outline-green ms-2">Back to Main Site</a>
            </div>
        </div>

    <?php
    else:
        $item = $single->fetch_assoc();

        $listingId = (int)($item[$idColumn] ?? $requestedId);
        $title = $item['title'] ?? $item['name'] ?? 'Untitled listing';
        $description = $item['description'] ?? 'No description available.';
        $price = $item['price'] ?? 0;
        $category = $item['category'] ?? 'Not specified';
        $condition = $item['item_condition'] ?? $item['condition'] ?? 'Not specified';
        $location = $item['location'] ?? 'Not specified';
        $image = $item['image'] ?? '';
        $sellerId = isset($item['seller_id']) ? (int)$item['seller_id'] : 0;
        $sellerName = $item['full_name'] ?? 'Unknown seller';
        $verified = !empty($item['verified']);

        $isOwnListing = $isLoggedIn && $sellerId > 0 && $currentUserId === $sellerId;
    ?>

        <div class="page-wrap">
            <div class="page-header">
                <div>
                    <h1><?php echo e($title); ?></h1>
                    <p>Listing details and seller information.</p>
                </div>

                <div>
                    <a href="listing.php" class="btn-light-green me-2">All Listings</a>
                    <a href="index.php" class="btn-light-green">Main Site</a>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <?php if (!empty($image)): ?>
                        <img 
                            src="uploads/<?php echo e($image); ?>" 
                            class="detail-img"
                            alt="<?php echo e($title); ?>"
                        >
                    <?php else: ?>
                        <div class="detail-img d-flex align-items-center justify-content-center text-muted">
                            No image available
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <div class="detail-card">
                        <h2><?php echo e($title); ?></h2>

                        <div class="price">
                            R<?php echo number_format((float)$price, 2); ?>
                        </div>

                        <p class="text-muted">
                            <?php echo nl2br(e($description)); ?>
                        </p>

                        <div class="meta-row">
                            <strong>Category:</strong> <?php echo e($category); ?>
                        </div>

                        <div class="meta-row">
                            <strong>Condition:</strong> <?php echo e($condition); ?>
                        </div>

                        <div class="meta-row">
                            <strong>Location:</strong> <?php echo e($location); ?>
                        </div>

                        <div class="meta-row">
                            <strong>Seller:</strong> <?php echo e($sellerName); ?>

                            <?php if ($verified): ?>
                                <span class="badge bg-success">Verified</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Unverified</span>
                            <?php endif; ?>
                        </div>

                        <div class="mt-4">
                            <?php if ($isLoggedIn): ?>
                                <?php if ($isOwnListing): ?>
                                    <div class="alert alert-info">
                                        This is your listing.
                                    </div>

                                    <a href="my_listings.php" class="btn-outline-green">
                                        Manage My Listings
                                    </a>
                                <?php else: ?>
                                    <a 
                                        href="checkout.php?id=<?php echo e($listingId); ?>" 
                                        class="btn-green"
                                    >
                                        Buy Now
                                    </a>

                                    <?php if ($sellerId > 0): ?>
                                        <a 
                                            href="messages.php?seller=<?php echo e($sellerId); ?>&listing=<?php echo e($listingId); ?>" 
                                            class="btn-outline-green ms-2"
                                        >
                                            Message Seller
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="login.php" class="btn-green">
                                    Login to Buy
                                </a>
                            <?php endif; ?>

                            <a href="index.php" class="btn-outline-green ms-2">
                                Back to Main Site
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

<?php endif; ?>

<div class="footer">
    &copy; <?php echo date("Y"); ?> GreenTrade
</div>

<script 
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>
</html>

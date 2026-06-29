<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/includes/db.php";
/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
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

/*
|--------------------------------------------------------------------------
| Login check
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Check required tables
|--------------------------------------------------------------------------
*/
if (!tableExists($conn, "orders")) {
    die("Orders table does not exist.");
}

/*
|--------------------------------------------------------------------------
| Detect orders table columns
|--------------------------------------------------------------------------
*/
$orderIdCol = firstExistingColumn($conn, "orders", [
    "order_id",
    "id"
]);

$orderUserCol = firstExistingColumn($conn, "orders", [
    "user_id",
    "buyer_id",
    "customer_id",
    "client_id"
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

if (!$orderIdCol) {
    die("Orders table has no order ID column. Expected order_id or id.");
}

if (!$orderUserCol) {
    die("Orders table has no user column. Expected user_id, buyer_id, customer_id, or client_id.");
}

/*
|--------------------------------------------------------------------------
| Detect listings table columns
|--------------------------------------------------------------------------
*/
$hasListings = tableExists($conn, "listings");

$listingIdCol = null;
$listingTitleCol = null;
$listingImageCol = null;

if ($hasListings) {
    $listingIdCol = firstExistingColumn($conn, "listings", [
        "listing_id",
        "id"
    ]);

    $listingTitleCol = firstExistingColumn($conn, "listings", [
        "title",
        "name",
        "listing_title"
    ]);

    $listingImageCol = firstExistingColumn($conn, "listings", [
        "image",
        "image_path",
        "photo",
        "picture"
    ]);
}

/*
|--------------------------------------------------------------------------
| Build query safely
|--------------------------------------------------------------------------
*/
$selectParts = [];
$selectParts[] = "o.`$orderIdCol` AS order_id";

if ($orderListingCol) {
    $selectParts[] = "o.`$orderListingCol` AS listing_id";
} else {
    $selectParts[] = "0 AS listing_id";
}

if ($orderTotalCol) {
    $selectParts[] = "o.`$orderTotalCol` AS total_amount";
} else {
    $selectParts[] = "0 AS total_amount";
}

if ($orderStatusCol) {
    $selectParts[] = "o.`$orderStatusCol` AS order_status";
} else {
    $selectParts[] = "'pending' AS order_status";
}

if ($orderDateCol) {
    $selectParts[] = "o.`$orderDateCol` AS order_date";
    $orderBy = "o.`$orderDateCol` DESC";
} else {
    $selectParts[] = "'' AS order_date";
    $orderBy = "o.`$orderIdCol` DESC";
}

$joinSql = "";

if ($hasListings && $orderListingCol && $listingIdCol) {
    if ($listingTitleCol) {
        $selectParts[] = "l.`$listingTitleCol` AS listing_title";
    } else {
        $selectParts[] = "'Order item' AS listing_title";
    }

    if ($listingImageCol) {
        $selectParts[] = "l.`$listingImageCol` AS listing_image";
    } else {
        $selectParts[] = "'' AS listing_image";
    }

    $joinSql = "
        LEFT JOIN listings l 
        ON l.`$listingIdCol` = o.`$orderListingCol`
    ";
} else {
    $selectParts[] = "'Order item' AS listing_title";
    $selectParts[] = "'' AS listing_image";
}

$sql = "
    SELECT " . implode(", ", $selectParts) . "
    FROM orders o
    $joinSql
    WHERE o.`$orderUserCol` = ?
    ORDER BY $orderBy
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Orders query prepare failed: " . $conn->error . "<br><br>SQL: " . e($sql));
}

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();

if (!$result) {
    die("Orders query failed: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Orders - GreenTrade</title>
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
            max-width: 1150px;
            margin: 40px auto;
            padding: 0 18px;
        }

        .page-header {
            background: linear-gradient(135deg, #1b5e20, #43a047);
            color: #ffffff;
            padding: 28px;
            border-radius: 18px;
            margin-bottom: 24px;
            box-shadow: 0 4px 16px rgba(0,0,0,.12);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .page-header h1 {
            margin: 0;
            font-size: 32px;
            font-weight: 700;
        }

        .btn-light-green {
            background: #ffffff;
            color: #1b5e20;
            border: none;
            font-weight: 600;
            border-radius: 9px;
            padding: 10px 15px;
            text-decoration: none;
        }

        .btn-light-green:hover {
            background: #e8f5e9;
            color: #124116;
        }

        .orders-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 3px 16px rgba(0,0,0,.08);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: #e8f5e9;
            color: #1b5e20;
            border-bottom: 2px solid #c8e6c9;
            padding: 14px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 14px;
            vertical-align: middle;
            border-bottom: 1px solid #edf2ef;
        }

        .table tbody tr:hover {
            background: #f7fbf8;
        }

        .order-img {
            width: 76px;
            height: 76px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #d7e8da;
            background: #f1f5f2;
        }

        .listing-title {
            font-weight: 600;
            color: #1f2933;
            margin-left: 10px;
        }

        .price {
            color: #1b5e20;
            font-weight: 700;
        }

        .badge-status {
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-paid,
        .status-completed,
        .status-delivered {
            background: #e8f5e9;
            color: #1b5e20;
        }

        .status-cancelled,
        .status-canceled,
        .status-failed {
            background: #fdecea;
            color: #b71c1c;
        }

        .btn-green {
            background: #1b5e20;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-green:hover {
            background: #124116;
            color: white;
        }

        .empty-box {
            text-align: center;
            padding: 45px 20px;
            background: #f7fbf8;
            border-radius: 14px;
            color: #5f6f64;
        }

        .debug-box {
            background: #fff3cd;
            color: #856404;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .footer {
            text-align: center;
            color: #6b7280;
            margin-top: 30px;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .page-wrap {
                margin: 22px auto;
            }

            .page-header h1 {
                font-size: 25px;
            }

            .orders-card {
                padding: 14px;
            }

            .table {
                min-width: 850px;
            }
        }
    </style>
</head>
<body>

<nav class="navbar-custom">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="index.php">GreenTrade</a>

        <div>
            <a href="index.php" class="me-3">Home</a>
            <a href="listing.php" class="me-3">Listings</a>
            <a href="my_listings.php" class="me-3">My Listings</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="page-wrap">

    <div class="page-header">
        <div>
            <h1>My Orders</h1>
            <p class="mb-0">Track your GreenTrade purchases.</p>
        </div>

        <a href="index.php" class="btn-light-green">
            Back to Home
        </a>
    </div>

    <div class="debug-box">
        Orders user column detected: <strong><?= e($orderUserCol) ?></strong>
    </div>

    <div class="orders-card">
        <?php if ($result->num_rows === 0): ?>
            <div class="empty-box">
                <h4>No orders yet</h4>
                <p>You have not placed any orders.</p>
                <a href="listing.php" class="btn-green">Browse Listings</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Listing</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while ($order = $result->fetch_assoc()): ?>
                            <?php
                                $orderId = $order['order_id'] ?? '';
                                $listingId = (int)($order['listing_id'] ?? 0);
                                $title = $order['listing_title'] ?? 'Order item';
                                $image = $order['listing_image'] ?? '';
                                $total = $order['total_amount'] ?? 0;
                                $status = strtolower((string)($order['order_status'] ?? 'pending'));
                                $date = $order['order_date'] ?? '';

                                $statusClass = "status-pending";

                                if (in_array($status, ['paid', 'completed', 'delivered'])) {
                                    $statusClass = "status-paid";
                                } elseif (in_array($status, ['cancelled', 'canceled', 'failed'])) {
                                    $statusClass = "status-cancelled";
                                }
                            ?>

                            <tr>
                                <td>
                                    <strong>#<?= e($orderId) ?></strong>
                                </td>

                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($image)): ?>
                                            <img 
                                                src="uploads/<?= e($image) ?>" 
                                                class="order-img"
                                                alt="<?= e($title) ?>"
                                            >
                                        <?php else: ?>
                                            <div class="order-img d-flex align-items-center justify-content-center text-muted">
                                                No img
                                            </div>
                                        <?php endif; ?>

                                        <span class="listing-title">
                                            <?= e($title) ?>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <span class="price">
                                        R<?= number_format((float)$total, 2) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge-status <?= e($statusClass) ?>">
                                        <?= e(ucfirst($status)) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e($date) ?>
                                </td>

                                <td>
                                    <?php if ($listingId > 0): ?>
                                        <a 
                                            href="listing.php?id=<?= e($listingId) ?>" 
                                            class="btn-green"
                                        >
                                            View Listing
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        &copy; <?= date("Y") ?> GreenTrade
    </div>

</div>

<script 
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>
</html>

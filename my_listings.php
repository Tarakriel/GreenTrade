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

if (!tableExists($conn, "listings")) {
    die("Listings table does not exist.");
}

if (columnExists($conn, "listings", "listing_id")) {
    $idColumn = "listing_id";
} elseif (columnExists($conn, "listings", "id")) {
    $idColumn = "id";
} else {
    die("No listing ID column found.");
}

if (!columnExists($conn, "listings", "seller_id")) {
    die("The listings table does not have a seller_id column.");
}

/*
|--------------------------------------------------------------------------
| Delete own listing
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];

    $stmt = $conn->prepare("
        DELETE FROM listings 
        WHERE `$idColumn` = ? AND seller_id = ?
    ");

    if (!$stmt) {
        die("Delete prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ii", $deleteId, $userId);
    $stmt->execute();

    header("Location: my_listings.php?deleted=1");
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch user's listings
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT *
    FROM listings
    WHERE seller_id = ?
    ORDER BY `$idColumn` DESC
");

if (!$stmt) {
    die("Listings query prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();

if (!$result) {
    die("Listings query failed: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Listings - GreenTrade</title>
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
            font-size: 32px;
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
            background: #ffffff;
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

        .btn-outline-red {
            background: transparent;
            color: #b71c1c;
            border: 1px solid #b71c1c;
            border-radius: 9px;
            padding: 9px 13px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }

        .btn-outline-red:hover {
            background: #b71c1c;
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

        .listing-description {
            color: #6b7280;
            min-height: 48px;
        }

        .price {
            color: #1b5e20;
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .meta {
            color: #4b5563;
            font-size: 14px;
            margin-bottom: 6px;
        }

        .meta strong {
            color: #1b5e20;
        }

        .badge-status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .badge-active,
        .badge-approved {
            background: #e8f5e9;
            color: #1b5e20;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-rejected,
        .badge-inactive {
            background: #fdecea;
            color: #b71c1c;
        }

        .empty-box {
            background: white;
            border-radius: 18px;
            padding: 45px 25px;
            text-align: center;
            box-shadow: 0 3px 16px rgba(0,0,0,.08);
        }

        .notice {
            background: #e8f5e9;
            color: #1b5e20;
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
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

    <script>
        function confirmDelete() {
            return confirm("Are you sure you want to delete this listing?");
        }
    </script>
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
        <div>
            <h1>My Listings</h1>
            <p>Manage the items you have posted on GreenTrade.</p>
        </div>

        <div>
            <a href="index.php" class="btn-light-green me-2">
                Back to Main Site
            </a>

            <a href="create_listing.php" class="btn-light-green">
                Add New Listing
            </a>
        </div>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="notice">
            Listing deleted successfully.
        </div>
    <?php endif; ?>

    <?php if ($result->num_rows === 0): ?>

        <div class="empty-box">
            <h4>No listings yet</h4>
            <p>You have not created any listings.</p>

            <a href="create_listing.php" class="btn-green">
                Create Your First Listing
            </a>

            <a href="index.php" class="btn-outline-green ms-2">
                Back to Main Site
            </a>
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
                    $condition = $row['item_condition'] ?? $row['condition'] ?? '';
                    $location = $row['location'] ?? '';
                    $image = $row['image'] ?? '';
                    $status = strtolower((string)($row['status'] ?? 'active'));

                    $statusClass = 'badge-active';

                    if ($status === 'pending') {
                        $statusClass = 'badge-pending';
                    } elseif ($status === 'rejected' || $status === 'inactive') {
                        $statusClass = 'badge-rejected';
                    } elseif ($status === 'approved') {
                        $statusClass = 'badge-approved';
                    }
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

                            <p class="listing-description">
                                <?php echo e(substr($description, 0, 95)); ?>
                                <?php if (strlen($description) > 95): ?>...<?php endif; ?>
                            </p>

                            <div class="price">
                                R<?php echo number_format((float)$price, 2); ?>
                            </div>

                            <p class="meta">
                                <strong>Category:</strong> <?php echo e($category); ?>
                            </p>

                            <?php if (!empty($condition)): ?>
                                <p class="meta">
                                    <strong>Condition:</strong> <?php echo e($condition); ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($location)): ?>
                                <p class="meta">
                                    <strong>Location:</strong> <?php echo e($location); ?>
                                </p>
                            <?php endif; ?>

                            <span class="badge-status <?php echo e($statusClass); ?>">
                                <?php echo e(ucfirst($status)); ?>
                            </span>

                            <div class="d-grid gap-2 mt-2">
                                <?php if ($listingId > 0): ?>
                                    <a 
                                        href="listing.php?id=<?php echo e($listingId); ?>" 
                                        class="btn-outline-green text-center"
                                    >
                                        View
                                    </a>

                                    <a 
                                        href="edit_listing.php?id=<?php echo e($listingId); ?>" 
                                        class="btn-outline-green text-center"
                                    >
                                        Edit
                                    </a>

                                    <a 
                                        href="my_listings.php?delete=<?php echo e($listingId); ?>" 
                                        class="btn-outline-red text-center"
                                        onclick="return confirmDelete();"
                                    >
                                        Delete
                                    </a>
                                <?php else: ?>
                                    <div class="alert alert-warning p-2">
                                        Missing listing ID
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>

            <?php endwhile; ?>
        </div>

    <?php endif; ?>

</div>

<div class="footer">
    &copy; <?php echo date("Y"); ?> GreenTrade
</div>

<script 
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>
</html>

<?php
$stmt->close();
?>

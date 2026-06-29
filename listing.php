<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/db.php";
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

function tableExists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

if (!tableExists($conn, "listings")) {
    die("Table `listings` does not exist.");
}

/*
|--------------------------------------------------------------------------
| Detect the real primary ID column
|--------------------------------------------------------------------------
*/
if (columnExists($conn, "listings", "listing_id")) {
    $idColumn = "listing_id";
} elseif (columnExists($conn, "listings", "id")) {
    $idColumn = "id";
} else {
    die("No ID column found in listings table. Expected `listing_id` or `id`.");
}

/*
|--------------------------------------------------------------------------
| Delete listing
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM listings WHERE `$idColumn` = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();

    header("Location: listing.php?deleted=1");
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch all listings
|--------------------------------------------------------------------------
*/
$result = $conn->query("SELECT * FROM listings ORDER BY `$idColumn` DESC");

if (!$result) {
    die("Query failed: " . $conn->error);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Listings</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            padding: 25px;
        }

        .box {
            background: white;
            padding: 25px;
            border-radius: 10px;
            max-width: 1300px;
            margin: auto;
        }

        h1 {
            color: #1b5e20;
        }

        .debug {
            background: #fff3cd;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #856404;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #e8f5e9;
            color: #1b5e20;
        }

        .btn {
            background: #1b5e20;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
        }

        .btn-danger {
            background: #b71c1c;
        }

        img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 6px;
        }
    </style>
</head>
<body>

<div class="box">
    <h1>Listings Management</h1>

    <p>
        <a href="dashboard.php" class="btn">Back to Dashboard</a>
    </p>

    <div class="debug">
        Using ID column: <strong><?= e($idColumn) ?></strong>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="debug">Listing deleted successfully.</div>
    <?php endif; ?>

    <?php if ($result->num_rows === 0): ?>
        <p>No listings found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Real ID</th>
                    <th>Generated Link</th>
                    <th>Image</th>
                    <th>Title</th>
                    <th>Price</th>
                    <th>Category</th>
                    <th>Seller ID</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                        $listingId = isset($row[$idColumn]) ? (int)$row[$idColumn] : 0;
                        $viewUrl = "../listing.php?id=" . $listingId;
                    ?>

                    <tr>
                        <td>
                            <?= e($listingId) ?>
                        </td>

                        <td>
                            <?= e($viewUrl) ?>
                        </td>

                        <td>
                            <?php if (!empty($row['image'])): ?>
                                <img src="../uploads/<?= e($row['image']) ?>" alt="">
                            <?php else: ?>
                                No image
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= e($row['title'] ?? $row['name'] ?? 'Untitled') ?>
                        </td>

                        <td>
                            R<?= number_format((float)($row['price'] ?? 0), 2) ?>
                        </td>

                        <td>
                            <?= e($row['category'] ?? '') ?>
                        </td>

                        <td>
                            <?= e($row['seller_id'] ?? '') ?>
                        </td>

                        <td>
                            <?php if ($listingId > 0): ?>
                                <a href="<?= e($viewUrl) ?>" class="btn" target="_blank">
                                    View
                                </a>

                                <a 
                                    href="listing.php?delete=<?= e($listingId) ?>" 
                                    class="btn btn-danger"
                                    onclick="return confirm('Delete this listing?');"
                                >
                                    Delete
                                </a>
                            <?php else: ?>
                                Missing ID
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once __DIR__ . "/../includes/db.php";
$message = "";

/* Update dispute status */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_status'])) {
    $dispute_id = (int)$_POST['dispute_id'];
    $status = $_POST['status'];

    $allowed_statuses = ['open', 'under_review', 'resolved', 'closed'];

    if (!in_array($status, $allowed_statuses)) {
        $message = "<div class='alert error'>Invalid status selected.</div>";
    } else {
        $stmt = $conn->prepare("UPDATE disputes SET status = ? WHERE dispute_id = ?");

        if (!$stmt) {
            die("Update prepare failed: " . $conn->error);
        }

        $stmt->bind_param("si", $status, $dispute_id);

        if ($stmt->execute()) {
            $message = "<div class='alert success'>Dispute status updated successfully.</div>";
        } else {
            $message = "<div class='alert error'>Update failed: " . $stmt->error . "</div>";
        }

        $stmt->close();
    }
}

/* Delete dispute */
if (isset($_GET['delete'])) {
    $dispute_id = (int)$_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM disputes WHERE dispute_id = ?");

    if (!$stmt) {
        die("Delete prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $dispute_id);

    if ($stmt->execute()) {
        $message = "<div class='alert success'>Dispute deleted successfully.</div>";
    } else {
        $message = "<div class='alert error'>Delete failed: " . $stmt->error . "</div>";
    }

    $stmt->close();
}

/* Get disputes */
$sql = "SELECT disputes.*, 
               users.full_name AS reported_by,
               users.email AS reporter_email,
               orders.order_status,
               listings.title AS listing_title
        FROM disputes
        LEFT JOIN users ON disputes.user_id = users.user_id
        LEFT JOIN orders ON disputes.order_id = orders.order_id
        LEFT JOIN listings ON orders.listing_id = listings.listing_id
        ORDER BY disputes.created_at DESC";

$disputes = $conn->query($sql);

if (!$disputes) {
    die("Disputes query failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dispute Management - GreenTrade Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f8f4;
            color: #222;
        }

        .navbar {
            background: #198754;
            color: white;
            padding: 18px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar h2 {
            margin: 0;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 18px;
            font-weight: bold;
        }

        .container {
            padding: 35px 40px;
        }

        h1 {
            color: #198754;
            margin-top: 0;
        }

        .table-wrap {
            background: white;
            padding: 20px;
            border-radius: 14px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #e8f5e9;
            color: #198754;
        }

        select {
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        button {
            background: #198754;
            color: white;
            border: none;
            padding: 8px 11px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #146c43;
        }

        .delete {
            background: #dc3545;
            color: white;
            padding: 8px 11px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
        }

        .delete:hover {
            background: #bb2d3b;
        }

        .badge {
            background: #d1e7dd;
            color: #0f5132;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 13px;
            display: inline-block;
        }

        .badge.open {
            background: #f8d7da;
            color: #842029;
        }

        .badge.under_review {
            background: #fff3cd;
            color: #664d03;
        }

        .badge.resolved {
            background: #d1e7dd;
            color: #0f5132;
        }

        .badge.closed {
            background: #e2e3e5;
            color: #41464b;
        }

        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 18px;
            text-align: center;
            font-weight: bold;
        }

        .success {
            background: #d1e7dd;
            color: #0f5132;
        }

        .error {
            background: #f8d7da;
            color: #842029;
        }

        @media(max-width: 700px) {
            .navbar {
                flex-direction: column;
                text-align: center;
                gap: 10px;
                padding: 15px;
            }

            .navbar a {
                display: inline-block;
                margin: 5px;
            }

            .container {
                padding: 25px 15px;
            }
        }
    </style>
</head>

<body>

<div class="navbar">
    <h2>GreenTrade Admin</h2>

    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="users.php">Users</a>
        <a href="roles.php">Roles</a>
        <a href="listing.php">Listings</a>
        <a href="../listing.php">Main Site</a>
    </div>
</div>

<div class="container">
    <h1>Dispute Management</h1>

    <?php echo $message; ?>

    <div class="table-wrap">
        <table>
            <tr>
                <th>Dispute ID</th>
                <th>Order ID</th>
                <th>Listing</th>
                <th>Reported By</th>
                <th>Reason</th>
                <th>Description</th>
                <th>Status</th>
                <th>Created</th>
                <th>Update Status</th>
                <th>Delete</th>
            </tr>

            <?php if ($disputes->num_rows > 0): ?>
                <?php while ($row = $disputes->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo (int)$row['dispute_id']; ?></td>

                        <td>#<?php echo (int)$row['order_id']; ?></td>

                        <td>
                            <?php echo htmlspecialchars($row['listing_title'] ?? 'No listing found'); ?><br>
                            <small>Order status: <?php echo htmlspecialchars($row['order_status'] ?? 'N/A'); ?></small>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($row['reported_by'] ?? 'Unknown'); ?><br>
                            <small><?php echo htmlspecialchars($row['reporter_email'] ?? ''); ?></small>
                        </td>

                        <td><?php echo htmlspecialchars($row['reason'] ?? ''); ?></td>

                        <td><?php echo htmlspecialchars($row['description'] ?? ''); ?></td>

                        <td>
                            <span class="badge <?php echo htmlspecialchars($row['status']); ?>">
                                <?php echo htmlspecialchars($row['status']); ?>
                            </span>
                        </td>

                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>

                        <td>
                            <form method="POST">
                                <input type="hidden" name="dispute_id" value="<?php echo (int)$row['dispute_id']; ?>">

                                <select name="status">
                                    <option value="open" <?php echo ($row['status'] === 'open') ? 'selected' : ''; ?>>Open</option>
                                    <option value="under_review" <?php echo ($row['status'] === 'under_review') ? 'selected' : ''; ?>>Under Review</option>
                                    <option value="resolved" <?php echo ($row['status'] === 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="closed" <?php echo ($row['status'] === 'closed') ? 'selected' : ''; ?>>Closed</option>
                                </select>

                                <button type="submit" name="update_status">Update</button>
                            </form>
                        </td>

                        <td>
                            <a class="delete"
                               href="disputes.php?delete=<?php echo (int)$row['dispute_id']; ?>"
                               onclick="return confirm('Are you sure you want to delete this dispute?');">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10">No disputes found.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

</body>
</html>
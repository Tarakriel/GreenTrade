<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once __DIR__ . "/../includes/db.php";
function countRows($conn, $table) {
    $sql = "SELECT COUNT(*) AS total FROM `$table`";
    $result = $conn->query($sql);

    if ($result) {
        return $result->fetch_assoc()['total'];
    }

    return 0;
}

$total_users = countRows($conn, "users");
$total_listings = countRows($conn, "listings");
$total_orders = countRows($conn, "orders");
$total_disputes = countRows($conn, "disputes");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - GreenTrade</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * { box-sizing: border-box; }

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

        .navbar h2 { margin: 0; }

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

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 22px;
            margin-bottom: 35px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 14px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        }

        .card h3 {
            margin-top: 0;
            color: #198754;
        }

        .card p {
            font-size: 34px;
            font-weight: bold;
            margin: 10px 0 0;
        }

        .admin-menu {
            background: white;
            padding: 25px;
            border-radius: 14px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        }

        .admin-menu h2 {
            color: #198754;
            margin-top: 0;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }

        .menu-grid a {
            background: #e8f5e9;
            color: #198754;
            padding: 15px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            text-align: center;
            border: 1px solid #cfe8d5;
        }

        .menu-grid a:hover {
            background: #198754;
            color: white;
        }

        @media(max-width: 700px) {
            .navbar {
                flex-direction: column;
                gap: 10px;
                text-align: center;
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
        <a href="../listing.php">Main Site</a>
        <a href="../login.php">Login</a>
    </div>
</div>

<div class="container">
    <h1>Admin Dashboard</h1>

    <div class="cards">
        <div class="card">
            <h3>Total Users</h3>
            <p><?php echo $total_users; ?></p>
        </div>

        <div class="card">
            <h3>Total Listings</h3>
            <p><?php echo $total_listings; ?></p>
        </div>

        <div class="card">
            <h3>Total Orders</h3>
            <p><?php echo $total_orders; ?></p>
        </div>

        <div class="card">
            <h3>Total Disputes</h3>
            <p><?php echo $total_disputes; ?></p>
        </div>
    </div>

    <div class="admin-menu">
        <h2>Admin Management Pages</h2>

        <div class="menu-grid">
            <a href="users.php">User Management</a>
            <a href="roles.php">Role Management</a>
            <a href="listing.php">Listing Management</a>
            <a href="disputes.php">Dispute Management</a>
            <a href="orders.php">Order Management</a>
            <a href="reports.php">Reports</a>
            <a href="audit_logs.php">Audit Logs</a>
        </div>
    </div>
</div>

</body>
</html>
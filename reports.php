<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/db.php";
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

function singleValue($conn, $sql, $default = 0) {
    $result = $conn->query($sql);

    if (!$result) {
        return $default;
    }

    $row = $result->fetch_row();

    return $row && $row[0] !== null ? $row[0] : $default;
}

function getRows($conn, $sql) {
    $result = $conn->query($sql);

    if (!$result) {
        return [];
    }

    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

/*
|--------------------------------------------------------------------------
| Detect tables
|--------------------------------------------------------------------------
*/
$hasUsers = tableExists($conn, "users");
$hasProducts = tableExists($conn, "products");
$hasOrders = tableExists($conn, "orders");
$hasOrderItems = tableExists($conn, "order_items");

/*
|--------------------------------------------------------------------------
| Detect useful columns
|--------------------------------------------------------------------------
*/
$userIdCol = $hasUsers ? firstExistingColumn($conn, "users", ["id", "user_id", "customer_id"]) : null;

$productIdCol = $hasProducts ? firstExistingColumn($conn, "products", ["id", "product_id"]) : null;
$productNameCol = $hasProducts ? firstExistingColumn($conn, "products", ["name", "product_name", "title"]) : null;
$productPriceCol = $hasProducts ? firstExistingColumn($conn, "products", ["price", "product_price", "selling_price"]) : null;
$productStockCol = $hasProducts ? firstExistingColumn($conn, "products", ["stock", "quantity", "qty", "stock_quantity"]) : null;

$orderIdCol = $hasOrders ? firstExistingColumn($conn, "orders", ["id", "order_id"]) : null;
$orderCustomerCol = $hasOrders ? firstExistingColumn($conn, "orders", ["customer_name", "name", "full_name", "buyer_name"]) : null;
$orderTotalCol = $hasOrders ? firstExistingColumn($conn, "orders", ["total_amount", "total", "grand_total", "amount", "price"]) : null;
$orderStatusCol = $hasOrders ? firstExistingColumn($conn, "orders", ["status", "order_status", "payment_status"]) : null;
$orderDateCol = $hasOrders ? firstExistingColumn($conn, "orders", ["created_at", "order_date", "date", "created_on"]) : null;

$itemProductIdCol = $hasOrderItems ? firstExistingColumn($conn, "order_items", ["product_id", "item_id"]) : null;
$itemQtyCol = $hasOrderItems ? firstExistingColumn($conn, "order_items", ["quantity", "qty"]) : null;
$itemPriceCol = $hasOrderItems ? firstExistingColumn($conn, "order_items", ["price", "unit_price", "product_price"]) : null;

/*
|--------------------------------------------------------------------------
| Main stats
|--------------------------------------------------------------------------
*/
$totalUsers = $hasUsers ? singleValue($conn, "SELECT COUNT(*) FROM users") : 0;
$totalProducts = $hasProducts ? singleValue($conn, "SELECT COUNT(*) FROM products") : 0;
$totalOrders = $hasOrders ? singleValue($conn, "SELECT COUNT(*) FROM orders") : 0;

$totalRevenue = 0;

if ($hasOrders && $orderTotalCol) {
    if ($orderStatusCol) {
        $totalRevenue = singleValue($conn, "
            SELECT COALESCE(SUM(`$orderTotalCol`), 0)
            FROM orders
            WHERE `$orderStatusCol` NOT IN ('cancelled', 'canceled')
        ");
    } else {
        $totalRevenue = singleValue($conn, "
            SELECT COALESCE(SUM(`$orderTotalCol`), 0)
            FROM orders
        ");
    }
}

$pendingOrders = 0;
$completedOrders = 0;

if ($hasOrders && $orderStatusCol) {
    $pendingOrders = singleValue($conn, "
        SELECT COUNT(*)
        FROM orders
        WHERE `$orderStatusCol` = 'pending'
    ");

    $completedOrders = singleValue($conn, "
        SELECT COUNT(*)
        FROM orders
        WHERE `$orderStatusCol` IN ('completed', 'delivered', 'paid')
    ");
}

/*
|--------------------------------------------------------------------------
| Recent orders
|--------------------------------------------------------------------------
*/
$recentOrders = [];

if ($hasOrders && $orderIdCol) {
    $selectParts = [];
    $selectParts[] = "`$orderIdCol` AS order_id";

    if ($orderCustomerCol) {
        $selectParts[] = "`$orderCustomerCol` AS customer_name";
    } else {
        $selectParts[] = "'Guest' AS customer_name";
    }

    if ($orderTotalCol) {
        $selectParts[] = "`$orderTotalCol` AS total_amount";
    } else {
        $selectParts[] = "0 AS total_amount";
    }

    if ($orderStatusCol) {
        $selectParts[] = "`$orderStatusCol` AS status";
    } else {
        $selectParts[] = "'Unknown' AS status";
    }

    if ($orderDateCol) {
        $selectParts[] = "`$orderDateCol` AS created_at";
        $orderBy = "`$orderDateCol` DESC";
    } else {
        $selectParts[] = "'' AS created_at";
        $orderBy = "`$orderIdCol` DESC";
    }

    $recentOrders = getRows($conn, "
        SELECT " . implode(", ", $selectParts) . "
        FROM orders
        ORDER BY $orderBy
        LIMIT 10
    ");
}

/*
|--------------------------------------------------------------------------
| Low stock products
|--------------------------------------------------------------------------
*/
$lowStockProducts = [];

if ($hasProducts && $productIdCol) {
    $selectParts = [];
    $selectParts[] = "`$productIdCol` AS product_id";

    if ($productNameCol) {
        $selectParts[] = "`$productNameCol` AS product_name";
    } else {
        $selectParts[] = "'Unnamed product' AS product_name";
    }

    if ($productStockCol) {
        $selectParts[] = "`$productStockCol` AS stock";
        $stockWhere = "WHERE `$productStockCol` <= 5";
        $stockOrder = "`$productStockCol` ASC";
    } else {
        $selectParts[] = "0 AS stock";
        $stockWhere = "";
        $stockOrder = "`$productIdCol` DESC";
    }

    if ($productPriceCol) {
        $selectParts[] = "`$productPriceCol` AS price";
    } else {
        $selectParts[] = "0 AS price";
    }

    $lowStockProducts = getRows($conn, "
        SELECT " . implode(", ", $selectParts) . "
        FROM products
        $stockWhere
        ORDER BY $stockOrder
        LIMIT 10
    ");
}

/*
|--------------------------------------------------------------------------
| Top products
|--------------------------------------------------------------------------
*/
$topProducts = [];

if (
    $hasProducts &&
    $hasOrderItems &&
    $productIdCol &&
    $productNameCol &&
    $itemProductIdCol &&
    $itemQtyCol
) {
    $salesExpression = $itemPriceCol
        ? "SUM(oi.`$itemQtyCol` * oi.`$itemPriceCol`) AS total_sales"
        : "0 AS total_sales";

    $topProducts = getRows($conn, "
        SELECT
            p.`$productNameCol` AS product_name,
            SUM(oi.`$itemQtyCol`) AS total_sold,
            $salesExpression
        FROM order_items oi
        INNER JOIN products p ON p.`$productIdCol` = oi.`$itemProductIdCol`
        GROUP BY p.`$productIdCol`, p.`$productNameCol`
        ORDER BY total_sold DESC
        LIMIT 10
    ");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            color: #222;
        }

        .page {
            max-width: 1250px;
            margin: 0 auto;
            padding: 24px;
        }

        .topbar {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        h1 {
            margin: 0;
            color: #1b5e20;
        }

        h2 {
            margin-top: 0;
            color: #1b5e20;
        }

        .btn {
            background: #1b5e20;
            color: #fff;
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 7px;
            display: inline-block;
        }

        .btn:hover {
            background: #124116;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .card,
        .section {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,.08);
        }

        .card h3 {
            margin: 0 0 10px;
            color: #555;
            font-size: 14px;
            font-weight: normal;
        }

        .card strong {
            font-size: 28px;
            color: #1b5e20;
        }

        .section {
            margin-bottom: 20px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 800px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }

        th {
            background: #e8f5e9;
            color: #1b5e20;
        }

        tr:hover {
            background: #f9f9f9;
        }

        .badge {
            display: inline-block;
            padding: 5px 9px;
            background: #e8f5e9;
            color: #1b5e20;
            border-radius: 20px;
            font-size: 13px;
        }

        .empty,
        .warning {
            padding: 16px;
            border-radius: 8px;
            color: #666;
            background: #f7f7f7;
        }

        .warning {
            background: #fff3cd;
            color: #856404;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="page">

    <div class="topbar">
        <h1>Reports</h1>
        <a href="dashboard.php" class="btn">Back to Dashboard</a>
    </div>

    <?php if (!$hasOrders || !$hasProducts): ?>
        <div class="warning">
            Some reports are limited because required tables are missing.
        </div>
    <?php endif; ?>

    <div class="cards">
        <div class="card">
            <h3>Total Users</h3>
            <strong><?= e($totalUsers) ?></strong>
        </div>

        <div class="card">
            <h3>Total Products</h3>
            <strong><?= e($totalProducts) ?></strong>
        </div>

        <div class="card">
            <h3>Total Orders</h3>
            <strong><?= e($totalOrders) ?></strong>
        </div>

        <div class="card">
            <h3>Total Revenue</h3>
            <strong>€<?= number_format((float)$totalRevenue, 2) ?></strong>
        </div>

        <div class="card">
            <h3>Pending Orders</h3>
            <strong><?= e($pendingOrders) ?></strong>
        </div>

        <div class="card">
            <h3>Completed Orders</h3>
            <strong><?= e($completedOrders) ?></strong>
        </div>
    </div>

    <div class="section">
        <h2>Recent Orders</h2>

        <?php if (empty($recentOrders)): ?>
            <div class="empty">No recent orders found.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td>#<?= e($order["order_id"] ?? "") ?></td>
                                <td><?= e($order["customer_name"] ?? "Guest") ?></td>
                                <td>€<?= number_format((float)($order["total_amount"] ?? 0), 2) ?></td>
                                <td>
                                    <span class="badge">
                                        <?= e($order["status"] ?? "Unknown") ?>
                                    </span>
                                </td>
                                <td><?= e($order["created_at"] ?? "") ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Low Stock Products</h2>

        <?php if (empty($lowStockProducts)): ?>
            <div class="empty">No low stock products found.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Product ID</th>
                            <th>Name</th>
                            <th>Stock</th>
                            <th>Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStockProducts as $product): ?>
                            <tr>
                                <td>#<?= e($product["product_id"] ?? "") ?></td>
                                <td><?= e($product["product_name"] ?? "") ?></td>
                                <td><?= e($product["stock"] ?? 0) ?></td>
                                <td>€<?= number_format((float)($product["price"] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Top Selling Products</h2>

        <?php if (empty($topProducts)): ?>
            <div class="empty">No product sales data found.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Total Sold</th>
                            <th>Total Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProducts as $product): ?>
                            <tr>
                                <td><?= e($product["product_name"] ?? "") ?></td>
                                <td><?= e($product["total_sold"] ?? 0) ?></td>
                                <td>€<?= number_format((float)($product["total_sales"] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
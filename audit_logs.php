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

function columnExists($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);

    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");

    return $result && $result->num_rows > 0;
}

/*
|--------------------------------------------------------------------------
| Create table if missing
|--------------------------------------------------------------------------
*/
$conn->query("
CREATE TABLE IF NOT EXISTS audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL,
    admin_name VARCHAR(150) NULL,
    action VARCHAR(255) NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/*
|--------------------------------------------------------------------------
| Add missing columns safely
|--------------------------------------------------------------------------
*/
$requiredColumns = [
    "admin_id" => "ALTER TABLE audit_logs ADD COLUMN admin_id INT NULL",
    "admin_name" => "ALTER TABLE audit_logs ADD COLUMN admin_name VARCHAR(150) NULL",
    "action" => "ALTER TABLE audit_logs ADD COLUMN action VARCHAR(255) NOT NULL DEFAULT 'Unknown action'",
    "description" => "ALTER TABLE audit_logs ADD COLUMN description TEXT NULL",
    "ip_address" => "ALTER TABLE audit_logs ADD COLUMN ip_address VARCHAR(45) NULL",
    "user_agent" => "ALTER TABLE audit_logs ADD COLUMN user_agent TEXT NULL",
    "created_at" => "ALTER TABLE audit_logs ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
];

foreach ($requiredColumns as $column => $sql) {
    if (!columnExists($conn, "audit_logs", $column)) {
        $conn->query($sql);
    }
}

/*
|--------------------------------------------------------------------------
| Find the ID column
|--------------------------------------------------------------------------
*/
$idColumn = null;

if (columnExists($conn, "audit_logs", "id")) {
    $idColumn = "id";
} elseif (columnExists($conn, "audit_logs", "log_id")) {
    $idColumn = "log_id";
}

/*
|--------------------------------------------------------------------------
| Clear logs
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["clear_logs"])) {
    $conn->query("TRUNCATE TABLE audit_logs");
    header("Location: audit_logs.php?cleared=1");
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch logs
|--------------------------------------------------------------------------
*/
$orderBy = columnExists($conn, "audit_logs", "created_at") ? "created_at DESC" : "1 DESC";

$result = $conn->query("
    SELECT *
    FROM audit_logs
    ORDER BY $orderBy
    LIMIT 300
");

if (!$result) {
    die("Could not load audit logs: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
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

        .btn {
            border: none;
            background: #1b5e20;
            color: white;
            padding: 10px 14px;
            border-radius: 7px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-danger {
            background: #b71c1c;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,.08);
        }

        .notice {
            background: #e8f5e9;
            color: #1b5e20;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .empty {
            padding: 24px;
            background: #f7f7f7;
            color: #666;
            border-radius: 8px;
            text-align: center;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 950px;
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

        .small {
            color: #666;
            font-size: 12px;
            max-width: 300px;
            word-break: break-word;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
    </style>

    <script>
        function confirmClearLogs() {
            return confirm("Are you sure you want to clear all audit logs?");
        }
    </script>
</head>
<body>

<div class="page">

    <div class="topbar">
        <h1>Audit Logs</h1>

        <div class="actions">
            <a href="dashboard.php" class="btn">Back to Dashboard</a>

            <form method="post" onsubmit="return confirmClearLogs();" style="margin:0;">
                <button type="submit" name="clear_logs" class="btn btn-danger">
                    Clear Logs
                </button>
            </form>
        </div>
    </div>

    <?php if (isset($_GET["cleared"])): ?>
        <div class="notice">Audit logs cleared successfully.</div>
    <?php endif; ?>

    <div class="card">
        <?php if ($result->num_rows === 0): ?>
            <div class="empty">No audit logs found.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th>User Agent</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    #<?= e($idColumn && isset($row[$idColumn]) ? $row[$idColumn] : '') ?>
                                </td>

                                <td>
                                    <?= e($row["admin_name"] ?? "Admin") ?><br>
                                    <span class="small">
                                        ID: <?= e($row["admin_id"] ?? "N/A") ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge">
                                        <?= e($row["action"] ?? "Unknown action") ?>
                                    </span>
                                </td>

                                <td><?= e($row["description"] ?? "") ?></td>

                                <td><?= e($row["ip_address"] ?? "") ?></td>

                                <td>
                                    <div class="small">
                                        <?= e($row["user_agent"] ?? "") ?>
                                    </div>
                                </td>

                                <td><?= e($row["created_at"] ?? "") ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
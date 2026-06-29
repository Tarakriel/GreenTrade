<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once __DIR__ . "/../includes/db.php";
$message = "";

/* Add new role */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_role'])) {
    $role_name = strtolower(trim($_POST['role_name']));

    if (empty($role_name)) {
        $message = "<div class='alert error'>Role name is required.</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO roles (role_name) VALUES (?)");

        if (!$stmt) {
            die("Insert prepare failed: " . $conn->error);
        }

        $stmt->bind_param("s", $role_name);

        if ($stmt->execute()) {
            $message = "<div class='alert success'>Role added successfully.</div>";
        } else {
            $message = "<div class='alert error'>Role could not be added: " . $stmt->error . "</div>";
        }

        $stmt->close();
    }
}

/* Update role */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_role'])) {
    $role_id = (int)$_POST['role_id'];
    $role_name = strtolower(trim($_POST['role_name']));

    if (empty($role_name)) {
        $message = "<div class='alert error'>Role name cannot be empty.</div>";
    } else {
        $stmt = $conn->prepare("UPDATE roles SET role_name = ? WHERE role_id = ?");

        if (!$stmt) {
            die("Update prepare failed: " . $conn->error);
        }

        $stmt->bind_param("si", $role_name, $role_id);

        if ($stmt->execute()) {
            $message = "<div class='alert success'>Role updated successfully.</div>";
        } else {
            $message = "<div class='alert error'>Role update failed: " . $stmt->error . "</div>";
        }

        $stmt->close();
    }
}

/* Delete role */
if (isset($_GET['delete'])) {
    $role_id = (int)$_GET['delete'];

    if ($role_id === 1 || $role_id === 2 || $role_id === 3 || $role_id === 4) {
        $message = "<div class='alert error'>Default roles cannot be deleted.</div>";
    } else {
        $stmt = $conn->prepare("DELETE FROM roles WHERE role_id = ?");

        if (!$stmt) {
            die("Delete prepare failed: " . $conn->error);
        }

        $stmt->bind_param("i", $role_id);

        if ($stmt->execute()) {
            $message = "<div class='alert success'>Role deleted successfully.</div>";
        } else {
            $message = "<div class='alert error'>Role could not be deleted. It may be assigned to users.</div>";
        }

        $stmt->close();
    }
}

/* Get roles with user counts */
$sql = "SELECT roles.role_id, roles.role_name, COUNT(users.user_id) AS user_count
        FROM roles
        LEFT JOIN users ON roles.role_id = users.role_id
        GROUP BY roles.role_id, roles.role_name
        ORDER BY roles.role_id ASC";

$roles = $conn->query($sql);

if (!$roles) {
    die("Roles query failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Role Management - GreenTrade Admin</title>
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

        .form-card,
        .table-wrap {
            background: white;
            padding: 22px;
            border-radius: 14px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        input {
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 15px;
        }

        button {
            background: #198754;
            color: white;
            border: none;
            padding: 11px 15px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #146c43;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th,
        td {
            padding: 13px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #e8f5e9;
            color: #198754;
        }

        .delete {
            background: #dc3545;
            color: white;
            padding: 9px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
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

        .inline-form {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
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

            table {
                font-size: 14px;
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
        <a href="listing.php">Listings</a>
        <a href="disputes.php">Disputes</a>
        <a href="../listing.php">Main Site</a>
    </div>
</div>

<div class="container">
    <h1>Role Management</h1>

    <?php echo $message; ?>

    <div class="form-card">
        <h2>Add New Role</h2>

        <form method="POST" class="inline-form">
            <input type="text" name="role_name" placeholder="Example: support" required>
            <button type="submit" name="add_role">Add Role</button>
        </form>
    </div>

    <div class="table-wrap">
        <h2>Existing Roles</h2>

        <table>
            <tr>
                <th>Role ID</th>
                <th>Role Name</th>
                <th>Users Assigned</th>
                <th>Update</th>
                <th>Delete</th>
            </tr>

            <?php if ($roles->num_rows > 0): ?>
                <?php while ($row = $roles->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo (int)$row['role_id']; ?></td>

                        <td>
                            <span class="badge">
                                <?php echo htmlspecialchars($row['role_name']); ?>
                            </span>
                        </td>

                        <td><?php echo (int)$row['user_count']; ?></td>

                        <td>
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="role_id" value="<?php echo (int)$row['role_id']; ?>">
                                <input type="text" name="role_name" value="<?php echo htmlspecialchars($row['role_name']); ?>" required>
                                <button type="submit" name="update_role">Update</button>
                            </form>
                        </td>

                        <td>
                            <?php if ((int)$row['role_id'] <= 4): ?>
                                Default Role
                            <?php else: ?>
                                <a class="delete"
                                   href="roles.php?delete=<?php echo (int)$row['role_id']; ?>"
                                   onclick="return confirm('Are you sure you want to delete this role?');">
                                    Delete
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">No roles found.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

</body>
</html>
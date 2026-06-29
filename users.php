<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once __DIR__ . "/../includes/db.php";
$message = "";

/* Delete user */
if (isset($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    if (!$stmt) {
        die("Delete prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $message = "<div class='alert success'>User deleted successfully.</div>";
    } else {
        $message = "<div class='alert error'>Delete failed: " . $stmt->error . "</div>";
    }

    $stmt->close();
}

/* Update user role */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_role'])) {
    $user_id = (int)$_POST['user_id'];
    $role_id = (int)$_POST['role_id'];

    $stmt = $conn->prepare("UPDATE users SET role_id = ? WHERE user_id = ?");
    if (!$stmt) {
        die("Update prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ii", $role_id, $user_id);

    if ($stmt->execute()) {
        $message = "<div class='alert success'>User role updated successfully.</div>";
    } else {
        $message = "<div class='alert error'>Update failed: " . $stmt->error . "</div>";
    }

    $stmt->close();
}

/* Get roles */
$roles = [];
$role_result = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_id ASC");

if ($role_result) {
    while ($role = $role_result->fetch_assoc()) {
        $roles[] = $role;
    }
}

/* Get users */
$sql = "SELECT users.user_id, users.full_name, users.email, users.phone, users.location, users.verified, users.created_at,
               roles.role_id, roles.role_name
        FROM users
        LEFT JOIN roles ON users.role_id = roles.role_id
        ORDER BY users.created_at DESC";

$users = $conn->query($sql);

if (!$users) {
    die("Users query failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management - GreenTrade Admin</title>
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
            min-width: 900px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            vertical-align: middle;
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
        }

        .delete:hover {
            background: #bb2d3b;
        }

        .badge {
            background: #d1e7dd;
            color: #0f5132;
            padding: 5px 10px;
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
        <a href="roles.php">Roles</a>
        <a href="listing.php">Listings</a>
        <a href="disputes.php">Disputes</a>
        <a href="../listing.php">Main Site</a>
    </div>
</div>

<div class="container">
    <h1>User Management</h1>

    <?php echo $message; ?>

    <div class="table-wrap">
        <table>
            <tr>
                <th>User ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Location</th>
                <th>Role</th>
                <th>Verified</th>
                <th>Created</th>
                <th>Update Role</th>
                <th>Delete</th>
            </tr>

            <?php if ($users->num_rows > 0): ?>
                <?php while ($row = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo (int)$row['user_id']; ?></td>
                        <td><?php echo htmlspecialchars($row['full_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['phone'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['location'] ?? ''); ?></td>
                        <td>
                            <span class="badge">
                                <?php echo htmlspecialchars($row['role_name'] ?? 'No role'); ?>
                            </span>
                        </td>
                        <td><?php echo ((int)$row['verified'] === 1) ? 'Yes' : 'No'; ?></td>
                        <td><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="user_id" value="<?php echo (int)$row['user_id']; ?>">

                                <select name="role_id">
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo (int)$role['role_id']; ?>"
                                            <?php echo ((int)$role['role_id'] === (int)$row['role_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role['role_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <button type="submit" name="update_role">Update</button>
                            </form>
                        </td>
                        <td>
                            <a class="delete"
                               href="users.php?delete=<?php echo (int)$row['user_id']; ?>"
                               onclick="return confirm('Are you sure you want to delete this user?');">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10">No users found.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

</body>
</html>
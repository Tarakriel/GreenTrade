<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/includes/db.php";

$message = "";
$messageType = "";

function columnExists($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

if (!tableExists($conn, "users")) {
    $conn->query("
        CREATE TABLE users (
            user_id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            phone VARCHAR(30) NULL,
            role VARCHAR(50) DEFAULT 'customer',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if ($fullName === "" || $email === "" || $password === "") {
        $message = "Please complete all required fields.";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";
    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = "error";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
        $messageType = "error";
    } else {
        $check = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result && $result->num_rows > 0) {
            $message = "This email address is already registered.";
            $messageType = "error";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $hasFullName = columnExists($conn, "users", "full_name");
            $hasName = columnExists($conn, "users", "name");
            $hasPhone = columnExists($conn, "users", "phone");
            $hasRole = columnExists($conn, "users", "role");

            if ($hasFullName && $hasPhone && $hasRole) {
                $role = "customer";
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $fullName, $email, $hashedPassword, $phone, $role);
            } elseif ($hasFullName && $hasPhone) {
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $fullName, $email, $hashedPassword, $phone);
            } elseif ($hasFullName) {
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $fullName, $email, $hashedPassword);
            } elseif ($hasName) {
                $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $fullName, $email, $hashedPassword);
            } else {
                $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
                $stmt->bind_param("ss", $email, $hashedPassword);
            }

            if ($stmt->execute()) {
                $message = "Registration successful. You can now log in.";
                $messageType = "success";
            } else {
                $message = "Registration failed: " . $stmt->error;
                $messageType = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - GreenTrade</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f8f4;
            color: #1f2933;
        }

        .navbar {
            background: #0f5a1f;
            color: white;
            padding: 18px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar h2 {
            margin: 0;
            font-size: 24px;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 18px;
            font-size: 15px;
        }

        .navbar a:hover {
            text-decoration: underline;
        }

        .container {
            max-width: 520px;
            margin: 50px auto;
            background: white;
            padding: 35px;
            border-radius: 14px;
            border: 1px solid #d5e6d5;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }

        h1 {
            margin-top: 0;
            color: #0f5a1f;
            text-align: center;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cfd8cf;
            border-radius: 8px;
            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #0f5a1f;
        }

        .btn {
            width: 100%;
            background: #0f5a1f;
            color: white;
            border: none;
            padding: 13px;
            border-radius: 9px;
            font-weight: bold;
            font-size: 15px;
            cursor: pointer;
        }

        .btn:hover {
            background: #0b4418;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 18px;
            text-align: center;
        }

        .success {
            background: #dff3e3;
            color: #0f5a1f;
        }

        .error {
            background: #fdecea;
            color: #8a1c1c;
        }

        .links {
            text-align: center;
            margin-top: 18px;
        }

        .links a {
            color: #0f5a1f;
            font-weight: bold;
            text-decoration: none;
        }

        .links a:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            .navbar {
                flex-direction: column;
                gap: 10px;
            }

            .container {
                margin: 25px 15px;
                padding: 25px;
            }
        }
    </style>
</head>
<body>

<div class="navbar">
    <h2>GreenTrade</h2>
    <div>
        <a href="index.php">Home</a>
        <a href="listing.php">Items</a>
        <a href="login.php">Login</a>
    </div>
</div>

<div class="container">
    <h1>Create Account</h1>

    <?php if (!empty($message)): ?>
        <div class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php">
        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input type="text" name="full_name" id="full_name" required>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" required>
        </div>

        <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="text" name="phone" id="phone">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" name="confirm_password" id="confirm_password" required>
        </div>

        <button type="submit" class="btn">Register</button>
    </form>

    <div class="links">
        Already have an account?
        <a href="login.php">Login here</a>
    </div>
</div>

</body>
</html>

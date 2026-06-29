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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $message = "Please enter your email and password.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();

            $storedPassword = $user["password"] ?? "";

            $passwordValid = false;

            if (password_verify($password, $storedPassword)) {
                $passwordValid = true;
            } elseif ($password === $storedPassword) {
                $passwordValid = true;
            }

            if ($passwordValid) {
                $idColumn = isset($user["user_id"]) ? "user_id" : "id";

                $_SESSION["user_id"] = $user[$idColumn] ?? null;
                $_SESSION["email"] = $user["email"] ?? "";
                $_SESSION["full_name"] = $user["full_name"] ?? ($user["name"] ?? "User");
                $_SESSION["role"] = $user["role"] ?? "customer";

                if ($_SESSION["role"] === "admin" || $_SESSION["role"] === "super_admin") {
                    header("Location: admin/dashboard.php");
                    exit;
                } else {
                    header("Location: index.php");
                    exit;
                }
            } else {
                $message = "Incorrect password.";
                $messageType = "error";
            }
        } else {
            $message = "No account found with that email address.";
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - GreenTrade</title>
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
            max-width: 480px;
            margin: 60px auto;
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
        <a href="register.php">Register</a>
    </div>
</div>

<div class="container">
    <h1>Login</h1>

    <?php if (!empty($message)): ?>
        <div class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>
        </div>

        <button type="submit" class="btn">Login</button>
    </form>

    <div class="links">
        Do not have an account?
        <a href="register.php">Register here</a>
    </div>
</div>

</body>
</html>

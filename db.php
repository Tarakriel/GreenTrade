<?php
$host = "sql112.infinityfree.com";
$user = "if0_42098336";
$password = "6Cancerjune";
$database = "if0_42098336_GreenTrade";

mysqli_report(MYSQLI_REPORT_OFF);

$conn = @new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("
        <div style='font-family:Arial; max-width:700px; margin:40px auto; padding:20px; background:#fdecea; color:#721c24; border-radius:10px;'>
            <h2>Database Connection Failed</h2>
            <p><strong>Error:</strong> " . htmlspecialchars($conn->connect_error) . "</p>
            <p>Please check your InfinityFree MySQL password, hostname, username and database name.</p>
        </div>
    ");
}

$conn->set_charset("utf8mb4");
?>

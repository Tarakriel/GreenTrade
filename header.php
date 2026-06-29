<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GreenTrade</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-success mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">GreenTrade</a>
        <div class="d-flex flex-wrap gap-2">
            <a href="index.php" class="btn btn-light btn-sm">Home</a>
            <a href="listing.php" class="btn btn-light btn-sm">Items</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="create_listing.php" class="btn btn-light btn-sm">Add Item</a>
                <a href="my_listings.php" class="btn btn-light btn-sm">My Items</a>
                <a href="orders.php" class="btn btn-light btn-sm">Orders</a>
                <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-light btn-sm">Login</a>
                <a href="register.php" class="btn btn-light btn-sm">Register</a>
            <?php endif; ?>
            <a href="admin/dashboard.php" class="btn btn-warning btn-sm">Admin</a>
        </div>
    </div>
</nav>
<div class="container">

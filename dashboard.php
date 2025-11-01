<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html"); // Not logged in → redirect to login
    exit;
}

// Check if user is admin
if ($_SESSION['is_admin'] != 1) {
    header("Location: ../index.html"); // Regular users → redirect to homepage
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard • DragonStone</title>
    <link rel="stylesheet" href="css/dashboard.css" />
</head>
<body>
    <h1>Welcome, Admin <?php echo htmlspecialchars($_SESSION['name']); ?>!</h1>
    <p>Email: <?php echo htmlspecialchars($_SESSION['email']); ?></p>

    <h2>Admin Panel</h2>
    <ul>
        <li><a href="manage-users.php">Manage Users</a></li>
        <li><a href="manage-products.php">Manage Products</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</body>
</html>

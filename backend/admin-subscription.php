<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1){
    header("Location: ../login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "dragonstone.db");
if($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$result = $conn->query("
    SELECT s.id AS sub_id, u.name AS user_name, u.surname, p.name AS product_name, s.quantity, s.interval_days, s.next_delivery
    FROM subscriptions s
    JOIN users u ON s.user_id = u.id
    JOIN products p ON s.product_id = p.id
    WHERE s.status=1
    ORDER BY s.next_delivery ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Subscriptions</title>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1>Active Subscriptions</h1>
    <table class="table table-striped mt-3">
        <thead>
            <tr>
                <th>Subscription ID</th>
                <th>User</th>
                <th>Product</th>
                <th>Quantity</th>
                <th>Interval (days)</th>
                <th>Next Delivery</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['sub_id'] ?></td>
                <td><?= htmlspecialchars($row['user_name'] . ' ' . $row['surname']) ?></td>
                <td><?= htmlspecialchars($row['product_name']) ?></td>
                <td><?= $row['quantity'] ?></td>
                <td><?= $row['interval_days'] ?></td>
                <td><?= $row['next_delivery'] ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

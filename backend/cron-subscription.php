<?php
$conn = new mysqli("localhost", "root", "", "dragonstone.db");
if($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Get all subscriptions due today
$subscriptions = $conn->query("SELECT * FROM subscriptions WHERE next_delivery <= CURDATE() AND status=1");

while($sub = $subscriptions->fetch_assoc()){
    $user_id = $sub['user_id'];
    $product_id = $sub['product_id'];
    $quantity = $sub['quantity'];
    $interval_days = $sub['interval_days'];

    // 1. Reduce stock
    $conn->query("UPDATE products SET stock = stock - $quantity WHERE id = $product_id");

    // 2. Insert into orders
    $conn->query("INSERT INTO orders (user_id, product_id, quantity, created_at) VALUES ($user_id, $product_id, $quantity, NOW())");

    // 3. Update next_delivery
    $next_delivery = date('Y-m-d', strtotime("+$interval_days days"));
    $conn->query("UPDATE subscriptions SET next_delivery='$next_delivery' WHERE id={$sub['id']}");
}
$conn->close();
?>

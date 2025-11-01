<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: ../login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "dragonstone.db");
if($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$user_id = $_SESSION['user_id'];
$product_id = intval($_POST['product_id']);
$quantity = intval($_POST['quantity']);
$interval_days = intval($_POST['interval_days']);

// Calculate next delivery
$next_delivery = date('Y-m-d', strtotime("+$interval_days days"));

// Insert into subscriptions
$stmt = $conn->prepare("INSERT INTO subscriptions (user_id, product_id, quantity, interval_days, next_delivery) VALUES (?,?,?,?,?)");
$stmt->bind_param("iiiss", $user_id, $product_id, $quantity, $interval_days, $next_delivery);
if($stmt->execute()){
    echo "✅ Subscription created successfully!";
    // Redirect back to product or subscriptions page
    header("Location: ../product.php?id=$product_id");
} else {
    echo "❌ Error creating subscription: " . $stmt->error;
}
$stmt->close();
$conn->close();
?>

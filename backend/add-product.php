<?php
session_start();

// Only logged-in non-admin users can add to cart
if(!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 0){
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if(isset($_POST['product_id']) && isset($_POST['quantity'])){
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);

    if($quantity < 1) $quantity = 1;

    $conn = new mysqli("localhost", "root", "", "dragonstone.db");
    if($conn->connect_error){
        die("Connection failed: " . $conn->connect_error);
    }

    // Check if product already in cart
    $stmt = $conn->prepare("SELECT id, quantity FROM cart_items WHERE user_id=? AND product_id=?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0){
        // Increment quantity
        $row = $result->fetch_assoc();
        $new_quantity = $row['quantity'] + $quantity;
        $update = $conn->prepare("UPDATE cart_items SET quantity=? WHERE id=?");
        $update->bind_param("ii", $new_quantity, $row['id']);
        $update->execute();
        $update->close();
    } else {
        // Add new item
        $insert = $conn->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $insert->bind_param("iii", $user_id, $product_id, $quantity);
        $insert->execute();
        $insert->close();
    }

    $stmt->close();
    $conn->close();

    header("Location: ../index.php?added=1"); // Redirect back to homepage
    exit();
} else {
    header("Location: ../index.php");
    exit();
}
?>

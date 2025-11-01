<?php
session_start();

// Only logged-in non-admin users can remove items from the cart
if(!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 0){
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if(isset($_GET['cart_id'])){
    $cart_id = intval($_GET['cart_id']);

    $conn = new mysqli("localhost", "root", "", "dragonstone.db");
    if($conn->connect_error){
        die("Connection failed: " . $conn->connect_error);
    }

    // Delete the item from cart_items
    $stmt = $conn->prepare("DELETE FROM cart_items WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $cart_id, $user_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

// Redirect back to cart page
header("Location: cart.php");
exit();
?>

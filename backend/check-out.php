<?php
session_start();

// Only logged-in non-admin users can checkout
if(!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 0){
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "dragonstone.db");
if($conn->connect_error){
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all cart items for this user
$stmt = $conn->prepare("SELECT c.id as cart_id, c.product_id, c.quantity, p.name, p.stock, p.price
                        FROM cart_items c
                        JOIN products p ON c.product_id = p.id
                        WHERE c.user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0){
    // No items in cart
    $stmt->close();
    $conn->close();
    header("Location: cart.php?empty=1");
    exit();
}

$insufficient_stock = false;
$cart_items = [];

while($row = $result->fetch_assoc()){
    if($row['quantity'] > $row['stock']){
        $insufficient_stock = true;
        break;
    }
    $cart_items[] = $row;
}

if($insufficient_stock){
    $stmt->close();
    $conn->close();
    header("Location: cart.php?error=stock");
    exit();
}

// Deduct stock and clear cart inside a transaction
$conn->begin_transaction();

try {
    foreach($cart_items as $item){
        // Deduct product stock
        $update = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $update->bind_param("ii", $item['quantity'], $item['product_id']);
        $update->execute();
        $update->close();
    }

    // Clear user's cart
    $clear = $conn->prepare("DELETE FROM cart_items WHERE user_id=?");
    $clear->bind_param("i", $user_id);
    $clear->execute();
    $clear->close();

    $conn->commit();

    $stmt->close();
    $conn->close();

    // Redirect to success page or display a success message
    header("Location: success.php");
    exit();

} catch (Exception $e){
    $conn->rollback();
    $stmt->close();
    $conn->close();
    header("Location: cart.php?error=1");
    exit();
}
?>

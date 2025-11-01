<?php
session_start();
if (!isset($_SESSION['user_id']) || (int)$_SESSION['is_admin'] !== 0) {
  header("Location: ../cart.php");
  exit;
}

$cart_id = (int)($_POST['cart_id'] ?? 0);
$change  = $_POST['change'] ?? null;  // '+1' / '-1' or null
$action  = $_POST['action'] ?? null;  // 'set' if pressing Update
$qty     = max(1, (int)($_POST['quantity'] ?? 1));

$conn = new mysqli("localhost","root","","dragonstone.db");
if ($conn->connect_error) { header("Location: ../cart.php"); exit; }

if ($cart_id > 0) {
  if ($change === '+1') $qty++;
  if ($change === '-1') $qty = max(1, $qty-1);

  $stmt = $conn->prepare("UPDATE cart_items SET quantity=? WHERE id=? AND user_id=?");
  $stmt->bind_param("iii", $qty, $cart_id, $_SESSION['user_id']);
  $stmt->execute();
  $stmt->close();
}

$conn->close();
header("Location: ../cart.php");

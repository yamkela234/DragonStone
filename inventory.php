<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1){
    header("Location: index.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "dragonstone.db");
$result = $conn->query("SELECT * FROM products");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Inventory • DragonStone</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
<h1>Admin Inventory</h1>
<a href="backend/add-product.php" class="btn btn-success mb-3">Add Product</a>
<table class="table">
<thead>
<tr><th>SKU</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Actions</th></tr>
</thead>
<tbody>
<?php while($row = $result->fetch_assoc()): ?>
<tr>
<td><?= $row['id'] ?></td>
<td><?= htmlspecialchars($row['name']) ?></td>
<td><?= $row['category'] ?></td>
<td>$<?= $row['price'] ?></td>
<td><?= $row['stock'] ?></td>
<td>
    <a href="backend/edit-product.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
    <a href="backend/delete-product.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this product?')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</body>
</html>

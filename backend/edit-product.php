<?php
session_start();

// Only allow admin users
if(!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1){
    header("Location: ../index.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "dragonstone.db");
if($conn->connect_error){
    die("Connection failed: " . $conn->connect_error);
}

// Check if product ID is provided
if(!isset($_GET['id'])){
    header("Location: inventory.php");
    exit();
}

$product_id = intval($_GET['id']);

// Handle form submission to update product
if(isset($_POST['update_product'])){
    $name = $_POST['name'];
    $category = $_POST['category'];
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);

    $stmt = $conn->prepare("UPDATE products SET name=?, category=?, price=?, stock=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("ssdii", $name, $category, $price, $stock, $product_id);
    $stmt->execute();
    $stmt->close();

    header("Location: inventory.php");
    exit();
}

// Fetch existing product data
$stmt = $conn->prepare("SELECT * FROM products WHERE id=?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product • DragonStone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<div class="container my-5">
    <h1>Edit Product</h1>
    <form method="POST" class="mt-4">
        <div class="mb-3">
            <label for="name" class="form-label">Product Name</label>
            <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($product['name']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="category" class="form-label">Category</label>
            <input type="text" name="category" id="category" class="form-control" value="<?= htmlspecialchars($product['category']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="price" class="form-label">Price ($)</label>
            <input type="number" step="0.01" name="price" id="price" class="form-control" value="<?= $product['price'] ?>" required>
        </div>
        <div class="mb-3">
            <label for="stock" class="form-label">Stock</label>
            <input type="number" name="stock" id="stock" class="form-control" value="<?= $product['stock'] ?>" required>
        </div>
        <button type="submit" name="update_product" class="btn btn-primary">Update Product</button>
        <a href="inventory.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>

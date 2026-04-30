<?php include 'config.php'; ?>

<h1>Product Management (Full CRUD)</h1>

<!-- CREATE Form -->
<h2>➕ CREATE New Product</h2>
<form method="POST">
    <input type="text" name="name" placeholder="Name" required>
    <input type="number" step="0.01" name="price" placeholder="Price" required>
    <input type="number" name="stock" placeholder="Stock">
    <button type="submit" name="create">Add Product</button>
</form>

<!-- READ Table -->
<h2>📋 READ - All Products</h2>
<table border="1">
    <tr><th>ID</th><th>Name</th><th>Price</th><th>Stock</th><th>Actions</th></tr>
    <?php
    $result = $conn->query("SELECT * FROM product");
    while($row = $result->fetch_assoc()):
    ?>
    <tr>
        <form method="POST">
        <td><?= $row['product_id'] ?><input type="hidden" name="id" value="<?= $row['product_id'] ?>"><?= $row['product_id'] ?></td>
        <td><input type="text" name="name" value="<?= $row['name'] ?>"></td>
        <td><input type="number" step="0.01" name="price" value="<?= $row['price'] ?>"></td>
        <td><input type="number" name="stock" value="<?= $row['stock'] ?>"></td>
        <td>
            <button type="submit" name="update">✏️ UPDATE</button>
            <button type="submit" name="delete" onclick="return confirm('Delete?')">🗑️ DELETE</button>
        </td>
        </form>
    </tr>
    <?php endwhile; ?>
</table>

<?php
// CREATE
if(isset($_POST['create'])) {
    $conn->query("INSERT INTO product (name, price, stock) VALUES ('{$_POST['name']}', {$_POST['price']}, {$_POST['stock']})");
    echo "<p>✅ Product created</p>";
    header("refresh:0");
}

// UPDATE
if(isset($_POST['update'])) {
    $conn->query("UPDATE product SET name='{$_POST['name']}', price={$_POST['price']}, stock={$_POST['stock']} WHERE product_id={$_POST['id']}");
    echo "<p>✅ Product updated</p>";
    header("refresh:0");
}

// DELETE
if(isset($_POST['delete'])) {
    $conn->query("DELETE FROM product WHERE product_id={$_POST['id']}");
    echo "<p>✅ Product deleted</p>";
    header("refresh:0");
}
?>
<br>
<a href="index.php">← Back</a>
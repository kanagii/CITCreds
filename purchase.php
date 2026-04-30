<?php include 'config.php'; 

// Process purchase
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['purchase'])) {
    $user_id = $_POST['user_id'];
    $product_id = $_POST['product_id'];
    $qty = $_POST['quantity'];
    
    $product = $conn->query("SELECT * FROM product WHERE product_id=$product_id")->fetch_assoc();
    $total = $product['price'] * $qty;
    $user = $conn->query("SELECT * FROM users WHERE user_id=$user_id")->fetch_assoc();
    
    if($user['balance'] >= $total && $product['stock'] >= $qty) {
        $conn->begin_transaction();
        $conn->query("INSERT INTO transaction (user_id, processed_by, total_amount) VALUES ($user_id, 1, $total)");
        $trans_id = $conn->insert_id;
        $conn->query("INSERT INTO transaction_item (transaction_id, product_id, quantity, subtotal) VALUES ($trans_id, $product_id, $qty, $total)");
        $conn->query("UPDATE users SET balance = balance - $total WHERE user_id=$user_id");
        $conn->query("UPDATE product SET stock = stock - $qty WHERE product_id=$product_id");
        $conn->commit();
        echo "<p style='color:green'>✅ Purchase successful! ₱$total deducted</p>";
    } else {
        echo "<p style='color:red'>❌ Insufficient balance or stock</p>";
    }
}
?>

<h1>Make a Purchase</h1>

<form method="POST">
    <select name="user_id" required>
        <option value="">Select User</option>
        <?php 
        $users = $conn->query("SELECT user_id, name, balance FROM users WHERE user_type IN ('student','teacher')");
        while($u = $users->fetch_assoc()): ?>
        <option value="<?=$u['user_id']?>"><?=$u['name']?> (₱<?=$u['balance']?>)</option>
        <?php endwhile; ?>
    </select><br><br>
    
    <select name="product_id" required>
        <option value="">Select Product</option>
        <?php 
        $products = $conn->query("SELECT * FROM product WHERE stock>0");
        while($p = $products->fetch_assoc()): ?>
        <option value="<?=$p['product_id']?>"><?=$p['name']?> - ₱<?=$p['price']?> (Stock: <?=$p['stock']?>)</option>
        <?php endwhile; ?>
    </select><br><br>
    
    <input type="number" name="quantity" placeholder="Quantity" min="1" required><br><br>
    <button type="submit" name="purchase">Buy Now</button>
</form>
<br>
<a href="index.php">← Back</a>
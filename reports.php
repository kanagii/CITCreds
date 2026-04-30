<?php include 'config.php'; ?>
<h1>Sales Reports</h1>

<h2>Today's Summary</h2>
<?php
$today = $conn->query("SELECT SUM(total_amount) as sales, COUNT(*) as count FROM transaction WHERE DATE(transaction_date)=CURDATE()")->fetch_assoc();
echo "<p>📊 Total Sales: ₱".number_format($today['sales']??0,2)." | Transactions: ".($today['count']??0)."</p>";
?>

<h2>All Transactions</h2>
<table border="1">
    <tr><th>ID</th><th>User</th><th>Amount</th><th>Date</th></tr>
    <?php
    $trans = $conn->query("SELECT t.*, u.name FROM transaction t JOIN users u ON t.user_id=u.user_id ORDER BY t.transaction_date DESC LIMIT 20");
    while($row = $trans->fetch_assoc()):
    ?>
    <tr>
        <td><?=$row['transaction_id']?></td>
        <td><?=$row['name']?></td>
        <td>₱<?=$row['total_amount']?></td>
        <td><?=$row['transaction_date']?></td>
    </tr>
    <?php endwhile; ?>
</table>
<br>
<a href="index.php">← Back</a>
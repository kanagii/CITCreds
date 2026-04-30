<?php
// =====================================================
//  Canteen System — Unified JSON API
//  All actions go through: api.php?action=XXX
// =====================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

include 'config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── helper ──────────────────────────────────────────
function ok($data = [])  { echo json_encode(['ok' => true]  + $data); exit; }
function err($msg)        { http_response_code(400); echo json_encode(['ok' => false, 'error' => $msg]); exit; }
function safe($v)         { global $conn; return $conn->real_escape_string(trim($v)); }

// ============================================================
//  PRODUCTS
// ============================================================

// GET  api.php?action=products
if ($action === 'products') {
    $rows = [];
    $res  = $conn->query("SELECT * FROM product ORDER BY product_id ASC");
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    ok(['products' => $rows]);
}

// POST api.php  action=product_create  name price stock
if ($action === 'product_create') {
    $name  = safe($_POST['name']  ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)  ($_POST['stock'] ?? 0);
    if (!$name || $price < 0) err('Name and a valid price are required.');
    $stmt = $conn->prepare("INSERT INTO product (name, price, stock) VALUES (?, ?, ?)");
    $stmt->bind_param('sdi', $name, $price, $stock);
    $stmt->execute();
    ok(['id' => $conn->insert_id]);
}

// POST api.php  action=product_update  id name price stock
if ($action === 'product_update') {
    $id    = (int)  ($_POST['id']    ?? 0);
    $name  = safe($_POST['name']  ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)  ($_POST['stock'] ?? 0);
    if (!$id || !$name) err('Invalid product data.');
    $stmt = $conn->prepare("UPDATE product SET name=?, price=?, stock=? WHERE product_id=?");
    $stmt->bind_param('sdii', $name, $price, $stock, $id);
    $stmt->execute();
    ok();
}

// POST api.php  action=product_delete  id
if ($action === 'product_delete') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) err('Invalid product ID.');
    $conn->query("DELETE FROM product WHERE product_id=$id");
    ok();
}

// ============================================================
//  USERS
// ============================================================

// GET  api.php?action=users
if ($action === 'users') {
    $rows = [];
    $res  = $conn->query("SELECT user_id, name, balance, user_type FROM users ORDER BY name ASC");
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    ok(['users' => $rows]);
}

// POST api.php  action=user_topup  user_id  amount
if ($action === 'user_topup') {
    $uid    = (int)  ($_POST['user_id'] ?? 0);
    $amount = (float)($_POST['amount']  ?? 0);
    if (!$uid || $amount <= 0) err('Invalid top-up data.');
    $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE user_id = ?");
    $stmt->bind_param('di', $amount, $uid);
    $stmt->execute();
    // return new balance
    $row = $conn->query("SELECT balance FROM users WHERE user_id=$uid")->fetch_assoc();
    ok(['new_balance' => (float)$row['balance']]);
}

// POST api.php  action=user_create  name  user_type  balance
if ($action === 'user_create') {
    $name     = safe($_POST['name']      ?? '');
    $type     = safe($_POST['user_type'] ?? 'student');
    $balance  = (float)($_POST['balance'] ?? 0);
    if (!$name) err('Name is required.');
    $stmt = $conn->prepare("INSERT INTO users (name, user_type, balance) VALUES (?, ?, ?)");
    $stmt->bind_param('ssd', $name, $type, $balance);
    $stmt->execute();
    ok(['id' => $conn->insert_id]);
}

// ============================================================
//  PURCHASE  (supports multi-item cart)
// ============================================================

// POST api.php  action=purchase
//   user_id
//   items = JSON array of { product_id, quantity }
if ($action === 'purchase') {
    $uid   = (int)($_POST['user_id'] ?? 0);
    $items = json_decode($_POST['items'] ?? '[]', true);

    if (!$uid || empty($items)) err('User and at least one item are required.');

    $user = $conn->query("SELECT * FROM users WHERE user_id=$uid")->fetch_assoc();
    if (!$user) err('User not found.');

    // Validate stock and compute total
    $total    = 0;
    $enriched = [];
    foreach ($items as $item) {
        $pid = (int)($item['product_id'] ?? 0);
        $qty = (int)($item['quantity']   ?? 1);
        if (!$pid || $qty < 1) err('Invalid item in cart.');
        $prod = $conn->query("SELECT * FROM product WHERE product_id=$pid")->fetch_assoc();
        if (!$prod)          err("Product #$pid not found.");
        if ($prod['stock'] < $qty) err("Not enough stock for \"{$prod['name']}\" (only {$prod['stock']} left).");
        $subtotal  = $prod['price'] * $qty;
        $total    += $subtotal;
        $enriched[] = ['prod' => $prod, 'qty' => $qty, 'subtotal' => $subtotal];
    }

    if ($user['balance'] < $total) {
        err('Insufficient balance. Needed: ₱' . number_format($total, 2) . ', Available: ₱' . number_format($user['balance'], 2) . '.');
    }

    // Commit
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO transaction (user_id, processed_by, total_amount) VALUES (?, 1, ?)");
        $stmt->bind_param('id', $uid, $total);
        $stmt->execute();
        $trans_id = $conn->insert_id;

        $istmt = $conn->prepare("INSERT INTO transaction_item (transaction_id, product_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
        $ustmt = $conn->prepare("UPDATE product SET stock = stock - ? WHERE product_id = ?");

        foreach ($enriched as $e) {
            $pid      = (int)$e['prod']['product_id'];
            $qty      = $e['qty'];
            $subtotal = $e['subtotal'];
            $istmt->bind_param('iiid', $trans_id, $pid, $qty, $subtotal);
            $istmt->execute();
            $ustmt->bind_param('ii', $qty, $pid);
            $ustmt->execute();
        }

        $conn->query("UPDATE users SET balance = balance - $total WHERE user_id=$uid");
        $conn->commit();

        ok([
            'transaction_id' => $trans_id,
            'total'          => $total,
            'new_balance'    => (float)($user['balance'] - $total),
            'items'          => array_map(fn($e) => [
                'name'     => $e['prod']['name'],
                'qty'      => $e['qty'],
                'subtotal' => $e['subtotal'],
            ], $enriched),
        ]);
    } catch (Exception $ex) {
        $conn->rollback();
        err('Transaction failed: ' . $ex->getMessage());
    }
}

// ============================================================
//  REPORTS
// ============================================================

// GET  api.php?action=reports
if ($action === 'reports') {
    $today = $conn->query(
        "SELECT SUM(total_amount) as sales, COUNT(*) as count
         FROM transaction WHERE DATE(transaction_date) = CURDATE()"
    )->fetch_assoc();

    $rows = [];
    $res  = $conn->query(
        "SELECT t.transaction_id, t.total_amount, t.transaction_date, u.name
         FROM transaction t
         JOIN users u ON t.user_id = u.user_id
         ORDER BY t.transaction_date DESC
         LIMIT 50"
    );
    while ($r = $res->fetch_assoc()) $rows[] = $r;

    ok([
        'today_sales' => (float)($today['sales'] ?? 0),
        'today_count' => (int)  ($today['count'] ?? 0),
        'transactions'=> $rows,
    ]);
}

// ============================================================
//  DASHBOARD
// ============================================================

// GET  api.php?action=dashboard
if ($action === 'dashboard') {
    $today = $conn->query(
        "SELECT SUM(total_amount) as sales, COUNT(*) as count
         FROM transaction WHERE DATE(transaction_date) = CURDATE()"
    )->fetch_assoc();

    $productCount = (int)$conn->query("SELECT COUNT(*) as c FROM product")->fetch_assoc()['c'];
    $lowStock     = (int)$conn->query("SELECT COUNT(*) as c FROM product WHERE stock <= 5")->fetch_assoc()['c'];
    $userCount    = (int)$conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];

    $recent = [];
    $res    = $conn->query(
        "SELECT t.transaction_id, t.total_amount, t.transaction_date, u.name
         FROM transaction t
         JOIN users u ON t.user_id = u.user_id
         ORDER BY t.transaction_date DESC
         LIMIT 8"
    );
    while ($r = $res->fetch_assoc()) $recent[] = $r;

    ok([
        'today_sales'   => (float)($today['sales'] ?? 0),
        'today_count'   => (int)  ($today['count'] ?? 0),
        'product_count' => $productCount,
        'low_stock'     => $lowStock,
        'user_count'    => $userCount,
        'recent'        => $recent,
    ]);
}

err('Unknown action: ' . htmlspecialchars($action));

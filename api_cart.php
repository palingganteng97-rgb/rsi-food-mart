<?php
ob_start();
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Refactor: development cart DB-driven (no $_SESSION['cart'])
$action = isset($_GET['action']) ? $_GET['action'] : '';

$tenant_id = 1;

// cari / pastikan ada cart sementara untuk tenant_id=1
$cart_id = 0;
$stmtCart = $conn->prepare("SELECT id FROM carts WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
$stmtCart->bind_param('i', $tenant_id);
$stmtCart->execute();
$resCart = $stmtCart->get_result();
if ($resCart && $resCart->num_rows > 0) {
    $row = $resCart->fetch_assoc();
    $cart_id = (int)($row['id'] ?? 0);
}
if ($cart_id <= 0) {
    $stmtInsCart = $conn->prepare("INSERT INTO carts (tenant_id) VALUES (?)");
    $stmtInsCart->bind_param('i', $tenant_id);
    $stmtInsCart->execute();
    $cart_id = (int)$conn->insert_id;
}


if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $image = isset($_POST['image']) ? trim((string)$_POST['image']) : '';
    $notes = isset($_POST['notes']) ? trim((string)$_POST['notes']) : '';

    // metadata varian & addons untuk mode edit
    $variant = isset($_POST['variant']) ? trim((string)$_POST['variant']) : '';
    $addons = isset($_POST['addons']) ? $_POST['addons'] : [];
    if (!is_array($addons)) { $addons = []; }

    if ($id > 0) {
        // 1) Cari cart_item yang sama (product + notes) untuk development
        // Catatan: skema cart_items saat ini hanya punya notes (dan price/qty). Variant/addons belum persisten.
        $sqlFind = "SELECT id, qty FROM cart_items WHERE cart_id = ? AND product_id = ? AND notes = ? LIMIT 1";
        $stmtFind = $conn->prepare($sqlFind);
        $stmtFind->bind_param('iis', $cart_id, $id, $notes);
        $stmtFind->execute();
        $resFind = $stmtFind->get_result();
        $existing = $resFind ? $resFind->fetch_assoc() : null;

        if ($existing && isset($existing['id'])) {
            $newQty = (int)$existing['qty'] + 1;
            // update qty & price
            $sqlUpd = "UPDATE cart_items SET qty = ?, price = ?, notes = ? WHERE id = ? AND cart_id = ?";
            $stmtUpd = $conn->prepare($sqlUpd);
            $stmtUpd->bind_param('idssi', $newQty, $price, $notes, (int)$existing['id'], $cart_id);
            $stmtUpd->execute();
        } else {
            $sqlIns = "INSERT INTO cart_items (cart_id, product_id, qty, price, notes) VALUES (?, ?, ?, ?, ?)";
            $stmtIns = $conn->prepare($sqlIns);
            $qty = 1;
            $stmtIns->bind_param('iiids', $cart_id, $id, $qty, $price, $notes);
            $stmtIns->execute();
        }

        // hitung total item
        $sqlTotal = "SELECT COALESCE(SUM(qty),0) AS total_items FROM cart_items WHERE cart_id = ?";
        $stmtTotal = $conn->prepare($sqlTotal);
        $stmtTotal->bind_param('i', $cart_id);
        $stmtTotal->execute();
        $resTotal = $stmtTotal->get_result();
        $rowTotal = $resTotal ? $resTotal->fetch_assoc() : ['total_items' => 0];

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'total_items' => (int)($rowTotal['total_items'] ?? 0)]);
        exit;
    }
}


if ($action === 'get_total') {
    $sqlTotal = "SELECT COALESCE(SUM(qty),0) AS total_items FROM cart_items WHERE cart_id = ?";
    $stmtTotal = $conn->prepare($sqlTotal);
    $stmtTotal->bind_param('i', $cart_id);
    $stmtTotal->execute();
    $resTotal = $stmtTotal->get_result();
    $rowTotal = $resTotal ? $resTotal->fetch_assoc() : ['total_items' => 0];

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['total_items' => (int)($rowTotal['total_items'] ?? 0)]);
    exit;
}


if ($action === 'get_cart_items') {
    $items = [];

    $sqlItems = "SELECT ci.id, ci.product_id, ci.qty, ci.price, ci.notes, p.name, p.image
                 FROM cart_items ci
                 JOIN products p ON p.id = ci.product_id
                 WHERE ci.cart_id = ?
                 ORDER BY ci.id DESC";
    $stmtItems = $conn->prepare($sqlItems);
    $stmtItems->bind_param('i', $cart_id);
    $stmtItems->execute();
    $resItems = $stmtItems->get_result();

    if ($resItems) {
        while ($row = $resItems->fetch_assoc()) {
            $items[] = [
                'id' => (int)$row['product_id'],
                'name' => $row['name'],
                'price' => (float)$row['price'],
                'image' => $row['image'],
                'notes' => $row['notes'] ?? '',
                'qty' => (int)$row['qty'],
                // field ini kadang dibutuhkan UI JS
                'variant' => 'Original',
                'addons' => []
            ];
        }
    }

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($items);
    exit;
}


// ========================================================
// EDIT ITEMS: untuk refactor development ini,
// JS yang ada kemungkinan masih memanggil endpoint ini.
// Karena saat ini cart disimpan DB-driven (tenant_id=1, tanpa session cart),
// kita lakukan update berdasarkan cart_id + product_id + notes.
// Catatan: qty/edit tetap "development sederhana".
// ========================================================

if ($action === 'get_item') {
    $key = isset($_GET['key']) ? trim((string)$_GET['key']) : '';

    // old_key tidak bisa dipetakan ke cart_item_id saat ini.
    // Kembalikan null agar UI modal edit bisa fallback.
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(null);
    exit;
}

if ($action === 'update_saved' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldKey = isset($_POST['old_key']) ? trim((string)$_POST['old_key']) : '';
    $id     = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $notes  = isset($_POST['notes']) ? trim((string)$_POST['notes']) : '';
    $price  = isset($_POST['price']) ? floatval($_POST['price']) : 0;

    if ($id > 0) {
        $sqlUpd = "UPDATE cart_items SET price = ?, notes = ?
                   WHERE cart_id = ? AND product_id = ?";
        $stmtUpd = $conn->prepare($sqlUpd);
        $stmtUpd->bind_param('dsii', $price, $notes, $cart_id, $id);
        $stmtUpd->execute();

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
}




ob_clean();
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit;

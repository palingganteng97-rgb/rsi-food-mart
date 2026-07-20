<?php
ob_start();
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

$tenant_id = 1;

function normalize_addon_ids($addons): array {
    if (!is_array($addons)) return [];
    $ids = [];
    foreach ($addons as $a) {
        $i = filter_var($a, FILTER_VALIDATE_INT);
        if ($i !== false && $i > 0) $ids[] = (int)$i;
    }
    sort($ids);
    return array_values(array_unique($ids));
}

function build_addon_ids_signature(array $addonIds): string {
    if (empty($addonIds)) return '';
    return implode(',', $addonIds);
}

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

    $__log_id = uniqid('cartlog_', true);
    $__log_path = __DIR__ . '/cart_add_debug.log';
    $__debug_payload = [
        'log_id' => $__log_id,
        'action' => $action,
        'method' => $_SERVER['REQUEST_METHOD'],
        'cart_id' => $cart_id,
        'product_id' => $id,
        'notes_raw' => isset($_POST['notes']) ? (string)$_POST['notes'] : null,
        'notes_trimmed' => $notes,
        'qty_expected' => 1,
        'price' => $price,
    ];
    @file_put_contents($__log_path, json_encode($__debug_payload, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);

    $variant = isset($_POST['variant']) ? trim((string)$_POST['variant']) : '';
    $addons = isset($_POST['addons']) ? $_POST['addons'] : [];
    if (!is_array($addons)) { $addons = []; }

    if ($id > 0) {

        // Guard: hanya izinkan produk aktif (soft delete harus memblokir)
        $sqlGuard = "SELECT deleted_at FROM products WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1";
        $stmtGuard = $conn->prepare($sqlGuard);
        $stmtGuard->bind_param('i', $id);
        $stmtGuard->execute();
        $resGuard = $stmtGuard->get_result();
        if ($resGuard && $resGuard->num_rows > 0) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Produk tidak tersedia (telah terhapus)']);
            exit;
        }

        $compiled_notes = $notes;
        $sqlFind = "SELECT id, qty FROM cart_items WHERE cart_id = ? AND product_id = ? AND notes = ? LIMIT 1";
        $stmtFind = $conn->prepare($sqlFind);
        $stmtFind->bind_param('iis', $cart_id, $id, $compiled_notes);
        $stmtFind->execute();
        $resFind = $stmtFind->get_result();

        $foundRow = null;
        if ($resFind && $resFind->num_rows > 0) {
            $foundRow = $resFind->fetch_assoc();
        }

        $__debug_after_select = [
            'log_id' => $__log_id,
            'compiled_notes_used' => $compiled_notes,
            'select_found' => ($foundRow !== null),
            'select_row' => $foundRow,
        ];
        @file_put_contents($__log_path, json_encode($__debug_after_select, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);

        $existing = $foundRow;

        if ($existing && isset($existing['id'])) {
            $newQty = (int)$existing['qty'] + 1;
            $sqlUpd = "UPDATE cart_items SET qty = ?, price = ?, notes = ? WHERE id = ? AND cart_id = ?";
            $stmtUpd = $conn->prepare($sqlUpd);
            $stmtUpd->bind_param('idssi', $newQty, $price, $compiled_notes, (int)$existing['id'], $cart_id);
            $stmtUpd->execute();
        } else {
            $sqlIns = "INSERT INTO cart_items (cart_id, product_id, qty, price, notes) VALUES (?, ?, ?, ?, ?)";
            $stmtIns = $conn->prepare($sqlIns);
            $qty = 1;
            $stmtIns->bind_param('iiids', $cart_id, $id, $qty, $price, $compiled_notes);
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
                 WHERE ci.cart_id = ? AND p.deleted_at IS NULL
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

if ($action === 'get_item') {
    $key = isset($_GET['key']) ? trim((string)$_GET['key']) : '';

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

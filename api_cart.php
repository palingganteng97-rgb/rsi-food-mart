<?php
ob_start();
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Input dari UI
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0; // product_id
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    // tenant/patient context
    $patient_session_id = isset($_SESSION['patient_session_id']) ? (int)$_SESSION['patient_session_id'] : 0;
    $tenant_id = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 1;

    if ($id <= 0 || $patient_session_id <= 0 || $tenant_id <= 0 || $price < 0) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid payload/session']);
        exit;
    }

    mysqli_begin_transaction($conn);
    try {
        // Ambil cart terakhir untuk patient+tenant (tanpa status aktif kolom)
        $sqlCart = "SELECT id FROM carts WHERE patient_session_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1";
        $stmtCart = $conn->prepare($sqlCart);
        $stmtCart->bind_param('ii', $patient_session_id, $tenant_id);
        $stmtCart->execute();
        $resCart = $stmtCart->get_result();

        if ($resCart && $resCart->num_rows > 0) {
            $cart = $resCart->fetch_assoc();
            $cart_id = (int)$cart['id'];
        } else {
            // Buat cart baru
            $sqlInsertCart = "INSERT INTO carts (patient_session_id, tenant_id) VALUES (?, ?)";
            $stmtInsertCart = $conn->prepare($sqlInsertCart);
            $stmtInsertCart->bind_param('ii', $patient_session_id, $tenant_id);
            if (!$stmtInsertCart->execute()) {
                throw new Exception('Gagal INSERT carts: ' . $conn->error);
            }
            $cart_id = (int)$conn->insert_id;
        }

        // UPSERT cart_items berdasarkan (cart_id, product_id, notes)
        $sqlCheckItem = "SELECT id FROM cart_items WHERE cart_id = ? AND product_id = ? AND notes = ? LIMIT 1";
        $stmtCheck = $conn->prepare($sqlCheckItem);
        $stmtCheck->bind_param('iis', $cart_id, $id, $notes);
        $stmtCheck->execute();
        $resItem = $stmtCheck->get_result();

        if ($resItem && $resItem->num_rows > 0) {
            $row = $resItem->fetch_assoc();
            $item_id = (int)$row['id'];

            $sqlUpd = "UPDATE cart_items SET qty = qty + 1, price = ? WHERE id = ?";
            $stmtUpd = $conn->prepare($sqlUpd);
            $stmtUpd->bind_param('di', $price, $item_id);
            if (!$stmtUpd->execute()) {
                throw new Exception('Gagal UPDATE cart_items: ' . $conn->error);
            }
        } else {
            $sqlIns = "INSERT INTO cart_items (cart_id, product_id, qty, price, notes) VALUES (?, ?, 1, ?, ?)";
            $stmtIns = $conn->prepare($sqlIns);
            // cart_id (i), product_id (i), price (d), notes (s)
            $stmtIns->bind_param('iids', $cart_id, $id, $price, $notes);

            if (!$stmtIns->execute()) {
                throw new Exception('Gagal INSERT cart_items: ' . $conn->error);
            }
        }

        mysqli_commit($conn);

        // get total qty
        $sqlTotal = "SELECT COALESCE(SUM(qty),0) AS total_items
                    FROM cart_items ci
                    JOIN carts c ON ci.cart_id = c.id
                    WHERE c.patient_session_id = ? AND c.tenant_id = ?";
        $stmtTotal = $conn->prepare($sqlTotal);
        $stmtTotal->bind_param('ii', $patient_session_id, $tenant_id);
        $stmtTotal->execute();
        $resTotal = $stmtTotal->get_result();
        $totalRow = $resTotal ? $resTotal->fetch_assoc() : ['total_items' => 0];
        $total_items = (int)($totalRow['total_items'] ?? 0);

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'total_items' => $total_items]);
        exit;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'get_total') {
    $patient_session_id = isset($_SESSION['patient_session_id']) ? (int)$_SESSION['patient_session_id'] : 0;
    $tenant_id = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 1;

    if ($patient_session_id <= 0 || $tenant_id <= 0) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['total_items' => 0]);
        exit;
    }

    $sqlTotal = "SELECT COALESCE(SUM(qty),0) AS total_items
                FROM cart_items ci
                JOIN carts c ON ci.cart_id = c.id
                WHERE c.patient_session_id = ? AND c.tenant_id = ?";
    $stmt = $conn->prepare($sqlTotal);
    $stmt->bind_param('ii', $patient_session_id, $tenant_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : ['total_items' => 0];
    $total_items = (int)($row['total_items'] ?? 0);

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['total_items' => $total_items]);
    exit;
}

if ($action === 'get_cart_items') {
    $patient_session_id = isset($_SESSION['patient_session_id']) ? (int)$_SESSION['patient_session_id'] : 0;
    $tenant_id = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 1;

    if ($patient_session_id <= 0 || $tenant_id <= 0) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }

    // Ambil cart terakhir untuk patient+tenant
    $sqlCart = "SELECT id FROM carts WHERE patient_session_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1";
    $stmtCart = $conn->prepare($sqlCart);
    $stmtCart->bind_param('ii', $patient_session_id, $tenant_id);
    $stmtCart->execute();
    $resCart = $stmtCart->get_result();
    $cart = $resCart ? $resCart->fetch_assoc() : null;

    if (!$cart) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }

    $cart_id = (int)$cart['id'];

    // Ambil detail cart_items + produk
    $sqlItems = "SELECT ci.product_id, ci.qty, ci.price, ci.notes, p.name, p.image
                  FROM cart_items ci
                  JOIN products p ON p.id = ci.product_id
                  WHERE ci.cart_id = ?
                  ORDER BY ci.id ASC";

    $stmtItems = $conn->prepare($sqlItems);
    $stmtItems->bind_param('i', $cart_id);
    $stmtItems->execute();
    $resItems = $stmtItems->get_result();

    $items = [];
    if ($resItems) {
        while ($row = $resItems->fetch_assoc()) {
            $items[] = [
                'name' => $row['name'],
                'image' => $row['image'],
                'price' => (float)$row['price'],
                'qty' => (int)$row['qty'],
                'notes' => $row['notes'] ?? '',
            ];
        }
    }

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($items);
    exit;
}

ob_clean();
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit;

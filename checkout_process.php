<?php
// checkout_process.php - proses checkout pesanan pasien (HP)

include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hanya proses untuk pasien: wajib patient_session_id
if (!isset($_SESSION['patient_session_id']) || empty($_SESSION['patient_session_id'])) {
    header('Location: index.php');
    exit;
}

$patient_session_id = (int)$_SESSION['patient_session_id'];

// Ambil keranjang dari database
$sqlCart = "SELECT id FROM carts WHERE patient_session_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1";
$tenant_id = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 1;
$stmtCart = $conn->prepare($sqlCart);
$stmtCart->bind_param('ii', $patient_session_id, $tenant_id);
$stmtCart->execute();
$resCart = $stmtCart->get_result();
$cartRow = $resCart ? $resCart->fetch_assoc() : null;

$cart_id = $cartRow && isset($cartRow['id']) ? (int)$cartRow['id'] : 0;

$cart = [];
if ($cart_id > 0) {
    $sqlItems = "SELECT ci.product_id, ci.qty, ci.price, ci.notes
                  FROM cart_items ci
                  WHERE ci.cart_id = ?
                  ORDER BY ci.id ASC";
    $stmtItems = $conn->prepare($sqlItems);
    $stmtItems->bind_param('i', $cart_id);
    $stmtItems->execute();
    $resItems = $stmtItems->get_result();
    while ($row = $resItems ? $resItems->fetch_assoc() : null) {
        if (!$row) break;
        $cart[] = [
            'id' => (int)$row['product_id'],
            'qty' => (int)$row['qty'],
            'price' => (float)$row['price'],
            'notes' => $row['notes'] ?? ''
        ];
    }
}

if (empty($cart)) {
    header('Location: keranjang.php?status=error&msg=Keranjang kosong, tidak ada pesanan untuk diproses');
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    header('Location: keranjang.php?status=error&msg=Koneksi database tidak tersedia');
    exit;
}

// Ambil data pasien dari session
$patient_name = $_SESSION['patient_name'] ?? '';
$medical_record_number = $_SESSION['medical_record_number'] ?? '';
$room = $_SESSION['room'] ?? '';
$bed = $_SESSION['bed'] ?? '';
$class = $_SESSION['class'] ?? '';
$doctor = $_SESSION['doctor'] ?? '';

$now = date('Y-m-d H:i:s');
$status = 'Pending';

// Siapkan insert orders sesuai struktur tabel orders:
// id, order_number, patient_session_id, tenant_id, subtotal, discount, delivery_fee, grand_total,
// payment_status, status, created_at

// Tenant ID: ambil dari tenant_settings/tenant context yang sudah ada.
// Jika belum ada variabel tenant_id di project Anda, fallback ke 1.
$tenant_id = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 1;

$discount = 0;
$delivery_fee = 0;
$subtotal = 0;
$grand_total = 0;


$order_number = 'ORD-' . date('Ymd') . '-' . $patient_session_id . '-' . time();

$ordersColumns = [
    'order_number' => $order_number,
    'patient_session_id' => $patient_session_id,
    'tenant_id' => $tenant_id,
    'subtotal' => 0,
    'discount' => $discount,
    'delivery_fee' => $delivery_fee,
    'grand_total' => 0,

    'payment_status' => 'Unpaid',
    'status' => $status,
    'created_at' => $now
];


// Hitung total harga dan kumpulkan item
$total_price = 0;
$order_items_rows = [];


foreach ($cart as $item) {
    if (!is_array($item)) continue;

    $product_id = isset($item['id']) ? (int)$item['id'] : 0;
    $qty = isset($item['qty']) ? (int)$item['qty'] : 1;

    $unit_price = isset($item['price']) ? (float)$item['price'] : 0;

    if ($product_id <= 0 || $qty <= 0) continue;

    $line_total = $unit_price * $qty;
    $total_price += $line_total;

    $order_items_rows[] = [
        'product_id' => $product_id,
        'qty' => $qty,
        'price' => $unit_price,
        'notes' => $item['notes'] ?? ''
    ];

}

if (empty($order_items_rows)) {
    header('Location: keranjang.php?status=error&msg=Keranjang kosong atau format item tidak valid');
    exit;
}

$subtotal = (float)$total_price;
$grand_total = $subtotal - (float)$discount + (float)$delivery_fee;
$ordersColumns['subtotal'] = $subtotal;
$ordersColumns['grand_total'] = $grand_total;


mysqli_begin_transaction($conn);

try {
    // Insert orders
    $cols = array_keys($ordersColumns);
    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $sqlOrders = 'INSERT INTO orders (' . implode(',', $cols) . ') VALUES (' . $placeholders . ')';

    $stmtOrders = $conn->prepare($sqlOrders);
    if (!$stmtOrders) {
        throw new Exception('Gagal prepare INSERT orders: ' . $conn->error);
    }

    $types = '';
    $values = [];
    foreach ($ordersColumns as $col => $val) {
        if ($col === 'patient_session_id' || $col === 'tenant_id') {
            $types .= 'i';
            $values[] = (int)$val;
        } elseif (in_array($col, ['subtotal', 'discount', 'delivery_fee', 'grand_total'])) {
            $types .= 'd';
            $values[] = (float)$val;
        } else {
            // string / datetime
            $types .= 's';
            $values[] = (string)$val;
        }
    }



    $bindParams = array_merge([$types], $values);
    $ref = [];
    foreach ($bindParams as $k => $v) {
        $ref[$k] = &$bindParams[$k];
    }
    call_user_func_array([$stmtOrders, 'bind_param'], $ref);

    if (!$stmtOrders->execute()) {
        throw new Exception('Gagal INSERT orders: ' . $stmtOrders->error);
    }

    $order_id = (int)$conn->insert_id;
    if ($order_id <= 0) {
        throw new Exception('Gagal mengambil ID order yang baru dibuat');
    }

    // Insert order_items
    $inserted = 0;
    foreach ($order_items_rows as $row) {
        $itemsColumns = [
            'order_id' => $order_id,
            'product_id' => $row['product_id'],
            'qty' => $row['qty'],
            'price' => $row['price'],
            'notes' => $row['notes']
        ];

        $itemCols = array_keys($itemsColumns);
        $itemPlaceholders = implode(',', array_fill(0, count($itemCols), '?'));
        $sqlItems = 'INSERT INTO order_items (' . implode(',', $itemCols) . ') VALUES (' . $itemPlaceholders . ')';


        $stmtItems = $conn->prepare($sqlItems);
        if (!$stmtItems) {
            throw new Exception('Gagal prepare INSERT order_items: ' . $conn->error);
        }

        $types = '';
        $values = [];
        foreach ($itemsColumns as $col => $val) {
            if (in_array($col, ['order_id', 'product_id', 'qty'])) {
                $types .= 'i';
                $values[] = (int)$val;
            } elseif ($col === 'price') {
                $types .= 'd';
                $values[] = (float)$val;
            } else {
                $types .= 's';
                $values[] = (string)$val;
            }
        }


        $bindParams = array_merge([$types], $values);
        $ref = [];
        foreach ($bindParams as $k => $v) {
            $ref[$k] = &$bindParams[$k];
        }
        call_user_func_array([$stmtItems, 'bind_param'], $ref);

        if (!$stmtItems->execute()) {
            throw new Exception('Gagal INSERT order_items: ' . $stmtItems->error);
        }

        $inserted++;
    }

    if ($inserted !== count($order_items_rows)) {
        throw new Exception('Sebagian item gagal tersimpan');
    }

    mysqli_commit($conn);

    // Kosongkan cart di database
    $sqlDel = "DELETE FROM cart_items WHERE cart_id = ?";
    $stmtDel = $conn->prepare($sqlDel);
    $stmtDel->bind_param('i', $cart_id);
    $stmtDel->execute();

    $sqlDelCart = "DELETE FROM carts WHERE id = ?";
    $stmtDelCart = $conn->prepare($sqlDelCart);
    $stmtDelCart->bind_param('i', $cart_id);
    $stmtDelCart->execute();


    // Redirect sukses -> detail pesanan
    header('Location: order_detail.php?order_id=' . $order_id);
    exit;



} catch (Throwable $e) {
    mysqli_rollback($conn);

    $error = $e->getMessage();
    header('Location: keranjang.php?status=error&msg=' . urlencode('Checkout gagal: ' . $error));
    exit;
}



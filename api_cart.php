<?php
ob_start();
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $image = isset($_POST['image']) ? trim($_POST['image']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if ($id > 0) {
        $cartKey = $id . '_' . md5($name . '_' . $notes);

        if (isset($_SESSION['cart'][$cartKey])) {
            $_SESSION['cart'][$cartKey]['qty'] += 1;
        } else {
            $_SESSION['cart'][$cartKey] = [
                'id' => $id,
                'name' => $name,
                'price' => $price,
                'image' => $image,
                'notes' => $notes,
                'qty' => 1
            ];
        }

        $totalItem = 0;
        foreach ($_SESSION['cart'] as $item) {
            $totalItem += $item['qty'];
        }

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'total_items' => $totalItem]);
        exit;
    }
}

if ($action === 'get_total') {
    $totalItem = 0;
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $totalItem += $item['qty'];
        }
    }
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['total_items' => $totalItem]);
    exit;
}

if ($action === 'get_cart_items') {
    $items = [];
    if (isset($_SESSION['cart'])) {
        $items = array_values($_SESSION['cart']);
    }
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($items);
    exit;
}

// ========================================================
// AMBIL DATA ITEM LAMA BERDASARKAN KEY (UNTUK MODAL EDIT)
// ========================================================
if ($action === 'get_item') {
    $key = isset($_GET['key']) ? trim($_GET['key']) : '';
    
    ob_clean();
    header('Content-Type: application/json');
    
    if (!empty($key) && isset($_SESSION['cart'][$key])) {
        echo json_encode($_SESSION['cart'][$key]);
    } else {
        echo json_encode(null);
    }
    exit;
}

// ========================================================
// SIMPAN PERUBAHAN EDIT DATA PRODUK KE SESSION KERANJANG
// ========================================================
if ($action === 'update_saved' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldKey = isset($_POST['old_key']) ? trim($_POST['old_key']) : '';
    $id     = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $name   = isset($_POST['name']) ? trim($_POST['name']) : '';
    $price  = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $image  = isset($_POST['image']) ? trim($_POST['image']) : '';
    $notes  = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if (!empty($oldKey) && isset($_SESSION['cart'][$oldKey]) && $id > 0) {
        // Ambil jumlah kuantitas yang lama agar tidak kembali ke angka 1
        $currentQty = $_SESSION['cart'][$oldKey]['qty'];
        
        // Hapus kunci session lama agar tidak terjadi duplikasi menu
        unset($_SESSION['cart'][$oldKey]);

        // Buat kunci session baru berdasarkan kombinasi nama varian/topping baru
        $newCartKey = $id . '_' . md5($name . '_' . $notes);

        // Masukkan data baru ke dalam session
        $_SESSION['cart'][$newCartKey] = [
            'id'    => $id,
            'name'  => $name,
            'price' => $price,
            'image' => $image,
            'notes' => $notes,
            'qty'   => $currentQty
        ];

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

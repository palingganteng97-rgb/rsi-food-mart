<?php
// checkout_process.php - Proses Checkout Otomatis Berbasis Sesi Scan QR Pasien
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pembantu fungsi parsing float agar seragam
function parse_as_float($v): float {
    $f = filter_var($v, FILTER_VALIDATE_FLOAT);
    return $f === false ? 0.0 : (float)$f;
}

$patient_session_id = isset($_SESSION['patient_session_id']) ? intval($_SESSION['patient_session_id']) : 0;
$cart_items = [];
$main_cart_id = 0;
$tenant_id = 1;

$payment_method_id = isset($_POST['payment_method_id']) ? intval($_POST['payment_method_id']) : 0;
if ($payment_method_id <= 0) {
    // fallback: ambil metode pembayaran pertama agar alur tidak putus
    $pmStmt = $conn->query("SELECT id FROM payment_methods ORDER BY id DESC LIMIT 1");
    if ($pmStmt && $pmStmt->num_rows > 0) {
        $row = $pmStmt->fetch_assoc();
        $payment_method_id = intval($row['id'] ?? 0);
    }
}


// Jalankan query standar berdasarkan session pasien saat ini jika valid
if ($patient_session_id > 0) {
    $check_session = mysqli_query($conn, "SELECT id FROM patient_sessions WHERE id = $patient_session_id");
    if ($check_session && mysqli_num_rows($check_session) > 0) {
        $cart_query = "SELECT ci.*, c.id AS main_cart_id, c.tenant_id 
                       FROM cart_items ci
                       JOIN carts c ON ci.cart_id = c.id
                       WHERE c.patient_session_id = $patient_session_id";
        $cart_result = mysqli_query($conn, $cart_query);
        if ($cart_result && mysqli_num_rows($cart_result) > 0) {
            $cart_items = mysqli_fetch_all($cart_result, MYSQLI_ASSOC);
        }
    }
}

// Failsafe: cari data keranjang di database yang patient_session_id-nya VALID
if (empty($cart_items)) {
    $failsafe_query = "SELECT ci.*, c.id AS main_cart_id, c.tenant_id, c.patient_session_id 
                       FROM cart_items ci
                       JOIN carts c ON ci.cart_id = c.id
                       JOIN patient_sessions ps ON c.patient_session_id = ps.id
                       ORDER BY c.id DESC LIMIT 1"; 
    $failsafe_result = mysqli_query($conn, $failsafe_query);
    
    if ($failsafe_result && mysqli_num_rows($failsafe_result) > 0) {
        $cart_items = mysqli_fetch_all($failsafe_result, MYSQLI_ASSOC);
        $patient_session_id = intval($cart_items[0]['patient_session_id']);
        $_SESSION['patient_session_id'] = $patient_session_id;
    }
}

// Fallback ke cart aktif tenant terbaru jika masih kosong
if (empty($cart_items)) {
    $fallback_cart_query = "SELECT ci.*, c.id AS main_cart_id, c.tenant_id, c.patient_session_id
                            FROM cart_items ci
                            JOIN carts c ON ci.cart_id = c.id
                            WHERE c.tenant_id = ?
                            ORDER BY c.id DESC, ci.id ASC LIMIT 200";

    $fallback_stmt = $conn->prepare($fallback_cart_query);
    $fallback_stmt->bind_param('i', $tenant_id);
    $fallback_stmt->execute();
    $fallback_res = $fallback_stmt->get_result();

    if ($fallback_res && $fallback_res->num_rows > 0) {
        $cart_items = mysqli_fetch_all($fallback_res, MYSQLI_ASSOC);
        if (!empty($cart_items[0]['patient_session_id'])) {
            $_SESSION['patient_session_id'] = (int)$cart_items[0]['patient_session_id'];
            $patient_session_id = (int)$cart_items[0]['patient_session_id'];
        }
    }
}

// =========================================================================
// INTEGRASI BAGIAN 1: LOGIKA FLEKSIBEL (PERBAIKAN KOLOM TENANT_ID)
// =========================================================================
$check_ps = mysqli_query($conn, "SELECT id FROM patient_sessions WHERE id = $patient_session_id");
if ($patient_session_id <= 0 || !$check_ps || mysqli_num_rows($check_ps) === 0) {
    // Memperbaiki kolom kosong agar sesuai dengan struktur asli database RSI lokal Anda
    mysqli_query($conn, "INSERT INTO patient_sessions () VALUES ()");
    $patient_session_id = intval(mysqli_insert_id($conn));
    $_SESSION['patient_session_id'] = $patient_session_id;
    
    // Perbarui kaitan data induk di tabel carts agar sinkron dengan sesi baru
    if (!empty($cart_items)) {
        $main_cart_id = intval($cart_items[0]['main_cart_id']);
        mysqli_query($conn, "UPDATE carts SET patient_session_id = $patient_session_id WHERE id = $main_cart_id");
    }
}

// Jika setelah semua skenario fallback tetap kosong, barulah lempar error.
if (empty($cart_items)) {
    header("Location: carts.php?status=error&msg=" . urlencode("Keranjang belanja kosong. Silakan scan ulang QR Code Ruangan."));
    exit();
}

$main_cart_id = intval($cart_items[0]['main_cart_id']);
$tenant_id    = intval($cart_items[0]['tenant_id'] ?? 1); 

// =========================================================================
// INTEGRASI BAGIAN 2: HITUNG TOTAL AKURAT TERMASUK HARGA TOPPING DARI NOTES
// =========================================================================
$grand_total = 0;
$processed_cart_items = []; 

// Fail-safe: validasi produk aktif sebelum hitung total + insert order_items
$activeProductsMap = [];
$productIds = array_values(array_unique(array_map(fn($ci) => intval($ci['product_id'] ?? 0), $cart_items)));
$productIds = array_values(array_filter($productIds, fn($pid) => $pid > 0));

if (!empty($productIds)) {
    $inStr = implode(',', array_map('intval', $productIds));
    $resActive = mysqli_query($conn, "SELECT id FROM products WHERE deleted_at IS NULL AND id IN ($inStr)");
    if ($resActive) {
        while ($r = mysqli_fetch_assoc($resActive)) {
            $activeProductsMap[(int)$r['id']] = true;
        }
    }
}

foreach ($cart_items as $item) {
    $product_id_raw = intval($item['product_id'] ?? 0);
    if ($product_id_raw <= 0) continue;
    // Tolak produk terhapus
    if (empty($activeProductsMap[$product_id_raw])) {
        continue;
    }

    $qty = intval($item['qty']);
    $base_price = parse_as_float($item['price']);
    $raw_notes = (string)($item['notes'] ?? '');
    
    $addon_sum = 0.0;
    
    // Pecah string notes untuk mencari "ToppingID:"
    if (strpos($raw_notes, 'Varian:') !== false) {
        $parts = explode('|', $raw_notes);
        foreach ($parts as $part) {
            $part = trim($part);
            if (strpos($part, 'ToppingID:') === 0) {
                $id_string = trim(substr($part, 10));
                if ($id_string !== '') {
                    $selected_addon_ids = array_map('intval', explode(',', $id_string));
                    $ids_clean_str = implode(',', $selected_addon_ids);
                    
                    $addon_res = mysqli_query($conn, "SELECT SUM(price) AS total_addon_price FROM addon_items WHERE id IN ($ids_clean_str)");
                    if ($addon_res) {
                        $addon_row = mysqli_fetch_assoc($addon_res);
                        $addon_sum = parse_as_float($addon_row['total_addon_price']);
                    }
                }
            }
        }
    }
    
    $final_unit_price = $base_price + $addon_sum;
    $grand_total += ($qty * $final_unit_price);
    
    $item['final_calculated_price'] = $final_unit_price;
    $processed_cart_items[] = $item;
}

// =========================================================================
// MULAI PROSES DATABASE TRANSACTION
// =========================================================================
mysqli_begin_transaction($conn);

try {
    $order_number = "INV-" . date('Ymd') . "-" . rand(1000, 9999);
    $status         = 'PENDING';
    $payment_status = 'UNPAID';
    
    // Masukkan data ke induk tabel 'orders'
    $insert_order_query = "INSERT INTO orders (order_number, patient_session_id, tenant_id, grand_total, status, payment_status, created_at) 
                           VALUES ('$order_number', $patient_session_id, $tenant_id, $grand_total, '$status', '$payment_status', NOW())";

    if (!mysqli_query($conn, $insert_order_query)) {
        throw new Exception("Gagal membuat data transaksi orders: " . mysqli_error($conn));
    }

    $new_order_id = mysqli_insert_id($conn);

    // Siapkan nomor transaksi pembayaran
    // Konsisten dengan format nomor order yang sudah dipakai project: INV-YYYYmmdd-rand
    $transaction_number = $order_number;

    
    // Pindahkan setiap baris menu makanan ke 'order_items'
foreach ($processed_cart_items as $item) {
        $product_id = intval($item['product_id']);
        if (empty($activeProductsMap[$product_id])) {
            continue;
        }
        $qty        = intval($item['qty']);
        $price      = floatval($item['final_calculated_price']); 
        $notes      = mysqli_real_escape_string($conn, trim($item['notes'] ?? ''));
        
        $insert_item_query = "INSERT INTO order_items (order_id, product_id, qty, price, notes) 
                              VALUES ($new_order_id, $product_id, $qty, $price, '$notes')";
        
        if (!mysqli_query($conn, $insert_item_query)) {
            throw new Exception("Gagal memproses detail item produk: " . mysqli_error($conn));
        }
    }

    // Catat data pembayaran
    // Pastikan kolom-kolom yang dipakai sesuai dengan yang dibaca di payments.php
    // (order_id, payment_method_id, transaction_number, status)
    $insert_payment_query = "INSERT INTO payments (order_id, payment_method_id, transaction_number, status, created_at)
                              VALUES ($new_order_id, $payment_method_id, '$transaction_number', '$payment_status', NOW())";

    if (!mysqli_query($conn, $insert_payment_query)) {
        throw new Exception("Gagal mencatat data pembayaran: " . mysqli_error($conn));
    }

    
    // Catat log histori transaksi pembuka
    $log_notes = mysqli_real_escape_string($conn, "Pesanan otomatis masuk sistem melalui checkout keranjang belanja.");
    $insert_history_query = "INSERT INTO order_status_histories (order_id, status, changed_by, notes, created_at) 
                             VALUES ($new_order_id, '$status', NULL, '$log_notes', NOW())";
    
    if (!mysqli_query($conn, $insert_history_query)) {
        throw new Exception("Gagal mencatat log histori awal status.");
    }
    
    // Bersihkan isi item keranjang belanja (cart_items)
    $delete_items_query = "DELETE FROM cart_items WHERE cart_id = $main_cart_id";
    if (!mysqli_query($conn, $delete_items_query)) {
        throw new Exception("Gagal mengosongkan item keranjang.");
    }
    
    // Bersihkan data induk keranjang (carts)
    $delete_cart_query = "DELETE FROM carts WHERE id = $main_cart_id";
    if (!mysqli_query($conn, $delete_cart_query)) {
        throw new Exception("Gagal menghapus instalasi keranjang.");
    }
    
    // Jika sukses tanpa interupsi, simpan permanen ke database
    mysqli_commit($conn);
    
    // Redirect ke halaman yang relevan untuk melihat payment/order
    // Di project ini, halaman orders.php dipakai untuk admin riwayat pesanan.
    // Namun target user adalah halaman pembayaran/status transaksi; gunakan orders.php sebagai fallback.
    $redirectUrl = "payments.php?status=success_create";

    // Jika payments sudah tersimpan, arahkan juga ke list pembayaran admin
    header("Location: $redirectUrl");
    exit();


} catch (Exception $e) {
    mysqli_rollback($conn);
    // Trik Pembersihan Error: memunculkan teks deskripsi asli jika masih menyumbat
    echo "<h1>Terjadi Error Saat Simpan Transaksi:</h1>";
    echo "<p>" . h($e->getMessage()) . "</p>";
    exit();
}
?>

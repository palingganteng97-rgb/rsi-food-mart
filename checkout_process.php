<?php
// checkout_process.php - Proses Checkout Otomatis Berbasis Sesi Scan QR Pasien
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Ambil patient_session_id dari session browser pasien
$patient_session_id = isset($_SESSION['patient_session_id']) ? intval($_SESSION['patient_session_id']) : 0;

$cart_items = [];
$main_cart_id = 0;
$tenant_id = 1;

// Jalankan query standar berdasarkan session pasien saat ini jika valid
if ($patient_session_id > 0) {
    // Pastikan ID tersebut benar-benar ada di tabel patient_sessions (Validasi Foreign Key)
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

// =========================================================================
// LOGIKA FAILSAFE: Jika session tidak cocok, cari data keranjang di database 
// yang patient_session_id-nya VALID & ADA di tabel patient_sessions
// =========================================================================
if (empty($cart_items)) {
    $failsafe_query = "SELECT ci.*, c.id AS main_cart_id, c.tenant_id, c.patient_session_id 
                       FROM cart_items ci
                       JOIN carts c ON ci.cart_id = c.id
                       JOIN patient_sessions ps ON c.patient_session_id = ps.id
                       ORDER BY c.id DESC LIMIT 1"; 
    $failsafe_result = mysqli_query($conn, $failsafe_query);
    
    if ($failsafe_result && mysqli_num_rows($failsafe_result) > 0) {
        $cart_items = mysqli_fetch_all($failsafe_result, MYSQLI_ASSOC);
        
        // PERBAIKAN UTAMA: Tambahkan indeks [0] karena mysqli_fetch_all menghasilkan array berundak
        $patient_session_id = intval($cart_items[0]['patient_session_id']);
        $_SESSION['patient_session_id'] = $patient_session_id;
    }
}

// Jika setelah validasi silang data keranjang tetap kosong, lakukan fallback ke cart aktif tenant terbaru.
// Ini mencegah redirect palsu ketika session pasien tidak sinkron, tapi item sebenarnya masih ada di database.
if (empty($cart_items)) {
    $fallback_cart_query = "SELECT ci.*, c.id AS main_cart_id, c.tenant_id, c.patient_session_id
                              FROM cart_items ci
                              JOIN carts c ON ci.cart_id = c.id
                              WHERE c.tenant_id = ?
                              ORDER BY c.id DESC, ci.id ASC
                              LIMIT 200";

    $fallback_stmt = $conn->prepare($fallback_cart_query);
    $fallback_stmt->bind_param('i', $tenant_id);
    $fallback_stmt->execute();
    $fallback_res = $fallback_stmt->get_result();

    if ($fallback_res && $fallback_res->num_rows > 0) {
        $cart_items = mysqli_fetch_all($fallback_res, MYSQLI_ASSOC);

        // Kalau ada patient_session_id dari cart, update session agar tetap konsisten
        if (!empty($cart_items[0]['patient_session_id'])) {
            $_SESSION['patient_session_id'] = (int)$cart_items[0]['patient_session_id'];
            $patient_session_id = (int)$cart_items[0]['patient_session_id'];
        }
    }
}

// Jika tetap kosong, barulah redirect error.
if (empty($cart_items)) {
    header("Location: carts.php?status=error&msg=" . urlencode("Keranjang belanja kosong. Silakan scan ulang QR Code Ruangan."));
    exit();
}

// Mengunci data ID induk keranjang dan ID tenant dari baris record pertama hasil query
$main_cart_id = intval($cart_items[0]['main_cart_id']);
$tenant_id    = intval($cart_items[0]['tenant_id'] ?? 1); 

// 2. Hitung total akumulasi bayar belanja di backend
$grand_total = 0;
foreach ($cart_items as $item) {
    $grand_total += (int)$item['qty'] * (float)$item['price'];
}

// =========================================================================
// MULAI PROSES DATABASE TRANSACTION (ANTI-GAGAL)
// =========================================================================
mysqli_begin_transaction($conn);

try {
    // Membuat nomor invoice unik otomatis (INV-TAHUNBULANTANGGAL-ACAK)
    $order_number = "INV-" . date('Ymd') . "-" . rand(1000, 9999);
    
    $status         = 'PENDING';
    $payment_status = 'UNPAID';
    
    // 3. Masukkan data ke induk tabel 'orders'
    $insert_order_query = "INSERT INTO orders (order_number, patient_session_id, tenant_id, grand_total, status, payment_status, created_at) 
                           VALUES ('$order_number', $patient_session_id, $tenant_id, $grand_total, '$status', '$payment_status', NOW())";
    
    if (!mysqli_query($conn, $insert_order_query)) {
        throw new Exception("Gagal membuat data transaksi orders: " . mysqli_error($conn));
    }
    
    // Mengambil ID pesanan orders yang baru saja tercipta
    $new_order_id = mysqli_insert_id($conn);
    
    // 4. Pindahkan setiap baris menu makanan dari 'cart_items' ke 'order_items'
    foreach ($cart_items as $item) {
        $product_id = intval($item['product_id']);
        $qty        = intval($item['qty']);
        $price      = floatval($item['price']);
        $notes      = mysqli_real_escape_string($conn, trim($item['notes'] ?? ''));
        
        $insert_item_query = "INSERT INTO order_items (order_id, product_id, qty, price, notes) 
                              VALUES ($new_order_id, $product_id, $qty, $price, '$notes')";
        
        if (!mysqli_query($conn, $insert_item_query)) {
            throw new Exception("Gagal memproses detail item produk: " . mysqli_error($conn));
        }
    }
    
    // 5. Catat log jejak transaksi pembuka ke tabel 'order_status_histories'
    $log_notes = mysqli_real_escape_string($conn, "Pesanan otomatis masuk sistem melalui checkout keranjang belanja.");
    $insert_history_query = "INSERT INTO order_status_histories (order_id, status, changed_by, notes, created_at) 
                             VALUES ($new_order_id, '$status', NULL, '$log_notes', NOW())";
    
    if (!mysqli_query($conn, $insert_history_query)) {
        throw new Exception("Gagal mencatat log histori awal status.");
    }
    
    // 6. Bersihkan isi item keranjang belanja (cart_items)
    $delete_items_query = "DELETE FROM cart_items WHERE cart_id = $main_cart_id";
    if (!mysqli_query($conn, $delete_items_query)) {
        throw new Exception("Gagal mengosongkan item keranjang.");
    }
    
    // 7. Bersihkan data induk keranjang (carts)
    $delete_cart_query = "DELETE FROM carts WHERE id = $main_cart_id";
    if (!mysqli_query($conn, $delete_cart_query)) {
        throw new Exception("Gagal menghapus instalasi keranjang.");
    }
    
    // Jika seluruh rangkaian proses di atas sukses tanpa interupsi, simpan permanen ke database
    mysqli_commit($conn);
    
    // Sukses! Alihkan halaman pasien langsung ke daftar pesanan utama
    header("Location: orders.php?status=success_add");
    exit();

} catch (Exception $e) {
    // Apabila terjadi error di tengah jalan, batalkan semua manipulasi data di atas agar database bersih kembali
    mysqli_rollback($conn);
    
    header("Location: carts.php?status=error&msg=" . urlencode($e->getMessage()));
    exit();
}
?>

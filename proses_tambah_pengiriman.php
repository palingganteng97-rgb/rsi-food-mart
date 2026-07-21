<?php
/**
 * proses_tambah_pengiriman.php
 * 
 * Handler khusus untuk menambah data pengiriman (delivery) secara manual dari form.
 * File ini dipanggil oleh form di deliveries.php dengan method POST.
 * 
 * Dilengkapi dengan:
 * - Validasi Foreign Key (order_id, courier_id)
 * - Penanganan error database transparan (menampilkan $stmt->error dan $conn->error)
 * - Transaction (BEGIN/COMMIT/ROLLBACK)
 * - Logging detail penyebab kegagalan
 */

include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =========================================================================
// Inisialisasi Variabel Default
// =========================================================================
$error_message = '';
$success       = false;

// =========================================================================
// Validasi Method Request
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: deliveries.php?status=error&msg=" . urlencode("Metode request tidak valid. Harus menggunakan POST."));
    exit();
}

// =========================================================================
// Ambil & Sanitasi Input
// =========================================================================
$order_id      = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$courier_id    = isset($_POST['courier_id']) ? intval($_POST['courier_id']) : 0;
$status        = isset($_POST['status']) ? trim($_POST['status']) : 'PENDING';
$pickup_time   = isset($_POST['pickup_time']) && !empty($_POST['pickup_time']) ? $_POST['pickup_time'] : null;
$delivery_time = isset($_POST['delivery_time']) && !empty($_POST['delivery_time']) ? $_POST['delivery_time'] : null;
$proof_photo   = isset($_POST['proof_photo']) ? trim($_POST['proof_photo']) : '';

// =========================================================================
// Validasi Input Dasar
// =========================================================================
$errors = [];

if ($order_id <= 0) {
    $errors[] = "⚠️ order_id tidak valid atau tidak diisi. Nilai: ($order_id)";
} else {
    // Validasi Foreign Key: pastikan order_id benar-benar ada di tabel orders
    $stmt_chk = $conn->prepare("SELECT id, order_number, status FROM orders WHERE id = ? LIMIT 1");
    if ($stmt_chk) {
        $stmt_chk->bind_param("i", $order_id);
        $stmt_chk->execute();
        $res_chk = $stmt_chk->get_result();
        if ($res_chk->num_rows === 0) {
            $errors[] = "❌ Foreign Key Constraint VIOLATION: order_id ($order_id) TIDAK DITEMUKAN di tabel `orders`. 
                         Pastikan Anda memilih Order yang valid. Error SQL: 'Cannot add or update a child row: a foreign key constraint fails'.";
        } else {
            $order_data = $res_chk->fetch_assoc();
            // Informasi tambahan untuk logging
            $order_number = $order_data['order_number'] ?? '-';
            $order_status = $order_data['status'] ?? '-';
        }
        $stmt_chk->close();
    } else {
        $errors[] = "⚠️ Gagal memeriksa tabel orders: " . $conn->error;
    }

    // Cegah duplikasi: cek apakah sudah ada delivery untuk order_id ini
    $stmt_dup = $conn->prepare("SELECT id FROM deliveries WHERE order_id = ? LIMIT 1");
    if ($stmt_dup) {
        $stmt_dup->bind_param("i", $order_id);
        $stmt_dup->execute();
        $res_dup = $stmt_dup->get_result();
        if ($res_dup->num_rows > 0) {
            $errors[] = "❌ DUPLICATE ENTRY: Delivery untuk order_id ($order_id) [Order: $order_number] SUDAH ADA. 
                         Setiap order hanya boleh memiliki SATU record pengiriman. Silakan gunakan fitur Edit jika ingin mengubah data.";
        }
        $stmt_dup->close();
    }
}

if ($courier_id <= 0) {
    $errors[] = "⚠️ courier_id tidak valid atau tidak diisi. Nilai: ($courier_id)";
} else {
    // Validasi Foreign Key: pastikan courier_id ada di tabel couriers
    $stmt_chk2 = $conn->prepare("SELECT id, name FROM couriers WHERE id = ? LIMIT 1");
    if ($stmt_chk2) {
        $stmt_chk2->bind_param("i", $courier_id);
        $stmt_chk2->execute();
        $res_chk2 = $stmt_chk2->get_result();
        if ($res_chk2->num_rows === 0) {
            $errors[] = "❌ Foreign Key Constraint VIOLATION: courier_id ($courier_id) TIDAK DITEMUKAN di tabel `couriers`. 
                         Pastikan Anda memilih Kurir yang valid. Error SQL: 'Cannot add or update a child row: a foreign key constraint fails'.";
        } else {
            $courier_data = $res_chk2->fetch_assoc();
            $courier_name = $courier_data['name'] ?? '-';
        }
        $stmt_chk2->close();
    } else {
        $errors[] = "⚠️ Gagal memeriksa tabel couriers: " . $conn->error;
    }
}

// =========================================================================
// Jika ada error validasi, tampilkan semua dan redirect
// =========================================================================
if (!empty($errors)) {
    $error_message = implode(" | ", $errors);
    header("Location: deliveries.php?status=error&msg=" . urlencode($error_message));
    exit();
}

// =========================================================================
// SIMPAN DATA DENGAN TRANSACTION DAN ERROR HANDLING TRANSPARAN
// =========================================================================
mysqli_begin_transaction($conn);

try {
    // Query INSERT dengan prepared statement
    $sql = "INSERT INTO deliveries (order_id, courier_id, status, pickup_time, delivery_time, proof_photo) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Error saat prepare (misal: kolom tidak sesuai)
        throw new Exception(
            "❌ SQL Prepare Error:\n" .
            "- Query: $sql\n" .
            "- Pesan Error: " . $conn->error . "\n" .
            "- Errno: " . $conn->errno . "\n" .
            "Kemungkinan penyebab: Nama kolom salah, atau tipe data tidak sesuai."
        );
    }

    // Binding parameter
    $bind_result = $stmt->bind_param("iissss", $order_id, $courier_id, $status, $pickup_time, $delivery_time, $proof_photo);
    if (!$bind_result) {
        throw new Exception(
            "❌ Binding Parameter Error:\n" .
            "- Pesan Error: " . $stmt->error . "\n" .
            "- Parameter: order_id=$order_id, courier_id=$courier_id, status=$status\n" .
            "Kemungkinan penyebab: Jumlah parameter tidak sesuai dengan query."
        );
    }

    // Eksekusi query
    $exec_result = $stmt->execute();
    
    if (!$exec_result) {
        // Tangkap detail error dari MySQL
        $sql_error_code = $stmt->errno;
        $sql_error_msg  = $stmt->error;
        
        // Buat pesan error yang deskriptif berdasarkan kode error MySQL
        $human_readable_error = '';
        
        switch ($sql_error_code) {
            case 1452: // Foreign key constraint fails
                $human_readable_error = "🔴 FOREIGN KEY CONSTRAINT ERROR (MySQL Error #$sql_error_code)";
                if (strpos($sql_error_msg, 'order_id') !== false) {
                    $human_readable_error .= "\n- Penyebab: order_id ($order_id) merujuk ke ID yang tidak ada di tabel `orders`.";
                    $human_readable_error .= "\n- Solusi: Pilih Order yang valid dari daftar yang tersedia.";
                } elseif (strpos($sql_error_msg, 'courier_id') !== false) {
                    $human_readable_error .= "\n- Penyebab: courier_id ($courier_id) merujuk ke ID yang tidak ada di tabel `couriers`.";
                    $human_readable_error .= "\n- Solusi: Pilih Kurir yang valid dari daftar yang tersedia.";
                } else {
                    $human_readable_error .= "\n- Penyebab: Salah satu Foreign Key tidak valid.";
                    $human_readable_error .= "\n- Detail SQL: $sql_error_msg";
                }
                break;
                
            case 1048: // Column cannot be null
                $human_readable_error = "🔴 NULL CONSTRAINT ERROR (MySQL Error #$sql_error_code)";
                if (strpos($sql_error_msg, 'order_id') !== false) {
                    $human_readable_error .= "\n- Penyebab: Kolom `order_id` tidak boleh NULL (NOT NULL constraint).";
                    $human_readable_error .= "\n- Solusi: Pastikan Order dipilih.";
                } elseif (strpos($sql_error_msg, 'courier_id') !== false) {
                    $human_readable_error .= "\n- Penyebab: Kolom `courier_id` tidak boleh NULL (NOT NULL constraint).";
                    $human_readable_error .= "\n- Solusi: Pastikan Kurir dipilih.";
                } else {
                    $human_readable_error .= "\n- Penyebab: Ada kolom wajib yang tidak diisi.";
                    $human_readable_error .= "\n- Detail SQL: $sql_error_msg";
                }
                break;
                
            case 1062: // Duplicate entry
                $human_readable_error = "🔴 DUPLICATE ENTRY ERROR (MySQL Error #$sql_error_code)";
                $human_readable_error .= "\n- Penyebab: Data dengan kombinasi yang sama sudah ada di database.";
                $human_readable_error .= "\n- Detail SQL: $sql_error_msg";
                $human_readable_error .= "\n- Solusi: Gunakan fitur Edit untuk mengubah data yang sudah ada.";
                break;
                
            default:
                $human_readable_error = "🔴 UNKNOWN SQL ERROR (MySQL Error #$sql_error_code)";
                $human_readable_error .= "\n- Detail SQL: $sql_error_msg";
                break;
        }
        
        throw new Exception($human_readable_error);
    }
    
    // =========================================================================
    // COMMIT: Semua berhasil
    // =========================================================================
    mysqli_commit($conn);
    
    // Log sukses ke error_log PHP
    error_log("[DELIVERY CREATE SUCCESS] order_id=$order_id, courier_id=$courier_id, status=$status");
    
    $stmt->close();
    
    // Redirect ke halaman deliveries dengan status sukses
    header("Location: deliveries.php?status=success_create");
    exit();
    
} catch (Exception $e) {
    // =========================================================================
    // ROLLBACK: Ada error, batalkan semua perubahan
    // =========================================================================
    mysqli_rollback($conn);
    
    $error_detail = $e->getMessage();
    
    // Log error ke file error_log PHP
    error_log("[DELIVERY CREATE FAILED] " . $error_detail);
    
    // Tambahkan informasi debugging tambahan
    $full_error_msg = $error_detail;
    $full_error_msg .= "\n\n--- DEBUG INFO ---";
    $full_error_msg .= "\n- Input: order_id=$order_id, courier_id=$courier_id, status=$status";
    $full_error_msg .= "\n- pickup_time=" . ($pickup_time ?? 'NULL');
    $full_error_msg .= "\n- delivery_time=" . ($delivery_time ?? 'NULL');
    $full_error_msg .= "\n- proof_photo=" . ($proof_photo ?: '(kosong)');
    $full_error_msg .= "\n- Waktu: " . date('Y-m-d H:i:s');
    
    // Redirect ke halaman deliveries dengan pesan error lengkap
    header("Location: deliveries.php?status=error&msg=" . urlencode($full_error_msg));
    exit();
}


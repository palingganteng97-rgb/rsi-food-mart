<?php
// add_favorite.php - Logika Menambahkan Menu ke Daftar Favorit Pasien
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Ambil patient_session_id aktif dari scan QR pasien (jika kosong, buat failsafe ID 1)
$patient_session_id = isset($_SESSION['patient_session_id']) ? intval($_SESSION['patient_session_id']) : 1;
$product_id         = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$tenant_id          = isset($_GET['tenant_id']) ? intval($_GET['tenant_id']) : 1;

if ($product_id > 0) {
    // 2. Cek apakah produk ini sudah pernah difavoritkan oleh pasien tersebut agar tidak ganda
    $check = $conn->prepare("SELECT id FROM favorites WHERE patient_session_id = ? AND product_id = ?");
    $check->bind_param("ii", $patient_session_id, $product_id);
    $check->execute();
    $res_check = $check->get_result();

    if ($res_check->num_rows === 0) {
        // 3. Jika belum ada, masukkan ke tabel favorites sesuai struktur HeidiSQL Anda
        $stmt = $conn->prepare("INSERT INTO favorites (patient_session_id, product_id, tenant_id) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $patient_session_id, $product_id, $tenant_id);
        $stmt->execute();
        
        // Alihkan kembali ke home dengan parameter sukses
        header("Location: home.php?fav_status=success");
    } else {
        // 4. Jika diklik lagi saat sudah ada, otomatis hapus dari favorit (Toggle Unfavorite)
        $stmt_del = $conn->prepare("DELETE FROM favorites WHERE patient_session_id = ? AND product_id = ?");
        $stmt_del->bind_param("ii", $patient_session_id, $product_id);
        $stmt_del->execute();
        
        header("Location: home.php?fav_status=removed");
    }
    exit();
}

header("Location: home.php");
exit();

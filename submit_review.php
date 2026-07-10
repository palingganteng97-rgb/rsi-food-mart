<?php
include "db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan pembeli sudah login
if (!isset($_SESSION['patient_session_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id         = intval($_POST['product_id']);
    $patient_session_id = intval($_SESSION['patient_session_id']); // Mengambil otomatis dari session login pembeli
    $rating             = intval($_POST['rating']);
    $review             = mysqli_real_escape_string($conn, trim($_POST['review']));

    if ($product_id > 0 && $rating >= 1 && $rating <= 5 && !empty($review)) {
        // Simpan langsung ke tabel product_reviews yang sudah Anda miliki
        $query = "INSERT INTO product_reviews (product_id, patient_session_id, rating, review) 
                  VALUES ($product_id, $patient_session_id, $rating, '$review')";
        
        if (mysqli_query($conn, $query)) {
            header("Location: riwayat_pesanan.php?status=review_success");
            exit;
        }
    }
}
header("Location: riwayat_pesanan.php?status=review_failed");
exit;

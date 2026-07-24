<?php
// db.php - koneksi database pusat
// Catatan: file ini juga menjalankan session_start().

// ===== DB CONFIG =====
$dbHost = '10.10.6.59';
$dbUser = 'root_host';
$dbPass = 'password';
$dbName = 'magang_rsi_food_mart';

$conn = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);

if (!$conn) {
    die('Koneksi database gagal: ' . mysqli_connect_error());
}

// session_start sudah diminta dilakukan di file ini
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Universal exception handler for mysqli to gracefully catch foreign key constraint errors
set_exception_handler(function($e) {
    if ($e instanceof mysqli_sql_exception) {
        $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        if ($e->getCode() == 1451) {
            echo "<script>alert('Gagal! Data ini tidak dapat dihapus karena masih digunakan/terhubung dengan data lain.'); window.location='$referer';</script>";
            exit();
        } else {
            echo "<script>alert('Terjadi kesalahan database: ". addslashes($e->getMessage()) ."'); window.location='$referer';</script>";
            exit();
        }
    }
    throw $e;
});

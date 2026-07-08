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
?>


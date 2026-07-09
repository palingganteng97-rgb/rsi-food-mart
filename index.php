<?php
include "db.php"; // Memanggil koneksi database ($conn) & session_start()

// Proteksi Halaman: Jika belum login, tendang balik ke login.php
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memuat Aplikasi...</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

    <style>
        .pill { border: 1px solid rgba(34,197,94,.35); background: rgba(34,197,94,.08); color: #86efac; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100" style="background: #0f172a; color: #e5e7eb;">

    <!-- TAMPILAN FULL SCREEN LOADING (SEPERTI SPLASH SCREEN APLIKASI) -->
    <div class="text-center">
        <div class="d-flex justify-content-center mb-4">
            <div class="p-3 rounded-4 pill">
                <i class="bi bi-hospital" style="font-size: 3rem;"></i>
            </div>
        </div>
        
        <h3 class="fw-bold text-white mb-2">RSI FOOD &amp; MART</h3>
        <p class="text-white-50 small mb-4">Menyiapkan Pemesanan Makanan Sehat Anda</p>
        
        <div class="spinner-border text-success" style="width: 2.5rem; height: 2.5rem;" role="status"></div>
        <div class="mt-3 text-white-50 small" style="letter-spacing: 0.5px;">Memverifikasi Akun Keamanan...</div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JAVASCRIPT: Menahan halaman selama 4 detik, lalu otomatis membuka home.php -->
    <script>
        setTimeout(function() {
            window.location.href = "home.php";
        }, 4000); // 4000ms = 4 detik (Sesuai rentang keinginan Anda 3-5 detik)
    </script>
</body>
</html>

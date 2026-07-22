<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ambil flash error dari session (diset oleh index.php saat login gagal)
$error = '';
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// BYPASS: Jika user sebenarnya sudah login, jangan tampilkan form login lagi, langsung lempar ke Etalase Toko
if (isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RSI FOOD & MART</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

  <style>
    .loading-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(15, 23, 42, 0.9);
        z-index: 9999; display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        opacity: 0; pointer-events: none; transition: opacity 0.3s ease;
    }
    .loading-overlay.show { opacity: 1; pointer-events: auto; }
  </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100" style="background: #0f172a; padding: 1.5rem;">

  <!-- KOMPONEN LAYAR MEMUAT -->
  <div id="loginLoading" class="loading-overlay">
      <div class="spinner-border text-success mb-3" style="width: 3.5rem; height: 3.5rem;" role="status"></div>
      <div class="fw-bold text-white fs-5">Memverifikasi Akun Keamanan...</div>
      <small class="text-white-50 mt-1">Harap tunggu beberapa saat.</small>
  </div>

    <div class="container d-flex justify-content-center align-items-center">
        <div class="card border-0 rounded-4 text-white shadow-lg p-4 w-100" style="background: #1e293b; max-width: 420px; border: 1px solid rgba(148,163,184,.15) !important;">
            <div class="text-center mb-3">
                <!-- Menampilkan logo PNG di atas judul dengan ukuran proposional -->
                <img src="uploads/logo rsi.png" alt="Logo RSI" style="height: 60px; object-fit: contain;" class="mb-2">
                <h4 class="fw-bold text-white mb-1">RSI FOOD &amp; MART</h4>
                <span class="text-white-50 small">Silakan login untuk melanjutkan</span>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger border-0 rounded-3 small py-2 mb-3" role="alert" style="background: rgba(239,68,68,.12); color: #fecaca;">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm" class="d-grid gap-3" action="index.php">
                <div>
                    <!-- Mengubah label dan placeholder agar mendukung Username / Email -->
                    <label class="form-label text-white-50 small fw-medium mb-2">Username / Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark bg-opacity-25 border-secondary border-opacity-50 text-white-50"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 py-2" placeholder="Masukkan username atau email" required value="<?= isset($_POST['username']) ? htmlspecialchars((string)$_POST['username']) : '' ?>" />
                    </div>
                </div>

                <div>
                    <label class="form-label text-white-50 small fw-medium mb-2">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark bg-opacity-25 border-secondary border-opacity-50 text-white-50"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 py-2" placeholder="Masukkan password" required />
                    </div>
                </div>

                <button type="submit" class="btn btn-success rounded-3 py-2 fw-medium mt-1">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Login
                </button>

                <div class="d-flex align-items-center justify-content-between mt-2" style="font-size: 0.85rem;">
                    <a class="text-decoration-none text-white-50" href="lupa-password.php">Lupa Password?</a>
                    <a class="text-decoration-none text-white-50" href="register.php">Daftar Akun Baru</a>
                </div>
            </form>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        e.preventDefault(); 
        const overlay = document.getElementById('loginLoading');
        overlay.classList.add('show'); 
        setTimeout(() => {
            this.submit(); 
        }, 3500);
    });
  </script>
</body>
</html>

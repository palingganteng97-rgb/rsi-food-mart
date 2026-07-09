<?php
// index.php
include "db.php"; // Memanggil koneksi database ($conn) & session_start()

// Jika sesi user_id sudah aktif, langsung alihkan ke home.php tanpa memuat splash screen
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

$errorMsg = '';

// Proses penanganan data saat menerima kiriman POST dari login.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // SINKRONISASI: Menangkap input gabungan (Bisa diisi Username ATAU Email)
    $login = trim($_POST['username'] ?? ''); 
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $errorMsg = 'Username/Email dan password wajib diisi.';
    } else {
        try {
            // Kueri SQL diperbarui untuk mencari kecocokan berdasarkan USERNAME atau EMAIL
            $stmt = $conn->prepare('SELECT id, name, username, email, phone, photo, role_id, status, password FROM users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->bind_param('ss', $login, $login);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res && $res->num_rows > 0) {
                $user = $res->fetch_assoc();

                // Verifikasi password terenkripsi BCRYPT dari database
                if (!empty($user['password']) && password_verify($password, $user['password'])) {
                    // Login Sukses, simpan data ke Session global aplikasi
                    $_SESSION['user_id']  = (int)$user['id'];
                    $_SESSION['name']     = $user['name'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email']    = $user['email'];
                    $_SESSION['phone']    = $user['phone'];
                    $_SESSION['role_id']  = $user['role_id'];
                    $_SESSION['status']   = $user['status'];
                    
                    if (!empty($user['photo'])) {
                        $_SESSION['photo'] = "uploads/" . $user['photo'];
                    } else {
                        $_SESSION['photo'] = "assets/img/default-avatar.png";
                    }
                    
                    $stmt->close();
                    // Alur tetap berada di index.php untuk merender UI Splash Screen Animasi di bawah
                } else {
                    $errorMsg = 'Login gagal. Username/Email atau password salah.';
                    $stmt->close();
                }
            } else {
                $errorMsg = 'Login gagal. Username/Email atau password salah.';
                if ($stmt) { $stmt->close(); }
            }
        } catch (Throwable $e) {
            $errorMsg = 'Terjadi kesalahan pada server.';
        }
    }
    
    // Jika validasi gagal, kembalikan user ke file login.php beserta pesan error-nya
    if (!empty($errorMsg)) {
        $_SESSION['flash_error'] = $errorMsg;
        header("Location: login.php");
        exit;
    }
} else {
    // Jika halaman index.php diakses langsung tanpa POST login, tendang ke login.php
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
    <!-- Pustaka Terikat Sesuai Permintaan (Terkunci) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

    <style>
        .pill { border: 1px solid rgba(34,197,94,.35); background: rgba(34,197,94,.08); color: #86efac; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100" style="background: #0f172a; color: #e5e7eb;">

    <!-- SPLASH SCREEN ANIMASI TRANSISI UTAMA APLIKASI -->
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

    <!-- JAVASCRIPT: Menahan layar transparan selama 3.5 detik, kemudian mengarahkan ke dashboard utama -->
    <script>
        setTimeout(function() {
            window.location.href = "home.php";
        }, 3500);
    </script>
</body>
</html>

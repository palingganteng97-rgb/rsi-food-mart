<?php
include "db.php";

// index.php - Halaman Login + Validasi Session

// session_start sudah dipanggil di db.php


// Redirect jika sudah login
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

$errorMsg = '';

// ===== Handle Login =====

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $errorMsg = 'Username/Email dan password wajib diisi.';
    } else {
        try {
            // Cari user berdasarkan username/email (prepared statement)
            $stmt = $conn->prepare('SELECT id, name, username, email, phone, photo, role_id, status, password FROM users WHERE username = ? OR email = ? LIMIT 1');

            $stmt->bind_param('ss', $login, $login);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res && $res->num_rows > 0) {
                $user = $res->fetch_assoc();

                if (!empty($user['password']) && password_verify($password, $user['password'])) {
                    // Login sukses
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['phone'] = $user['phone'];
                    $_SESSION['photo'] = $user['photo'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['status'] = $user['status'];

                    $stmt->close();


                    header('Location: home.php');
                    exit;
                }

                $errorMsg = 'Login gagal. Username/Email atau password salah.';
            } else {
                // Jangan bocorkan apakah user ada atau tidak
                $errorMsg = 'Login gagal. Username/Email atau password salah.';
            }

            $stmt->close();

        } catch (Throwable $e) {
            // Hindari bocor detail error ke user
            $errorMsg = 'Terjadi kesalahan pada server.';
        }
    }
}

?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>RSI Food &amp; Mart - Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <style>
    :root{--bg:#0f172a;}
    body{background:var(--bg); color:#e5e7eb;}
    .card-glow{background:rgba(2,6,23,.55); border:1px solid rgba(148,163,184,.25);}
    .pill{border:1px solid rgba(34,197,94,.35); background:rgba(34,197,94,.08); color:#86efac;}
  </style>
</head>
<body>
  <main class="min-vh-100 d-flex align-items-center">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-12 col-md-6">
          <div class="card card-glow shadow-sm rounded-4 p-4">
            <div class="d-flex align-items-center gap-3 mb-3">
              <div class="p-2 rounded-3 pill">
                <i class="bi bi-hospital fs-4"></i>
              </div>
              <div>
                <div class="fw-bold">RSI FOOD &amp; MART</div>
                <div class="text-white-50" style="font-size:.92rem">Pemesanan Makanan Sehat</div>
              </div>
            </div>

            <h5 class="mb-2">Silakan login untuk melanjutkan</h5>
            <p class="text-white-50 mb-4" style="line-height:1.45">
              Redirect akan otomatis menuju <strong>home.php</strong> ketika sesi login aktif.
            </p>

            <?php
              $flash = $_SESSION['flash_success'] ?? '';
              if ($flash) { 
                echo '<div class="alert alert-success border-0 rounded-4" role="alert" style="background:rgba(34,197,94,.12); color:#86efac;">'.htmlspecialchars($flash).'</div>';
                unset($_SESSION['flash_success']);
              }
            ?>

            <?php if (!empty($errorMsg)): ?>
              <div class="alert alert-danger border-0 rounded-4" role="alert" style="background:rgba(239,68,68,.12); color:#fecaca;">
                <?php echo htmlspecialchars($errorMsg); ?>
              </div>
            <?php endif; ?>

            <form method="post" class="d-grid gap-3" action="index.php">
              <div>
                <label class="form-label text-white-50" style="font-size:.9rem">Username / Email</label>
                <div class="input-group">
                  <span class="input-group-text bg-transparent border-0 text-white-50"><i class="bi bi-person"></i></span>
                  <input type="text" name="login" class="form-control rounded-3" placeholder="Masukkan username atau email" required />
                </div>
              </div>

              <div>
                <label class="form-label text-white-50" style="font-size:.9rem">Password</label>
                <div class="input-group">
                  <span class="input-group-text bg-transparent border-0 text-white-50"><i class="bi bi-lock"></i></span>
                  <input type="password" name="password" class="form-control rounded-3" placeholder="Masukkan password" required />
                </div>
              </div>

              <button type="submit" class="btn btn-success rounded-3">
                <i class="bi bi-box-arrow-in-right me-2"></i> Login
              </button>

              <div class="d-flex flex-column flex-md-row gap-2 align-items-md-center justify-content-between">
                <a class="text-decoration-none text-white-50" href="register.php">Daftar Akun Baru</a>
                <a class="text-decoration-none text-white-50" href="lupa-password.php">Lupa Password?</a>
              </div>
            </form>


          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


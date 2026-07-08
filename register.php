<?php
include "db.php";

// session_start sudah dipanggil di db.php

// Jika user sudah login, langsung alihkan ke home.php
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($name === '' || $username === '' || $email === '' || $phone === '' || $password === '' || $password2 === '') {
        $errorMsg = 'Semua field wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = 'Email tidak valid.';
    } elseif (strlen($password) < 8) {
        $errorMsg = 'Password minimal 8 karakter.';
    } elseif (!hash_equals($password, $password2)) {
        $errorMsg = 'Konfirmasi password tidak sesuai.';
    } else {
        try {
            // Cek apakah username atau email sudah terdaftar sebelumnya
            $check = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $check->bind_param('ss', $username, $email);
            $check->execute();
            $checkRes = $check->get_result();

            if ($checkRes && $checkRes->num_rows > 0) {
                $errorMsg = 'Username atau email sudah terdaftar.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                // CATATAN: Ubah angka di bawah ini sesuai dengan ID yang valid di tabel `roles` Anda.
                // Jika error foreign key masih muncul, pastikan angka ini terdaftar di kolom `id` tabel `roles`.
                $roleIdDefault = 1; 
                $statusDefault = 1;

                // Query INSERT dengan prepared statement menggunakan kolom wajib database Anda
                $stmt = $conn->prepare('INSERT INTO users (name, username, email, phone, password, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)');

                // Menggunakan binding parameter ('sssssii' -> 5 string, 2 integer)
                $stmt->bind_param('sssssii', $name, $username, $email, $phone, $passwordHash, $roleIdDefault, $statusDefault);

                if (!$stmt->execute()) {
                    $errorMsg = 'Gagal mendaftarkan akun. Silakan coba lagi.';
                } else {
                    $_SESSION['flash_success'] = 'Pendaftaran berhasil. Silakan login.';
                    header('Location: index.php');
                    exit;
                }

                $stmt->close();
            }

            $check->close();

        } catch (Throwable $e) {
            // Menangkap pesan error asli jika masih ada masalah relasi key database
            $errorMsg = 'Terjadi kesalahan pada server. (Detail: ' . $e->getMessage() . ')';
        }
    }
}
?>

<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Daftar Akun Baru - RSI Food &amp; Mart</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <style>
    :root{--bg:#0f172a;}
    body{background:var(--bg); color:#e5e7eb;}
    .card-glow{background:rgba(2,6,23,.55); border:1px solid rgba(148,163,184,.25);}
    .pill{border:1px solid rgba(34,197,94,.35); background:rgba(34,197,94,.08); color:#86efac;}
    .form-control, .form-select{background:rgba(2,6,23,.25); border-color:rgba(148,163,184,.22); color:#e5e7eb;}
    .form-control::placeholder{color:rgba(148,163,184,.85)}
    .form-control:focus{box-shadow:none; border-color:rgba(34,197,94,.55)}
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
                <div class="text-white-50" style="font-size:.92rem">Daftar Akun Pasien</div>
              </div>
            </div>

            <h5 class="mb-2">Buat akun baru</h5>
            <p class="text-white-50 mb-4" style="line-height:1.45">Isi data berikut untuk membuat akun Anda.</p>

            <?php if ($errorMsg): ?>
              <div class="alert alert-danger border-0 rounded-4" role="alert" style="background:rgba(239,68,68,.12); color:#fecaca;">
                <?php echo htmlspecialchars($errorMsg); ?>
              </div>
            <?php endif; ?>

            <form method="post" class="d-grid gap-3">
              <div>
                <label class="form-label text-white-50" style="font-size:.9rem">Nama Lengkap</label>
                <input type="text" name="name" class="form-control rounded-3" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" />
              </div>
              <div>
                <label class="form-label text-white-50" style="font-size:.9rem">Username</label>
                <input type="text" name="username" class="form-control rounded-3" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" />
              </div>
              <div>
                <label class="form-label text-white-50" style="font-size:.9rem">Email</label>
                <input type="email" name="email" class="form-control rounded-3" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" />
              </div>
              <div>
                <label class="form-label text-white-50" style="font-size:.9rem">Nomor Telepon</label>
                <input type="text" name="phone" class="form-control rounded-3" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" />
              </div>
              <div>
                <label class="form-label text-white-50" style="font-size:.9rem">Password</label>
                <input type="password" name="password" class="form-control rounded-3" required minlength="8" />
              </div>
              <div>
                <label class="form-label text-white-50" style="font-size:.9rem">Konfirmasi Password</label>
                <input type="password" name="password2" class="form-control rounded-3" required minlength="8" />
              </div>

              <button type="submit" class="btn btn-success rounded-3">
                <i class="bi bi-person-plus me-2"></i> Daftar Sekarang
              </button>

              <div class="text-center pt-1">
                <a href="index.php" class="text-decoration-none text-white-50">Sudah punya akun? Login</a>
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


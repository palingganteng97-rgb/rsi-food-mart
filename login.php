<?php
include "db.php"; // Memanggil koneksi database ($conn) & session_start()

// Jika sesi user_id sudah aktif, langsung alihkan ke index.php agar tidak berputar-putar
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $conn->prepare("SELECT id, name, username, phone, photo, role_id, status, password FROM users WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($row = $res->fetch_assoc()) {
                if (password_verify($password, $row['password'])) {
                    $_SESSION['user_id']  = $row['id'];
                    $_SESSION['name']     = $row['name'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['phone']    = $row['phone'];
                    $_SESSION['role_id']  = $row['role_id'];
                    $_SESSION['status']   = $row['status'];

                    if (!empty($row['photo'])) {
                        $_SESSION['photo'] = "uploads/" . $row['photo'];
                    } else {
                        $_SESSION['photo'] = "assets/img/default-avatar.png";
                    }

                    header("Location: index.php");
                    exit;
                } else {
                    $error = "Password yang Anda masukkan salah.";
                }
            } else {
                $error = "Username tidak ditemukan.";
            }
            $stmt->close();
        } catch (Throwable $e) {
            $error = "Terjadi kesalahan sistem: " . $e->getMessage();
        }
    } else {
        $error = "Kolom login tidak boleh kosong!";
    }
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

</head>
<body class="d-flex align-items-center justify-content-center min-vh-100" style="background: #0f172a;">

    <div class="card border-0 rounded-4 text-white shadow-lg p-4" style="background: #1e293b; width: 100%; max-width: 420px; border: 1px solid rgba(148,163,184,.15) !important;">
        <div class="text-center mb-3">
            <h4 class="fw-bold text-white mb-1"><i class="bi bi-shield-lock-fill text-success me-2"></i> RSI FOOD & MART</h4>
            <span class="text-white-50 small">Silakan login untuk melanjutkan</span>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger border-0 rounded-3 small py-2 mb-3" role="alert" style="background: rgba(239,68,68,.12); color: #fecaca;">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="d-grid gap-3">
            <div>
                <label class="form-label text-white-50 small fw-medium mb-2">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark bg-opacity-25 border-secondary border-opacity-50 text-white-50"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 py-2" placeholder="Masukkan username" required value="<?= isset($_POST['username']) ? htmlspecialchars((string)$_POST['username']) : '' ?>" />
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

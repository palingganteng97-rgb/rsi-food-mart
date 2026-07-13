<?php
include "db.php"; // Memanggil koneksi database ($conn) & session_start()

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';

    if (!empty($username) && !empty($newPassword)) {
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
                $updateStmt->bind_param("ss", $hashedPassword, $username);

                if ($updateStmt->execute()) {
                    $success = "Password berhasil diperbarui! Silakan login kembali.";
                } else {
                    $error = "Gagal memperbarui password.";
                }
                $updateStmt->close();
            } else {
                $error = "Username tidak ditemukan di sistem.";
            }
            $stmt->close();
        } catch (Throwable $e) {
            $error = "Terjadi kesalahan sistem.";
        }
    } else {
        $error = "Semua kolom wajib diisi!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - RSI FOOD & MART</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

</head>
<body class="d-flex align-items-center justify-content-center min-vh-100" style="background: #0f172a;">

<div class="card border-0 rounded-4 text-white shadow-lg p-4" style="background: #1e293b; width: 100%; max-width: 420px; border: 1px solid rgba(148,163,184,.15) !important;">
        <div class="text-center mb-3">
            <!-- Ikon tameng diganti dengan gambar PNG gembok hijau -->
            <img src="uploads/lupa password.png" alt="Lupa Password" style="height: 60px; object-fit: contain;" class="mb-2">
            <h4 class="fw-bold text-white mb-1">RSI FOOD &amp; MART</h4>
            <span class="text-white-50 small">Masukkan username untuk membuat password baru</span>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger border-0 rounded-3 small py-2 mb-3" role="alert" style="background: rgba(239,68,68,.12); color: #fecaca;">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success border-0 rounded-3 small py-2 mb-3" role="alert" style="background: rgba(34,197,94,.12); color: #86efac;">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="d-grid gap-3">
            <div>
                <label class="form-label text-white-50 small fw-medium mb-2">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark bg-opacity-25 border-secondary border-opacity-50 text-white-50"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 py-2" placeholder="Masukkan Username Anda" required value="<?= isset($_POST['username']) ? htmlspecialchars((string)$_POST['username']) : '' ?>" />
                </div>
            </div>

            <div>
                <label class="form-label text-white-50 small fw-medium mb-2">Password Baru</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark bg-opacity-25 border-secondary border-opacity-50 text-white-50"><i class="bi bi-lock"></i></span>
                    <input type="password" name="new_password" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 py-2" placeholder="Masukkan Password Baru" required />
                </div>
            </div>

            <button type="submit" class="btn btn-success rounded-3 py-2 fw-medium mt-1 d-flex align-items-center justify-content-center gap-2 w-100">
                <img src="uploads/reset password.png" alt="Reset" style="height: 20px; width: 20px; object-fit: contain;"> Reset Password
            </button>

            <div class="text-center mt-2" style="font-size: 0.88rem;">
                <a class="text-decoration-none fw-medium" style="color: #86efac" href="login.php">
                    <i class="bi bi-arrow-left me-1"></i>Kembali ke Login
                </a>
            </div>
        </form>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

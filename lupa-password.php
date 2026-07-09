<?php
include "db.php";

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';

    if (!empty($email) && !empty($newPassword)) {
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                $updateStmt->bind_param("ss", $hashedPassword, $email);

                if ($updateStmt->execute()) {
                    $success = "Password berhasil diperbarui! Silakan login kembali.";
                } else {
                    $error = "Gagal memperbarui password.";
                }
                $updateStmt->close();
            } else {
                $error = "Email tidak ditemukan di sistem.";
            }
            $stmt->close();
        } catch (Throwable $e) {
            $error = "Terjadi kesalahan sistem.";
        }
    } else {
        $error = "Semua kolom wajib diisi!";
    }
}

require __DIR__ . '/_auth_ui_shared.php';
auth_ui_header('Lupa Password');
require __DIR__ . '/_auth_ui_body_open.php';
?>

<h5 class="mb-2">Reset Password</h5>
<p class="text-white-50 mb-4" style="line-height:1.45">Masukkan email terdaftar lalu buat password baru.</p>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger border-0 rounded-4" role="alert" style="background:rgba(239,68,68,.12); color:#fecaca;">
    <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
  <div class="alert alert-success border-0 rounded-4" role="alert" style="background:rgba(34,197,94,.12); color:#86efac;">
    <?= htmlspecialchars($success) ?>
  </div>
<?php endif; ?>

<form method="POST" class="d-grid gap-3">
  <div>
    <label class="form-label text-white-50" style="font-size:.9rem">Email Terdaftar</label>
    <div class="input-group">
      <span class="input-group-text bg-transparent border-0 text-white-50"><i class="bi bi-envelope"></i></span>
      <input type="email" name="email" class="form-control rounded-3" placeholder="Masukkan Email Terdaftar" required value="<?= isset($_POST['email']) ? htmlspecialchars((string)$_POST['email']) : '' ?>" />
    </div>
  </div>

  <div>
    <label class="form-label text-white-50" style="font-size:.9rem">Password Baru</label>
    <div class="input-group">
      <span class="input-group-text bg-transparent border-0 text-white-50"><i class="bi bi-lock"></i></span>
      <input type="password" name="new_password" class="form-control rounded-3" placeholder="Masukkan Password Baru" required />
    </div>
  </div>

  <button type="submit" class="btn btn-success rounded-3">
    <i class="bi bi-shield-lock me-2"></i> Reset Password
  </button>

  <div class="text-center">
    <a class="text-decoration-none" style="color:#86efac" href="login.php">
      <i class="bi bi-arrow-left me-1"></i>Kembali ke Login
    </a>
  </div>
</form>

<?php
require __DIR__ . '/_auth_ui_body_close.php';
auth_ui_footer();


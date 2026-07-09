<?php
include "db.php";

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = trim($_POST['login_input'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($usernameOrEmail) && !empty($password)) {
        try {
            $stmt = $conn->prepare("SELECT id, name, username, email, phone, photo, role_id, status, password FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->bind_param("ss", $usernameOrEmail, $usernameOrEmail);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($row = $res->fetch_assoc()) {
                if (password_verify($password, $row['password'])) {
                    $_SESSION['user_id']  = $row['id'];
                    $_SESSION['name']     = $row['name'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['email']    = $row['email'];
                    $_SESSION['phone']    = $row['phone'];
                    $_SESSION['photo']    = $row['photo'];
                    $_SESSION['role_id']  = $row['role_id'];
                    $_SESSION['status']   = $row['status'];

                    header("Location: index.php");
                    exit;
                } else {
                    $error = "Password yang Anda masukkan salah.";
                }
            } else {
                $error = "Username atau Email tidak ditemukan.";
            }
            $stmt->close();
        } catch (Throwable $e) {
            $error = "Terjadi kesalahan sistem.";
        }
    } else {
        $error = "Kolom login tidak boleh kosong!";
    }
}

require __DIR__ . '/_auth_ui_shared.php';
auth_ui_header('Login');
require __DIR__ . '/_auth_ui_body_open.php';
?>

<h5 class="mb-2">Silakan login untuk melanjutkan</h5>
<p class="text-white-50 mb-4" style="line-height:1.45">
  Redirect akan menuju halaman utama jika sesi login aktif.
</p>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger border-0 rounded-4" role="alert" style="background:rgba(239,68,68,.12); color:#fecaca;">
    <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>

<form method="POST" class="d-grid gap-3">
  <div>
    <label class="form-label text-white-50" style="font-size:.9rem">Username / Email</label>
    <div class="input-group">
      <span class="input-group-text bg-transparent border-0 text-white-50">
        <i class="bi bi-person"></i>
      </span>
      <input type="text" name="login_input" class="form-control rounded-3" placeholder="Masukkan username atau email" required value="<?= isset($_POST['login_input']) ? htmlspecialchars((string)$_POST['login_input']) : '' ?>" />
    </div>
  </div>

  <div>
    <label class="form-label text-white-50" style="font-size:.9rem">Password</label>
    <div class="input-group">
      <span class="input-group-text bg-transparent border-0 text-white-50">
        <i class="bi bi-lock"></i>
      </span>
      <input type="password" name="password" class="form-control rounded-3" placeholder="Masukkan password" required />
    </div>
  </div>

  <button type="submit" class="btn btn-success rounded-3">
    <i class="bi bi-box-arrow-in-right me-2"></i> Login
  </button>

  <div class="d-flex flex-column flex-md-row gap-2 align-items-md-center justify-content-between">
    <a class="text-decoration-none text-white-50" href="lupa-password.php">Lupa Password?</a>
    <a class="text-decoration-none text-white-50" href="register.php">Daftar Akun Baru</a>
  </div>
</form>

<?php
require __DIR__ . '/_auth_ui_body_close.php';
auth_ui_footer();
?>

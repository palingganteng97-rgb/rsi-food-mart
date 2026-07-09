<?php
include "db.php";

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($name) && !empty($username) && !empty($email) && !empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        // OTOMATISASI: Pengguna tidak perlu memilih peran saat mendaftar.
        // Angka 1 otomatis diisikan langsung ke database (Master Role ID: 1 = Pasien).
        $roleId = 1; 
        $status = 1; 

        try {
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
            $checkStmt->bind_param("ss", $username, $email);
            $checkStmt->execute();

            $checkRes = $checkStmt->get_result();
            if ($checkRes && $checkRes->num_rows > 0) {
                $error = "Username atau Email sudah digunakan!";
            } else {
                $stmt = $conn->prepare("INSERT INTO users (name, username, email, phone, password, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssii", $name, $username, $email, $phone, $hashedPassword, $roleId, $status);

                if ($stmt->execute()) {
                    $success = "Registrasi berhasil! Silakan login.";
                } else {
                    $error = "Gagal mendaftarkan akun.";
                }
                $stmt->close();
            }

            $checkStmt->close();
        } catch (Throwable $e) {
            $error = "Terjadi kesalahan sistem: " . $e->getMessage();
        }
    } else {
        $error = "Semua kolom wajib diisi kecuali nomor telepon!";
    }
}

require __DIR__ . '/_auth_ui_shared.php';
auth_ui_header('Register');
require __DIR__ . '/_auth_ui_body_open.php';
?>

<h5 class="mb-2">Buat akun baru</h5>
<p class="text-white-50 mb-4" style="line-height:1.45">Isi data berikut untuk mendaftar.</p>

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
    <label class="form-label text-white-50" style="font-size:.9rem">Nama Lengkap</label>
    <div class="input-group">
      <span class="input-group-text bg-transparent border-0 text-white-50"><i class="bi bi-person"></i></span>
      <input type="text" name="name" class="form-control rounded-3" placeholder="Nama Lengkap" required value="<?= isset($_POST['name']) ? htmlspecialchars((string)$_POST['name']) : '' ?>" />
    </div>
  </div>

  <div>
    <label class="form-label text-white-50" style="font-size:.9rem">Username</label>
    <div class="input-group">
      <span class="input-group-text bg-transparent border-0 text-white-50"><i class="bi bi-at"></i></span>
      <input type="text" name="username" class="form-control rounded-3" placeholder="Username" required value="<?= isset($_POST['username']) ? htmlspecialchars((string)$_POST['username']) : '' ?>" />
    </div>
  </div>

  <div>
    <label class="form-label text-white-50" style="font-size:.9rem">Email</label>
    <div class="input-group">
      <span class="input-group-text bg-transparent border-0 text-white-50"><i class="bi bi-envelope"></i></span>
      <input type="email" name="email" class="form-control rounded-3" placeholder="Email" required value="<?= isset($_POST['email']) ? htmlspecialchars((string)$_POST['email']) : '' ?>" />
    </div>
  </div>

  <div>
    <label class="form-label text-white-50" style="font-size:.9rem">Nomor Telepon (opsional)</label>
    <div class="input-group">
      <span class="input-group-text bg-transparent border-0 text-white-50"><i class="bi bi-telephone"></i></span>
      <input type="text" name="phone" class="form-control rounded-3" placeholder="Nomor Telepon" value="<?= isset($_POST['phone']) ? htmlspecialchars((string)$_POST['phone']) : '' ?>" />
    </div>
  </div>

  <div>
    <label class="form-label text-white-50" style="font-size:.9rem">Password</label>
    <div class="input-group">
      <span class="input-group-text bg-transparent border-0 text-white-50"><i class="bi bi-lock"></i></span>
      <input type="password" name="password" class="form-control rounded-3" placeholder="Password" required />
    </div>
  </div>

  <button type="submit" class="btn btn-success rounded-3 mt-2">
    <i class="bi bi-person-plus me-2"></i> Daftar
  </button>

  <div class="text-center mt-2">
    <span class="text-white-50" style="font-size:.92rem">Sudah punya akun?</span>
    <a class="text-decoration-none ms-1" style="color:#86efac" href="login.php">Login di sini</a>
  </div>
</form>

<?php
require __DIR__ . '/_auth_ui_body_close.php';
auth_ui_footer();
?>

<?php
include "db.php"; // Memanggil koneksi database ($conn) & session_start()

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? ''); // Menangkap input email baru
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($name) && !empty($username) && !empty($email) && !empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        $roleId = 1; 
        $status = 1; 

        try {
            // Memeriksa duplikasi data berdasarkan username ATAU email
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
            $checkStmt->bind_param("ss", $username, $email);
            $checkStmt->execute();

            $checkRes = $checkStmt->get_result();
            if ($checkRes && $checkRes->num_rows > 0) {
                $error = "Username atau Email sudah digunakan!";
            } else {
                // Kolom email dimasukkan kembali ke kueri INSERT
                $stmt = $conn->prepare("INSERT INTO users (role_id, name, username, email, phone, password, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssssi", $roleId, $name, $username, $email, $phone, $hashedPassword, $status);

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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - RSI FOOD & MART</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

</head>
<body class="d-flex align-items-center justify-content-center min-vh-100" style="background: #0f172a; padding: 20px 0;">

    <div class="card border-0 rounded-4 text-white shadow-lg p-4" style="background: #1e293b; width: 100%; max-width: 440px; border: 1px solid rgba(148,163,184,.15) !important;">
        <div class="text-center mb-3">
            <h4 class="fw-bold text-white mb-1"><i class="bi bi-person-plus-fill text-success me-2"></i> RSI FOOD &amp; MART</h4>
            <span class="text-white-50 small">Buat akun baru untuk mendaftar</span>
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
                <label class="form-label text-white-50 small fw-medium mb-2">Nama Lengkap</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark bg-opacity-25 border-secondary border-opacity-50 text-white-50"><i class="bi bi-person"></i></span>
                    <input type="text" name="name" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 py-2" placeholder="Masukkan nama lengkap" required value="<?= isset($_POST['name']) ? htmlspecialchars((string)$_POST['name']) : '' ?>" />
                </div>
            </div>

            <div>
                <label class="form-label text-white-50 small fw-medium mb-2">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark bg-opacity-25 border-secondary border-opacity-50 text-white-50"><i class="bi bi-at"></i></span>
                    <input type="text" name="username" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 py-2" placeholder="Masukkan username" required value="<?= isset($_POST['username']) ? htmlspecialchars((string)$_POST['username']) : '' ?>" />
                </div>
            </div>

            <!-- FORM INPUT BARU: Kolom Alamat Email -->
            <div>
                <label class="form-label text-white-50 small fw-medium mb-2">Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark bg-opacity-25 border-secondary border-opacity-50 text-white-50"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 py-2" placeholder="contoh@email.com" required value="<?= isset($_POST['email']) ? htmlspecialchars((string)$_POST['email']) : '' ?>" />
                </div>
            </div>

            <div>
                <label class="form-label text-white-50 small fw-medium mb-2">Nomor Telepon <span class="text-white-50 text-opacity-50">(Opsional)</span></label>
                <div class="input-group">
                    <span class="input-group-text bg-dark bg-opacity-25 border-secondary border-opacity-50 text-white-50"><i class="bi bi-telephone"></i></span>
                    <input type="text" name="phone" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 py-2" placeholder="Masukkan nomor telepon" value="<?= isset($_POST['phone']) ? htmlspecialchars((string)$_POST['phone']) : '' ?>" />
                </div>
            </div>

            <div>
                <label class="form-label text-white-50 small fw-medium mb-2">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark bg-opacity-25 border-secondary border-opacity-50 text-white-50"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 py-2" placeholder="Buat password baru" required />
                </div>
            </div>

            <button type="submit" class="btn btn-success rounded-3 py-2 fw-medium mt-1">
                <i class="bi bi-person-plus me-2"></i> Daftar Akun
            </button>

            <div class="text-center mt-2" style="font-size: 0.88rem;">
                <span class="text-white-50">Sudah punya akun?</span>
                <a class="text-decoration-none ms-1 fw-medium" style="color: #86efac" href="login.php">Login di sini</a>
            </div>
        </form>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

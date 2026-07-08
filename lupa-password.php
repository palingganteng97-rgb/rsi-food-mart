<?php
include "db.php";

// session_start sudah dipanggil di db.php


if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}

$message = '';
$isError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Masukkan email yang valid.';
        $isError = true;
    } else {
            try {
                // Simulasi: cek apakah email terdaftar, lalu tampilkan instruksi placeholder.
                $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');

            $stmt->bind_param('s', $email);
            $stmt->execute();

            $res = $stmt->get_result();

            if ($res && $res->num_rows > 0) {
                $message = 'Jika email Anda terdaftar, kami akan mengirim tautan pemulihan. (Simulasi)';
                $isError = false;
            } else {
                // Jangan bocorkan apakah email terdaftar; tampilkan pesan generik.
                $message = 'Jika email Anda terdaftar, kami akan mengirim tautan pemulihan. (Simulasi)';
                $isError = false;
            }


            $stmt->close();

        } catch (Throwable $e) {
            $message = 'Terjadi kesalahan pada server. (Detail: ' . $e->getMessage() . ')';
            $isError = true;
        }
    }
}

?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Lupa Password - RSI Food &amp; Mart</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <style>
    :root{--bg:#0f172a;}
    body{background:var(--bg); color:#e5e7eb;}
    .card-glow{background:rgba(2,6,23,.55); border:1px solid rgba(148,163,184,.25);}
    .pill{border:1px solid rgba(34,197,94,.35); background:rgba(34,197,94,.08); color:#86efac;}
    .form-control{background:rgba(2,6,23,.25); border-color:rgba(148,163,184,.22); color:#e5e7eb;}
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
                <div class="text-white-50" style="font-size:.92rem">Pemulihan Akun</div>
              </div>
            </div>

            <h5 class="mb-2">Lupa Password?</h5>
            <p class="text-white-50 mb-4" style="line-height:1.45">Masukkan email Anda untuk menerima instruksi reset password. (Simulasi)</p>

            <?php if ($message): ?>
              <div class="alert <?php echo $isError ? 'alert-danger' : 'alert-success'; ?> border-0 rounded-4" role="alert" style="<?php echo $isError ? 'background:rgba(239,68,68,.12); color:#fecaca;' : 'background:rgba(34,197,94,.12); color:#86efac;'; ?>">
                <?php echo htmlspecialchars($message); ?>
              </div>
            <?php endif; ?>

            <form method="post" class="d-grid gap-3">
              <div>
                <label class="form-label text-white-50" style="font-size:.9rem">Alamat Email</label>
                <input type="email" name="email" class="form-control rounded-3" placeholder="contoh@domain.com" required />
              </div>

              <button type="submit" class="btn btn-success rounded-3">
                <i class="bi bi-send me-2"></i> Kirim Tautan Pemulihan
              </button>

              <div class="text-center pt-1">
                <a href="index.php" class="text-decoration-none text-white-50">Kembali ke Login</a>
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


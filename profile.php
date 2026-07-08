<?php
include "db.php";

// session_start sudah dipanggil di db.php

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}


$userId = (int)$_SESSION['user_id'] ?? 0;

$name = $_SESSION['name'] ?? 'Pasien';

$username = $_SESSION['username'] ?? '-';
$email = $_SESSION['email'] ?? '-';
$phone = $_SESSION['phone'] ?? '-';
$photo = $_SESSION['photo'] ?? '';
$roleId = $_SESSION['role_id'] ?? null;
$status = $_SESSION['status'] ?? 'PENDING';

try {
    $stmt = $conn->prepare('SELECT name, username, email, phone, photo, role_id, status FROM users WHERE id = ? LIMIT 1');

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $name = $row['name'] ?? $name;
        $username = $row['username'] ?? $username;
        $email = $row['email'] ?? $email;
        $phone = $row['phone'] ?? $phone;
        $photo = $row['photo'] ?? $photo;
        $roleId = $row['role_id'] ?? $roleId;
        $status = $row['status'] ?? $status;
    }

    $stmt->close();
} catch (Throwable $e) {

    // fallback: tetap tampil dengan data dari session
}

$photoUrl = $photo ? $photo : 'https://images.unsplash.com/photo-1524504388940-b1c1723d785f?auto=format&fit=crop&w=800&q=60';
$isVerified = strtoupper((string)$status) === 'VERIFIED' || strtoupper((string)$status) === 'VERIFIKASI' || strtoupper((string)$status) === 'ACTIVE';

?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Profil Diri - RSI Food &amp; Mart</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

  <style>
    :root { --bg:#0f172a; --text:#e5e7eb; --muted:#94a3b8; --green:#22c55e; }
    body{ background:var(--bg) !important; color:var(--text); }

    .profile-card{
      background: rgba(2,6,23,.42);
      border: 1px solid rgba(148,163,184,.22);
      border-radius: 20px;
      overflow:hidden;
      box-shadow: 0 10px 30px rgba(0,0,0,.25);
    }

    .profile-photo-frame{
      position: relative;
      border-right: 1px solid rgba(148,163,184,.18);
      background: linear-gradient(180deg, rgba(34,197,94,.10), rgba(2,6,23,.0));
      min-height: 340px;
    }

    .profile-photo{
      width: 180px;
      height: 180px;
      border-radius: 18px;
      object-fit: cover;
      border: 1px solid rgba(148,163,184,.25);
      box-shadow: 0 12px 26px rgba(0,0,0,.25);
      background: rgba(15,23,42,.4);
    }

    .verified-badge{
      position: absolute;
      top: 18px;
      left: 18px;
      background: rgba(34,197,94,.18);
      border: 1px solid rgba(34,197,94,.6);
      color: #86efac;
      padding: .45rem .65rem;
      border-radius: 999px;
      backdrop-filter: blur(8px);
      display:flex;
      align-items:center;
      gap:.45rem;
      font-weight: 700;
    }

    @media (max-width: 991.98px){
      .profile-photo-frame{ border-right: none; border-bottom: 1px solid rgba(148,163,184,.18);} 
    }

    @media (min-width: 992px) {
      main.content-shift{ margin-left: 280px; }
    }

  </style>
</head>
<body>
  <?php require __DIR__ . '/sidebar.php'; ?>

  <main class="content-shift page-body">
    <div class="container py-3">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <div class="fw-bold fs-5">Profil Diri</div>
          <div class="text-white-50" style="font-size:.9rem">Kelola identitas dan kontak Anda</div>
        </div>
      </div>

      <div class="profile-card">
        <div class="row g-0">
          <div class="col-12 col-lg-5 profile-photo-frame d-flex justify-content-center align-items-center py-4">
            <?php if ($isVerified): ?>
              <div class="verified-badge">
                <i class="bi bi-check2-circle"></i>
                VERIFIED
              </div>
            <?php endif; ?>

            <div class="text-center">
              <img class="profile-photo" src="<?php echo htmlspecialchars($photoUrl); ?>" alt="Foto profil" />
              <div class="mt-3">
                <div class="fw-bold fs-4" style="line-height:1.1;">"<?php echo htmlspecialchars($name); ?>"</div>
                <div class="text-white-50" style="font-size:.92rem">@<?php echo htmlspecialchars($username); ?></div>
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-7 p-3 p-md-4">
            <div class="d-flex align-items-center gap-2 mb-3">
              <div class="p-2 rounded-3" style="background: rgba(34,197,94,.12); border:1px solid rgba(34,197,94,.35);">
                <i class="bi bi-person-badge text-success"></i>
              </div>
              <div>
                <div class="fw-bold">Identitas</div>
                <div class="text-white-50" style="font-size:.9rem">Data pribadi pasien</div>
              </div>
            </div>

            <div class="row g-2">
              <div class="col-12 col-md-6">
                <div class="p-3 rounded-4" style="background:rgba(15,23,42,.4); border:1px solid rgba(148,163,184,.18);">
                  <div class="text-white-50" style="font-size:.85rem">Nama</div>
                  <div class="fw-semibold"><?php echo htmlspecialchars($name); ?></div>
                </div>
              </div>
              <div class="col-12 col-md-6">
                <div class="p-3 rounded-4" style="background:rgba(15,23,42,.4); border:1px solid rgba(148,163,184,.18);">
                  <div class="text-white-50" style="font-size:.85rem">Role ID</div>
                  <div class="fw-semibold"><?php echo htmlspecialchars((string)$roleId); ?></div>
                </div>
              </div>
            </div>

            <div class="d-flex align-items-center gap-2 mt-3 mb-3">
              <div class="p-2 rounded-3" style="background: rgba(148,163,184,.10); border:1px solid rgba(148,163,184,.25);">
                <i class="bi bi-envelope text-info"></i>
              </div>
              <div>
                <div class="fw-bold">Kontak</div>
                <div class="text-white-50" style="font-size:.9rem">Untuk kebutuhan komunikasi</div>
              </div>
            </div>

            <div class="row g-2">
              <div class="col-12">
                <div class="p-3 rounded-4" style="background:rgba(15,23,42,.4); border:1px solid rgba(148,163,184,.18);">
                  <div class="text-white-50" style="font-size:.85rem">Email</div>
                  <div class="fw-semibold"><?php echo htmlspecialchars($email); ?></div>
                </div>
              </div>
              <div class="col-12">
                <div class="p-3 rounded-4" style="background:rgba(15,23,42,.4); border:1px solid rgba(148,163,184,.18);">
                  <div class="text-white-50" style="font-size:.85rem">Telepon</div>
                  <div class="fw-semibold"><?php echo htmlspecialchars($phone); ?></div>
                </div>
              </div>
            </div>

            <div class="mt-4">
              <div class="text-white-50" style="font-size:.85rem">Status akun:</div>
              <div class="mt-1 d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill" style="background:rgba(15,23,42,.35); border:1px solid rgba(148,163,184,.18);">
                <i class="bi bi-shield-check text-success"></i>
                <span class="fw-semibold"><?php echo htmlspecialchars((string)$status); ?></span>
              </div>
            </div>

          </div>
        </div>
      </div>

      <footer class="text-center text-white-50 mt-4" style="font-size:.9rem">
        RSI FOOD &amp; MART
      </footer>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


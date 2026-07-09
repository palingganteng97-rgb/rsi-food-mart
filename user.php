<?php
// ====================================================================
// TAHAP 1: STRUKTUR OTENTIKASI & PROTEKSI BACKEND AMAN (ANTI KEPENTAL)
// ====================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Menyertakan file database utama
include 'db.php';

// Proteksi tunggal: Memastikan variabel kunci pendeteksi akun login aktif terisi
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Memproses file hapus data jika pemicu action delete dipanggil
include "delete_handler.php";

$listRoles = [];
try {
    $roleQuery = $conn->query("SELECT id, name FROM roles ORDER BY name ASC");
    if ($roleQuery) {
        while ($rRow = $roleQuery->fetch_assoc()) {
            $listRoles[] = $rRow;
        }
    }
} catch (Throwable $e) {
}

$userId = (int)$_SESSION['user_id'];

// Inisialisasi awal variabel parameter profil dengan data Session (Fallback)
$roleId    = $_SESSION['role_id'] ?? '-';
$tenantId  = $_SESSION['tenant_id'] ?? '-';
$name      = $_SESSION['name'] ?? 'Pasien';
$username  = $_SESSION['username'] ?? '-';
$email     = $_SESSION['email'] ?? '-';
$phone     = $_SESSION['phone'] ?? '-';
$photo     = $_SESSION['photo'] ?? '';
$status    = $_SESSION['status'] ?? 0;
$lastLogin = '-';

try {
    $stmt = $conn->prepare('SELECT id, role_id, tenant_id, name, username, email, phone, photo, status, last_login FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $roleId    = $row['role_id'] ?? $roleId;
        $tenantId  = $row['tenant_id'] ?? $tenantId;
        $name      = $row['name'] ?? $name;
        $username  = $row['username'] ?? $username;
        $email     = $row['email'] ?? $email;
        $phone     = $row['phone'] ?? $phone;
        $photo     = $row['photo'] ?? $photo;
        $status    = $row['status'] ?? $status;
        $lastLogin = $row['last_login'] ?? $lastLogin;
    }
    $stmt->close();
} catch (Throwable $e) {
}

$isVerified = (int)$status === 1;
$photoUrl = $photo ? (strpos($photo, 'uploads/') === 0 ? $photo : 'uploads/' . $photo) : 'assets/img/default-avatar.png';

$updateError = '';
$updateSuccess = '';

// ====================================================================
// TAHAP 2: PROSES COUPLING FORM DATA TAMBAH USER (INSERT)
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_register'])) {
    $newName     = trim($_POST['name'] ?? '');
    $newUsername = trim($_POST['username'] ?? '');
    $newEmail    = trim($_POST['email'] ?? '');
    $newPhone    = trim($_POST['phone'] ?? '');
    $newPassword = $_POST['password'] ?? '';
    $newRoleId   = (int)($_POST['role_id'] ?? 0);

    if (empty($newName) || empty($newUsername) || empty($newEmail) || empty($newPassword) || $newRoleId <= 0) {
        header("Location: user.php?status=error_insert&msg=" . urlencode("Semua kolom wajib termasuk Role Akses harus diisi!"));
        exit;
    }

    $uploadedPhotoName = '';
    $uploadOk = true;
    $updateError = '';

    if (!empty($_FILES['photo']['name'])) {
        $targetDir = "uploads/";
        if (!file_exists($targetDir)) { mkdir($targetDir, 0777, true); }

        $fileExtension = strtolower(pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION));
        $newFileName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
        $targetFilePath = $targetDir . $newFileName;

        if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
            if ($_FILES["photo"]["size"] <= 2 * 1024 * 1024) {
                if (move_uploaded_file($_FILES["photo"]["tmp_name"], $targetFilePath)) {
                    $uploadedPhotoName = $newFileName;
                } else { $uploadOk = false; $updateError = "Gagal mengunggah berkas gambar."; }
            } else { $uploadOk = false; $updateError = "Ukuran file foto maksimal 2MB."; }
        } else { $uploadOk = false; $updateError = "Format berkas tidak didukung."; }
    }

    if ($uploadOk) {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $defaultStatus = 1;

            $insertStmt = $conn->prepare("INSERT INTO users (role_id, name, username, email, phone, password, photo, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->bind_param("issssssi", $newRoleId, $newName, $newUsername, $newEmail, $newPhone, $hashedPassword, $uploadedPhotoName, $defaultStatus);
            
            if ($insertStmt->execute()) {
                header("Location: user.php?status=success_insert");
                exit;
            } else {
                header("Location: user.php?status=error_insert&msg=" . urlencode($insertStmt->error));
                exit;
            }
            $insertStmt->close();
        } catch (Throwable $e) { 
            header("Location: user.php?status=error_insert&msg=" . urlencode($e->getMessage()));
            exit;
        }
    } else {
        header("Location: user.php?status=error_insert&msg=" . urlencode($updateError));
        exit;
    }
}

// ====================================================================
// TAHAP 3: PROSES COUPLING FORM DATA EDIT USER (UPDATE LENGKAP)
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_admin'])) {
    $targetId = (int)($_POST['id'] ?? 0);
    $upName   = trim($_POST['name'] ?? '');
    $upEmail  = trim($_POST['email'] ?? '');
    $upPhone  = trim($_POST['phone'] ?? '');
    $upPass   = $_POST['password'] ?? '';
    $oldPhoto = trim($_POST['old_photo'] ?? '');
    $upRoleId = (int)($_POST['role_id'] ?? 0);

    $finalPhotoName = $oldPhoto; 
    $uploadOk = true;
    $updateError = '';

    if (!empty($_FILES['photo']['name'])) {
        $targetDir = "uploads/";
        if (!file_exists($targetDir)) { mkdir($targetDir, 0777, true); }

        $fileExtension = strtolower(pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION));
        $newFileName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
        $targetFilePath = $targetDir . $newFileName;

        if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
            if ($_FILES["photo"]["size"] <= 2 * 1024 * 1024) {
                if (move_uploaded_file($_FILES["photo"]["tmp_name"], $targetFilePath)) {
                    $finalPhotoName = $newFileName;
                    if (!empty($oldPhoto) && file_exists("uploads/" . $oldPhoto)) {
                        @unlink("uploads/" . $oldPhoto);
                    }
                } else { $uploadOk = false; $updateError = "Gagal memproses unggahan gambar baru."; }
            } else { $uploadOk = false; $updateError = "Ukuran file foto maksimal 2MB."; }
        } else { $uploadOk = false; $updateError = "Format gambar tidak valid."; }
    }

    if ($uploadOk && $targetId > 0 && !empty($upName) && !empty($upEmail) && $upRoleId > 0) {
        try {
            if (!empty($upPass)) {
                // Eksekusi Update lengkap jika admin ikut mengubah kata sandi baru
                $hashedPass = password_hash($upPass, PASSWORD_BCRYPT);
                $updateStmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, password = ?, photo = ?, role_id = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->bind_param("sssssii", $upName, $upEmail, $upPhone, $hashedPass, $finalPhotoName, $upRoleId, $targetId);
            } else {
                // Eksekusi Update biasa jika password dikosongkan (tidak diubah)
                $updateStmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, photo = ?, role_id = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->bind_param("ssssii", $upName, $upEmail, $upPhone, $finalPhotoName, $upRoleId, $targetId);
            }
            
            if ($updateStmt->execute()) {
                header("Location: user.php?status=success_update");
                exit;
            } else {
                header("Location: user.php?status=error_update&msg=" . urlencode($updateStmt->error));
                exit;
            }
            $updateStmt->close();
        } catch (Throwable $e) {
            header("Location: user.php?status=error_update&msg=" . urlencode($e->getMessage()));
            exit;
        }
    } else {
        header("Location: user.php?status=error_update&msg=" . urlencode("Gagal validasi data form atau file unggahan."));
        exit;
    }
}
?>

<!Doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Etalase Menu - RSI Food &amp; Mart</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

<style>
    :root { --bg:#0f172a; --text:#e5e7eb; --muted:#94a3b8; --green:#22c55e; }
    body { background:var(--bg) !important; color:var(--text); overflow-y: hidden !important; } /* Menyembunyikan scrollbar halaman utama */
    .content-bg { background: transparent; }
    .search-box { background: rgba(2,6,23,.35); border:1px solid rgba(148,163,184,.25); border-radius: 18px; }
    .diet-pill { border:1px solid rgba(34,197,94,.35); background: rgba(34,197,94,.08); color:#86efac; }
    .diet-pill[data-active="true"] { background: rgba(34,197,94,.92); color:#06210f; border-color: rgba(34,197,94,.65); }
    .card-food { background: rgba(2,6,23,.40); border:1px solid rgba(148,163,184,.22); border-radius: 18px; overflow:hidden; transition: transform .15s ease, border-color .15s ease; }
    .card-food:hover { transform: translateY(-2px); border-color: rgba(34,197,94,.35); }
    .food-img { height: 150px; background: linear-gradient(180deg, rgba(34,197,94,.10), rgba(2,6,23,.0)); display:flex; align-items:center; justify-content:center; color: rgba(148,163,184,.8); position: relative; }
    .food-img img { width:100%; height:100%; object-fit: cover; }
    .price-badge { display:inline-flex; align-items:center; gap:.35rem; padding:.35rem .7rem; background: rgba(15,23,42,.55); border:1px solid rgba(148,163,184,.25); border-radius: 999px; color: var(--text); }
    .bottom-nav { position: fixed; left:0; right:0; bottom:0; z-index: 1035; background: rgba(15,23,42,.88); backdrop-filter: blur(10px); border-top: 1px solid rgba(148,163,184,.25); display:block; }
    
    /* Penyelarasan Tabel & Sembunyikan Scrollbar Horizontal */
    #dragScrollUserContainer::-webkit-scrollbar, #dragScrollContainer::-webkit-scrollbar, .drag-scroll-container::-webkit-scrollbar { display: none !important; }
    #dragScrollUserContainer, #dragScrollContainer, .drag-scroll-container { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow-x: auto !important; cursor: grab !important; border: none !important; box-shadow: none !important; -webkit-box-shadow: none !important; }
    #dragScrollUserContainer:active, #dragScrollContainer:active, .drag-scroll-container:active { cursor: grabbing !important; }
    #dragScrollUserContainer table, #dragScrollContainer table, .drag-scroll-container table { border-collapse: collapse !important; border: none !important; }
    #dragScrollUserContainer table th, #dragScrollUserContainer table td, #dragScrollContainer table th, #dragScrollContainer table td, .drag-scroll-container table th, .drag-scroll-container table td { border-left: none !important; border-right: none !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; }
    .text-white-element { -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }
    
    /* PERBAIKAN: MODAL TENGAH MELEBAR BERSIH TANPA SCROLLBAR FISIK BROWSER */
    .modal, .modal-open { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow-y: hidden !important; }
    .modal::-webkit-scrollbar { display: none !important; }
    
    /* MENGUBAH overflow-y JADI auto AGAR KONTEKS SCROLLABLE BOOTSTRAP BEKERJA DI DALAM MODAL-BODY */
    .modal-body { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow-y: auto !important; }
    .modal-body::-webkit-scrollbar { display: none !important; }

    @media (min-width: 992px) { main.content-shift { margin-left: 280px; } .bottom-nav { display:none; } }
</style>

</head>
<body>
  <?php require __DIR__ . '/sidebar.php'; ?>

<!-- TAHAP 1: PEMBUNGKUS LUAR UTAMA (MAIN CONTAINER)    -->
<main class="content-shift p-4">
  <!-- Container tabel dengan tema gelap transparan -->
  <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
    
    <!-- HEADER TABEL & TOMBOL TAMBAH USER -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
      <div>
        <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;"> Data User </h2>
      </div>
      <div>
        <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
          <i class="bi bi-person-plus-fill"></i> Tambah User
        </button>
      </div>
    </div>

    <!-- STRUKTUR TABEL LIST DATA USER (TANPA SCROLLBAR, DAPAT DIGESER MOUSE & BEBAS GARIS PUTIH) -->
    <div id="dragScrollUserContainer" class="table-responsive rounded-3 drag-scroll-container" style="border: none !important; background: transparent !important; cursor: grab; box-shadow: none !important; -webkit-box-shadow: none !important;">
      <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 1500px; user-select: none; border-collapse: collapse !important;">
        <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
          <tr>
            <th class="py-3 px-2 text-center text-white" style="background: transparent !important; border: none !important;">ID</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important;">Role ID</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important;">Tenant ID</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important;">Photo</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Name</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Username</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Email</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Phone</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important;">Status</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Last Login</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Created At</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Updated At</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Deleted At</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important;">Aksi</th>
          </tr>
        </thead>
        <tbody style="background: transparent !important;">
          <?php
          try {
              $queryAllFields = "SELECT id, role_id, tenant_id, name, username, email, phone, photo, status, last_login, created_at, updated_at, deleted_at FROM users ORDER BY id ASC";
              $resultAllFields = $conn->query($queryAllFields);
              
              if ($resultAllFields && $resultAllFields->num_rows > 0) {
                  while ($userRow = $resultAllFields->fetch_assoc()) {
                      $isUserActive = (int)$userRow['status'] === 1;
                      
                      if (!empty($userRow['photo']) && file_exists($userRow['photo'])) {
                          $imgDisplay = '<img src="'.htmlspecialchars($userRow['photo']).'" class="rounded-circle shadow-sm" style="width: 35px; height: 35px; object-fit: cover; border: 1px solid rgba(148, 163, 184, 0.2);" draggable="false">';
                      } else {
                          $initials = strtoupper(substr($userRow['name'] ?? 'US', 0, 2));
                          $imgDisplay = '<div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width: 35px; height: 35px; background: rgba(148, 163, 184, 0.25); border: 1px solid rgba(148, 163, 184, 0.2); font-size: 0.75rem;">'.$initials.'</div>';
                      }
                      ?>
                      <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                        <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= $userRow['id'] ?></td>
                        <td class="text-center text-white-50" style="background: transparent !important; border: none !important;"><?= $userRow['role_id'] ?? 'NULL' ?></td>
                        <td class="text-center text-white-50" style="background: transparent !important; border: none !important;"><?= $userRow['tenant_id'] ?? 'NULL' ?></td>
                        <td class="text-center" style="background: transparent !important; border: none !important;"><?= $imgDisplay ?></td>
                        <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($userRow['name'] ?? '-') ?></td>
                        <td style="color: #94a3b8 !important; background: transparent !important; border: none !important;">@<?= htmlspecialchars($userRow['username'] ?? '-') ?></td>
                        <td class="text-white-50" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($userRow['email'] ?? '-') ?></td>
                        <td class="text-white-50" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($userRow['phone'] ? $userRow['phone'] : '-') ?></td>
                        <td class="text-center" style="background: transparent !important; border: none !important;">
                          <?php if ($isUserActive): ?>
                            <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem;">1 (Aktif)</span>
                          <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem;">0 (Nonaktif)</span>
                          <?php endif; ?>
                        </td>
                        <td class="text-white-50 small" style="background: transparent !important; border: none !important;"><?= $userRow['last_login'] ?? 'NULL' ?></td>
                        <td class="text-white-50 small" style="background: transparent !important; border: none !important;"><?= $userRow['created_at'] ?? 'NULL' ?></td>
                        <td class="text-white-50 small" style="background: transparent !important; border: none !important;"><?= $userRow['updated_at'] ?? 'NULL' ?></td>
                        <td class="text-white-50 small" style="background: transparent !important; border: none !important;"><?= $userRow['deleted_at'] ?? 'NULL' ?></td>
                        
                        <td class="text-center" style="background: transparent !important; border: none !important;">
                          <div class="d-flex justify-content-center gap-1">
                            <button class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Edit User" onclick='openEditUser(<?= json_encode($userRow) ?>)'>
                              <i class="bi bi-pencil-square"></i>
                            </button>
                            <a href="user.php?action=delete&id=<?= $userRow['id'] ?>" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Delete User" onclick="return confirm('Apakah Anda yakin ingin menghapus data pengguna ini?')">
                              <i class="bi bi-trash-fill"></i>
                            </a>
                          </div>
                        </td>
                      </tr>
                      <?php
                  }
              } else {
                  ?>
                  <tr>
                    <td colspan="14" class="text-center py-5 text-muted shadow-none" style="background: transparent !important; border: none !important;">
                      <i class="bi bi-folder-x d-block mb-2" style="font-size: 2rem; color: rgba(148, 163, 184, 0.4);"></i>
                      Data user tidak ditemukan.
                    </td>
                  </tr>
                  <?php
              }
          } catch (Exception $e) {
              echo '<tr><td colspan="14" class="text-danger text-center py-3">Terjadi Kesalahan: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- 1. MODAL DIALOG: TAMBAH USER BARU (2-KOLOM HORIZONTAL EXTRA LEBAR & BISA SCROLL) -->
<div class="modal fade" id="modalTambahUser" tabindex="-1" aria-hidden="true">
    <!-- Style internal khusus untuk merombak tombol input file bawaan browser menjadi dark mode -->
    <style>
        #tambah_input_photo::-webkit-file-upload-button,
        #tambah_input_photo::file-selector-button {
            background-color: rgba(255, 255, 255, 0.08) !important;
            color: #e2e8f0 !important;
            border: 1px solid rgba(148, 163, 184, 0.25) !important;
            border-radius: 6px !important;
            padding: 0.25rem 0.75rem !important;
            margin-right: 10px !important;
            font-size: 0.82rem;
            transition: all 0.2s ease-in-out;
        }
        #tambah_input_photo::-webkit-file-upload-button:hover,
        #tambah_input_photo::file-selector-button:hover {
            background-color: rgba(255, 255, 255, 0.15) !important;
            cursor: pointer;
        }
    </style>

    <!-- MEMPERBESAR UKURAN KE KANAN DAN MENAMBAHKAN SCROLL AGAR NYAMAN DI LAYAR KECIL -->
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width: 1140px;"> 
        <div class="modal-content border-0 rounded-4 text-white shadow-lg" style="background: #1e293b; border: 1px solid rgba(148,163,184,.15) !important;">
            <div class="modal-header border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-white"><i class="bi bi-person-plus-fill me-2 text-success"></i> Tambah User Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Mengosongkan action agar form memproses datanya ke file user.php itu sendiri -->
            <form method="POST" action="" enctype="multipart/form-data">
                <!-- Elemen penanda pemicu kueri INSERT di PHP bagian atas user.php -->
                <input type="hidden" name="action_register" value="1">

                <div class="modal-body">
                    <div class="row g-4"> <!-- Menggunakan g-4 agar jarak padding antar kolom lebih proporsional saat melebar -->
                        
                        <!-- KOLOM KIRI: Identitas Utama & Akses Level -->
                        <div class="col-md-6 d-flex flex-column gap-3">
                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Nama Lengkap</label>
                                <input type="text" name="name" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" placeholder="Masukkan nama lengkap" required>
                            </div>

                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Username</label>
                                <input type="text" name="username" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" placeholder="Masukkan username" required>
                            </div>

                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Email</label>
                                <input type="email" name="email" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" placeholder="contoh@email.com" required>
                            </div>

                            <!-- INPUT BARU: Dropdown dinamis mengambil data dari array $listRoles -->
                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Role Akses</label>
                                <select name="role_id" class="form-select bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" style="color-scheme: dark;" required>
                                    <option value="" disabled selected>-- Pilih Role Akses --</option>
                                    <?php if (!empty($listRoles)): ?>
                                        <?php foreach ($listRoles as $roleOption): ?>
                                            <option value="<?= $roleOption['id']; ?>"><?= htmlspecialchars($roleOption['name']); ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <!-- KOLOM KANAN: Kontak, Keamanan & Unggah Foto -->
                        <div class="col-md-6 d-flex flex-column gap-3">
                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Nomor Telepon</label>
                                <input type="text" name="phone" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" placeholder="Masukkan nomor telepon">
                            </div>

                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Password Default</label>
                                <input type="password" name="password" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" placeholder="••••••••" required>
                            </div>
                            
                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Foto Profil (Opsional)</label>
                                <!-- Ditambahkan d-flex dan align-items-center agar teks berada di tengah vertikal -->
                                <input type="file" name="photo" id="tambah_input_photo" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2 d-flex align-items-center" style="color-scheme: dark;" accept="image/*" onchange="previewImage(this)">
                            </div>
                        </div>
                        
                        <!-- BARIS BAWAH: Area Pratinjau Foto Horizontal -->
                        <div class="col-12 text-center mt-3">
                            <div class="d-inline-block p-2 rounded-3" style="background: rgba(0,0,0,0.15); min-width: 120px;">
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Pratinjau Foto</label>
                                <!-- Menggunakan 1x1 piksel transparan base64 agar kosong tanpa ikon gambar rusak -->
                                <img id="tambah_preview_photo" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="" class="rounded-circle border border-secondary shadow-sm" style="width: 80px; height: 80px; object-fit: cover;">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary border-opacity-25">
                    <button type="button" class="btn btn-secondary rounded-3 text-white-50 border-0" data-bs-dismiss="modal" style="background: rgba(148,163,184, 0.1);">Batal</button>
                    <button type="submit" class="btn btn-success rounded-3 px-4">Simpan User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. MODAL DIALOG: EDIT USER (EXTRA LEBAR & BISA SCROLL - USERNAME, STATUS & LOG LENGKAP) -->
<div class="modal fade" id="modalEditUser" tabindex="-1" aria-hidden="true">
    <!-- Style internal khusus untuk merombak tombol input file membawaan browser menjadi dark mode -->
    <style>
        #edit_input_photo::-webkit-file-upload-button,
        #edit_input_photo::file-selector-button {
            background-color: rgba(255, 255, 255, 0.08) !important;
            color: #e2e8f0 !important;
            border: 1px solid rgba(148, 163, 184, 0.25) !important;
            border-radius: 6px !important;
            padding: 0.25rem 0.75rem !important;
            margin-right: 10px !important;
            font-size: 0.82rem;
            transition: all 0.2s ease-in-out;
        }
        #edit_input_photo::-webkit-file-upload-button:hover,
        #edit_input_photo::file-selector-button:hover {
            background-color: rgba(255, 255, 255, 0.15) !important;
            cursor: pointer;
        }
    </style>

    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width: 1140px;"> 
        <div class="modal-content border-0 rounded-4 text-white shadow-lg" style="background: #1e293b; border: 1px solid rgba(148,163,184,.15) !important;">
            <div class="modal-header border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-white"><i class="bi bi-pencil-square me-2 text-warning"></i> Edit Data User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action_update_admin" value="1">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="old_photo" id="edit_old_photo">

                <div class="modal-body">
                    <div class="row g-4">
                        
                        <!-- KOLOM KIRI: Identitas Utama & Akses Level -->
                        <div class="col-md-6 d-flex flex-column gap-3">
                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Nama Lengkap</label>
                                <input type="text" name="name" id="edit_name" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" required>
                            </div>

                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Username</label>
                                <input type="text" name="username" id="edit_username" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" required>
                            </div>

                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Email</label>
                                <input type="email" name="email" id="edit_email" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" required>
                            </div>

                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Nomor Telepon</label>
                                <input type="text" name="phone" id="edit_phone" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2">
                            </div>

                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Role Akses</label>
                                <select name="role_id" id="edit_role_id" class="form-select bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" style="color-scheme: dark;" required>
                                    <option value="" disabled>-- Pilih Role Akses --</option>
                                    <?php if (!empty($listRoles)): ?>
                                        <?php foreach ($listRoles as $roleOption): ?>
                                            <option value="<?= $roleOption['id']; ?>"><?= htmlspecialchars($roleOption['name']); ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Status Akun</label>
                                <select name="status" id="edit_status" class="form-select bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" style="color-scheme: dark;" required>
                                    <option value="1">1 (Aktif)</option>
                                    <option value="0">0 (Nonaktif)</option>
                                </select>
                            </div>
                        </div>

                        <!-- KOLOM KANAN: Keamanan, Unggah Berkas & Sistem Log Meta -->
                        <div class="col-md-6 d-flex flex-column gap-3">
                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Ganti Password (Opsional)</label>
                                <input type="password" name="password" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" placeholder="Kosongkan jika tidak ingin diubah">
                            </div>

                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Unggah Foto Baru (Opsional)</label>
                                <input type="file" name="photo" id="edit_input_photo" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2 d-flex align-items-center" style="color-scheme: dark;" accept="image/*" onchange="previewEditImage(this)">
                                <div class="form-text text-white-50" style="font-size: 0.72rem;">Biarkan kosong jika tidak ingin mengubah foto profil.</div>
                            </div>

                            <!-- INFORMASI READ-ONLY LOG TIME METADATA BERDASARKAN HEIDISQL -->
                            <div class="mt-2 p-3 rounded-3" style="background: rgba(0, 0, 0, 0.15); border: 1px solid rgba(148, 163, 184, 0.1);">
                                <h6 class="text-white fw-bold mb-3" style="font-size: 0.85rem;"><i class="bi bi-info-circle me-1 text-info"></i> Metadata Log Sistem</h6>
                                <div class="row g-2" style="font-size: 0.78rem;">
                                    <div class="col-6 text-white-50">Last Login:</div>
                                    <div class="col-6 fw-mono text-end" id="log_last_login">-</div>
                                    
                                    <div class="col-6 text-white-50">Created At:</div>
                                    <div class="col-6 fw-mono text-end" id="log_created_at">-</div>
                                    
                                    <div class="col-6 text-white-50">Updated At:</div>
                                    <div class="col-6 fw-mono text-end" id="log_updated_at">-</div>
                                    
                                    <div class="col-6 text-white-50">Deleted At:</div>
                                    <div class="col-6 fw-mono text-end" id="log_deleted_at">-</div>
                                </div>
                            </div>
                        </div>

                        <!-- BARIS BAWAH: Area Pratinjau Foto Horizontal -->
                        <div class="col-12 text-center mt-2">
                            <div class="d-inline-block p-2 rounded-3" style="background: rgba(0,0,0,0.15); min-width: 120px;">
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Pratinjau Foto</label>
                                <img id="edit_preview_photo" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="" class="rounded-circle border border-secondary shadow-sm" style="width: 80px; height: 80px; object-fit: cover;">
                            </div>
                        </div>
                        
                    </div>
                </div>
                <div class="modal-footer border-secondary border-opacity-25">
                    <button type="button" class="btn btn-secondary rounded-3 text-white-50 border-0" data-bs-dismiss="modal" style="background: rgba(148,163,184, 0.1);">Batal</button>
                    <button type="submit" class="btn btn-warning rounded-3 px-4 text-dark fw-medium">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SCRIPT MANAJEMEN USER (PREVIEW, POPULATE & DRAG TO SCROLL - PERBAIKAN SCROLL MODAL) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const userSlider = document.getElementById('dragScrollUserContainer');
    if (!userSlider) return;
    let isDown = false, startX, scrollLeft;
    userSlider.addEventListener('mousedown', (e) => {
        if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input')) return;
        isDown = true; userSlider.style.cursor = 'grabbing';
        startX = e.pageX - userSlider.offsetLeft; scrollLeft = userSlider.scrollLeft;
    });
    userSlider.addEventListener('mouseleave', () => { isDown = false; userSlider.style.cursor = 'grab'; });
    userSlider.addEventListener('mouseup', () => { isDown = false; userSlider.style.cursor = 'grab'; });
    userSlider.addEventListener('mousemove', (e) => {
        if (!isDown) return; e.preventDefault();
        const x = e.pageX - userSlider.offsetLeft;
        userSlider.scrollLeft = scrollLeft - ((x - startX) * 1.5);
    });
});

function previewImage(input) {
    const preview = document.getElementById('tambah_preview_photo');
    if (preview && input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => preview.src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    } else if (preview) { 
        preview.src = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"; 
    }
}

function previewEditImage(input) {
    const preview = document.getElementById('edit_preview_photo');
    if (preview && input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => preview.src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    } else if (preview) {
        const oldPhoto = document.getElementById('edit_old_photo').value;
        const baseUrl = "http://localhost:8080/RSI_FOOD&MART/";
        if (oldPhoto) {
            const cleanPhoto = oldPhoto.replace('uploads/', '');
            preview.src = baseUrl + "uploads/" + cleanPhoto;
        } else {
            preview.src = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";
        }
    }
}

// GANTI populateEditModal MENJADI openEditUser
function openEditUser(user) {
    if (!user) return;

    // Membuka modal edit secara terprogram agar sinkron
    const modalEdit = new bootstrap.Modal(document.getElementById('modalEditUser'));
    modalEdit.show();

    // Mapping komponen input form utama
    if (document.getElementById('edit_id')) document.getElementById('edit_id').value = user.id;
    if (document.getElementById('edit_name')) document.getElementById('edit_name').value = user.name || '';
    if (document.getElementById('edit_username')) document.getElementById('edit_username').value = user.username || '';
    if (document.getElementById('edit_email')) document.getElementById('edit_email').value = user.email || '';
    if (document.getElementById('edit_phone')) document.getElementById('edit_phone').value = user.phone || '';
    if (document.getElementById('edit_role_id')) document.getElementById('edit_role_id').value = user.role_id || '';
    if (document.getElementById('edit_status')) document.getElementById('edit_status').value = user.status;
    if (document.getElementById('edit_old_photo')) document.getElementById('edit_old_photo').value = user.photo || '';

    // Mapping teks Log info Metadata Read-only di sisi kanan modal
    if (document.getElementById('log_last_login')) document.getElementById('log_last_login').innerText = user.last_login || 'NULL';
    if (document.getElementById('log_created_at')) document.getElementById('log_created_at').innerText = user.created_at || 'NULL';
    if (document.getElementById('log_updated_at')) document.getElementById('log_updated_at').innerText = user.updated_at || 'NULL';
    if (document.getElementById('log_deleted_at')) document.getElementById('log_deleted_at').innerText = user.deleted_at || 'NULL';

    // Rute render pratinjau gambar profil
    const preview = document.getElementById('edit_preview_photo');
    if (preview) {
        let photoPath = user.photo ? user.photo.trim() : '';
        const baseUrl = "http://localhost:8080/RSI_FOOD&MART/";

        if (photoPath !== '') {
            if (photoPath.startsWith('uploads/')) {
                photoPath = photoPath.replace('uploads/', '');
            }
            preview.src = baseUrl + "uploads/" + photoPath;
        } else {
            preview.src = 'https://ui-avatars.com' + encodeURIComponent(user.name || 'US') + '&background=0d6efd&color=fff';
        }

        preview.onerror = function() {
            this.onerror = null;
            this.src = 'https://ui-avatars.com' + encodeURIComponent(user.name || 'US') + '&background=0d6efd&color=fff';
        };
    }
}
</script>

<!-- CSS BENTENG TERAKHIR: Memaksa area modal meloloskan diri dari penguncian overflow-y gaya lama -->
<style>
    .modal-body {
        overflow-y: auto !important;
        max-height: calc(100vh - 210px) !important;
    }
    .modal-dialog-scrollable .modal-content {
        max-height: 100% !important;
        overflow: hidden !important;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// user.php
include "db.php"; // Memanggil koneksi database ($conn) & session_start()

// Proteksi Halaman: Jika sesi user_id kosong, tendang kembali ke login.php
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Cukup panggil file handler di sini setelah koneksi database ($conn) aktif
include "delete_handler.php";

// =========================================================================
// PERBAIKAN: AMBIL DATA ROLES DI SINI (SETELAH $conn SUDAH PASTI TERSEDIA)
// =========================================================================
$listRoles = [];
try {
    // Memastikan koneksi database aktif menggunakan mysqli
    $roleQuery = $conn->query("SELECT id, name FROM roles ORDER BY name ASC");
    if ($roleQuery) {
        while ($rRow = $roleQuery->fetch_assoc()) {
            $listRoles[] = $rRow;
        }
    }
} catch (Throwable $e) {
    // Meredam eror jika tabel roles belum memiliki data
}

$userId = (int)$_SESSION['user_id'];

// Inisialisasi awal variabel dengan data Session (sebagai Fallback)
$roleId = $_SESSION['role_id'] ?? '-';
$tenantId = $_SESSION['tenant_id'] ?? '-';
$name = $_SESSION['name'] ?? 'Pasien';
$username = $_SESSION['username'] ?? '-';
$email = $_SESSION['email'] ?? '-';
$phone = $_SESSION['phone'] ?? '-';
$photo = $_SESSION['photo'] ?? '';
$status = $_SESSION['status'] ?? 0;
$lastLogin = '-';

// READ DATA: Mengambil data lengkap user dari database berdasarkan ID Sesi (User Logged In)
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
    // Tetap menggunakan data dari session jika query bermasalah
}

$isVerified = (int)$status === 1;
$photoUrl = $photo ? (strpos($photo, 'uploads/') === 0 ? $photo : 'uploads/' . $photo) : 'assets/img/default-avatar.png';

// Variabel status notifikasi global halaman
$updateError = '';
$updateSuccess = '';

// =========================================================================
// 1. CRUD: LOGIKA INSERT DATA USER BARU (DARI MODAL TAMBAH)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_register'])) {
    $newName     = trim($_POST['name'] ?? '');
    $newUsername = trim($_POST['username'] ?? '');
    $newEmail    = trim($_POST['email'] ?? '');
    $newPhone    = trim($_POST['phone'] ?? '');
    $newPassword = $_POST['password'] ?? '';
    $newRoleId   = (int)($_POST['role_id'] ?? 0); // PERBAIKAN: Menangkap role_id dinamis dari dropdown modal tambah

    // Validasi input wajib di sisi PHP (Sekarang memeriksa apakah role_id sudah dipilih)
    if (empty($newName) || empty($newUsername) || empty($newEmail) || empty($newPassword) || $newRoleId <= 0) {
        header("Location: user.php?status=error_insert&msg=" . urlencode("Semua kolom wajib termasuk Role Akses harus diisi!"));
        exit;
    }

    $uploadedPhotoName = '';
    $uploadOk = true;
    $updateError = '';

    // Proses upload foto
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
            $defaultStatus = 1; // Menyesuaikan TINYINT status default '1'

            // PERBAIKAN: Mengikat variabel $newRoleId dari dropdown select modal ke query INSERT
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

// =========================================================================
// 2. CRUD: LOGIKA UPDATE DATA USER OLEH ADMIN (DARI MODAL EDIT)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_admin'])) {
    $targetId = (int)($_POST['id'] ?? 0);
    $upName   = trim($_POST['name'] ?? '');
    $upEmail  = trim($_POST['email'] ?? '');
    $upPhone  = trim($_POST['phone'] ?? '');
    $upPass   = $_POST['password'] ?? '';
    $oldPhoto = trim($_POST['old_photo'] ?? '');
    $upRoleId = (int)($_POST['role_id'] ?? 0); // PERBAIKAN: Menangkap role_id baru dari dropdown modal edit

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

    // PERBAIKAN: Memastikan $upRoleId ikut divalidasi tidak boleh kosong sebelum kueri dieksekusi
    if ($uploadOk && $targetId > 0 && !empty($upName) && !empty($upEmail) && $upRoleId > 0) {
        try {
            if (!empty($upPass)) {
                $hashedPass = password_hash($upPass, PASSWORD_BCRYPT);
                // PERBAIKAN: Menambahkan `role_id = ?` ke dalam kueri kueri UPDATE berserta parameter password
                $updateStmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, photo = ?, password = ?, role_id = ? WHERE id = ?");
                $updateStmt->bind_param("sssssii", $upName, $upEmail, $upPhone, $finalPhotoName, $hashedPass, $upRoleId, $targetId);
            } else {
                // PERBAIKAN: Menambahkan `role_id = ?` ke dalam kueri kueri UPDATE tanpa mengganti password
                $updateStmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, photo = ?, role_id = ? WHERE id = ?");
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
        $errorMsg = !empty($updateError) ? $updateError : "Semua kolom wajib atau Role Akses belum dipilih.";
        header("Location: user.php?status=error_update&msg=" . urlencode($errorMsg));
        exit;
    }
}

// =========================================================================
// 3. CRUD: LOGIKA UPDATE MANDIRI (PROFIL AKUN YANG SEDANG LOG IN)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update'])) {
    $newName = trim($_POST['update_name'] ?? '');
    $newPhone = trim($_POST['update_phone'] ?? '');
    $newEmail = trim($_POST['update_email'] ?? '');
    
    $uploadedPhotoPath = $photo; 
    $uploadOk = true;

    if (!empty($_FILES['update_photo']['name'])) {
        $targetDir = "uploads/";
        if (!file_exists($targetDir)) { mkdir($targetDir, 0777, true); }

        $fileName = time() . '_' . basename($_FILES["update_photo"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        
        $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
        if (in_array($fileType, $allowTypes)) {
            if (move_uploaded_file($_FILES["update_photo"]["tmp_name"], $targetFilePath)) {
                $uploadedPhotoPath = $targetFilePath;
                if (!empty($photo) && file_exists($photo) && strpos($photo, 'uploads/') === 0) {
                    @unlink($photo);
                }
            } else { $updateError = "Terjadi kesalahan saat mengunggah foto profil."; $uploadOk = false; }
        } else { $updateError = "Format berkas gambar tidak valid."; $uploadOk = false; }
    }

    if ($uploadOk && !empty($newName) && !empty($newEmail)) {
        try {
            $updateStmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, email = ?, photo = ? WHERE id = ?");
            $updateStmt->bind_param("ssssi", $newName, $newPhone, $newEmail, $uploadedPhotoPath, $userId);
            
            if ($updateStmt->execute()) {
                $_SESSION['name'] = $newName;
                $_SESSION['phone'] = $newPhone;
                $_SESSION['email'] = $newEmail;
                $_SESSION['photo'] = $uploadedPhotoPath;
                
                $updateSuccess = "Profil dan foto Anda berhasil diperbarui!";
                
                $name = $newName;
                $phone = $newPhone;
                $email = $newEmail;
                $photo = $uploadedPhotoPath;
                $photoUrl = $uploadedPhotoPath;
            } else { $updateError = "Gagal menyimpan perubahan data profil."; }
            $updateStmt->close();
        } catch (Throwable $e) { $updateError = "Terjadi kesalahan database: " . $e->getMessage(); }
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
        body { background:var(--bg) !important; color:var(--text); }
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
        @media (min-width: 992px) {
        main.content-shift { margin-left: 280px; }
        .bottom-nav { display:none; }
        }
    </style>

</head>
<body>
  <?php require __DIR__ . '/sidebar.php'; ?>

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

    <!-- STRUKTUR TABEL LIST DATA (14 KOLOM LENGKAP) -->
    <div class="table-responsive border rounded-3" style="border-color: rgba(148, 163, 184, 0.15) !important; background: transparent !important;">
      <table class="table table-hover align-middle mb-0 text-white" style="background: transparent !important; color: #e5e7eb !important; min-width: 1500px;">
        <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
          <tr>
            <th class="py-3 px-2 text-center text-white" style="background: transparent !important;">#1 ID</th>
            <th class="py-3 text-center text-white" style="background: transparent !important;">#2 Role ID</th>
            <th class="py-3 text-center text-white" style="background: transparent !important;">#3 Tenant ID</th>
            <th class="py-3 text-center text-white" style="background: transparent !important;">#9 Photo</th>
            <th class="py-3 text-white" style="background: transparent !important;">#4 Name</th>
            <th class="py-3 text-white" style="background: transparent !important;">#5 Username</th>
            <th class="py-3 text-white" style="background: transparent !important;">#6 Email</th>
            <th class="py-3 text-white" style="background: transparent !important;">#7 Phone</th>
            <th class="py-3 text-center text-white" style="background: transparent !important;">#10 Status</th>
            <th class="py-3 text-white" style="background: transparent !important;">#11 Last Login</th>
            <th class="py-3 text-white" style="background: transparent !important;">#12 Created At</th>
            <th class="py-3 text-white" style="background: transparent !important;">#13 Updated At</th>
            <th class="py-3 text-white" style="background: transparent !important;">#14 Deleted At</th>
            <th class="py-3 text-center text-white" style="background: transparent !important;">Aksi</th>
          </tr>
        </thead>
        <tbody style="background: transparent !important;">
          <?php
          try {
              // Menarik seluruh data kolom tanpa terkecuali
              $queryAllFields = "SELECT id, role_id, tenant_id, name, username, email, phone, photo, status, last_login, created_at, updated_at, deleted_at FROM users ORDER BY id ASC";
              $resultAllFields = $conn->query($queryAllFields);
              
              if ($resultAllFields && $resultAllFields->num_rows > 0) {
                  while ($userRow = $resultAllFields->fetch_assoc()) {
                      $isUserActive = (int)$userRow['status'] === 1;
                      
                      // Manajemen Gambar Profil
                      if (!empty($userRow['photo']) && file_exists($userRow['photo'])) {
                          $imgDisplay = '<img src="'.htmlspecialchars($userRow['photo']).'" class="rounded-circle shadow-sm" style="width: 35px; height: 35px; object-fit: cover; border: 1px solid rgba(148, 163, 184, 0.2);">';
                      } else {
                          $initials = strtoupper(substr($userRow['name'] ?? 'US', 0, 2));
                          $imgDisplay = '<div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width: 35px; height: 35px; background: rgba(148, 163, 184, 0.25); border: 1px solid rgba(148, 163, 184, 0.2); font-size: 0.75rem;">'.$initials.'</div>';
                      }
                      ?>
                      <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                        <!-- #1 id -->
                        <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important;"><?= $userRow['id'] ?></td>
                        <!-- #2 role_id -->
                        <td class="text-center text-white-50" style="background: transparent !important;"><?= $userRow['role_id'] ?? 'NULL' ?></td>
                        <!-- #3 tenant_id -->
                        <td class="text-center text-white-50" style="background: transparent !important;"><?= $userRow['tenant_id'] ?? 'NULL' ?></td>
                        <!-- #9 photo -->
                        <td class="text-center" style="background: transparent !important;"><?= $imgDisplay ?></td>
                        <!-- #4 name -->
                        <td class="fw-semibold text-white" style="background: transparent !important;"><?= htmlspecialchars($userRow['name'] ?? '-') ?></td>
                        <!-- #5 username -->
                        <td style="color: #94a3b8 !important; background: transparent !important;">@<?= htmlspecialchars($userRow['username'] ?? '-') ?></td>
                        <!-- #6 email -->
                        <td class="text-white-50" style="background: transparent !important;"><?= htmlspecialchars($userRow['email'] ?? '-') ?></td>
                        <!-- #7 phone -->
                        <td class="text-white-50" style="background: transparent !important;"><?= htmlspecialchars($userRow['phone'] ? $userRow['phone'] : '-') ?></td>
                        <!-- #10 status -->
                        <td class="text-center" style="background: transparent !important;">
                          <?php if ($isUserActive): ?>
                            <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem;">1 (Aktif)</span>
                          <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem;">0 (Nonaktif)</span>
                          <?php endif; ?>
                        </td>
                        <!-- #11 last_login -->
                        <td class="text-white-50 small" style="background: transparent !important;"><?= $userRow['last_login'] ?? 'NULL' ?></td>
                        <!-- #12 created_at -->
                        <td class="text-white-50 small" style="background: transparent !important;"><?= $userRow['created_at'] ?? 'NULL' ?></td>
                        <!-- #13 updated_at -->
                        <td class="text-white-50 small" style="background: transparent !important;"><?= $userRow['updated_at'] ?? 'NULL' ?></td>
                        <!-- #14 deleted_at -->
                        <td class="text-white-50 small" style="background: transparent !important;"><?= $userRow['deleted_at'] ?? 'NULL' ?></td>
                        
                        <!-- AKSI TOMBOL CRUD -->
                        <td class="text-center" style="background: transparent !important;">
                          <div class="d-flex justify-content-center gap-1">
                            <button class="btn btn-sm btn-outline-success border-0 rounded-2" title="Edit User" data-bs-toggle="modal" data-bs-target="#modalEditUser" onclick="populateEditModal(<?= htmlspecialchars(json_encode($userRow)) ?>)">
                              <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger border-0 rounded-2" title="Hapus User">
                              <i class="bi bi-trash"></i>
                            </button>
                          </div>
                        </td>
                      </tr>
                      <?php
                  }
              } else {
                  echo '<tr><td colspan="14" class="text-center py-5 border-0" style="color: #94a3b8 !important; background: transparent !important;">Belum ada data user terdaftar di sistem.</td></tr>';
              }
          } catch (Throwable $e) {
              echo '<tr><td colspan="14" class="text-center py-4 text-danger border-0" style="background: transparent !important;">Gagal memuat data: '.$e->getMessage().'</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>

  </div>
</main>

<!-- 1. MODAL DIALOG: TAMBAH USER BARU (2-KOLOM HORIZONTAL) -->
<div class="modal fade" id="modalTambahUser" tabindex="-1" aria-hidden="true">
    <!-- Style internal khusus untuk merombak tombol input file bawaan browser menjadi dark mode -->
    <style>
        #tambah_input_photo::-webkit-file-upload-button,
        #tambah_input_photo::file-selector-button {background-color: rgba(255, 255, 255, 0.08) !important;color: #e2e8f0 !important;border: 1px solid rgba(148, 163, 184, 0.25) !important;border-radius: 6px !important;padding: 0.25rem 0.75rem !important;margin-right: 10px !important;font-size: 0.82rem;transition: all 0.2s ease-in-out;}
        #tambah_input_photo::-webkit-file-upload-button:hover,
        #tambah_input_photo::file-selector-button:hover {background-color: rgba(255, 255, 255, 0.15) !important;cursor: pointer;}
    </style>

    <div class="modal-dialog modal-lg modal-dialog-centered"> <!-- Menggunakan modal-lg agar lebih lebar ke kanan -->
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
                    <div class="row g-3">
                        
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

<!-- 2. MODAL DIALOG: EDIT USER (2-KOLOM HORIZONTAL) -->
<div class="modal fade" id="modalEditUser" tabindex="-1" aria-hidden="true">
    <!-- Style internal khusus untuk merombak tombol input file bawaan browser menjadi dark mode -->
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

    <div class="modal-dialog modal-lg modal-dialog-centered"> <!-- Menggunakan modal-lg agar lebih lebar ke kanan -->
        <div class="modal-content border-0 rounded-4 text-white shadow-lg" style="background: #1e293b; border: 1px solid rgba(148,163,184,.15) !important;">
            <div class="modal-header border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-white"><i class="bi bi-pencil-square me-2 text-warning"></i> Edit Data User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Mengosongkan action agar form memproses datanya ke file user.php itu sendiri -->
            <form method="POST" action="" enctype="multipart/form-data">
                <!-- Elemen penanda pemicu kueri UPDATE di PHP bagian atas user.php -->
                <input type="hidden" name="action_update_admin" value="1">
                <!-- ID User (Hidden) untuk parameter WHERE di query UPDATE -->
                <input type="hidden" name="id" id="edit_id">
                <!-- Path foto lama (Hidden) jika user tidak ingin mengganti fotonya -->
                <input type="hidden" name="old_photo" id="edit_old_photo">

                <div class="modal-body">
                    <div class="row g-3">
                        
                        <!-- KOLOM KIRI: Identitas Utama & Akses Level -->
                        <div class="col-md-6 d-flex flex-column gap-3">
                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Nama Lengkap</label>
                                <input type="text" name="name" id="edit_name" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" required>
                            </div>

                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Email</label>
                                <input type="email" name="email" id="edit_email" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" required>
                            </div>

                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Nomor Telepon</label>
                                <input type="text" name="phone" id="edit_phone" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2">
                            </div>

                            <!-- INPUT DROPDOWN ROLE: Otomatis memilih data sesuai id="edit_role_id" dari JS -->
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
                        </div>

                        <!-- KOLOM KANAN: Keamanan & Unggah Berkas Baru -->
                        <div class="col-md-6 d-flex flex-column gap-3">
                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Ganti Password (Opsional)</label>
                                <input type="password" name="password" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" placeholder="Kosongkan jika tidak ingin diubah">
                            </div>
                            <div>
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Unggah Foto Baru (Opsional)</label>
                                <!-- Ditambahkan d-flex dan align-items-center agar teks berada di tengah vertikal -->
                                <input type="file" name="photo" id="edit_input_photo" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2 d-flex align-items-center" style="color-scheme: dark;" accept="image/*" onchange="previewEditImage(this)">
                                <div class="form-text text-white-50" style="font-size: 0.72rem;">Biarkan kosong jika tidak ingin mengubah foto profil.</div>
                            </div>
                        </div>

                        <!-- BARIS BAWAH: Area Pratinjau Foto Horizontal -->
                        <div class="col-12 text-center mt-3">
                            <div class="d-inline-block p-2 rounded-3" style="background: rgba(0,0,0,0.15); min-width: 120px;">
                                <label class="form-label text-white-50 small d-block fw-medium mb-2">Pratinjau Foto</label>
                                <!-- Menggunakan 1x1 piksel transparan base64 sebagai fallback awal -->
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

<!-- SCRIPT MANAJEMEN USER (PREVIEW & POPULATE) -->
<script>

/**
 * 1. Live Preview Foto untuk Modal Tambah User
 */
function previewImage(input) {
    const preview = document.getElementById('tambah_preview_photo');
    // PERBAIKAN: Menambahkan [0] untuk menangkap file pertama yang dipilih
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]); // PERBAIKAN: Menambahkan [0]
    } else {
        preview.src = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";
    }
}

/**
 * 2. Live Preview Foto untuk Modal Edit User
 */
function previewEditImage(input) {
    const preview = document.getElementById('edit_preview_photo');
    // PERBAIKAN: Menambahkan [0] untuk menangkap file pertama yang dipilih
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]); // PERBAIKAN: Menambahkan [0]
    } else {
        const oldPhoto = document.getElementById('edit_old_photo').value;
        preview.src = oldPhoto ? "uploads/" + oldPhoto : "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";
    }
}

/**
 * 3. Mengisi Data Pengguna ke Form Modal Edit
 */
function populateEditModal(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_name').value = user.name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_phone').value = user.phone;
    
    document.getElementById('edit_old_photo').value = user.photo || '';
    
    const preview = document.getElementById('edit_preview_photo');
    if (user.photo && user.photo.trim() !== '') {
        preview.src = "uploads/" + user.photo;
    } else {
        preview.src = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";
    }
}

</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

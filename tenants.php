<?php
// tenant.php
include "db.php"; // Memanggil koneksi database ($conn) & session_start()

// Proteksi Halaman: Jika sesi user_id kosong, tendang kembali ke login.php
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Menghubungkan logika modular penghapusan data (jika ada file delete_handler tersendiri)
if (file_exists("delete_handler.php")) {
    include "delete_handler.php";
}

// =========================================================================
// 0. CRUD: LOGIKA SOFT DELETE DATA TENANT (MENGISI DELETED_AT)
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $idToDelete = (int)$_GET['id'];
    if ($idToDelete > 0) {
        try {
            // Update kolom deleted_at dengan penanda waktu saat ini (Soft Delete)
            $deleteStmt = $conn->prepare("UPDATE tenants SET deleted_at = NOW() WHERE id = ?");
            $deleteStmt->bind_param("i", $idToDelete);
            if ($deleteStmt->execute()) {
                header("Location: tenant.php?status=success_delete");
                exit;
            } else {
                header("Location: tenant.php?status=error_delete&msg=" . urlencode($deleteStmt->error));
                exit;
            }
            $deleteStmt->close();
        } catch (Throwable $e) {
            header("Location: tenant.php?status=error_delete&msg=" . urlencode($e->getMessage()));
            exit;
        }
    }
}

// =========================================================================
// 1. CRUD: LOGIKA INSERT DATA TENANT BARU (DARI MODAL TAMBAH)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_tenant'])) {
    $tenantName  = trim($_POST['name'] ?? '');
    $tenantType  = trim($_POST['type'] ?? '');
    $prepTime    = (int)($_POST['preparation_time'] ?? 15);
    $tenantPhone = trim($_POST['phone'] ?? '');
    $tenantEmail = trim($_POST['email'] ?? '');
    $tenantDesc  = trim($_POST['description'] ?? '');

    $uploadedLogoName = '';
    $uploadOk = true;
    $errorMessage = '';

    if (empty($tenantName) || empty($tenantType)) {
        header("Location: tenant.php?status=error_insert&msg=" . urlencode("Nama tenant dan tipe kategori wajib diisi!"));
        exit;
    }

    if (!empty($_FILES['logo']['name'])) {
        $targetDir = "uploads/";
        if (!file_exists($targetDir)) { mkdir($targetDir, 0777, true); }

        $fileExtension = strtolower(pathinfo($_FILES["logo"]["name"], PATHINFO_EXTENSION));
        $newFileName = 'tenant_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
        $targetFilePath = $targetDir . $newFileName;

        if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
            if ($_FILES["logo"]["size"] <= 2 * 1024 * 1024) {
                if (move_uploaded_file($_FILES["logo"]["tmp_name"], $targetFilePath)) {
                    $uploadedLogoName = $newFileName;
                } else { $uploadOk = false; $errorMessage = "Gagal memindahkan berkas logo ke server."; }
            } else { $uploadOk = false; $errorMessage = "Ukuran file logo terlalu besar. Maksimal 2MB."; }
        } else { $uploadOk = false; $errorMessage = "Format gambar tidak didukung."; }
    }

    if ($uploadOk) {
        try {
            $defaultStatus = 1;
            $insertStmt = $conn->prepare("INSERT INTO tenants (name, type, description, logo, phone, email, preparation_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->bind_param("ssssssii", $tenantName, $tenantType, $tenantDesc, $uploadedLogoName, $tenantPhone, $tenantEmail, $prepTime, $defaultStatus);
            
            if ($insertStmt->execute()) {
                header("Location: tenant.php?status=success_insert");
                exit;
            } else {
                header("Location: tenant.php?status=error_insert&msg=" . urlencode($insertStmt->error));
                exit;
            }
            $insertStmt->close();
        } catch (Throwable $e) { 
            header("Location: tenant.php?status=error_insert&msg=" . urlencode($e->getMessage()));
            exit;
        }
    } else {
        header("Location: tenant.php?status=error_insert&msg=" . urlencode($errorMessage));
        exit;
    }
}

// =========================================================================
// 2. CRUD: LOGIKA UPDATE DATA TENANT (DARI MODAL EDIT)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_tenant'])) {
    $targetId    = (int)($_POST['id'] ?? 0);
    $upName      = trim($_POST['name'] ?? '');
    $upType      = trim($_POST['type'] ?? '');
    $upPrepTime  = (int)($_POST['preparation_time'] ?? 15);
    $upPhone     = trim($_POST['phone'] ?? '');
    $upEmail     = trim($_POST['email'] ?? '');
    $upDesc      = trim($_POST['description'] ?? '');
    $oldLogo     = trim($_POST['old_photo'] ?? '');

    $finalLogoName = $oldLogo; 
    $uploadOk = true;
    $errorMessage = '';

    if (!empty($_FILES['logo']['name'])) {
        $targetDir = "uploads/";
        if (!file_exists($targetDir)) { mkdir($targetDir, 0777, true); }

        $fileExtension = strtolower(pathinfo($_FILES["logo"]["name"], PATHINFO_EXTENSION));
        $newFileName = 'tenant_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
        $targetFilePath = $targetDir . $newFileName;

        if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
            if ($_FILES["logo"]["size"] <= 2 * 1024 * 1024) {
                if (move_uploaded_file($_FILES["logo"]["tmp_name"], $targetFilePath)) {
                    $finalLogoName = $newFileName;
                    if (!empty($oldLogo) && file_exists("uploads/" . $oldLogo)) {
                        @unlink("uploads/" . $oldLogo);
                    }
                } else { $uploadOk = false; $errorMessage = "Gagal memproses unggahan gambar baru."; }
            } else { $uploadOk = false; $errorMessage = "Ukuran file logo terlalu besar. Maksimal 2MB."; }
        } else { $uploadOk = false; $errorMessage = "Format berkas logo tidak valid."; }
    }

    if ($uploadOk && $targetId > 0 && !empty($upName) && !empty($upType)) {
        try {
            $updateStmt = $conn->prepare("UPDATE tenants SET name = ?, type = ?, description = ?, logo = ?, phone = ?, email = ?, preparation_time = ? WHERE id = ?");
            $updateStmt->bind_param("ssssssii", $upName, $upType, $upDesc, $finalLogoName, $upPhone, $upEmail, $upPrepTime, $targetId);
            
            if ($updateStmt->execute()) {
                header("Location: tenant.php?status=success_update");
                exit;
            } else {
                header("Location: tenant.php?status=error_update&msg=" . urlencode($updateStmt->error));
                exit;
            }
            $updateStmt->close();
        } catch (Throwable $e) {
            header("Location: tenant.php?status=error_update&msg=" . urlencode($e->getMessage()));
            exit;
        }
    } else {
        $errorMsg = !empty($errorMessage) ? $errorMessage : "Semua kolom wajib harus diisi dengan benar.";
        header("Location: tenant.php?status=error_update&msg=" . urlencode($errorMsg));
        exit;
    }
}

// =========================================================================
// 3. READ DATA: AMBIL DATA YANG BELUM DI-SOFT-DELETE (`deleted_at IS NULL`)
// =========================================================================
$listTenants = [];
try {
    // MENYARING DATA: Hanya menarik record yang data deleted_at-nya masih kosong/NULL
    $tenantQuery = $conn->query("SELECT * FROM tenants WHERE deleted_at IS NULL ORDER BY id DESC");
    if ($tenantQuery) {
        while ($tRow = $tenantQuery->fetch_assoc()) {
            $listTenants[] = $tRow;
        }
    }
} catch (Throwable $e) {
    // Tangani error query jika diperlukan
}

$currentFile = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
$menu = [
    'home.php'        => [ 'href' => 'home.php',        'label' => 'Etalase Menu', 'icon' => 'bi-shop' ],
    'tenant.php'      => [ 'href' => 'tenant.php',      'label' => 'Tenants',      'icon' => 'bi-house' ], 
    'user.php'        => [ 'href' => 'user.php',        'label' => 'User',         'icon' => 'bi-person' ],
    'roles.php'       => [ 'href' => 'roles.php',       'label' => 'Roles',        'icon' => 'bi-shield-lock' ],
    'permissions.php' => [ 'href' => 'permissions.php', 'label' => 'Permissions',  'icon' => 'bi-key' ],
];
?>

<!DOCTYPE html>
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
    #dragScrollUserContainer::-webkit-scrollbar, #dragScrollContainer::-webkit-scrollbar, .drag-scroll-container::-webkit-scrollbar { display: none !important; }
    #dragScrollUserContainer, #dragScrollContainer, .drag-scroll-container { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow-x: auto !important; cursor: grab !important; border: none !important; box-shadow: none !important; -webkit-box-shadow: none !important; }
    #dragScrollUserContainer:active, #dragScrollContainer:active, .drag-scroll-container:active { cursor: grabbing !important; }
    #dragScrollUserContainer table, #dragScrollContainer table, .drag-scroll-container table { border-collapse: collapse !important; border: none !important; }
    #dragScrollUserContainer table th, #dragScrollUserContainer table td, #dragScrollContainer table th, #dragScrollContainer table td, .drag-scroll-container table th, .drag-scroll-container table td { border-left: none !important; border-right: none !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; }
    .text-white-element { -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }
    .modal-dialog { max-width: 800px !important; }
    .modal-body::-webkit-scrollbar { display: none !important; }
    .modal-body { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow: visible !important; }
    .bi-clock-history, .text-white-icon { color: #ffffff !important; opacity: 1 !important; filter: drop-shadow(0 0 1px rgba(255,255,255,0.2)); }
    input[type="time"]::-webkit-calendar-picker-indicator,
    input[type="date"]::-webkit-calendar-picker-indicator {filter: invert(1) brightness(100%) contrast(100%) !important;cursor: pointer;}
    @media (min-width: 992px) { main.content-shift { margin-left: 280px; } .bottom-nav { display:none; } }
</style>

</head>
<body>
  <?php require __DIR__ . '/sidebar.php'; ?>

<main class="content-shift p-4">
  <!-- Container tabel dengan tema gelap transparan yang selaras dengan halaman user -->
  <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
    
    <!-- HEADER TABEL & TOMBOL TAMBAH TENANT -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
      <div>
        <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;"> Data Tenant </h2>
      </div>
      <div>
        <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalTenantRight" onclick="openTambahModal()">
          <i class="bi bi-house-add-fill"></i> Tambah Tenant
        </button>
      </div>
    </div>

    <!-- STRUKTUR TABEL LIST DATA TENANT (TANPA SCROLL BAR & DAPAT DIGESER MOUSE - BEBAS GARIS PUTIH) -->
    <div id="dragScrollContainer" class="table-responsive rounded-3 drag-scroll-container" style="border: none !important; background: transparent !important; cursor: grab; box-shadow: none !important; -webkit-box-shadow: none !important;">
      <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 1500px; user-select: none; border-collapse: collapse !important;">
        <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
          <tr> 
              <th class="py-3 px-2 text-center text-white" style="background: transparent !important; border: none !important;"> ID</th> 
              <th class="py-3 text-white" style="background: transparent !important; border: none !important;"> Name</th> 
              <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important;">Type</th> 
              <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Description</th> 
              <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important;">Logo</th> 
              <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Phone</th> 
              <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Email</th> 
              <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important;">Prep Time</th> 
              <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important;">Status</th> 
              <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Created At</th> 
              <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Updated At</th> 
              <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Deleted At</th> 
              <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important;">Aksi</th> 
          </tr>
        </thead>
        <tbody style="background: transparent !important;">
          <?php
          try {
              $queryTenants = "SELECT id, name, type, description, logo, phone, email, preparation_time, status, created_at, updated_at, deleted_at FROM tenants WHERE deleted_at IS NULL ORDER BY id ASC";
              $resultTenants = $conn->query($queryTenants);
              
              if ($resultTenants && $resultTenants->num_rows > 0) {
                  while ($tenantRow = $resultTenants->fetch_assoc()) {
                      $isTenantActive = (int)$tenantRow['status'] === 1;
                      
                      if (!empty($tenantRow['logo']) && file_exists("uploads/" . $tenantRow['logo'])) {
                          $imgDisplay = '<img src="uploads/'.htmlspecialchars($tenantRow['logo']).'" class="rounded-circle shadow-sm" style="width: 35px; height: 35px; object-fit: cover; border: 1px solid rgba(148, 163, 184, 0.2);" draggable="false">';
                      } else {
                          $initials = strtoupper(substr($tenantRow['name'] ?? 'TN', 0, 2));
                          $imgDisplay = '<div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width: 35px; height: 35px; background: rgba(148, 163, 184, 0.25); border: 1px solid rgba(148, 163, 184, 0.2); font-size: 0.75rem;">'.$initials.'</div>';
                      }
                      ?>
                      <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                        <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= $tenantRow['id'] ?></td>
                        <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($tenantRow['name'] ?? '-') ?></td>
                        <td class="text-center" style="background: transparent !important; border: none !important;">
                          <span class="badge bg-info-subtle text-info border border-info border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem; background: rgba(13, 202, 240, 0.15);"><?= htmlspecialchars($tenantRow['type'] ?? '-') ?></span>
                        </td>
                        <td class="text-white-50" style="background: transparent !important; border: none !important; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($tenantRow['description'] ?? '-') ?></td>
                        <td class="text-center" style="background: transparent !important; border: none !important;"><?= $imgDisplay ?></td>
                        <td class="text-white-50" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($tenantRow['phone'] ?: '-') ?></td>
                        <td class="text-white-50" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($tenantRow['email'] ?: '-') ?></td>
                        <td class="text-center text-white-50" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($tenantRow['preparation_time']) ?> mnt</td>
                        <td class="text-center" style="background: transparent !important; border: none !important;">
                          <?php if ($isTenantActive): ?>
                            <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem;">1 (Aktif)</span>
                          <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem;">0 (Nonaktif)</span>
                          <?php endif; ?>
                        </td>
                        <td class="text-white-50 small" style="background: transparent !important; border: none !important;"><?= $tenantRow['created_at'] ?? 'NULL' ?></td>
                        <td class="text-white-50 small" style="background: transparent !important; border: none !important;"><?= $tenantRow['updated_at'] ?? 'NULL' ?></td>
                        <td class="text-white-50 small" style="background: transparent !important; border: none !important;"><?= $tenantRow['deleted_at'] ?? 'NULL' ?></td>
                        
                        <td class="text-center" style="background: transparent !important; border: none !important;">
                          <div class="d-flex justify-content-center gap-1">
                            <button class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Edit Tenant" onclick='openEditModal(<?= json_encode($tenantRow) ?>)'>
                              <i class="bi bi-pencil-square"></i>
                            </button>
                            <a href="tenant.php?action=delete&id=<?= $tenantRow['id'] ?>" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Delete Tenant" onclick="return confirm('Apakah Anda yakin ingin menghapus tenant ini?')">
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
                    <td colspan="13" class="text-center py-5 text-muted shadow-none" style="background: transparent !important; border: none !important;">
                      <i class="bi bi-folder-x d-block mb-2" style="font-size: 2rem; color: rgba(148, 163, 184, 0.4);"></i>
                      Tidak ada data tenant yang aktif saat ini.
                    </td>
                  </tr>
                  <?php
              }
          } catch (Throwable $e) {
              echo "<tr><td colspan='13' class='text-danger text-center py-3' style='background: transparent !important; border: none !important;'>Gagal memuat data: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- Modal CRUD (Memanjang Ke Kanan & Transparan Gelap Selaras) -->
<div class="modal fade modal-right" id="modalTenantRight" tabindex="-1" aria-labelledby="modalTenantLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.88) !important; backdrop-filter: blur(10px); border-left: 1px solid rgba(148, 163, 184, 0.2); color: #e5e7eb;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
                <h5 class="modal-title fw-bold text-white" id="modalTenantLabel">Form Tenant</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formTenant" action="tenants.php?action=save" method="POST">
                <div class="modal-body" style="overflow-y: auto; max-height: calc(100vh - 130px); padding-bottom: 80px;">
                    <input type="hidden" name="id" id="tenant_id">
                    <div class="mb-3">
                        <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Nama Tenant</label>
                        <input type="text" class="form-control" name="name" id="tenant_name" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Tipe Tenant</label>
                        <select class="form-select" name="type" id="tenant_type" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                            <option value="kantin">Kantin</option>
                            <option value="cafe">Cafe</option>
                            <option value="koperasi">Koperasi</option>
                            <option value="laundry">Laundry</option>
                            <option value="florist">Florist</option>
                            <option value="giftshop">Giftshop</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Deskripsi</label>
                        <textarea class="form-control" name="description" id="tenant_description" rows="3" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Nomor Telepon</label>
                        <input type="text" class="form-control" name="phone" id="tenant_phone" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Email</label>
                        <input type="email" class="form-control" name="email" id="tenant_email" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Waktu Persiapan (Menit)</label>
                        <input type="number" class="form-control" name="preparation_time" id="tenant_prep" value="15" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.15); position: absolute; bottom: 0; width: 100%; background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(10px);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

  <?php include "bottom_nav.php"; ?>
  
<!-- JavaScript Integrasi Modal, Isian Form, dan Drag to Scroll -->
<script>
// =========================================================================
// FUNGSI UTAMA: MOUSE DRAG TO SCROLL (GESER TABEL DENGAN KURSOR)
// =========================================================================
document.addEventListener('DOMContentLoaded', function() {
    const slider = document.querySelector('.table-responsive');
    if (!slider) return;

    let isDown = false;
    let startX;
    let scrollLeft;

    slider.addEventListener('mousedown', (e) => {
        // Mencegah trigger drag jika yang diklik adalah tombol aksi atau input text
        if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input')) return;
        
        isDown = true;
        startX = e.pageX - slider.offsetLeft;
        scrollLeft = slider.scrollLeft;
    });

    slider.addEventListener('mouseleave', () => {
        isDown = false;
    });

    slider.addEventListener('mouseup', () => {
        isDown = false;
    });

    slider.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault(); // Mencegah pemblokiran teks saat digeser
        const x = e.pageX - slider.offsetLeft;
        const walk = (x - startX) * 1.5; // Mengatur sensitivitas kecepatan pergeseran kursor
        slider.scrollLeft = scrollLeft - walk;
    });
});

// =========================================================================
// FUNGSI PENDUKUNG: MODAL & ISIAN FORM CRUD
// =========================================================================
function resetForm() {
    document.getElementById('formTenant').reset();
    document.getElementById('tenant_id').value = '';
    document.getElementById('modalTenantLabel').innerText = 'Tambah Tenant';
}

function editTenant(data) {
    resetForm();
    document.getElementById('modalTenantLabel').innerText = 'Ubah Data Tenant';
    document.getElementById('tenant_id').value = data.id;
    document.getElementById('tenant_name').value = data.name;
    document.getElementById('tenant_type').value = data.type;
    document.getElementById('tenant_description').value = data.description;
    document.getElementById('tenant_phone').value = data.phone;
    document.getElementById('tenant_email').value = data.email;
    document.getElementById('tenant_prep').value = data.preparation_time;
    
    var myModal = new bootstrap.Modal(document.getElementById('modalTenantRight'));
    myModal.show();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


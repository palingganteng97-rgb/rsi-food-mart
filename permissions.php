<?php
// permissions.php
include 'db.php'; 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$status = isset($_GET['status']) ? htmlspecialchars($_GET['status']) : "";
$msg = "";

if (isset($_SESSION['perm_error_msg'])) {
    $status = "error";
    $msg = $_SESSION['perm_error_msg'];
    unset($_SESSION['perm_error_msg']); 
}

// 1. PROSES BERKAS CREATE (TAMBAH DATA)
if (isset($_POST['create'])) {
    $module_name     = trim($_POST['module_name']);
    $permission_name = trim($_POST['permission_name']);

    if (empty($module_name) || empty($permission_name)) {
        $_SESSION['perm_error_msg'] = "Semua kolom input wajib diisi!";
        header("Location: permissions.php");
        exit();
    }

    // Cek duplicate module_name (case insensitive)
    $checkStmt = $conn->prepare("SELECT id FROM permissions WHERE LOWER(module_name) = LOWER(?) LIMIT 1");
    $checkStmt->bind_param("s", $module_name);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $_SESSION['perm_error_msg'] = "Module Name sudah terdaftar! Tidak boleh ada duplikat.";
        $checkStmt->close();
        header("Location: permissions.php");
        exit();
    }
    $checkStmt->close();

    $stmt = $conn->prepare("INSERT INTO permissions (module_name, permission_name) VALUES (?, ?)");
    $stmt->bind_param("ss", $module_name, $permission_name);
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: permissions.php?status=success_create");
        exit();
    } else {
        $_SESSION['perm_error_msg'] = "Gagal menambah data: " . $stmt->error;
        $stmt->close();
        header("Location: permissions.php");
        exit();
    }
}

// 2. PROSES BERKAS UPDATE (UBAH DATA)
if (isset($_POST['update'])) {
    $id              = intval($_POST['id']);
    $module_name     = trim($_POST['module_name']);
    $permission_name = trim($_POST['permission_name']);

    if (empty($module_name) || empty($permission_name)) {
        $_SESSION['perm_error_msg'] = "Semua kolom input wajib diisi!";
        header("Location: permissions.php");
        exit();
    }

    // Cek duplicate module_name (case insensitive, exclude current record)
    $checkStmt = $conn->prepare("SELECT id FROM permissions WHERE LOWER(module_name) = LOWER(?) AND id != ? LIMIT 1");
    $checkStmt->bind_param("si", $module_name, $id);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $_SESSION['perm_error_msg'] = "Module Name sudah dipakai oleh data lain! Tidak boleh ada duplikat.";
        $checkStmt->close();
        header("Location: permissions.php");
        exit();
    }
    $checkStmt->close();

    $stmt = $conn->prepare("UPDATE permissions SET module_name = ?, permission_name = ? WHERE id = ?");
    $stmt->bind_param("ssi", $module_name, $permission_name, $id);
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: permissions.php?status=success_update");
        exit();
    } else {
        $_SESSION['perm_error_msg'] = "Gagal memperbarui data: " . $stmt->error;
        $stmt->close();
        header("Location: permissions.php");
        exit();
    }
}

// 3. PROSES BERKAS DELETE (HAPUS DATA)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM permissions WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: permissions.php?status=success_delete");
            exit();
        } else {
            $_SESSION['perm_error_msg'] = "Gagal menghapus data: " . $stmt->error;
            $stmt->close();
            header("Location: permissions.php");
            exit();
        }
    } else {
        $_SESSION['perm_error_msg'] = "ID tidak valid.";
        header("Location: permissions.php");
        exit();
    }
}

// 4. READ DATA UNTUK LOOPING TABEL VIEW
$permissionsData = [];
$sql = "SELECT id, module_name, permission_name FROM permissions ORDER BY id DESC";
$fetchQuery = mysqli_query($conn, $sql);

if ($fetchQuery) {
    while ($row = mysqli_fetch_assoc($fetchQuery)) {
        $permissionsData[] = $row;
    }
}
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
    .text-white-element { -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }
    .modal-lg-custom { max-width: 800px !important; }
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
  <!-- Container tabel dengan tema gelap transparan menyatu dengan background halaman -->
  <div class="container-fluid rounded-4 p-4 text-white" style="background: transparent !important; border: none !important; box-shadow: none !important;">
    
    <!-- HEADER TABEL & TOMBOL TAMBAH PERMISSION -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
      <div>
        <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Manajemen Hak Akses / Permissions</h2>
      </div>
      <div>
        <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalPermission" onclick="openTambahPermission()">
          <i class="bi bi-plus-circle"></i> Tambah Hak Akses
        </button>
      </div>
    </div>

    <!-- NOTIFIKASI INFORMASI ALERT STATUS OPERASI CRUD -->
    <?php if (!empty($status) && strpos($status, 'success') !== false): ?>
      <div class="alert alert-success border-0 rounded-3 mb-4 alert-dismissible fade show" role="alert" style="background: rgba(34, 197, 94, 0.12) !important; color: #86efac !important;">
        <i class="bi bi-check-circle-fill me-2"></i> 
        <?php 
        if ($status == 'success_create') echo "Data hak akses berhasil ditambahkan!";
        elseif ($status == 'success_update') echo "Data hak akses berhasil diperbarui!";
        elseif ($status == 'success_delete') echo "Data hak akses berhasil deleted!";
        ?>
        <!-- PERBAIKAN: Menambahkan tombol close interaktif -->
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close" style="font-size: 0.75rem; box-shadow: none;"></button>
      </div>
    <?php endif; ?>
    
    <?php if (!empty($status) && $status == 'error'): ?>
      <div class="alert alert-danger border-0 rounded-3 mb-4 alert-dismissible fade show" role="alert" style="background: rgba(239, 68, 68, 0.12) !important; color: #fecaca !important;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($msg) ?>
        <!-- PERBAIKAN: Menambahkan tombol close interaktif -->
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close" style="font-size: 0.75rem; box-shadow: none;"></button>
      </div>
    <?php endif; ?>

    <!-- TABEL LIST DATA PERMISSIONS (Mendukung drag-to-scroll & Kustom Hover) -->
    <div class="table-responsive" id="dragScrollStockContainer" style="overflow-x: auto; cursor: grab; scrollbar-width: none; -ms-overflow-style: none;">
      <style>
          #dragScrollStockContainer::-webkit-scrollbar { display: none; }
          .table-custom-hover tbody tr:hover { background: rgba(148, 163, 184, 0.15) !important; transition: background 0.15s ease-in-out; }
          .table-force-white th, .table-force-white td { color: #ffffff !important; }
      </style>
      <table class="table align-middle mb-0 table-force-white table-custom-hover" style="--bs-table-bg: transparent; border-collapse: separate; border-spacing: 0 8px; min-width: 1000px; user-select: none;">
        <thead style="font-size: 0.85rem; background: rgba(30, 41, 59, 0.65) !important;">
            <tr>
              <th class="py-3 px-4 text-center" style="width: 80px; border-radius: 10px 0 0 10px; border-bottom: 1px solid rgba(148, 163, 184, 0.2); background: transparent !important;">id</th>
              <th class="py-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.2); width: 250px; background: transparent !important;">module_name</th>
              <th class="py-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.2); background: transparent !important;">permission_name</th>
              <th class="py-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.2); width: 180px; background: transparent !important;">created_at</th>
              <th class="py-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.2); width: 180px; background: transparent !important;">updated_at</th>
              <th class="py-3 text-center" style="width: 120px; border-radius: 0 10px 10px 0; border-bottom: 1px solid rgba(148, 163, 184, 0.2); background: transparent !important;">Aksi</th>
            </tr>
        </thead>
        <tbody>
          <?php
          try {
              if (!empty($permissionsData)) {
                  foreach ($permissionsData as $permRow) {
                      ?>
                      <tr style="background: rgba(30, 41, 59, 0.55); border: 1px solid rgba(148, 163, 184, 0.15);">
                        <!-- #1 id -->
                        <td class="text-center fw-semibold" style="border-radius: 10px 0 0 10px;"><?= $permRow['id'] ?></td>
                        
                        <!-- #2 module_name -->
                        <td class="fw-bold">
                          <i class="bi bi-box-seam me-2 text-white-50"></i><?= htmlspecialchars($permRow['module_name'] ?? '-') ?>
                        </td>
                        
                        <!-- #3 permission_name -->
                        <td>
                          <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-10 rounded-2" style="font-size: 0.8rem; background: rgba(13, 110, 253, 0.12); padding: 6px 12px;">
                            <i class="bi bi-key me-1"></i><?= htmlspecialchars($permRow['permission_name'] ?? '-') ?>
                          </span>
                        </td>
                        
                        <!-- #4 created_at -->
                        <td class="fw-medium"><?= $permRow['created_at'] ?? 'NULL' ?></td>
                        
                        <!-- #5 updated_at -->
                        <td class="fw-medium"><?= $permRow['updated_at'] ?? 'NULL' ?></td>
                        
                        <!-- TOMBOL AKSI CRUD MODERAT -->
                        <td class="text-center" style="border-radius: 0 10px 10px 0;">
                          <div class="d-inline-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-warning rounded-2" title="Edit Permission" onclick="openEditPermission(<?= htmlspecialchars(json_encode($permRow)) ?>)">
                              <i class="bi bi-pencil-square"></i>
                            </button>
                            
                            <button type="button" 
                                    class="btn btn-sm btn-outline-danger rounded-2" 
                                    title="Delete Permission"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalDeletePermission" 
                                    onclick="document.getElementById('delete_permission_title_display').innerText = '<?= addslashes($permRow['permission_name']); ?>'; document.getElementById('btn_confirm_delete_permission').setAttribute('href', 'permissions.php?delete=<?= $permRow['id']; ?>')">
                                <i class="bi bi-trash3-fill"></i>
                            </button>
                          </div>
                        </td>
                      </tr>
                      <?php
                  }
              } else {
                  echo '<tr><td colspan="6" class="text-center py-5 fw-bold" style="background: rgba(30, 41, 59, 0.2); border-radius: 10px;">Belum ada data hak akses terdaftar di database.</td></tr>';
              }
          } catch (Throwable $e) {
              echo '<tr><td colspan="6" class="text-center py-4 text-danger fw-bold" style="background: rgba(30, 41, 59, 0.2); border-radius: 10px;">Gagal memuat data: '.$e->getMessage().'</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>

  </div>
</main>

<!-- 1. MODAL FORM CRUD (TAMBAH / EDIT PERMISSION) -->
<div class="modal fade" id="modalPermission" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px !important;">
        <form action="permissions.php" method="POST" id="formPermission" class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(10px); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-header border-0 pb-0" style="padding: 1.5rem 1.5rem 0 1.5rem;">
                <h5 class="fw-bold text-white m-0" id="modalPermissionLabel">Tambah Hak Akses</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Input Hidden ID untuk Operasi Update -->
                <input type="hidden" name="id" id="perm_id">
                
                <!-- Input Nama Modul -->
                <div class="mb-3">
                    <label for="perm_module_name" class="form-label small text-white-50 fw-medium">Nama Modul (Module Name)</label>
                    <input type="text" name="module_name" id="perm_module_name" class="form-control rounded-3 text-white" style="background: rgba(2, 6, 23, 0.4); border: 1px solid rgba(148, 163, 184, 0.2); box-shadow: none;" placeholder="Contoh: Products, Users, Orders" required>
                </div>
                
                <!-- Input Nama Hak Akses -->
                <div class="mb-2">
                    <label for="perm_permission_name" class="form-label small text-white-50 fw-medium">Nama Hak Akses (Permission Name)</label>
                    <input type="text" name="permission_name" id="perm_permission_name" class="form-control rounded-3 text-white" style="background: rgba(2, 6, 23, 0.4); border: 1px solid rgba(148, 163, 184, 0.2); box-shadow: none;" placeholder="Contoh: create_product, view_dashboard" required>
                </div>
            </div>
            
            <div class="modal-footer border-0 pt-0 d-flex gap-2 justify-content-end" style="padding: 0 1.5rem 1.5rem 1.5rem;">
                <button type="button" class="btn btn-sm btn-secondary rounded-3 px-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
                <button type="submit" name="create" id="btnSubmitPermission" class="btn btn-sm btn-success rounded-3 px-3 py-2 fw-medium">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. MODAL KONFIRMASI HAPUS PERMISSION -->
<div class="modal fade" id="modalDeletePermission" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px !important;">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(10px); border: 1px solid rgba(239, 68, 68, 0.25); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-body text-center p-4">
                <div class="text-danger mb-3">
                    <i class="bi bi-exclamation-triangle-fill" style="font-size: 3rem; filter: drop-shadow(0 0 10px rgba(239, 68, 68, 0.3));"></i>
                </div>
                <h5 class="fw-bold text-white mb-2">Hapus Hak Akses?</h5>
                <p class="text-white-50 small mb-4">Tindakan ini akan menghapus data permission <span id="delete_permission_title_display" class="text-white fw-semibold"></span> secara permanen. Data yang dihapus tidak dapat dikembalikan.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-sm btn-secondary rounded-3 px-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
                    <a id="btn_confirm_delete_permission" href="#" class="btn btn-sm btn-danger rounded-3 px-3 py-2 fw-medium">Ya, Hapus</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Variabel global untuk menampung URL hapus data permission
    let deletePermissionUrlTarget = '';

    document.addEventListener('DOMContentLoaded', function() {
        // PERBAIKAN MUTLAK: Membersihkan sisa ekor query transaksi dari URL agar alert tidak kembali muncul saat di-refresh manual (F5)
        if (window.history.replaceState && window.location.search !== '') {
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
        }

        // ==========================================
        // LOGIKA SELEKTOR GAYA DRAG SCROLL
        // ==========================================
        // PERBAIKAN: Menyelaraskan selector ID ke 'dragScrollStockContainer' sesuai layout HTML main Anda
        const permSlider = document.getElementById('dragScrollStockContainer');
        if (permSlider) {
            let isDown = false;
            let startX;
            let scrollLeft;

            permSlider.addEventListener('mousedown', (e) => {
                if (e.target.closest('button') || e.target.closest('a')) return;
                isDown = true;
                permSlider.style.cursor = 'grabbing';
                startX = e.pageX - permSlider.offsetLeft;
                scrollLeft = permSlider.scrollLeft;
            });

            permSlider.addEventListener('mouseleave', () => {
                isDown = false;
                permSlider.style.cursor = 'grab';
            });

            permSlider.addEventListener('mouseup', () => {
                isDown = false;
                permSlider.style.cursor = 'grab';
            });

            permSlider.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - permSlider.offsetLeft;
                const walk = (x - startX) * 2; // Pengali 2 mempercepat respons pergeseran mouse
                permSlider.scrollLeft = scrollLeft - walk;
            });
        }

        // ==========================================
        // LOGIKA EKSEKUSI TOMBOL HAPUS DI MODAL
        // ==========================================
        const btnConfirmDelete = document.getElementById('btn_confirm_delete_permission');
        if (btnConfirmDelete) {
            btnConfirmDelete.addEventListener('click', function(e) {
                if (deletePermissionUrlTarget) {
                    e.preventDefault();
                    window.location.href = deletePermissionUrlTarget;
                }
            });
        }
    });

    // ==========================================
    // LOGIKA FORM MODAL TAMBAH & EDIT PERMISSION
    // ==========================================
    function openTambahPermission() {
        const formPerm = document.getElementById('formPermission');
        if (formPerm) formPerm.reset();
        
        if (document.getElementById('modalPermissionLabel')) {
            document.getElementById('modalPermissionLabel').innerText = 'Tambah Hak Akses';
        }
        if (document.getElementById('perm_id')) document.getElementById('perm_id').value = '';
        if (document.getElementById('perm_module_name')) document.getElementById('perm_module_name').value = '';
        if (document.getElementById('perm_permission_name')) document.getElementById('perm_permission_name').value = '';
        
        const btnSubmit = document.getElementById('btnSubmitPermission');
        if (btnSubmit) {
            btnSubmit.setAttribute('name', 'create');
            btnSubmit.className = "btn btn-sm btn-success rounded-3 px-3 py-2 fw-medium";
            btnSubmit.innerText = "Simpan Data";
        }
    }

    function openEditPermission(data) {
        if (data) {
            if (document.getElementById('modalPermissionLabel')) {
                document.getElementById('modalPermissionLabel').innerText = 'Ubah Hak Akses';
            }
            if (document.getElementById('perm_id')) document.getElementById('perm_id').value = data.id;
            if (document.getElementById('perm_module_name')) document.getElementById('perm_module_name').value = data.module_name;
            if (document.getElementById('perm_permission_name')) document.getElementById('perm_permission_name').value = data.permission_name;
            
            const btnSubmit = document.getElementById('btnSubmitPermission');
            if (btnSubmit) {
                btnSubmit.setAttribute('name', 'update');
                btnSubmit.className = "btn btn-sm btn-warning text-dark rounded-3 px-3 py-2 fw-semibold";
                btnSubmit.innerText = "Simpan Perubahan";
            }
            
            const modalEl = document.getElementById('modalPermission');
            if (modalEl) {
                const instance = bootstrap.Modal.getOrCreateInstance(modalEl);
                instance.show();
            }
        }
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

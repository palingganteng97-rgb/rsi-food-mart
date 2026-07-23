<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$status = '';
$msg = '';
if (isset($_SESSION['crud_status'])) {
    $status = $_SESSION['crud_status'];
    $msg = $_SESSION['crud_msg'] ?? '';
    unset($_SESSION['crud_status']);
    unset($_SESSION['crud_msg']);
}
if (isset($_POST['create'])) {
    $module_name     = trim($_POST['module_name']);
    $permission_name = trim($_POST['permission_name']);
    if (empty($module_name) || empty($permission_name)) {
        $_SESSION['crud_status'] = 'error';
        $_SESSION['crud_msg'] = 'Semua kolom input wajib diisi!';
    } else {
        $checkStmt = $conn->prepare("SELECT id FROM permissions WHERE module_name = ? LIMIT 1");
        $checkStmt->bind_param("s", $module_name);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $_SESSION['crud_status'] = 'error';
            $_SESSION['crud_msg'] = 'Module Name sudah terdaftar!';
        } else {
            $stmt = $conn->prepare("INSERT INTO permissions (module_name, permission_name) VALUES (?, ?)");
            $stmt->bind_param("ss", $module_name, $permission_name);
            if ($stmt->execute()) {
                $_SESSION['crud_status'] = 'success_create';
            } else {
                $_SESSION['crud_status'] = 'error';
                $_SESSION['crud_msg'] = 'Gagal menambah data: ' . $stmt->error;
            }
            $stmt->close();
        }
        $checkStmt->close();
    }
    header("Location: permissions.php");
    exit();
}
if (isset($_POST['update'])) {
    $id              = intval($_POST['id']);
    $module_name     = trim($_POST['module_name']);
    $permission_name = trim($_POST['permission_name']);
    if (empty($module_name) || empty($permission_name)) {
        $_SESSION['crud_status'] = 'error';
        $_SESSION['crud_msg'] = 'Semua kolom input wajib diisi!';
    } else {
        $checkStmt = $conn->prepare("SELECT id FROM permissions WHERE module_name = ? AND id != ? LIMIT 1");
        $checkStmt->bind_param("si", $module_name, $id);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $_SESSION['crud_status'] = 'error';
            $_SESSION['crud_msg'] = 'Module Name sudah digunakan oleh data lain!';
        } else {
            $stmt = $conn->prepare("UPDATE permissions SET module_name = ?, permission_name = ? WHERE id = ?");
            $stmt->bind_param("ssi", $module_name, $permission_name, $id);
            if ($stmt->execute()) {
                $_SESSION['crud_status'] = 'success_update';
            } else {
                $_SESSION['crud_status'] = 'error';
                $_SESSION['crud_msg'] = 'Gagal memperbarui data: ' . $stmt->error;
            }
            $stmt->close();
        }
        $checkStmt->close();
    }
    header("Location: permissions.php");
    exit();
}
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM role_permissions WHERE permission_id = $id");
    $query = "DELETE FROM permissions WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        $resultMax = $conn->query("SELECT MAX(id) as max_id FROM permissions");
        $rowMax = $resultMax->fetch_assoc();
        $nextId = $rowMax['max_id'] ? $rowMax['max_id'] + 1 : 1;
        $conn->query("ALTER TABLE permissions AUTO_INCREMENT = $nextId");
        $_SESSION['crud_status'] = 'success_delete';
    } else {
        $_SESSION['crud_status'] = 'error';
        $_SESSION['crud_msg'] = 'Gagal menghapus data: ' . mysqli_error($conn);
    }
    header("Location: permissions.php");
    exit();
}
$permissionsData = [];
$sql = "SELECT id, module_name, permission_name, NULL as created_at, NULL as updated_at FROM permissions ORDER BY id ASC";
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
  <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
      <div><h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Manajemen Hak Akses / Permissions</h2></div>
      <div>
        <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalPermission" onclick="openTambahPermission()">
          <i class="bi bi-plus-circle"></i> Tambah Hak Akses
        </button>
      </div>
    </div>
    <?php if ($status === 'success_create' || $status === 'success_update' || $status === 'success_delete'): ?>
      <div class="alert alert-success alert-dismissible fade show border-0 rounded-3 mb-4" role="alert" style="background: rgba(34, 197, 94, 0.12) !important; color: #86efac !important; padding-right: 3rem;">
        <i class="bi bi-check-circle-fill me-2"></i> 
        <?php 
        if ($status == 'success_create') echo "Data hak akses berhasil ditambahkan!";
        elseif ($status == 'success_update') echo "Data hak akses berhasil diperbarui!";
        elseif ($status == 'success_delete') echo "Data hak akses berhasil dihapus!";
        ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close" style="position: absolute; top: 50%; right: 10px; transform: translateY(-50%); opacity: 0.8;"></button>
      </div>
    <?php endif; ?>
    <?php if ($status === 'error'): ?>
      <div class="alert alert-danger alert-dismissible fade show border-0 rounded-3 mb-4" role="alert" style="background: rgba(239, 68, 68, 0.12) !important; color: #fecaca !important; padding-right: 3rem;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close" style="position: absolute; top: 50%; right: 10px; transform: translateY(-50%); opacity: 0.8;"></button>
      </div>
    <?php endif; ?>
    <div class="table-responsive border rounded-3" style="border-color: rgba(148, 163, 184, 0.15) !important; background: transparent !important;">
      <table class="table table-hover align-middle mb-0 text-white" style="background: transparent !important; color: #e5e7eb !important;">
        <thead class="text-uppercase" style="font-size: 0.85rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
            <tr>
            <th class="py-3 px-3 text-center text-white" style="width: 80px; background: transparent !important;">id</th>
            <th class="py-3 text-white" style="background: transparent !important; width: 250px;">module_name</th>
            <th class="py-3 text-white" style="background: transparent !important;">permission_name</th>
            <th class="py-3 text-white" style="background: transparent !important; width: 180px;">created_at</th>
            <th class="py-3 text-white" style="background: transparent !important; width: 180px;">updated_at</th>
            <th class="py-3 text-center text-white" style="width: 120px; background: transparent !important;">Aksi</th>
            </tr>
        </thead>
        <tbody style="background: transparent !important;">
          <?php
          try {
              if (!empty($permissionsData)) {
                  foreach ($permissionsData as $permRow) {
                      ?>
                      <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.9rem;">
                        <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important;"><?= $permRow['id'] ?></td>
                        <td class="text-white-50 fw-medium" style="background: transparent !important;">
                          <i class="bi bi-box-seam me-2"></i><?= htmlspecialchars($permRow['module_name'] ?? '-') ?>
                        </td>
                        <td style="background: transparent !important;">
                          <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-10 rounded-2" style="font-size: 0.8rem; background: rgba(13, 110, 253, 0.12); padding: 6px 12px;">
                            <i class="bi bi-key me-1"></i><?= htmlspecialchars($permRow['permission_name'] ?? '-') ?>
                          </span>
                        </td>
                        <td class="text-white-50 small" style="background: transparent !important;"><?= $permRow['created_at'] ?? 'NULL' ?></td>
                        <td class="text-white-50 small" style="background: transparent !important;"><?= $permRow['updated_at'] ?? 'NULL' ?></td>
                        <td class="text-center" style="background: transparent !important;">
                          <div class="d-flex justify-content-center gap-1">
                            <button type="button" class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Edit Permission" onclick="openEditPermission(<?= htmlspecialchars(json_encode($permRow)) ?>)">
                              <i class="bi bi-pencil-square"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Delete Permission" onclick="deletePermissionUrlTarget = 'permissions.php?delete=<?= $permRow['id']; ?>'; document.getElementById('delete_permission_title_display').innerText = '<?= addslashes($permRow['permission_name']) ?>'; new bootstrap.Modal(document.getElementById('modalDeletePermission')).show();">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                          </div>
                        </td>
                      </tr>
                      <?php
                  }
              } else {
                  echo '<tr><td colspan="6" class="text-center py-5 border-0" style="color: #94a3b8 !important; background: transparent !important;">Belum ada data hak akses terdaftar di database.</td></tr>';
              }
          } catch (Throwable $e) {
              echo '<tr><td colspan="6" class="text-center py-4 text-danger border-0" style="background: transparent !important;">Gagal memuat data: '.$e->getMessage().'</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!--- 1. UNIFIED MODAL FORM CRUD (TAMBAH / EDIT PERMISSION) --->
<div class="modal fade" id="modalPermission" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px !important;">
        <form action="permissions.php" method="POST" id="formPermission" class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(10px); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-header border-0 pb-0" style="padding: 1.5rem 1.5rem 0 1.5rem;">
                <h5 class="fw-bold text-white m-0" id="modalPermissionLabel">Tambah Hak Akses</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="id" id="perm_id">
                <div class="mb-3">
                    <label for="perm_module_name" class="form-label small text-white-50 fw-medium">Nama Modul (Module Name)</label>
                    <input type="text" name="module_name" id="perm_module_name" class="form-control rounded-3 text-white" style="background: rgba(2, 6, 23, 0.4); border: 1px solid rgba(148, 163, 184, 0.2); box-shadow: none;" placeholder="Contoh: Products, Users, Orders" required>
                </div>
                <div class="mb-2">
                    <label for="perm_permission_name" class="form-label small text-white-50 fw-medium">Nama Hak Akses (Permission Name)</label>
                    <input type="text" name="permission_name" id="perm_permission_name" class="form-control rounded-3 text-white" style="background: rgba(2, 6, 23, 0.4); border: 1px solid rgba(148, 163, 184, 0.2); box-shadow: none;" placeholder="Contoh: create_product, view_dashboard" required>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 d-flex gap-2 justify-content-end" style="padding: 0 1.5rem 1.5rem 1.5rem;">
                <button type="button" class="btn btn-secondary rounded-3 px-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
                <button type="submit" id="btnSubmitPermission" class="btn btn-success rounded-3 px-3 py-2 fw-medium">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<!--- 2. MODAL KONFIRMASI HAPUS PERMISSION --->
<div class="modal fade" id="modalDeletePermission" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px !important;">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(10px); border: 1px solid rgba(239, 68, 68, 0.25); color: #e5e7eb; border-radius: 16px; position: relative;">
            <!-- Tombol X Close khusus di pojok kanan modal konfirmasi hapus -->
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="position: absolute; top: 15px; right: 15px; z-index: 1055;"></button>
            <div class="modal-body text-center p-4">
                <div class="text-danger mb-3">
                    <i class="bi bi-exclamation-triangle-fill" style="font-size: 3rem; filter: drop-shadow(0 0 10px rgba(239, 68, 68, 0.3));"></i>
                </div>
                <h5 class="fw-bold text-white mb-2">Hapus Hak Akses?</h5>
                <p class="text-white-50 small mb-4">Tindakan ini akan menghapus data permission <span id="delete_permission_title_display" class="text-white fw-semibold"></span> secara permanen. Data yang dihapus tidak dapat dikembalikan.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-secondary rounded-3 px-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
                    <a id="btn_confirm_delete_permission" href="#" class="btn btn-danger rounded-3 px-3 py-2 fw-medium">Ya, Hapus</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let deletePermissionUrlTarget = '';
    document.addEventListener('DOMContentLoaded', function() {
        if (window.location.search.includes('status=')) {
            setTimeout(function() {
                const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
            }, 50);
        }
        const permSlider = document.getElementById('dragScrollPermissionContainer');
        if (permSlider) {
            let isDown = false;
            let startX;
            let scrollLeft;
            permSlider.addEventListener('mousedown', (e) => {
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
                const walk = (x - startX) * 2;
                permSlider.scrollLeft = scrollLeft - walk;
            });
        }
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

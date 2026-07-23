<?php
// roles.php
include "db.php";
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include "delete_handler.php";
$crudError = '';
$crudSuccess = '';
if (isset($_GET['status'])) {
    $status = $_GET['status'];
    if ($status === 'created') {
        $crudSuccess = 'Role baru berhasil ditambahkan!';
    } elseif ($status === 'updated') {
        $crudSuccess = 'Nama role berhasil diperbarui!';
    } elseif ($status === 'deleted') {
        $crudSuccess = 'Role berhasil dihapus dari sistem!';
    } elseif ($status === 'duplicate_create') {
        $crudError = 'Nama Role sudah terdaftar!';
    } elseif ($status === 'duplicate_update') {
        $crudError = 'Nama role sudah digunakan oleh role lain!';
    } elseif ($status === 'invalid') {
        $crudError = 'Input nama role tidak valid.';
    } elseif ($status === 'delete_error' || $status === 'error') {
        $errorMsg = isset($_GET['msg']) ? urldecode($_GET['msg']) : 'Terjadi kesalahan sistem.';
        $crudError = htmlspecialchars($errorMsg);
    }
}
// ==========================================
// 1. PROSES CRUD: TAMBAH ROLE BARU (CREATE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create'])) {
    $name = trim($_POST['name'] ?? '');
    if (!empty($name)) {
        try {
            $check = $conn->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
            $check->bind_param("s", $name);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                header("Location: roles.php?status=duplicate_create");
                exit;
            } else {
                $stmt = $conn->prepare("INSERT INTO roles (name, created_at) VALUES (?, NOW())");
                $stmt->bind_param("s", $name);
                if ($stmt->execute()) {
                    header("Location: roles.php?status=created");
                    exit;
                } else {
                    header("Location: roles.php?status=error&msg=" . urlencode("Gagal menyimpan role baru ke database."));
                    exit;
                }
                $stmt->close();
            }
            $check->close();
        } catch (Throwable $e) {
            header("Location: roles.php?status=error&msg=" . urlencode("Kesalahan sistem: " . $e->getMessage()));
            exit;
        }
    } else {
        header("Location: roles.php?status=error&msg=" . urlencode("Nama Role wajib diisi!"));
        exit;
    }
}
// ==========================================
// 2. PROSES CRUD: SIMPAN PERUBAHAN ROLE (UPDATE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update'])) {
    $targetId = (int)($_POST['edit_id'] ?? 0);
    $newName = trim($_POST['update_name'] ?? '');
    if ($targetId > 0 && !empty($newName)) {
        try {
            $check = $conn->prepare("SELECT id FROM roles WHERE name = ? AND id != ? LIMIT 1");
            $check->bind_param("si", $newName, $targetId);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                header("Location: roles.php?status=duplicate_update");
                exit;
            } else {
                $updateStmt = $conn->prepare("UPDATE roles SET name = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->bind_param("si", $newName, $targetId);
                if ($updateStmt->execute()) {
                    header("Location: roles.php?status=updated");
                    exit;
                } else {
                    header("Location: roles.php?status=error&msg=" . urlencode("Gagal memperbarui data role di database."));
                    exit;
                }
                $updateStmt->close();
            }
            $check->close();
        } catch (Throwable $e) {
            header("Location: roles.php?status=error&msg=" . urlencode("Kesalahan sistem saat mengubah data: " . $e->getMessage()));
            exit;
        }
    } else {
        header("Location: roles.php?status=invalid");
        exit;
    }
}
// ==========================================
// 3. PROSES CRUD: HAPUS ROLE (DELETE)
// ==========================================
if (isset($_GET['action_delete'])) {
    $deleteId = (int)$_GET['action_delete'];
    error_log("[DELETE_ROLE] action_delete ID: " . $deleteId);
    if ($deleteId > 0) {
        try {
            $findPrev = $conn->prepare("SELECT id FROM roles WHERE id < ? ORDER BY id DESC LIMIT 1");
            $findPrev->bind_param("i", $deleteId);
            $findPrev->execute();
            $resPrev = $findPrev->get_result();
            if ($resPrev->num_rows > 0) {
                $targetRole = $resPrev->fetch_assoc()['id'];
            } else {
                $findNext = $conn->prepare("SELECT id FROM roles WHERE id > ? ORDER BY id ASC LIMIT 1");
                $findNext->bind_param("i", $deleteId);
                $findNext->execute();
                $resNext = $findNext->get_result();
                $targetRole = ($resNext->num_rows > 0) ? $resNext->fetch_assoc()['id'] : 0;
                $findNext->close();
            }
            $findPrev->close();
            if ($targetRole > 0) {
                $updateUser = $conn->prepare("UPDATE users SET role_id = ? WHERE role_id = ?");
                $updateUser->bind_param("ii", $targetRole, $deleteId);
                $updateUser->execute();
                $updateUser->close();
            }
            $deletePerms = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $deletePerms->bind_param("i", $deleteId);
            $deletePerms->execute();
            $deletePerms->close();
            $deleteStmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
            $deleteStmt->bind_param("i", $deleteId);
            if ($deleteStmt->execute()) {
                error_log("[DELETE_ROLE] affected_rows: " . $deleteStmt->affected_rows);
                $resultMax = $conn->query("SELECT MAX(id) as max_id FROM roles");
                $rowMax = $resultMax->fetch_assoc();
                $nextId = $rowMax['max_id'] ? $rowMax['max_id'] + 1 : 1;
                $conn->query("ALTER TABLE roles AUTO_INCREMENT = $nextId");
                header("Location: roles.php?status=deleted");
                exit;
            } else {
                $sqlError = $deleteStmt->error;
                error_log("[DELETE_ROLE] SQL Error: " . $sqlError);
                header("Location: roles.php?status=delete_error&msg=" . urlencode("Gagal menghapus data dari database. SQL Error: " . $sqlError));
                exit;
            }
            $deleteStmt->close();
        } catch (Throwable $e) {
            error_log("[DELETE_ROLE] Exception: " . $e->getMessage());
            header("Location: roles.php?status=delete_error&msg=" . urlencode("Kesalahan sistem saat menghapus data: " . $e->getMessage()));
            exit;
        }
    } else {
        header("Location: roles.php?status=delete_error&msg=" . urlencode("ID role tidak valid."));
        exit;
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
  <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
    <!-- HEADER TABEL & TOMBOL TAMBAH ROLE -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
      <div><h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Manajemen Data Roles</h2></div>
      <div>
        <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" onclick="openModalRole('create')">
          <i class="bi bi-shield-plus"></i> Tambah Role
        </button>
      </div>
    </div>
    <!-- NOTIFIKASI INFORMASI ALERT STATUS OPERASI CRUD -->
    <?php if (!empty($crudSuccess)): ?>
      <div class="alert alert-success alert-dismissible fade show border-0 rounded-3 mb-4" role="alert" style="background: rgba(34, 197, 94, 0.12) !important; color: #86efac !important; padding-right: 3rem;">
        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($crudSuccess) ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close" style="position: absolute; top: 50%; right: 10px; transform: translateY(-50%); opacity: 0.8;"></button>
      </div>
    <?php endif; ?>
    <?php if (!empty($crudError)): ?>
      <div class="alert alert-danger alert-dismissible fade show border-0 rounded-3 mb-4" role="alert" style="background: rgba(239, 68, 68, 0.12) !important; color: #fecaca !important; padding-right: 3rem;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($crudError) ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close" style="position: absolute; top: 50%; right: 10px; transform: translateY(-50%); opacity: 0.8;"></button>
      </div>
    <?php endif; ?>
    <!-- TABEL LIST DATA ROLES (4 KOLOM LENGKAP SESUAI DATABASE) -->
    <div class="table-responsive border rounded-3" style="border-color: rgba(148, 163, 184, 0.15) !important; background: transparent !important;">
      <table class="table table-hover align-middle mb-0 text-white" style="background: transparent !important; color: #e5e7eb !important;">
        <thead class="text-uppercase" style="font-size: 0.85rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
            <tr>
            <th class="py-3 px-3 text-center text-white" style="width: 80px; background: transparent !important;">id</th>
            <th class="py-3 text-white" style="background: transparent !important;">name</th>
            <th class="py-3 text-white" style="background: transparent !important;">created_at</th>
            <th class="py-3 text-white" style="background: transparent !important;">updated_at</th>
            <th class="py-3 text-center text-white" style="width: 120px; background: transparent !important;">Aksi</th>
            </tr>
        </thead>
        <tbody style="background: transparent !important;">
          <?php
          try {
              $queryRoles = "SELECT id, name, created_at, updated_at FROM roles ORDER BY id ASC";
              $resultRoles = $conn->query($queryRoles);
              if ($resultRoles && $resultRoles->num_rows > 0) {
                  while ($roleRow = $resultRoles->fetch_assoc()) {
                      ?>
                      <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.9rem;">
                        <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important;"><?= $roleRow['id'] ?></td>
                        <td class="fw-semibold text-white" style="background: transparent !important;"><?= htmlspecialchars($roleRow['name'] ?? '-') ?></td>
                        <td class="text-white-50 small" style="background: transparent !important;"><?= $roleRow['created_at'] ?? 'NULL' ?></td>
                        <td class="text-white-50 small" style="background: transparent !important;"><?= $roleRow['updated_at'] ?? 'NULL' ?></td>
                        <td class="text-center" style="background: transparent !important;">
                          <div class="d-flex justify-content-center gap-1">
                            <button class="btn btn-sm btn-outline-success border-0 rounded-2" title="Edit Role" onclick="openModalRole('update', <?= htmlspecialchars(json_encode($roleRow)) ?>)">
                              <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="triggerDeleteRole('roles.php?action_delete=<?= $roleRow['id'] ?>', '<?= addslashes($roleRow['name']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                          </div>
                        </td>
                      </tr>
                      <?php
                  }
              } else {
                  echo '<tr><td colspan="5" class="text-center py-5 border-0" style="color: #94a3b8 !important; background: transparent !important;">Belum ada data role terdaftar di database.</td></tr>';
              }
          } catch (Throwable $e) {
              echo '<tr><td colspan="5" class="text-center py-4 text-danger border-0" style="background: transparent !important;">Gagal memuat data: '.$e->getMessage().'</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- GABUNGAN MODAL CRUD (SATU FORM GANDA: TAMBAH & EDIT) -->
<div class="modal fade" id="modalCrudRole" tabindex="-1" aria-labelledby="modalRoleLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.92) !important; backdrop-filter: blur(10px); border: 1px solid rgba(148, 163, 184, 0.2); color: #e5e7eb;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
                <h5 class="modal-title fw-bold text-white" id="modalRoleLabel">Form Role</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="roles.php" method="POST" id="formRole">
                <input type="hidden" name="edit_id" id="role_id">
                <input type="hidden" name="action_create" id="trigger_create" value="1" disabled>
                <input type="hidden" name="action_update" id="trigger_update" value="1" disabled>
                <div class="modal-body" style="padding: 25px;">
                    <div class="mb-3">
                        <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;" id="labelRoleInput">Nama Role</label>
                        <input type="text" class="form-control" id="role_name" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" placeholder="Masukkan nama role..." required>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.15);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="btnSubmitRole">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus Role -->
<div class="modal fade" id="modalDeleteRole" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(10px); border: 1px solid rgba(239, 68, 68, 0.25); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-body text-center p-4">
                <div class="text-danger mb-3">
                    <i class="bi bi-exclamation-triangle-fill" style="font-size: 3rem; filter: drop-shadow(0 0 10px rgba(239, 68, 68, 0.3));"></i>
                </div>
                <h5 class="fw-bold text-white mb-2">Hapus Data Role?</h5>
                <p class="text-white-50 small mb-4">Tindakan ini akan menghapus data role <span id="delete_role_name" class="text-white fw-semibold"></span> secara permanen. Data yang dihapus tidak dapat dikembalikan.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-sm btn-secondary rounded-3 px-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
                    <a id="btn_confirm_delete_role" href="#" class="btn btn-sm btn-danger rounded-3 px-3 py-2 fw-medium">Ya, Hapus</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let deleteRoleUrlTarget = '';
    document.addEventListener('DOMContentLoaded', function() {
        // Otomatis bersihkan parameter status dari URL browser agar tidak stuck saat di-refresh
        if (window.location.search.includes('status=')) {
            setTimeout(function() {
                const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
            }, 50);
        }
        const btnConfirmDeleteRole = document.getElementById('btn_confirm_delete_role');
        if (btnConfirmDeleteRole) {
            btnConfirmDeleteRole.addEventListener('click', function(e) {
                if (deleteRoleUrlTarget) { e.preventDefault(); window.location.href = deleteRoleUrlTarget; }
            });
        }
    });

    function triggerDeleteRole(url, roleName) {
        const namePlaceholder = document.getElementById('delete_role_name');
        if (namePlaceholder) { namePlaceholder.innerText = roleName; }
        const btnConfirm = document.getElementById('btn_confirm_delete_role');
        if (btnConfirm) { btnConfirm.setAttribute('href', url); }
        const modalElement = document.getElementById('modalDeleteRole');
        if (modalElement) {
            const existingInstance = bootstrap.Modal.getInstance(modalElement);
            if (existingInstance) { existingInstance.dispose(); }
            const newModalInstance = new bootstrap.Modal(modalElement);
            newModalInstance.show();
        }
    }

    function openModalRole(mode, data = null) {
        const modalLabel = document.getElementById('modalRoleLabel');
        const labelInput = document.getElementById('labelRoleInput');
        const inputName = document.getElementById('role_name');
        const inputId = document.getElementById('role_id');
        const btnSubmit = document.getElementById('btnSubmitRole');
        const triggerCreate = document.getElementById('trigger_create');
        const triggerUpdate = document.getElementById('trigger_update');
        if (mode === 'create') {
            modalLabel.innerHTML = '<i class="bi bi-shield-plus me-2 text-success"></i>Tambah Role Baru';
            labelInput.innerText = 'Nama Role';
            inputName.name = 'name';
            inputName.value = '';
            inputId.value = '';
            btnSubmit.innerText = 'Simpan Data';
            triggerCreate.disabled = false;
            triggerUpdate.disabled = true;
        } else if (mode === 'update' && data) {
            modalLabel.innerHTML = '<i class="bi bi-pencil-square me-2 text-success"></i>Ubah Data Role';
            labelInput.innerText = 'Nama Role Baru';
            inputName.name = 'update_name';
            inputName.value = data.name;
            inputId.value = data.id;
            btnSubmit.innerText = 'Perbarui Data';
            triggerCreate.disabled = true;
            triggerUpdate.disabled = false;
        }
        const roleModalElement = document.getElementById('modalCrudRole');
        if (roleModalElement) {
            const existingInstance = bootstrap.Modal.getInstance(roleModalElement);
            if (existingInstance) { existingInstance.dispose(); }
            const roleModalInstance = new bootstrap.Modal(roleModalElement);
            roleModalInstance.show();
        }
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// roles.php (Full Kode Logika Atas Menggunakan Metode Pengalihan PRG)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';
include 'notification_helper.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$crudError = '';
$crudSuccess = '';

// Mengambil pesan status dari session hasil pengalihan (PRG) agar tidak duplikat saat refresh
if (isset($_SESSION['roles_success'])) {
    $crudSuccess = $_SESSION['roles_success'];
    unset($_SESSION['roles_success']);
}
if (isset($_SESSION['roles_error'])) {
    $crudError = $_SESSION['roles_error'];
    unset($_SESSION['roles_error']);
}

// =========================================================================
// 1. PROSES CRUD: TAMBAH ROLE BARU (CREATE)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create'])) {
    $name = trim($_POST['name'] ?? '');

    if (!empty($name)) {
        try {
            $check = $conn->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
            $check->bind_param("s", $name);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $_SESSION['roles_error'] = "Nama Role sudah terdaftar!";
            } else {
                $stmt = $conn->prepare("INSERT INTO roles (name, created_at) VALUES (?, NOW())");
                $stmt->bind_param("s", $name);
                if ($stmt->execute()) {
                    $_SESSION['roles_success'] = "Role baru berhasil ditambahkan!";
                    // SINKRONISASI HELPER: Menambahkan parameter kelima berupa tautan tujuan
                    createNotification('admin', (int)$_SESSION['user_id'], 'Role Baru', "Role $name berhasil ditambahkan", 'roles.php');
                } else {
                    $_SESSION['roles_error'] = "Gagal menyimpan role baru ke database.";
                }
                $stmt->close();
            }
            $check->close();
        } catch (Throwable $e) {
            $_SESSION['roles_error'] = "Kesalahan sistem: " . $e->getMessage();
        }
    } else {
        $_SESSION['roles_error'] = "Nama Role wajib diisi!";
    }
    header("Location: roles.php");
    exit;
}

// =========================================================================
// 2. PROSES CRUD: SIMPAN PERUBAHAN ROLE (UPDATE)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update'])) {
    $targetId = (int)($_POST['edit_id'] ?? 0);
    $newName = trim($_POST['update_name'] ?? '');

    if ($targetId > 0 && !empty($newName)) {
        try {
            $check = $conn->prepare("SELECT id FROM roles WHERE name = ? AND id != ? LIMIT 1");
            $check->bind_param("si", $newName, $targetId);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $_SESSION['roles_error'] = "Nama Role tersebut sudah digunakan oleh data lain!";
            } else {
                $updateStmt = $conn->prepare("UPDATE roles SET name = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->bind_param("si", $newName, $targetId);
                if ($updateStmt->execute()) {
                    $_SESSION['roles_success'] = "Nama role berhasil diperbarui!";
                    // SINKRONISASI HELPER: Menambahkan parameter kelima berupa tautan tujuan
                    createNotification('admin', (int)$_SESSION['user_id'], 'Role Diperbarui', "Role $newName berhasil diperbarui", 'roles.php');
                } else {
                    $_SESSION['roles_error'] = "Gagal memperbarui data role di database.";
                }
                $updateStmt->close();
            }
            $check->close();
        } catch (Throwable $e) {
            $_SESSION['roles_error'] = "Kesalahan sistem saat mengubah data: " . $e->getMessage();
        }
    } else {
        $_SESSION['roles_error'] = "Input nama role tidak valid.";
    }
    header("Location: roles.php");
    exit;
}

// =========================================================================
// 3. PROSES CRUD: HAPUS ROLE (DELETE)
// =========================================================================
if (isset($_GET['action_delete'])) {
    $deleteId = (int)$_GET['action_delete'];

    if ($deleteId > 0) {
        try {
            $checkUsed = $conn->prepare("SELECT id FROM users WHERE role_id = ? LIMIT 1");
            $checkUsed->bind_param("i", $deleteId);
            $checkUsed->execute();
            if ($checkUsed->get_result()->num_rows > 0) {
                $_SESSION['roles_error'] = "Role tidak bisa dihapus karena masih digunakan oleh beberapa pengguna!";
            } else {
                // Mengambil nama role terlebih dahulu untuk dicatat di isi notifikasi sebelum dihapus
                $roleQuery = $conn->prepare("SELECT name FROM roles WHERE id = ? LIMIT 1");
                $roleQuery->bind_param("i", $deleteId);
                $roleQuery->execute();
                $roleResult = $roleQuery->get_result()->fetch_assoc();
                $savedRoleName = $roleResult ? $roleResult['name'] : "ID " . $deleteId;
                $roleQuery->close();

                $deleteStmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
                $deleteStmt->bind_param("i", $deleteId);
                if ($deleteStmt->execute()) {
                    $_SESSION['roles_success'] = "Role berhasil dihapus dari sistem!";
                    // SINKRONISASI HELPER: Mengganti teks log agar memuat nama asli role dan menambahkan parameter tautan
                    createNotification('admin', (int)$_SESSION['user_id'], 'Role Dihapus', "Role '$savedRoleName' berhasil dihapus", 'roles.php');
                } else {
                    $_SESSION['roles_error'] = "Gagal menghapus data dari database.";
                }
                $deleteStmt->close();
            }
            $checkUsed->close();
        } catch (Throwable $e) {
            $_SESSION['roles_error'] = "Kesalahan sistem saat menghapus data: " . $e->getMessage();
        }
    }
    header("Location: roles.php");
    exit;
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
  <div class="container-fluid rounded-4 p-4 text-white" style="background: transparent !important; border: none !important; box-shadow: none !important;">
    
    <!-- HEADER TABEL & TOMBOL TAMBAH ROLE -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
      <div>
        <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Manajemen Data Roles</h2>
      </div>
      <div>
        <!-- FIX: Mengubah target modal ke #modalRole dan fungsi ke openTambahRole() -->
        <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalRole" onclick="openTambahRole()">
          <i class="bi bi-shield-plus"></i> Tambah Role
        </button>
      </div>
    </div>

    <!-- NOTIFIKASI INFORMASI ALERT STATUS OPERASI CRUD -->
    <?php if (!empty($crudSuccess)): ?>
      <div class="alert alert-success border-0 rounded-3 mb-4 alert-dismissible fade show" role="alert" style="background: rgba(34, 197, 94, 0.12) !important; color: #86efac !important;">
        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($crudSuccess) ?>
        <!-- PERBAIKAN: Menambahkan tombol close interaktif -->
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close" style="font-size: 0.75rem; box-shadow: none;"></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($crudError)): ?>
      <div class="alert alert-danger border-0 rounded-3 mb-4 alert-dismissible fade show" role="alert" style="background: rgba(239, 68, 68, 0.12) !important; color: #fecaca !important;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($crudError) ?>
        <!-- PERBAIKAN: Menambahkan tombol close interaktif -->
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close" style="font-size: 0.75rem; box-shadow: none;"></button>
      </div>
    <?php endif; ?>

    <!-- TABEL LIST DATA ROLES (Mendukung drag-to-scroll & Kustom Hover) -->
    <div class="table-responsive" id="dragScrollStockContainer" style="overflow-x: auto; cursor: grab; scrollbar-width: none; -ms-overflow-style: none;">
      <style>
          #dragScrollStockContainer::-webkit-scrollbar { display: none; }
          .table-custom-hover tbody tr:hover { background: rgba(148, 163, 184, 0.15) !important; transition: background 0.15s ease-in-out; }
          .table-force-white th, .table-force-white td { color: #ffffff !important; }
      </style>
      <table class="table align-middle mb-0 table-force-white table-custom-hover" style="--bs-table-bg: transparent; border-collapse: separate; border-spacing: 0 8px; min-width: 900px; user-select: none;">
        <thead style="font-size: 0.85rem; background: rgba(30, 41, 59, 0.65) !important;">
            <tr>
              <th class="py-3 px-4 text-center" style="width: 80px; border-radius: 10px 0 0 10px; border-bottom: 1px solid rgba(148, 163, 184, 0.2);">id</th>
              <th class="py-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.2);">name</th>
              <th class="py-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.2);">created_at</th>
              <th class="py-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.2);">updated_at</th>
              <th class="py-3 text-center" style="width: 120px; border-radius: 0 10px 10px 0; border-bottom: 1px solid rgba(148, 163, 184, 0.2);">Aksi</th>
            </tr>
        </thead>
        <tbody>
          <?php
          try {
              $queryRoles = "SELECT id, name, created_at, updated_at FROM roles ORDER BY id ASC";
              $resultRoles = $conn->query($queryRoles);
              
              if ($resultRoles && $resultRoles->num_rows > 0) {
                  while ($roleRow = $resultRoles->fetch_assoc()) {
                      ?>
                      <tr style="background: rgba(30, 41, 59, 0.55); border: 1px solid rgba(148, 163, 184, 0.15);">
                        <!-- #1 id -->
                        <td class="text-center fw-semibold" style="border-radius: 10px 0 0 10px;"><?= $roleRow['id'] ?></td>
                        
                        <!-- #2 name -->
                        <td class="fw-bold"><?= htmlspecialchars($roleRow['name'] ?? '-') ?></td>
                        
                        <!-- #3 created_at -->
                        <td class="fw-medium"><?= $roleRow['created_at'] ?? 'NULL' ?></td>
                        
                        <!-- #4 updated_at -->
                        <td class="fw-medium"><?= $roleRow['updated_at'] ?? 'NULL' ?></td>
                        
                        <!-- TOMBOL AKSI CRUD -->
                        <td class="text-center" style="border-radius: 0 10px 10px 0;">
                          <div class="d-inline-flex gap-2">
                            <!-- FIX: Memanggil openEditRole() untuk melempar objek data secara instan ke form modal -->
                            <button type="button" class="btn btn-sm btn-outline-warning rounded-2" title="Edit Role" onclick="openEditRole(<?= htmlspecialchars(json_encode($roleRow)) ?>)">
                              <i class="bi bi-pencil-square"></i>
                            </button>
                            <!-- FIX: Memanggil triggerDeleteRole() agar sinkron dengan modal konfirmasi hapus kustom -->
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-2" title="Hapus Role" onclick="triggerDeleteRole('roles.php?action_delete=<?= $roleRow['id']; ?>', '<?= addslashes($roleRow['name']); ?>')">
                              <i class="bi bi-trash3-fill"></i>
                            </button>
                          </div>
                        </td>
                      </tr>
                      <?php
                  }
              } else {
                  echo '<tr><td colspan="5" class="text-center py-5 fw-bold" style="background: rgba(30, 41, 59, 0.2); border-radius: 10px;">Belum ada data role terdaftar di database.</td></tr>';
              }
          } catch (Throwable $e) {
              echo '<tr><td colspan="5" class="text-center py-4 text-danger fw-bold" style="background: rgba(30, 41, 59, 0.2); border-radius: 10px;">Gagal memuat data: '.$e->getMessage().'</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>

  </div>
</main>

<!-- MODAL FORM CRUD INTEGRASI (TAMBAH / UBAH DATA ROLE) -->
<div class="modal fade" id="modalRole" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="roles.php" method="POST" id="formRole" class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(10px); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb; border-radius: 16px;">
            
            <div class="modal-header border-0 pb-0" style="padding: 1.5rem 1.5rem 0 1.5rem;">
                <h5 class="fw-bold text-white m-0" id="modalRoleLabel">Tambah Role Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Input Hidden ID: Wajib bernama name="edit_id" sesuai backend PHP -->
                <input type="hidden" name="edit_id" id="role_id">
                
                <div class="mb-3">
                    <label class="form-label small text-white-50 fw-medium" id="inputRoleLabel">Nama Kelompok / Role Akses</label>
                    <!-- Input Text: Wajib menggunakan id="role_name_input" agar sinkron dengan JavaScript -->
                    <input type="text" id="role_name_input" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none;" required placeholder="Contoh: Petugas Gudang, Kasir">
                </div>
            </div>

            <div class="modal-footer border-0 pt-0" style="padding: 0 1.5rem 1.5rem 1.5rem;">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 px-4 py-2" data-bs-dismiss="modal">Batal</button>
                <button type="submit" id="btnSubmitRole" class="btn btn-sm btn-success rounded-3 px-4 py-2 fw-medium">Simpan Data</button>
            </div>
        </form>
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
        // PERBAIKAN MUTLAK: Membersihkan sisa ekor query secara total tepat setelah DOM dimuat 
        // Langkah ini memaksa URL bersih murni sehingga alert tidak akan muncul lagi saat F5 / Refresh manual
        if (window.history.replaceState && window.location.search !== '') {
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
        }

        // Handler konfirmasi hapus data
        const btnConfirmDeleteRole = document.getElementById('btn_confirm_delete_role');
        if (btnConfirmDeleteRole) {
            btnConfirmDeleteRole.addEventListener('click', function(e) {
                if (deleteRoleUrlTarget) {
                    e.preventDefault();
                    window.location.href = deleteRoleUrlTarget;
                }
            });
        }

        // Fungsionalitas Drag-to-Scroll
        const roleSlider = document.getElementById('dragScrollStockContainer');
        if (roleSlider) {
            let isDown = false;
            let startX, scrollLeft;
            
            roleSlider.addEventListener('mousedown', (e) => {
                if (e.target.closest('button') || e.target.closest('a')) return;
                isDown = true; 
                roleSlider.style.cursor = 'grabbing';
                startX = e.pageX - roleSlider.offsetLeft; 
                scrollLeft = roleSlider.scrollLeft;
            });
            roleSlider.addEventListener('mouseleave', () => { isDown = false; roleSlider.style.cursor = 'grab'; });
            roleSlider.addEventListener('mouseup', () => { isDown = false; roleSlider.style.cursor = 'grab'; });
            roleSlider.addEventListener('mousemove', (e) => {
                if (!isDown) return; 
                e.preventDefault();
                const x = e.pageX - roleSlider.offsetLeft;
                roleSlider.scrollLeft = scrollLeft - ((x - startX) * 2);
            });
        }
    });

    // Fungsi Pembuka Formulir Tambah Data
    function openTambahRole() {
        const form = document.getElementById('formRole');
        if (form) form.reset();

        document.getElementById('modalRoleLabel').innerHTML = '<i class="bi bi-shield-plus text-success me-2"></i> Tambah Role Baru';
        document.getElementById('inputRoleLabel').innerText = 'Nama Kelompok / Role Akses';
        document.getElementById('role_id').value = '';
        
        const inputField = document.getElementById('role_name_input');
        if (inputField) {
            inputField.setAttribute('name', 'name');
            inputField.value = '';
            inputField.placeholder = "Contoh: Petugas Gudang, Kasir";
        }

        const btnSubmit = document.getElementById('btnSubmitRole');
        if (btnSubmit) {
            btnSubmit.setAttribute('name', 'action_create');
            btnSubmit.className = "btn btn-sm btn-success rounded-3 px-4 py-2 fw-medium";
            btnSubmit.innerText = "Simpan Data";
        }
    }

    // Fungsi Pembuka Formulir Ubah Data
    function openEditRole(roleRow) {
        if (roleRow) {
            document.getElementById('modalRoleLabel').innerHTML = '<i class="bi bi-pencil-square text-warning me-2"></i> Ubah Nama Role';
            document.getElementById('inputRoleLabel').innerText = 'Nama Kelompok / Role Akses Baru';
            
            document.getElementById('role_id').value = roleRow.id;
            
            const inputField = document.getElementById('role_name_input');
            if (inputField) {
                inputField.setAttribute('name', 'update_name');
                inputField.value = roleRow.name || ''; 
                inputField.placeholder = "Masukkan nama baru...";
            }

            const btnSubmit = document.getElementById('btnSubmitRole');
            if (btnSubmit) {
                btnSubmit.setAttribute('name', 'action_update');
                btnSubmit.className = "btn btn-sm btn-warning text-dark rounded-3 px-4 py-2 fw-bold";
                btnSubmit.innerText = "Simpan Perubahan";
            }

            const modalEl = document.getElementById('modalRole');
            if (modalEl) {
                const instance = bootstrap.Modal.getOrCreateInstance(modalEl);
                instance.show();
            }
        }
    }

    function triggerDeleteRole(url, roleName) {
        const namePlaceholder = document.getElementById('delete_role_name');
        if (namePlaceholder) { namePlaceholder.innerText = roleName; }
        deleteRoleUrlTarget = url;
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
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
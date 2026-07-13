<?php
// roles.php
include "db.php"; // Pastikan di dalam db.php sudah dipanggil session_start()

// Proteksi Halaman: Jika sesi user_id kosong, tendang kembali ke login.php
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Menghubungkan logika modular penghapusan data dan berkas fisik
include "delete_handler.php";

$crudError = '';
$crudSuccess = '';

// ==========================================
// 1. PROSES CRUD: TAMBAH ROLE BARU (CREATE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create'])) {
    $name = trim($_POST['name'] ?? '');

    if (!empty($name)) {
        try {
            // Cek apakah nama role sudah ada agar tidak duplikat
            $check = $conn->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
            $check->bind_param("s", $name);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $crudError = "Nama Role sudah terdaftar!";
            } else {
                // Kolom created_at diisi otomatis menggunakan NOW()
                $stmt = $conn->prepare("INSERT INTO roles (name, created_at) VALUES (?, NOW())");
                $stmt->bind_param("s", $name);
                
                if ($stmt->execute()) {
                    $crudSuccess = "Role baru berhasil ditambahkan!";
                } else {
                    $crudError = "Gagal menyimpan role baru ke database.";
                }
                $stmt->close();
            }
            $check->close();
        } catch (Throwable $e) {
            $crudError = "Kesalahan sistem: " . $e->getMessage();
        }
    } else {
        $crudError = "Nama Role wajib diisi!";
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
            // Update data role dan isi kolom updated_at dengan waktu saat ini (NOW())
            $updateStmt = $conn->prepare("UPDATE roles SET name = ?, updated_at = NOW() WHERE id = ?");
            $updateStmt->bind_param("si", $newName, $targetId);
            
            if ($updateStmt->execute()) {
                $crudSuccess = "Nama role berhasil diperbarui!";
            } else {
                $crudError = "Gagal memperbarui data role di database.";
            }
            $updateStmt->close();
        } catch (Throwable $e) {
            $crudError = "Kesalahan sistem saat mengubah data: " . $e->getMessage();
        }
    } else {
        $crudError = "Input nama role tidak valid.";
    }
}

// ==========================================
// 3. PROSES CRUD: HAPUS ROLE (DELETE)
// ==========================================
if (isset($_GET['action_delete'])) {
    $deleteId = (int)$_GET['action_delete'];

    if ($deleteId > 0) {
        try {
            // Keamanan tambahan: Cek apakah role ini sedang dipakai oleh user di tabel users
            $checkUsed = $conn->prepare("SELECT id FROM users WHERE role_id = ? LIMIT 1");
            $checkUsed->bind_param("i", $deleteId);
            $checkUsed->execute();
            if ($checkUsed->get_result()->num_rows > 0) {
                $crudError = "Role tidak bisa dihapus karena masih digunakan oleh beberapa pengguna!";
            } else {
                $deleteStmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
                $deleteStmt->bind_param("i", $deleteId);
                
                if ($deleteStmt->execute()) {
                    $crudSuccess = "Role berhasil dihapus dari sistem!";
                } else {
                    $crudError = "Gagal menghapus data dari database.";
                }
                $deleteStmt->close();
            }
            $checkUsed->close();
        } catch (Throwable $e) {
            $crudError = "Kesalahan sistem saat menghapus data: " . $e->getMessage();
        }
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
      <div>
        <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Manajemen Data Roles</h2>
      </div>
      <div>
        <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalTambahRole">
          <i class="bi bi-shield-plus"></i> Tambah Role
        </button>
      </div>
    </div>

    <!-- NOTIFIKASI INFORMASI ALERT STATUS OPERASI CRUD -->
    <?php if (!empty($crudSuccess)): ?>
      <div class="alert alert-success border-0 rounded-3 mb-4" role="alert" style="background: rgba(34, 197, 94, 0.12) !important; color: #86efac !important;">
        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($crudSuccess) ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($crudError)): ?>
      <div class="alert alert-danger border-0 rounded-3 mb-4" role="alert" style="background: rgba(239, 68, 68, 0.12) !important; color: #fecaca !important;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($crudError) ?>
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
              // Ambil 4 kolom lengkap dari tabel roles sesuai struktur database
              $queryRoles = "SELECT id, name, created_at, updated_at FROM roles ORDER BY id ASC";
              $resultRoles = $conn->query($queryRoles);
              
              if ($resultRoles && $resultRoles->num_rows > 0) {
                  while ($roleRow = $resultRoles->fetch_assoc()) {
                      ?>
                      <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.9rem;">
                        <!-- #1 id (BIGINT - AUTO_INCREMENT) -->
                        <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important;"><?= $roleRow['id'] ?></td>
                        
                        <!-- #2 name (VARCHAR 100) -->
                        <td class="fw-semibold text-white" style="background: transparent !important;"><?= htmlspecialchars($roleRow['name'] ?? '-') ?></td>
                        
                        <!-- #3 created_at (TIMESTAMP) -->
                        <td class="text-white-50 small" style="background: transparent !important;"><?= $roleRow['created_at'] ?? 'NULL' ?></td>
                        
                        <!-- #4 updated_at (TIMESTAMP) -->
                        <td class="text-white-50 small" style="background: transparent !important;"><?= $roleRow['updated_at'] ?? 'NULL' ?></td>
                        
                        <!-- TOMBOL AKSI CRUD -->
                        <td class="text-center" style="background: transparent !important;">
                          <div class="d-flex justify-content-center gap-1">
                            <!-- Tombol Edit memicu pengisian data otomatis ke modal edit via JS -->
                            <button class="btn btn-sm btn-outline-success border-0 rounded-2" title="Edit Role" data-bs-toggle="modal" data-bs-target="#modalEditRole" onclick="populateEditRoleModal(<?= htmlspecialchars(json_encode($roleRow)) ?>)">
                              <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" 
                                    class="btn btn-sm btn-danger" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalDeleteRole" 
                                    onclick="document.getElementById('delete_role_name').innerText = '<?php echo addslashes($row['role_name']); ?>'; document.getElementById('btn_confirm_delete_role').setAttribute('href', 'roles.php?action_delete=<?php echo $row['id']; ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                            </a>
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

<!-- MODAL TAMBAH ROLE (Hitam Gelap) -->
<div class="modal fade" id="modalTambahRole" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="roles.php" method="POST" class="modal-content" style="background: #1e293b; color: #e5e7eb; border: 1px solid rgba(148, 163, 184, 0.25);">
            <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
                <h5 class="modal-title fw-bold">Tambah Role Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" style="color: #94a3b8;">Nama Role</label>
                    <input type="text" name="name" class="form-control" style="background: rgba(2, 6, 23, 0.35); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb;" placeholder="Contoh: admin, kasir, pelanggan" required>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.15);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="action_create" class="btn btn-success">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT ROLE (Hitam Gelap) -->
<div class="modal fade" id="modalEditRole" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="roles.php" method="POST" class="modal-content" style="background: #1e293b; color: #e5e7eb; border: 1px solid rgba(148, 163, 184, 0.25);">
            <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
                <h5 class="modal-title fw-bold">Ubah Data Role</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Input Hidden ID untuk target pembaruan query -->
                <input type="hidden" name="id" id="edit-role-id">
                
                <div class="mb-3">
                    <label class="form-label" style="color: #94a3b8;">Nama Role</label>
                    <input type="text" name="name" id="edit-role-name" class="form-control" style="background: rgba(2, 6, 23, 0.35); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb;" required>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.15);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="action_update" class="btn btn-success">Perbarui</button>
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
let bootstrapDeleteRoleModalInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    // Handler untuk mengeksekusi aksi hapus saat tombol konfirmasi diklik
    const btnConfirmDeleteRole = document.getElementById('btn_confirm_delete_role');
    if (btnConfirmDeleteRole) {
        btnConfirmDeleteRole.addEventListener('click', function(e) {
            if (deleteRoleUrlTarget) {
                e.preventDefault();
                window.location.href = deleteRoleUrlTarget;
            }
        });
    }
});

function triggerDeleteRole(url, roleName) {
    // 1. Suntik nama ke teks deskripsi modal
    const namePlaceholder = document.getElementById('delete_role_name');
    if (namePlaceholder) {
        namePlaceholder.innerText = roleName;
    }
    
    // 2. Ubah URL tujuan tombol "Ya, Hapus" secara langsung
    const btnConfirm = document.getElementById('btn_confirm_delete_role');
    if (btnConfirm) {
        btnConfirm.setAttribute('href', url);
    }
    
    // 3. Tampilkan modal secara aman dan bersih
    const modalElement = document.getElementById('modalDeleteRole');
    if (modalElement) {
        const existingInstance = bootstrap.Modal.getInstance(modalElement);
        if (existingInstance) {
            existingInstance.dispose(); // Hapus sisa instans memori lama yang macet
        }
        
        const newModalInstance = new bootstrap.Modal(modalElement);
        newModalInstance.show();
    }
}

// Fungsi pengisi data modal edit role otomatis sekaligus memicu tampilnya modal kustom
function populateEditRoleModal(role) {
    if (role) {
        document.getElementById('edit_role_id').value = role.id;
        
        // Menangani penyesuaian jika properti data object dari backend menggunakan role.name atau role.role_name
        document.getElementById('edit_role_name').value = role.name || role.role_name || '';
        
        // Membuka bootstrap modal edit kustom Anda secara terprogram
        const editModalElement = document.getElementById('modalEditRole');
        if (editModalElement) {
            const bootstrapEditModalInstance = new bootstrap.Modal(editModalElement);
            bootstrapEditModalInstance.show();
        }
    }
}

</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

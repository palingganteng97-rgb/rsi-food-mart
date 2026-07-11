<?php
// permissions.php
include 'db.php'; // Hubungkan ke koneksi database $conn

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$status = isset($_GET['status']) ? $_GET['status'] : "";
$msg = "";

// ==========================================
// 1. PROSES BERKAS CREATE (TAMBAH DATA)
// ==========================================
if (isset($_POST['create'])) {
    $module_name     = mysqli_real_escape_string($conn, $_POST['module_name']);
    $permission_name = mysqli_real_escape_string($conn, $_POST['permission_name']);

    if (empty($module_name) || empty($permission_name)) {
        $status = "error";
        $msg = "Semua kolom input wajib diisi!";
    }

    if ($status !== "error") {
        $query = "INSERT INTO permissions (module_name, permission_name) VALUES ('$module_name', '$permission_name')";
        if (mysqli_query($conn, $query)) {
            header("Location: permissions.php?status=success_create");
            exit();
        } else {
            $status = "error";
            $msg = "Gagal menambah data: " . mysqli_error($conn);
        }
    }
}

// ==========================================
// 2. PROSES BERKAS UPDATE (UBAH DATA)
// ==========================================
if (isset($_POST['update'])) {
    $id              = intval($_POST['id']);
    $module_name     = mysqli_real_escape_string($conn, $_POST['module_name']);
    $permission_name = mysqli_real_escape_string($conn, $_POST['permission_name']);

    if (empty($module_name) || empty($permission_name)) {
        $status = "error";
        $msg = "Semua kolom input wajib diisi!";
    }

    if ($status !== "error") {
        $query = "UPDATE permissions SET module_name = '$module_name', permission_name = '$permission_name' WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            header("Location: permissions.php?status=success_update");
            exit();
        } else {
            $status = "error";
            $msg = "Gagal memperbarui data: " . mysqli_error($conn);
        }
    }
}

// ==========================================
// 3. PROSES BERKAS DELETE (HAPUS DATA)
// ==========================================
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    $query = "DELETE FROM permissions WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        header("Location: permissions.php?status=success_delete");
        exit();
    } else {
        $status = "error";
        $msg = "Gagal menghapus data: " . mysqli_error($conn);
    }
}

// ==========================================
// 4. READ DATA UNTUK LOOPING TABEL VIEW
// ==========================================
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
  <!-- Container tabel dengan tema gelap transparan menyatu dengan background halaman -->
  <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
    
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
      <div class="alert alert-success border-0 rounded-3 mb-4" role="alert" style="background: rgba(34, 197, 94, 0.12) !important; color: #86efac !important;">
        <i class="bi bi-check-circle-fill me-2"></i> 
        <?php 
        if ($status == 'success_create') echo "Data hak akses berhasil ditambahkan!";
        elseif ($status == 'success_update') echo "Data hak akses berhasil diperbarui!";
        elseif ($status == 'success_delete') echo "Data hak akses berhasil dihapus!";
        ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($status) && $status == 'error'): ?>
      <div class="alert alert-danger border-0 rounded-3 mb-4" role="alert" style="background: rgba(239, 68, 68, 0.12) !important; color: #fecaca !important;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($msg) ?>
      </div>
    <?php endif; ?>

    <!-- TABEL LIST DATA PERMISSIONS (5 KOLOM LENGKAP SESUAI DATABASE) -->
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
                        <!-- #1 id (BIGINT - AUTO_INCREMENT) -->
                        <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important;"><?= $permRow['id'] ?></td>
                        
                        <!-- #2 module_name (VARCHAR 100) -->
                        <td class="text-white-50 fw-medium" style="background: transparent !important;">
                          <i class="bi bi-box-seam me-2"></i><?= htmlspecialchars($permRow['module_name'] ?? '-') ?>
                        </td>
                        
                        <!-- #3 permission_name (VARCHAR 100) -->
                        <td style="background: transparent !important;">
                          <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-10 rounded-2" style="font-size: 0.8rem; background: rgba(13, 110, 253, 0.12); padding: 6px 12px;">
                            <i class="bi bi-key me-1"></i><?= htmlspecialchars($permRow['permission_name'] ?? '-') ?>
                          </span>
                        </td>
                        
                        <!-- #4 created_at (TIMESTAMP) -->
                        <td class="text-white-50 small" style="background: transparent !important;"><?= $permRow['created_at'] ?? 'NULL' ?></td>
                        
                        <!-- #5 updated_at (TIMESTAMP) -->
                        <td class="text-white-50 small" style="background: transparent !important;"><?= $permRow['updated_at'] ?? 'NULL' ?></td>
                        
                        <!-- TOMBOL AKSI CRUD MODERAT -->
                        <td class="text-center" style="background: transparent !important;">
                          <div class="d-flex justify-content-center gap-1">
                            <!-- Tombol Edit memicu fungsi openEditPermission -->
                            <button type="button" class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Edit Permission" onclick="openEditPermission(<?= htmlspecialchars(json_encode($permRow)) ?>)">
                              <i class="bi bi-pencil-square"></i>
                            </button>
                            
                            <!-- Tombol Hapus memicu modal konfirmasi kustom -->
                            <button type="button" 
                                    class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" 
                                    title="Delete Permission"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalDeletePermission" 
                                    onclick="document.getElementById('delete_permission_title_display').innerText = '<?php echo addslashes($permRow['permission_name']); ?>'; document.getElementById('btn_confirm_delete_permission').setAttribute('href', 'permissions.php?delete=<?= $permRow['id']; ?>')">
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

<!-- ==========================================
     1. MODAL FORM CRUD (TAMBAH / EDIT PERMISSION)
=========================================== -->
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

<!-- ==========================================
     2. MODAL KONFIRMASI HAPUS PERMISSION
=========================================== -->
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

<!-- Modal Konfirmasi Hapus Permissions -->
<div class="modal fade" id="modalDeletePermission" tabindex="-1" aria-labelledby="modalDeletePermissionLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-bg-dark border-secondary" style="background-color: #111827 !important; border-color: #374151 !important;">
      
      <!-- Bagian Atas / Header Modal -->
      <div class="modal-header border-bottom border-secondary">
        <h5 class="modal-title text-white fw-bold d-flex align-items-center" id="modalDeletePermissionLabel">
          <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Konfirmasi Hapus
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <!-- Bagian Tengah / Isi Modal -->
      <div class="modal-body text-center p-4">
        <div class="mb-3">
          <!-- Ikon Tempat Sampah Besar berwarna Merah -->
          <i class="bi bi-trash3-fill text-danger" style="font-size: 3.5rem;"></i>
        </div>
        <p class="text-light fs-6 mb-1">Apakah Anda yakin ingin menghapus hak akses ini?</p>
        <!-- Tempat nama permission akan muncul secara dinamis -->
        <h6 id="delete_permission_title_display" class="text-warning fw-bold mt-2"></h6>
      </div>
      
      <!-- Bagian Bawah / Tombol Aksi -->
      <div class="modal-footer border-top border-secondary justify-content-center">
        <button type="button" class="btn btn-secondary px-4 rounded-2" data-bs-dismiss="modal">Batal</button>
        <button type="button" id="btn_confirm_delete_permission" class="btn btn-danger px-4 rounded-2 fw-bold">Oke, Hapus</button>
      </div>

    </div>
  </div>
</div>

<script>
// Variabel global untuk menampung URL hapus data permission
let deletePermissionUrlTarget = '';

document.addEventListener('DOMContentLoaded', function() {
    // ==========================================
    // LOGIKA SELEKTOR GAYA DRAG SCROLL
    // ==========================================
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

<?php
// permissions.php
require_once 'db.php';

// Cek koneksi dari db.php (asumsi variabel koneksi bernama $conn atau $koneksi)
if (!isset($conn) && isset($koneksi)) { $conn = $koneksi; }

// Menghubungkan logika modular penghapusan data dan berkas fisik
include "delete_handler.php";

// ==========================================
// 1. READ (Menampilkan Data)
// ==========================================
function getPermissions($conn) {
    $sql = "SELECT * FROM permissions ORDER BY id DESC";
    return mysqli_query($conn, $sql);
}

// ==========================================
// 2. CREATE (Tambah Data)
// ==========================================
if (isset($_POST['create'])) {
    $module_name     = mysqli_real_escape_string($conn, $_POST['module_name']);
    $permission_name = mysqli_real_escape_string($conn, $_POST['permission_name']);

    $sql = "INSERT INTO permissions (module_name, permission_name) VALUES ('$module_name', '$permission_name')";
    if (mysqli_query($conn, $sql)) {
        header("Location: permissions.php?status=success_create");
        exit;
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

// ==========================================
// 3. UPDATE (Ubah Data)
// ==========================================
if (isset($_POST['update'])) {
    $id              = mysqli_real_escape_string($conn, $_POST['id']);
    $module_name     = mysqli_real_escape_string($conn, $_POST['module_name']);
    $permission_name = mysqli_real_escape_string($conn, $_POST['permission_name']);

    $sql = "UPDATE permissions SET module_name = '$module_name', permission_name = '$permission_name' WHERE id = '$id'";
    if (mysqli_query($conn, $sql)) {
        header("Location: permissions.php?status=success_update");
        exit;
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

// ==========================================
// 4. DELETE (Hapus Data)
// ==========================================
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);

    $sql = "DELETE FROM permissions WHERE id = '$id'";
    if (mysqli_query($conn, $sql)) {
        header("Location: permissions.php?status=success_delete");
        exit;
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

$permissionsData = getPermissions($conn);
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
  <!-- Container tabel dengan tema gelap transparan yang selaras sempurna -->
  <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
    
    <!-- HEADER TABEL & TOMBOL TAMBAH PERMISSION -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
      <div>
        <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;"> Hak Akses / Permissions </h2>
      </div>
      <div>
        <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalPermission" onclick="openTambahPermission()">
          <i class="bi bi-plus-circle"></i> Tambah Hak Akses
        </button>
      </div>
    </div>

    <!-- STRUKTUR TABEL LIST DATA PERMISSIONS (DRAG SCROLL & TRANSPARAN) -->
    <div id="dragScrollPermissionContainer" class="table-responsive rounded-3 drag-scroll-container" style="border: none !important; background: transparent !important; cursor: grab; box-shadow: none !important; -webkit-box-shadow: none !important;">
      <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 1000px; user-select: none; border-collapse: collapse !important;">
        <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
            <tr>
              <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 100px;">ID</th>
              <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 250px;">Module Name</th>
              <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Permission Name</th>
              <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;">Aksi</th>
            </tr>
        </thead>
        <tbody style="background: transparent !important;">
          <?php
          try {
              if ($permissionsData && mysqli_num_rows($permissionsData) > 0) {
                  while ($row = mysqli_fetch_assoc($permissionsData)) {
                      ?>
                      <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                        <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= $row['id'] ?></td>
                        <td class="text-white-50 fw-medium" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['module_name'] ?? '-') ?></td>
                        <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;">
                          <span class="badge bg-secondary-subtle text-white-50 border border-secondary border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.8rem; background: rgba(30, 41, 59, 0.5) !important;">
                            <?= htmlspecialchars($row['permission_name'] ?? '-') ?>
                          </span>
                        </td>
                        <td class="text-center" style="background: transparent !important; border: none !important;">
                          <div class="d-flex justify-content-center gap-1">
                            <!-- Tombol Edit -->
                            <button type="button" class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Edit" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalPermission" 
                                    onclick='openEditPermission(<?= json_encode($row) ?>)'>
                              <i class="bi bi-pencil-square"></i>
                            </button>
                            
                            <!-- Tombol Hapus Modern Anti-Macet -->
                            <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Delete"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalDeletePermission" 
                                    onclick="document.getElementById('delete_permission_display').innerText = '<?php echo addslashes($row['permission_name']); ?>'; document.getElementById('btn_confirm_delete_permission').setAttribute('href', 'permissions.php?delete=<?= $row['id'] ?>')">
                              <i class="bi bi-trash-fill"></i>
                            </button>
                          </div>
                        </td>
                      </tr>
                      <?php
                  }
              } else {
                  ?>
                  <tr>
                    <td colspan="4" class="text-center py-5 text-muted shadow-none" style="background: transparent !important; border: none !important;">
                      <i class="bi bi-folder-x d-block mb-2" style="font-size: 2rem; color: rgba(148, 163, 184, 0.4);"></i>
                      Tidak ada data hak akses (permissions) saat ini.
                    </td>
                  </tr>
                  <?php
              }
          } catch (Throwable $e) {
              echo "<tr><td colspan='4' class='text-danger text-center py-3' style='background: transparent !important; border: none !important;'>Gagal memuat data</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- MODAL FORM CRUD (TAMBAH / UBAH PRODUCT ADDON) -->
<div class="modal fade" id="modalAddon" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <!-- Form diarahkan kembali ke file product_addons.php -->
        <form action="product_addons.php" method="POST" class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(10px); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-header border-0 pb-0" style="padding: 1.5rem 1.5rem 0 1.5rem;">
                <h5 class="fw-bold text-white m-0" id="modalAddonLabel">Tambah Addon</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Input Hidden untuk menampung ID saat operasi Update/Edit -->
                <input type="hidden" name="id" id="addon_id">
                
                <!-- Input ID Produk -->
                <div class="mb-3">
                    <label for="addon_product_id" class="form-label small text-white-50 fw-medium">Product ID</label>
                    <input type="number" name="product_id" id="addon_product_id" class="form-control rounded-3 text-white" style="background: rgba(2, 6, 23, 0.4); border: 1px solid rgba(148, 163, 184, 0.2); shadow: none;" placeholder="Contoh: 12" required>
                </div>
                
                <!-- Input Nama Addon -->
                <div class="mb-3">
                    <label for="addon_name_input" class="form-label small text-white-50 fw-medium">Addon Name</label>
                    <input type="text" name="addon_name" id="addon_name_input" class="form-control rounded-3 text-white" style="background: rgba(2, 6, 23, 0.4); border: 1px solid rgba(148, 163, 184, 0.2); shadow: none;" placeholder="Contoh: Ekstra Sambal" required>
                </div>
                
                <!-- Pilihan Status Sifat Pilihan (Required) -->
                <div class="mb-2">
                    <label for="addon_required" class="form-label small text-white-50 fw-medium">Sifat Pilihan (Required)</label>
                    <select name="required" id="addon_required" class="form-select rounded-3 text-white" style="background: rgba(2, 6, 23, 0.4); border: 1px solid rgba(148, 163, 184, 0.2); shadow: none;" required>
                        <option value="0" class="bg-dark text-white">Opsional (0)</option>
                        <option value="1" class="bg-dark text-white">Wajib (1)</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer border-0 pt-0 d-flex gap-2 justify-content-end" style="padding: 0 1.5rem 1.5rem 1.5rem;">
                <button type="button" class="btn btn-sm btn-secondary rounded-3 px-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
                <button type="submit" name="action_add" id="btnSubmitAddon" class="btn btn-sm btn-success rounded-3 px-3 py-2 fw-medium">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL KONFIRMASI HAPUS PRODUCT ADDON KUSTOM (Tempatkan Sebelum Tag Penutup </body>) -->
<div class="modal fade" id="modalDeleteProductAddon" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(10px); border: 1px solid rgba(239, 68, 68, 0.25); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-body text-center p-4">
                <div class="text-danger mb-3">
                    <i class="bi bi-exclamation-triangle-fill" style="font-size: 3rem; filter: drop-shadow(0 0 10px rgba(239, 68, 68, 0.3));"></i>
                </div>
                <h5 class="fw-bold text-white mb-2">Hapus Product Addon?</h5>
                <p class="text-white-50 small mb-4">Tindakan ini akan menghapus data addon <span id="delete_addon_title_display" class="text-white fw-semibold"></span> secara permanen. Data yang dihapus tidak dapat dikembalikan.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-sm btn-secondary rounded-3 px-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
                    <a id="btn_confirm_delete_addon" href="#" class="btn btn-sm btn-danger rounded-3 px-3 py-2 fw-medium">Ya, Hapus</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- LOGIKA JAVASCRIPT POPULATE DATA & MANIPULASI MODAL -->
<script>
// Fungsi pemicu saat menekan tombol "Tambah Addon"
function openTambahAddon() {
    document.getElementById('modalAddonLabel').innerText = 'Tambah Addon Baru';
    document.getElementById('addon_id').value = '';
    document.getElementById('addon_product_id').value = '';
    document.getElementById('addon_name_input').value = '';
    document.getElementById('addon_required').value = '0'; // Default opsional
    
    // Ubah nama atribut submit menjadi create
    const btnSubmit = document.getElementById('btnSubmitAddon');
    btnSubmit.setAttribute('name', 'create_addon');
    btnSubmit.className = "btn btn-sm btn-success rounded-3 px-3 py-2 fw-medium";
    btnSubmit.innerText = "Simpan Data";
}

// Fungsi pemicu saat menekan tombol ikon Edit di tabel data
function openEditAddon(data) {
    if (data) {
        document.getElementById('modalAddonLabel').innerText = 'Ubah Data Addon';
        document.getElementById('addon_id').value = data.id;
        document.getElementById('addon_product_id').value = data.product_id;
        document.getElementById('addon_name_input').value = data.addon_name;
        document.getElementById('addon_required').value = data.required;
        
        // Ubah nama atribut submit menjadi update beserta style warna kuning peringatan
        const btnSubmit = document.getElementById('btnSubmitAddon');
        btnSubmit.setAttribute('name', 'update_addon');
        btnSubmit.className = "btn btn-sm btn-warning text-dark rounded-3 px-3 py-2 fw-semibold";
        btnSubmit.innerText = "Simpan Perubahan";
        
        // Menampilkan modal programatik sebagai fallback jikalau data-bs-toggle pada tombol loop mengalami delay
        const modalEl = document.getElementById('modalAddon');
        const instance = bootstrap.Modal.getOrCreateInstance(modalEl);
        instance.show();
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

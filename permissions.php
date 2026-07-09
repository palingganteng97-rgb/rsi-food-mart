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
    /* Kustomisasi Modal Tengah Melebar & Sembunyikan Semua Scrollbar Dalam Modal */
    .modal-dialog { max-width: 800px !important; }
    .modal, .modal-body, .modal-open { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow-y: hidden !important; }
    .modal::-webkit-scrollbar, .modal-body::-webkit-scrollbar { display: none !important; }
    @media (min-width: 992px) { main.content-shift { margin-left: 280px; } .bottom-nav { display:none; } }
</style>

</head>
<body>
  <?php require __DIR__ . '/sidebar.php'; ?>

<main class="content-shift p-4">
  <!-- Container tabel dengan tema gelap transparan yang selaras sempurna -->
  <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
    
    <!-- HEADER TABEL & TOMBOL TAMBAH PRODUCT ADDON -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
      <div>
        <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;"> Product Addons </h2>
      </div>
      <div>
        <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalAddon" onclick="openTambahAddon()">
          <i class="bi bi-plus-circle"></i> Tambah Addon
        </button>
      </div>
    </div>

    <!-- STRUKTUR TABEL LIST DATA PRODUCT ADDONS (DRAG SCROLL & TRANSPARAN) -->
    <div id="dragScrollAddonContainer" class="table-responsive rounded-3 drag-scroll-container" style="border: none !important; background: transparent !important; cursor: grab; box-shadow: none !important; -webkit-box-shadow: none !important;">
      <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 1000px; user-select: none; border-collapse: collapse !important;">
        <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
            <tr>
            <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 100px;">ID</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;">Product ID</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Addon Name</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 180px;">Required</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;">Aksi</th>
            </tr>
        </thead>
        <tbody style="background: transparent !important;">
          <?php
          try {
              // Menarik data berdasarkan susunan kolom asli di database HeidiSQL Anda
              $queryAddons = "SELECT id, product_id, addon_name, required FROM product_addons ORDER BY id ASC";
              $resultAddons = $conn->query($queryAddons);
              
              if ($resultAddons && $resultAddons->num_rows > 0) {
                  while ($addonRow = $resultAddons->fetch_assoc()) {
                      $isRequired = (int)$addonRow['required'] === 1;
                      ?>
                      <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                        <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= $addonRow['id'] ?></td>
                        <td class="text-center text-white-50" style="background: transparent !important; border: none !important;"><?= $addonRow['product_id'] ?></td>
                        <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($addonRow['addon_name'] ?? '-') ?></td>
                        <td class="text-center" style="background: transparent !important; border: none !important;">
                          <?php if ($isRequired): ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem;">Wajib (1)</span>
                          <?php else: ?>
                            <span class="badge bg-secondary-subtle text-muted border border-secondary border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem;">Opsional (0)</span>
                          <?php endif; ?>
                        </td>
                        <td class="text-center" style="background: transparent !important; border: none !important;">
                          <div class="d-flex justify-content-center gap-1">
                            <button class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Edit" onclick='openEditAddon(<?= json_encode($addonRow) ?>)'>
                              <i class="bi bi-pencil-square"></i>
                            </button>
                            <a href="product_addons.php?action=delete&id=<?= $addonRow['id'] ?>" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Delete" onclick="return confirm('Yakin ingin menghapus addon ini?')">
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
                    <td colspan="5" class="text-center py-5 text-muted shadow-none" style="background: transparent !important; border: none !important;">
                      <i class="bi bi-folder-x d-block mb-2" style="font-size: 2rem; color: rgba(148, 163, 184, 0.4);"></i>
                      Tidak ada data product addon saat ini.
                    </td>
                  </tr>
                  <?php
              }
          } catch (Throwable $e) {
              echo "<tr><td colspan='5' class='text-danger text-center py-3' style='background: transparent !important; border: none !important;'>Gagal memuat data</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

    <!-- MODAL CREATE -->
    <div class="modal fade" id="modalCreate" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="permissions.php" method="POST" class="modal-content" style="background: #1e293b; color: #e5e7eb; border: 1px solid rgba(148, 163, 184, 0.25);">
                <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.25);">
                    <h5 class="modal-title">Tambah Permission Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="color: #94a3b8;">Nama Modul</label>
                        <input type="text" name="module_name" class="form-control" style="background: rgba(2, 6, 23, 0.35); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb;" placeholder="Contoh: orders, users, atau products" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: #94a3b8;">Nama Permission</label>
                        <input type="text" name="permission_name" class="form-control" style="background: rgba(2, 6, 23, 0.35); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb;" placeholder="Contoh: create_order, edit_user" required>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.25);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="create" class="btn btn-success">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>

<!-- MODAL UPDATE -->
    <div class="modal fade" id="modalUpdate" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="permissions.php" method="POST" class="modal-content" style="background: #1e293b; color: #e5e7eb; border: 1px solid rgba(148, 163, 184, 0.25);">
                <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.25);">
                    <h5 class="modal-title">Ubah Data Permission</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Input Hidden untuk menampung ID record yang akan diupdate -->
                    <input type="hidden" name="id" id="edit-id">
                    
                    <div class="mb-3">
                        <label class="form-label" style="color: #94a3b8;">Nama Modul</label>
                        <input type="text" name="module_name" id="edit-module" class="form-control" style="background: rgba(2, 6, 23, 0.35); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb;" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: #94a3b8;">Nama Permission</label>
                        <input type="text" name="permission_name" id="edit-permission" class="form-control" style="background: rgba(2, 6, 23, 0.35); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb;" required>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.25);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="update" class="btn btn-warning text-dark">Perbarui</button>
                </div>
            </form>
        </div>
    </div>

<!-- MODAL DELETE -->
<div class="modal fade" id="modalDelete" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="background: #1e293b; color: #e5e7eb; border: 1px solid rgba(239, 68, 68, 0.35);">
            <div class="modal-body text-center p-4">
                <!-- Icon Peringatan -->
                <div class="text-danger mb-3">
                    <i class="bi bi-exclamation-triangle" style="font-size: 3rem;"></i>
                </div>
                <h5 class="modal-title mb-2">Hapus Permission?</h5>
                <p class="text-muted small">Anda yakin ingin menghapus data permission <code id="delete-permission-text" class="text-danger"></code>? Tindakan ini tidak dapat dibatalkan.</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a href="#" id="delete-confirm-btn" class="btn btn-sm btn-danger">Ya, Hapus</a>
            </div>
        </div>
    </div>
</div>

    <script>
        // Logika Javascript untuk memindahkan data dari baris tabel ke dalam field Input di Modal Update
        document.addEventListener('DOMContentLoaded', function() {
            const editButtons = document.querySelectorAll('.btn-edit');
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('edit-id').value = this.getAttribute('data-id');
                    document.getElementById('edit-module').value = this.getAttribute('data-module');
                    document.getElementById('edit-permission').value = this.getAttribute('data-permission');
                });
            });
        });

        // Logika untuk memindahkan parameter data ke Modal Hapus
        const deleteButtons = document.querySelectorAll('.btn-delete');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-permission');
                
                // Set tulisan nama permission di dalam teks modal
                document.getElementById('delete-permission-text').textContent = name;
                // Set href tombol konfirmasi hapus dengan ID target
                document.getElementById('delete-confirm-btn').setAttribute('href', 'permissions.php?delete=' + id);
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const addonSlider = document.getElementById('dragScrollAddonContainer');
            if (!addonSlider) return;
            let isDown = false, startX, scrollLeft;
            addonSlider.addEventListener('mousedown', (e) => {
                if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input')) return;
                isDown = true; addonSlider.style.cursor = 'grabbing';
                startX = e.pageX - addonSlider.offsetLeft; scrollLeft = addonSlider.scrollLeft;
            });
            addonSlider.addEventListener('mouseleave', () => { isDown = false; addonSlider.style.cursor = 'grab'; });
            addonSlider.addEventListener('mouseup', () => { isDown = false; addonSlider.style.cursor = 'grab'; });
            addonSlider.addEventListener('mousemove', (e) => {
                if (!isDown) return; e.preventDefault();
                const x = e.pageX - addonSlider.offsetLeft;
                addonSlider.scrollLeft = scrollLeft - ((x - startX) * 1.5);
            });
        });
        </script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

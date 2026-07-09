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
        @media (min-width: 992px) {
        main.content-shift { margin-left: 280px; }
        .bottom-nav { display:none; }
        }
    </style>

</head>
<body>
  <?php require __DIR__ . '/sidebar.php'; ?>

<!-- Main Content Area -->
    <main class="content-shift p-4">
        <div class="container-fluid">
            
            <!-- Header & Alert Status -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Permissions Management</h2>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalCreate">
                    <i class="bi bi-plus-circle me-2"></i>Tambah Permission
                </button>
            </div>

            <?php if (isset($_GET['status'])): ?>
                <div class="alert alert-success alert-dismissible fade show bg-success text-white border-0" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php
                        if ($_GET['status'] == 'success_create') echo 'Data berhasil ditambahkan!';
                        if ($_GET['status'] == 'success_update') echo 'Data berhasil diperbarui!';
                        if ($_GET['status'] == 'success_delete') echo 'Data berhasil dihapus!';
                    ?>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Table Data Box -->
            <div class="card-food p-4">
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="80">No</th>
                                <th>Nama Modul</th>
                                <th>Nama Permission</th>
                                <th width="180" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($permissionsData) > 0): ?>
                                <?php $no = 1; while($row = mysqli_fetch_assoc($permissionsData)): ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($row['module_name']); ?></span></td>
                                        <td><code><?= htmlspecialchars($row['permission_name']); ?></code></td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-warning me-1 btn-edit" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalUpdate"
                                                    data-id="<?= $row['id']; ?>"
                                                    data-module="<?= htmlspecialchars($row['module_name']); ?>"
                                                    data-permission="<?= htmlspecialchars($row['permission_name']); ?>">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <!-- Tombol Hapus Baru terintegrasi Modal Konfirmasi -->
                                            <button class="btn btn-sm btn-outline-danger btn-delete" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalDelete"
                                                    data-id="<?= $row['id']; ?>"
                                                    data-permission="<?= htmlspecialchars($row['permission_name']); ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Belum ada data permission.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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

    </script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

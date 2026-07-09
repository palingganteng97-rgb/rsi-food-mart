<?php
// ====================================================================
// SKRIP BACKEND PHP: CRUD CATEGORIES (MENGGUNAKAN HEIDISQL DATA)
// ====================================================================
include 'db.php'; // Pastikan koneksi database Anda disimpan di variabel $conn

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteksi halaman login (Opsional)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 1. PROSES TAMBAH KATEGORI BARU (INSERT)
if (isset($_POST['tambah_category'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
    
    // Ambil tenant_id dari session jika sistem Anda menggunakan multi-tenant
    // Jika tidak menggunakan tenant_id, ganti menjadi NULL
    $tenant_id = isset($_SESSION['tenant_id']) && !empty($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : "NULL";

    if (!empty($name)) {
        $query = "INSERT INTO categories (tenant_id, name) VALUES ($tenant_id, '$name')";
        if (mysqli_query($conn, $query)) {
            echo "<script>alert('Kategori baru berhasil ditambahkan!'); window.location='categories.php';</script>";
            exit();
        } else {
            echo "Error: " . mysqli_error($conn);
        }
    } else {
        echo "<script>alert('Nama kategori tidak boleh kosong!'); window.location='categories.php';</script>";
        exit();
    }
}

// 2. PROSES UBAH DATA KATEGORI (UPDATE)
if (isset($_POST['edit_category'])) {
    $id   = (int)($_POST['id'] ?? 0);
    $name = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));

    if ($id > 0 && !empty($name)) {
        $query = "UPDATE categories SET name = '$name' WHERE id = '$id'";
        if (mysqli_query($conn, $query)) {
            echo "<script>alert('Kategori berhasil diperbarui!'); window.location='categories.php';</script>";
            exit();
        } else {
            echo "Error: " . mysqli_error($conn);
        }
    } else {
        echo "<script>alert('Data tidak valid!'); window.location='categories.php';</script>";
        exit();
    }
}

// 3. PROSES HAPUS KATEGORI (DELETE)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    if ($id > 0) {
        $query = "DELETE FROM categories WHERE id = '$id'";
        if (mysqli_query($conn, $query)) {
            echo "<script>alert('Kategori berhasil dihapus!'); window.location='categories.php';</script>";
            exit();
        } else {
            echo "Error: " . mysqli_error($conn);
        }
    }
}

// 4. MEMBACA DATA KATEGORI UNTUK DITAMPILKAN KE TABEL DESKTOP
$listCategories = [];
$query_select = "SELECT * FROM categories ORDER BY id ASC";
$result = mysqli_query($conn, $query_select);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $listCategories[] = $row;
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
    <!-- Container tabel dengan tema gelap transparan -->
    <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
        
        <!-- HEADER TABEL & TOMBOL TAMBAH KATEGORI -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
            <div>
                <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Categories</h2>
            </div>
            <div>
                <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalCategory" onclick="openTambahCategory()">
                    <i class="bi bi-plus-circle"></i> Tambah Kategori
                </button>
            </div>
        </div>

        <!-- NOTIFIKASI STATUS OPERASI CRUD -->
        <?php if (!empty($status)): ?>
            <div class="alert <?= strpos($status, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
                <strong>
                    <?php 
                    if ($status == 'success_insert') echo "Data kategori berhasil ditambahkan!";
                    elseif ($status == 'success_update') echo "Data kategori berhasil diperbarui!";
                    elseif ($status == 'success_delete') echo "Data kategori berhasil dihapus!";
                    else echo "Operasi gagal: " . htmlspecialchars($msg);
                    ?>
                </strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- STRUKTUR TABEL LIST DATA KATEGORI -->
        <div id="dragScrollCategoryContainer" class="table-responsive rounded-3 drag-scroll-container" style="border: none !important; background: transparent !important; cursor: grab; box-shadow: none !important; -webkit-box-shadow: none !important;">
            <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 800px; user-select: none; border-collapse: collapse !important;">
                <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
                    <tr>
                        <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 100px;">ID</th>
                        <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 250px;">Tenant Name / ID</th>
                        <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Category Name</th>
                        <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody style="background: transparent !important;">
                    <?php if (!empty($listCategories)): foreach ($listCategories as $row): ?>
                        <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                            <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= $row['id'] ?></td>
                            <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;">
                                <?= htmlspecialchars($row['tenant_name'] ?? 'Tenant ID: '.$row['tenant_id']) ?>
                            </td>
                            <td class="text-white" style="background: transparent !important; border: none !important;">
                                <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-25 rounded-2 px-2.5 py-1" style="font-size: 0.85rem; background: rgba(13, 110, 253, 0.15);">
                                    <?= htmlspecialchars($row['name']) ?>
                                </span>
                            </td>
                            <td class="text-center" style="background: transparent !important; border: none !important;">
                                <div class="d-flex justify-content-center gap-1">
                                    <button class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Edit" onclick='openEditCategory(<?= json_encode($row) ?>)'>
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <a href="categories.php?action=delete&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Delete" onclick="return confirm('Apakah Anda yakin ingin menghapus kategori ini?')">
                                        <i class="bi bi-trash-fill"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted shadow-none" style="background: transparent !important; border: none !important;">
                                <i class="bi bi-folder-x d-block mb-2" style="font-size: 2rem; color: rgba(148, 163, 184, 0.4);"></i>
                                Tidak ada data kategori saat ini.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- MODAL FORM INPUT MELEBAR DI TENGAH (WIDE MODE & BEBAS SCROLLBAR) -->
<div class="modal fade" id="modalCategory" tabindex="-1" aria-labelledby="modalCategoryLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.93) !important; backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.2); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
                <h5 class="modal-title fw-bold text-white" id="modalCategoryLabel">Form Kategori</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formCategory" action="categories.php" method="POST">
                <input type="hidden" name="id" id="category_id">
                <div id="category_action_flag"></div>
                <div class="modal-body" style="overflow: visible !important;">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Pilih Tenant <span class="text-danger">*</span></label>
                            <select class="form-select" name="tenant_id" id="category_tenant_id" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                                <option value="" disabled selected>-- Pilih Tenant --</option>
                                <?php foreach ($listActiveTenants as $tOption): ?>
                                    <option value="<?= $tOption['id'] ?>"><?= htmlspecialchars($tOption['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Nama Kategori <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="category_name" placeholder="Contoh: Makanan, Minuman, Snak..." style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.15); background: rgba(15, 23, 42, 0.95); border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="btnSubmitCategory">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JAVASCRIPT EVENT MOUSE DRAG TO SCROLL & HANDLER MODAL -->
<script>

function openTambahCategory() {
    document.getElementById('formCategory').reset();
    document.getElementById('modalCategoryLabel').innerText = 'Tambah Kategori Baru';
    document.getElementById('category_id').value = '';
    document.getElementById('btnSubmitCategory').className = "btn btn-success";
    document.getElementById('btnSubmitCategory').innerText = "Simpan Data";
    document.getElementById('category_action_flag').innerHTML = '<input type="hidden" name="action_add_category" value="1">';
}

function openEditCategory(data) {
    openTambahCategory();
    document.getElementById('modalCategoryLabel').innerText = 'Ubah Data Kategori';
    document.getElementById('category_id').value = data.id;
    document.getElementById('category_tenant_id').value = data.tenant_id;
    document.getElementById('category_name').value = data.name;
    document.getElementById('btnSubmitCategory').className = "btn btn-warning text-dark fw-medium";
    document.getElementById('btnSubmitCategory').innerText = "Simpan Perubahan";
    document.getElementById('category_action_flag').innerHTML = '<input type="hidden" name="action_update_category" value="1">';
    var myModal = new bootstrap.Modal(document.getElementById('modalCategory'));
    myModal.show();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

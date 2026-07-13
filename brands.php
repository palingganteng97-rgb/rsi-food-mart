<?php
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$status = "";
$msg = "";
$uploadDir = "uploads/brands/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

if (isset($_POST['action_add_brand'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $logoName = "";

    if (isset($_FILES['logo']) && !empty($_FILES['logo']['name'])) {
        $fileName = $_FILES['logo']['name'];
        $fileTmp  = $_FILES['logo']['tmp_name'];
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $logoName = "brand_" . time() . "_" . uniqid() . "." . $fileExt;
        $targetFile = $uploadDir . $logoName;

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($fileExt, $allowedExtensions)) {
            if (!move_uploaded_file($fileTmp, $targetFile)) {
                $status = "error";
                $msg = "Gagal mengunggah gambar logo ke server.";
            }
        } else {
            $status = "error";
            $msg = "Format file tidak didukung! Hanya diperbolehkan: " . implode(', ', $allowedExtensions);
        }
    }

    if ($status !== "error") {
        $query = "INSERT INTO brands (name, logo) VALUES ('$name', '$logoName')";
        if (mysqli_query($conn, $query)) {
            $status = "success_insert";
        } else {
            $status = "error";
            $msg = "Gagal menyimpan data ke database: " . mysqli_error($conn);
        }
    }
}

if (isset($_POST['action_update_brand'])) {
    $id = intval($_POST['id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);

    $checkQuery = mysqli_query($conn, "SELECT logo FROM brands WHERE id = $id");
    $currentData = mysqli_fetch_assoc($checkQuery);
    $oldLogo = $currentData['logo'];

    $logoName = $oldLogo;

    if (isset($_FILES['logo']) && !empty($_FILES['logo']['name'])) {
        $fileName = $_FILES['logo']['name'];
        $fileTmp  = $_FILES['logo']['tmp_name'];
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $logoName = "brand_" . time() . "_" . uniqid() . "." . $fileExt;
        $targetFile = $uploadDir . $logoName;

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($fileExt, $allowedExtensions)) {
            if (move_uploaded_file($fileTmp, $targetFile)) {
                if (!empty($oldLogo) && file_exists($uploadDir . $oldLogo)) {
                    unlink($uploadDir . $oldLogo);
                }
            } else {
                $status = "error";
                $msg = "Gagal mengunggah logo baru ke server.";
            }
        } else {
            $status = "error";
            $msg = "Format file baru tidak didukung.";
        }
    }

    if ($status !== "error") {
        $query = "UPDATE brands SET name = '$name', logo = '$logoName' WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            $status = "success_update";
        } else {
            $status = "error";
            $msg = "Gagal memperbarui database: " . mysqli_error($conn);
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $checkQuery = mysqli_query($conn, "SELECT logo FROM brands WHERE id = $id");
    if (mysqli_num_rows($checkQuery) > 0) {
        $currentData = mysqli_fetch_assoc($checkQuery);
        $oldLogo = $currentData['logo'];

        $query = "DELETE FROM brands WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            if (!empty($oldLogo) && file_exists($uploadDir . $oldLogo)) {
                unlink($uploadDir . $oldLogo);
            }
            $status = "success_delete";
        } else {
            $status = "error";
            $msg = "Gagal menghapus data dari database: " . mysqli_error($conn);
        }
    }
}

$listBrands = [];
$fetchQuery = mysqli_query($conn, "SELECT * FROM brands ORDER BY id DESC");
if ($fetchQuery) {
    while ($row = mysqli_fetch_assoc($fetchQuery)) {
        $listBrands[] = $row;
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
    <!-- Container tabel dengan tema gelap transparan -->
    <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
        
        <!-- HEADER TABEL & TOMBOL TAMBAH BRAND -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
            <div>
                <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Brands</h2>
            </div>
            <div>
                <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalBrand" onclick="openTambahBrand()">
                    <i class="bi bi-plus-circle"></i> Tambah Brand
                </button>
            </div>
        </div>

        <!-- NOTIFIKASI STATUS OPERASI CRUD -->
        <?php if (!empty($status)): ?>
            <div class="alert <?= strpos($status, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
                <strong>
                    <?php 
                    if ($status == 'success_insert') echo "Data brand berhasil ditambahkan!";
                    elseif ($status == 'success_update') echo "Data brand berhasil diperbarui!";
                    elseif ($status == 'success_delete') echo "Data brand berhasil dihapus!";
                    else echo "Operasi gagal: " . htmlspecialchars($msg);
                    ?>
                </strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- STRUKTUR TABEL LIST DATA BRAND -->
        <div id="dragScrollBrandContainer" class="table-responsive rounded-3 drag-scroll-container" style="border: none !important; background: transparent !important; cursor: grab; box-shadow: none !important; -webkit-box-shadow: none !important;">
            <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 800px; user-select: none; border-collapse: collapse !important;">
                <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
                    <tr>
                        <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 100px;">ID</th>
                        <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;">Logo</th>
                        <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Brand Name</th>
                        <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody style="background: transparent !important;">
                    <?php if (!empty($listBrands)): foreach ($listBrands as $row): ?>
                        <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                            <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= $row['id'] ?></td>
                            <td class="text-center" style="background: transparent !important; border: none !important;">
                                <?php if (!empty($row['logo'])): ?>
                                    <img src="uploads/brands/<?= htmlspecialchars($row['logo']) ?>" alt="Logo" class="rounded-2" style="max-height: 40px; max-width: 120px; object-fit: contain;">
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 0.75rem;">No Logo</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;">
                                <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-2 px-2.5 py-1" style="font-size: 0.85rem; background: rgba(25, 135, 84, 0.15);">
                                    <?= htmlspecialchars($row['name']) ?>
                                </span>
                            </td>
                            <td class="text-center" style="background: transparent !important; border: none !important;">
                                <div class="d-flex justify-content-center gap-1">
                                    <button class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Edit" onclick='openEditBrand(<?= json_encode($row) ?>)'>
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Delete" onclick='confirmDeleteBrand(<?= json_encode($row) ?>)'>
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted shadow-none" style="background: transparent !important; border: none !important;">
                                <i class="bi bi-folder-x d-block mb-2" style="font-size: 2rem; color: rgba(148, 163, 184, 0.4);"></i>
                                Tidak ada data brand saat ini.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- MODAL FORM INPUT MELEBAR DI TENGAH (WIDE MODE & BEBAS SCROLLBAR) -->
<div class="modal fade" id="modalBrand" tabindex="-1" aria-labelledby="modalBrandLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.93) !important; backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.2); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
                <h5 class="modal-title fw-bold text-white" id="modalBrandLabel">Form Brand</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formBrand" action="brands.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="brand_id">
                <div id="brand_action_flag"></div>
                <div class="modal-body" style="overflow: visible !important;">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Nama Brand <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="brand_name" placeholder="Contoh: Coca-Cola, Indofood, Unilever..." style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Logo Brand</label>
                            <input type="file" class="form-control" name="logo" id="brand_logo" accept="image/*" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
                            
                            <!-- Opsi Hapus Gambar Saat Edit -->
                            <div id="container_hapus_logo" class="mt-2" style="display: none;">
                                <div class="form-check text-start">
                                    <input class="form-check-input" type="checkbox" name="delete_current_logo" id="delete_current_logo" value="1" style="background-color: rgba(2, 6, 23, 0.4); border-color: rgba(148, 163, 184, 0.3);">
                                    <label class="form-check-label text-danger fw-medium" for="delete_current_logo" style="font-size: 0.85rem; cursor: pointer;">
                                        <i class="bi bi-trash3-fill me-1"></i> Hapus logo saat ini
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-text text-muted" style="font-size: 0.75rem;">Format gambar didukung: JPG, JPEG, PNG, WEBP.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.15); background: rgba(15, 23, 42, 0.95); border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="btnSubmitBrand">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL HAPUS -->
<div class="modal fade" id="modalDeleteBrand" tabindex="-1" aria-labelledby="modalDeleteBrandLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(12px); border: 1px solid rgba(239, 68, 68, 0.2); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-body text-center p-4">
                <div class="text-danger mb-3">
                    <i class="bi bi-exclamation-triangle-fill" style="font-size: 3rem;"></i>
                </div>
                <h5 class="modal-title fw-bold text-white mb-2" id="modalDeleteBrandLabel">Hapus Brand / Merk?</h5>
                <p class="text-white-50 small mb-4">
                    Apakah Anda yakin ingin menghapus brand <span id="delete_brand_name" class="fw-bold text-white"></span>? Tindakan ini tidak dapat dibatalkan.
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal" style="border-radius: 8px;">Batal</button>
                    <a id="btn_confirm_delete_brand" href="#" class="btn btn-danger px-4" style="border-radius: 8px;">Ya, Hapus</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT EVENT MOUSE DRAG TO SCROLL & HANDLER MODAL -->
<script>
let deleteBrandModalInstance = null;

function openTambahBrand() {
    document.getElementById('formBrand').reset();
    document.getElementById('modalBrandLabel').innerText = 'Tambah Brand Baru';
    document.getElementById('brand_id').value = '';
    document.getElementById('btnSubmitBrand').className = "btn btn-success";
    document.getElementById('btnSubmitBrand').innerText = "Simpan Data";
    document.getElementById('brand_action_flag').innerHTML = '<input type="hidden" name="action_add_brand" value="1">';
}

function openEditBrand(data) {
    openTambahBrand();
    document.getElementById('modalBrandLabel').innerText = 'Ubah Data Brand';
    document.getElementById('brand_id').value = data.id;
    document.getElementById('brand_name').value = data.name;
    document.getElementById('btnSubmitBrand').className = "btn btn-warning text-dark fw-medium";
    document.getElementById('btnSubmitBrand').innerText = "Simpan Perubahan";
    document.getElementById('brand_action_flag').innerHTML = '<input type="hidden" name="action_update_brand" value="1">';
    var myModal = new bootstrap.Modal(document.getElementById('modalBrand'));
    myModal.show();
}

function confirmDeleteBrand(data) {
    document.getElementById('delete_brand_name').innerText = '"' + data.name + '"';
    document.getElementById('btn_confirm_delete_brand').href = 'brands.php?action=delete&id=' + data.id;
    
    if (!deleteBrandModalInstance) {
        deleteBrandModalInstance = new bootstrap.Modal(document.getElementById('modalDeleteBrand'));
    }
    deleteBrandModalInstance.show();
}

</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

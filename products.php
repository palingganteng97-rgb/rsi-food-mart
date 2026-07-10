<?php
// ====================================================================
// SKRIP BACKEND PHP: CRUD PRODUCTS (PRODUK)
// ====================================================================
include 'db.php'; // Hubungkan ke koneksi database $conn

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteksi halaman login (Opsional)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Inisialisasi variabel status untuk notifikasi alert
$status = isset($_GET['status']) ? $_GET['status'] : "";
$msg = "";

// Tentukan direktori penyimpanan file gambar produk
$uploadDir = "uploads/products/";

// Pastikan folder penyimpanan sudah dibuat
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

// -------------------------------------------------------------
// LOGIKA 1: TAMBAH DATA (INSERT)
// -------------------------------------------------------------
if (isset($_POST['action_add_product'])) {
    $tenant_id   = intval($_POST['tenant_id']);
    $category_id = intval($_POST['category_id']);
    $brand_id    = !empty($_POST['brand_id']) ? intval($_POST['brand_id']) : "NULL";
    $unit_id     = !empty($_POST['unit_id']) ? intval($_POST['unit_id']) : "NULL";
    $sku         = mysqli_real_escape_string($conn, $_POST['sku']);
    $barcode     = mysqli_real_escape_string($conn, $_POST['barcode']);
    $name        = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $base_price  = floatval($_POST['base_price']);
    $stock       = intval($_POST['stock']);
    $p_status    = isset($_POST['status']) ? intval($_POST['status']) : 1;
    $imageName   = ""; 

    // Validasi duplikasi SKU jika diisi
    if (!empty($sku)) {
        $checkSku = mysqli_query($conn, "SELECT id FROM products WHERE sku = '$sku' LIMIT 1");
        if (mysqli_num_rows($checkSku) > 0) {
            $status = "error"; $msg = "SKU sudah digunakan oleh produk lain!";
        }
    }

    // Proses upload file gambar produk jika ada
    if ($status !== "error" && isset($_FILES['image']) && !empty($_FILES['image']['name'])) {
        $fileName = $_FILES['image']['name'];
        $fileTmp  = $_FILES['image']['tmp_name'];
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $imageName = "prod_" . time() . "_" . uniqid() . "." . $fileExt;
        $targetFile = $uploadDir . $imageName;

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($fileExt, $allowedExtensions)) {
            if (!move_uploaded_file($fileTmp, $targetFile)) {
                $status = "error"; $msg = "Gagal mengunggah gambar produk.";
            }
        } else {
            $status = "error"; $msg = "Format gambar tidak didukung (Gunakan: JPG, PNG, WEBP).";
        }
    }

    // Eksekusi Query Insert ke Database
    if ($status !== "error") {
        $query = "INSERT INTO products (tenant_id, category_id, brand_id, unit_id, sku, barcode, name, description, base_price, stock, image, status) 
                  VALUES ($tenant_id, $category_id, $brand_id, $unit_id, '$sku', '$barcode', '$name', '$description', $base_price, $stock, '$imageName', $p_status)";
        
        if (mysqli_query($conn, $query)) {
            $status = "success_insert";
        } else {
            $status = "error"; $msg = "Gagal menyimpan produk: " . mysqli_error($conn);
        }
    }
}

// -------------------------------------------------------------
// LOGIKA 2: UBAH DATA (UPDATE)
// -------------------------------------------------------------
if (isset($_POST['action_update_product'])) {
    $id          = intval($_POST['id']);
    $tenant_id   = intval($_POST['tenant_id']);
    $category_id = intval($_POST['category_id']);
    $brand_id    = !empty($_POST['brand_id']) ? intval($_POST['brand_id']) : "NULL";
    $unit_id     = !empty($_POST['unit_id']) ? intval($_POST['unit_id']) : "NULL";
    $sku         = mysqli_real_escape_string($conn, $_POST['sku']);
    $barcode     = mysqli_real_escape_string($conn, $_POST['barcode']);
    $name        = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $base_price  = floatval($_POST['base_price']);
    $stock       = intval($_POST['stock']);
    $p_status    = isset($_POST['status']) ? intval($_POST['status']) : 1;

    // Ambil info gambar lama
    $checkQuery = mysqli_query($conn, "SELECT image FROM products WHERE id = $id");
    $currentData = mysqli_fetch_assoc($checkQuery);
    $oldImage = $currentData['image'];
    $imageName = $oldImage;

    // Opsi Hapus Gambar Saat Ini
    if (isset($_POST['delete_current_image']) && $_POST['delete_current_image'] == '1') {
        if (!empty($oldImage) && file_exists($uploadDir . $oldImage)) {
            unlink($uploadDir . $oldImage);
        }
        $imageName = "";
    }

    // Proses upload gambar baru jika diganti
    if (isset($_FILES['image']) && !empty($_FILES['image']['name'])) {
        $fileName = $_FILES['image']['name'];
        $fileTmp  = $_FILES['image']['tmp_name'];
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $imageName = "prod_" . time() . "_" . uniqid() . "." . $fileExt;
        $targetFile = $uploadDir . $imageName;

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($fileExt, $allowedExtensions)) {
            if (move_uploaded_file($fileTmp, $targetFile)) {
                if (!empty($oldImage) && file_exists($uploadDir . $oldImage) && !isset($_POST['delete_current_image'])) {
                    unlink($uploadDir . $oldImage);
                }
            } else {
                $status = "error"; $msg = "Gagal mengunggah gambar baru.";
            }
        } else {
            $status = "error"; $msg = "Format file gambar baru tidak didukung.";
        }
    }

    // Eksekusi Query Update ke Database
    if ($status !== "error") {
        $query = "UPDATE products SET 
                    tenant_id = $tenant_id, 
                    category_id = $category_id, 
                    brand_id = $brand_id, 
                    unit_id = $unit_id, 
                    sku = '$sku', 
                    barcode = '$barcode', 
                    name = '$name', 
                    description = '$description', 
                    base_price = $base_price, 
                    stock = $stock, 
                    image = '$imageName', 
                    status = $p_status 
                  WHERE id = $id";
        
        if (mysqli_query($conn, $query)) {
            $status = "success_update";
        } else {
            $status = "error"; $msg = "Gagal memperbarui produk: " . mysqli_error($conn);
        }
    }
}

// -------------------------------------------------------------
// AMBIL DATA RELASI UNTUK FORM INPUT (DROPDOWN MODAL)
// -------------------------------------------------------------
$listTenants    = [];
$listCategories = [];
$listBrands     = [];
$listUnits      = [];

$qTenant = mysqli_query($conn, "SELECT id, name FROM tenants ORDER BY name ASC");
while ($r = mysqli_fetch_assoc($qTenant)) { $listTenants[] = $r; }

$qCat = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
while ($r = mysqli_fetch_assoc($qCat)) { $listCategories[] = $r; }

$qBrand = mysqli_query($conn, "SELECT id, name FROM brands ORDER BY name ASC");
while ($r = mysqli_fetch_assoc($qBrand)) { $listBrands[] = $r; }

$qUnit = mysqli_query($conn, "SELECT id, name FROM units ORDER BY name ASC");
while ($r = mysqli_fetch_assoc($qUnit)) { $listUnits[] = $r; }

// -------------------------------------------------------------
// AMBIL DATA UTAMA PRODUK UNTUK DITAMPILKAN DI TABEL
// -------------------------------------------------------------
$listProducts = [];
$sql = "SELECT p.*, t.name AS tenant_name, c.name AS category_name, b.name AS brand_name, u.name AS unit_name 
        FROM products p 
        LEFT JOIN tenants t ON p.tenant_id = t.id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN units u ON p.unit_id = u.id
        WHERE p.deleted_at IS NULL 
        ORDER BY p.id DESC";

$fetchQuery = mysqli_query($conn, $sql);
if ($fetchQuery) {
    while ($row = mysqli_fetch_assoc($fetchQuery)) {
        $listProducts[] = $row;
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
    body.modal-open { overflow:hidden !important; }
    .content-bg { background:transparent; }
    .text-white-element { -webkit-user-select:none; -moz-user-select:none; -ms-user-select:none; user-select:none; }
    .search-box { background:rgba(2,6,23,.35); border:1px solid rgba(148,163,184,.25); border-radius:18px; }
    .diet-pill { border:1px solid rgba(34,197,94,.35); background:rgba(34,197,94,.08); color:#86efac; }
    .diet-pill[data-active="true"] { background:rgba(34,197,94,.92); color:#06210f; border-color:rgba(34,197,94,.65); }
    .price-badge { display:inline-flex; align-items:center; gap:.35rem; padding:.35rem .7rem; background:rgba(15,23,42,.55); border:1px solid rgba(148,163,184,.25); border-radius:999px; color:var(--text); }
    .card-food { background:rgba(2,6,23,.40); border:1px solid rgba(148,163,184,.22); border-radius:18px; overflow:hidden; transition:transform .15s ease, border-color .15s ease; }
    .card-food:hover { transform:translateY(-2px); border-color:rgba(34,197,94,.35); }
    .food-img { height:150px; background:linear-gradient(180deg, rgba(34,197,94,.10), rgba(2,6,23,.0)); display:flex; align-items:center; justify-content:center; color:rgba(148,163,184,.8); position:relative; }
    .food-img img { width:100%; height:100%; object-fit:cover; }
    #dragScrollUserContainer, #dragScrollContainer, .drag-scroll-container { -ms-overflow-style:none !important; scrollbar-width:none !important; overflow-x:auto !important; cursor:grab !important; border:none !important; box-shadow:none !important; -webkit-box-shadow:none !important; }
    #dragScrollUserContainer::-webkit-scrollbar, #dragScrollContainer::-webkit-scrollbar, .drag-scroll-container::-webkit-scrollbar { display:none !important; }
    #dragScrollUserContainer:active, #dragScrollContainer:active, .drag-scroll-container:active { cursor:grabbing !important; }
    #dragScrollUserContainer table, #dragScrollContainer table, .drag-scroll-container table { border-collapse:collapse !important; border:none !important; }
    #dragScrollUserContainer table th, #dragScrollUserContainer table td, #dragScrollContainer table th, #dragScrollContainer table td, .drag-scroll-container table th, .drag-scroll-container table td { border-left:none !important; border-right:none !important; border-bottom:1px solid rgba(148, 163, 184, 0.12) !important; }
    .modal-dialog { max-width:800px !important; }
    .modal-body { -ms-overflow-style:none !important; scrollbar-width:none !important; overflow-y:auto !important; max-height:calc(100vh - 200px); }
    .modal-body::-webkit-scrollbar { display:none !important; width:0 !important; }
    .modal-body::-webkit-scrollbar-track { background:rgba(15,23,42,0.2); }
    .modal-body::-webkit-scrollbar-thumb { background:rgba(148,163,184,0.3); border-radius:4px; }
    .modal-body::-webkit-scrollbar-thumb:hover { background:rgba(148,163,184,0.5); }
    .bi-clock-history, .text-white-icon { color:#ffffff !important; opacity:1 !important; filter:drop-shadow(0 0 1px rgba(255,255,255,0.2)); }
    input[type="time"]::-webkit-calendar-picker-indicator, input[type="date"]::-webkit-calendar-picker-indicator { filter:invert(1) brightness(100%) contrast(100%) !important; cursor:pointer; }
    .bottom-nav { position:fixed; left:0; right:0; bottom:0; z-index:1035; background:rgba(15,23,42,.88); backdrop-filter:blur(10px); border-top:1px solid rgba(148,163,184,.25); display:block; }
    @media (min-width: 992px) { main.content-shift { margin-left:280px; } .bottom-nav { display:none; } }
</style>

</head>
<body>
  <?php require __DIR__ . '/sidebar.php'; ?>

<main class="content-shift p-4">
    <!-- Container tabel dengan tema gelap transparan -->
    <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
        
        <!-- HEADER TABEL & TOMBOL TAMBAH PRODUK -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
            <div>
                <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Products</h2>
            </div>
            <div>
                <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalProduct" onclick="openTambahProduct()">
                    <i class="bi bi-plus-circle"></i> Tambah Produk
                </button>
            </div>
        </div>

        <!-- NOTIFIKASI STATUS OPERASI CRUD -->
        <?php if (!empty($status)): ?>
            <div class="alert <?= strpos($status, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
                <strong>
                    <?php 
                    if ($status == 'success_insert') echo "Data produk berhasil ditambahkan!";
                    elseif ($status == 'success_update') echo "Data produk berhasil diperbarui!";
                    elseif ($status == 'success_delete') echo "Data produk berhasil dihapus!";
                    else echo "Operasi gagal: " . htmlspecialchars($msg);
                    ?>
                </strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- STRUKTUR TABEL LIST DATA PRODUK (DRAG SCROLL MOUSE WIDE MODE) -->
        <div id="dragScrollProductContainer" class="table-responsive rounded-3 drag-scroll-container" style="border: none !important; background: transparent !important; cursor: grab; box-shadow: none !important; -webkit-box-shadow: none !important;">
            <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 1200px; user-select: none; border-collapse: collapse !important;">
                <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
                    <tr>
                        <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 80px;">ID</th>
                        <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 100px;">Gambar</th>
                        <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 250px;">Nama Produk</th>
                        <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 150px;">Tenant / Kategori</th>
                        <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 120px;">SKU / Brand</th>
                        <th class="py-3 text-end text-white" style="background: transparent !important; border: none !important; width: 130px;">Harga Jual</th>
                        <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 100px;">Stok</th>
                        <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 110px;">Status</th>
                        <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 130px;">Aksi</th>
                    </tr>
                </thead>
                <tbody style="background: transparent !important;">
                    <?php if (!empty($listProducts)): foreach ($listProducts as $row): ?>
                        <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                            <!-- Kolom ID -->
                            <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= $row['id'] ?></td>
                            
                            <!-- Kolom Gambar -->
                            <td class="text-center" style="background: transparent !important; border: none !important;">
                                <?php if (!empty($row['image'])): ?>
                                    <img src="uploads/products/<?= htmlspecialchars($row['image']) ?>" alt="Gambar" class="rounded-2" style="max-height: 45px; max-width: 45px; object-fit: cover;">
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 0.75rem;">No Image</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Kolom Nama & Deskripsi Pendek -->
                            <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;">
                                <div class="text-truncate" style="max-width: 230px;" title="<?= htmlspecialchars($row['name']) ?>"><?= htmlspecialchars($row['name']) ?></div>
                                <div class="text-muted text-truncate fw-normal" style="font-size: 0.75rem; max-width: 230px;"><?= htmlspecialchars($row['description'] ?: '-') ?></div>
                            </td>
                            
                            <!-- Kolom Tenant & Kategori -->
                            <td style="background: transparent !important; border: none !important;">
                                <div class="text-white-50 text-truncate" style="max-width: 140px; font-size: 0.8rem;"><i class="bi bi-shop me-1"></i><?= htmlspecialchars($row['tenant_name']) ?></div>
                                <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-10 rounded-2 mt-1" style="font-size: 0.72rem; background: rgba(13, 110, 253, 0.12);"><?= htmlspecialchars($row['category_name']) ?></span>
                            </td>
                            
                            <!-- Kolom SKU & Brand -->
                            <td style="background: transparent !important; border: none !important;">
                                <div class="font-monospace text-warning" style="font-size: 0.8rem;"><?= htmlspecialchars($row['sku'] ?: '-') ?></div>
                                <div class="text-white-50 text-truncate mt-0.5" style="font-size: 0.75rem; max-width: 110px;"><i class="bi bi-tag me-1"></i><?= htmlspecialchars($row['brand_name'] ?: 'Tanpa Merek') ?></div>
                            </td>
                            
                            <!-- Kolom Harga -->
                            <td class="text-end fw-bold text-white" style="background: transparent !important; border: none !important;">
                                Rp <?= number_format($row['base_price'], 0, ',', '.') ?>
                            </td>
                            
                            <!-- Kolom Stok & Satuan -->
                            <td class="text-center" style="background: transparent !important; border: none !important;">
                                <span class="fw-semibold <?= $row['stock'] <= 5 ? 'text-danger' : 'text-white' ?>"><?= $row['stock'] ?></span>
                                <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($row['unit_name'] ?: 'pcs') ?></div>
                            </td>
                            
                            <!-- Kolom Status Keaktifan -->
                            <td class="text-center" style="background: transparent !important; border: none !important;">
                                <?php if ($row['status'] == 1): ?>
                                    <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-2 py-0.5" style="font-size: 0.72rem; background: rgba(25, 135, 84, 0.15);">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25 rounded-pill px-2 py-0.5" style="font-size: 0.72rem; background: rgba(220, 53, 69, 0.15);">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Kolom Aksi -->
                            <td class="text-center" style="background: transparent !important; border: none !important;">
                                <div class="d-flex justify-content-center gap-1">
                                    <button class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Edit" onclick='openEditProduct(<?= json_encode($row) ?>)'>
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <a href="delete_handler.php?delete_product_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Delete" onclick="return confirm('Apakah Anda yakin ingin menghapus produk ini?')">
                                        <i class="bi bi-trash-fill"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <!-- Sesuaikan jumlah colspan dengan jumlah total kolom tabel Anda, contoh: 10 kolom -->
                            <td colspan="10" class="text-center py-5 text-muted shadow-none" style="background: transparent !important; border: none !important;">
                                <i class="bi bi-folder-x d-block mb-2" style="font-size: 2rem; color: rgba(148, 163, 184, 0.4);"></i>
                                Tidak ada data produk saat ini.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- MODAL FORM INPUT MELEBAR DI TENGAH (WIDE MODE & FIX SCROLL INTERNAL) -->
<div class="modal fade" id="modalProduct" tabindex="-1" aria-labelledby="modalProductLabel" aria-hidden="true" style="overflow-y: hidden !important;">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" style="max-height: calc(100vh - 2rem);">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.2); color: #e5e7eb; border-radius: 16px; max-height: inherit; display: flex; flex-direction: column;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15); flex-shrink: 0;">
                <h5 class="modal-title fw-bold text-white" id="modalProductLabel">Form Produk</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formProduct" action="products.php" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; overflow: hidden; margin-bottom: 0;">
                <input type="hidden" name="id" id="product_id">
                <div id="product_action_flag"></div>
                
                <!-- PENTING: overflow-y: auto memaksa scrollbar aktif hanya di area form ini -->
                <div class="modal-body" style="overflow-y: auto !important; flex-grow: 1; padding-right: 8px;">
                    <div class="row g-3">

                        <!-- Pilihan Tenant -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Pilih Tenant <span class="text-danger">*</span></label>
                            <select class="form-select" name="tenant_id" id="product_tenant_id" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                                <option value="" disabled selected>-- Pilih Tenant --</option>
                                <?php foreach ($listTenants as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Pilihan Kategori -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Pilih Kategori <span class="text-danger">*</span></label>
                            <select class="form-select" name="category_id" id="product_category_id" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                                <option value="" disabled selected>-- Pilih Kategori --</option>
                                <?php foreach ($listCategories as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Pilihan Brand -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Pilih Brand</label>
                            <select class="form-select" name="brand_id" id="product_brand_id" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
                                <option value="">-- Tanpa Brand --</option>
                                <?php foreach ($listBrands as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Pilihan Satuan / Unit -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Pilih Satuan</label>
                            <select class="form-select" name="unit_id" id="product_unit_id" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
                                <option value="">-- Tanpa Satuan --</option>
                                <?php foreach ($listUnits as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- SKU -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">SKU</label>
                            <input type="text" class="form-control" name="sku" id="product_sku" placeholder="Contoh: SKU-1002" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
                        </div>
                        <!-- Barcode -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Barcode</label>
                            <input type="text" class="form-control" name="barcode" id="product_barcode" placeholder="Contoh: 8991234567" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
                        </div>
                        <!-- Nama Produk -->
                        <div class="col-md-12">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Nama Produk <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="product_name" placeholder="Contoh: Ayam Goreng Kremes" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>
                        <!-- Harga Dasar -->
                        <div class="col-md-4">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Harga Dasar <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="base_price" id="product_base_price" placeholder="0" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>
                        <!-- Stok -->
                        <div class="col-md-4">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Stok <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="stock" id="product_stock" value="0" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>
                        <!-- Status Aktif -->
                        <div class="col-md-4">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Status <span class="text-danger">*</span></label>
                            <select class="form-select" name="status" id="product_status" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                                <option value="1">Aktif</option>
                                <option value="0">Non-Aktif</option>
                            </select>
                        </div>
                        <!-- Gambar Produk -->
                        <div class="col-md-12">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Gambar Produk</label>
                            <input type="file" class="form-control" name="image" id="product_image" accept="image/*" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
                            
                            <!-- Opsi Hapus Gambar Saat Edit -->
                            <div id="container_hapus_image" class="mt-2" style="display: none;">
                                <div class="form-check text-start">
                                    <input class="form-check-input" type="checkbox" name="delete_current_image" id="delete_current_image" value="1" style="background-color: rgba(2, 6, 23, 0.4); border-color: rgba(148, 163, 184, 0.3);">
                                    <label class="form-check-label text-danger fw-medium" for="delete_current_image" style="font-size: 0.85rem; cursor: pointer;">
                                        <i class="bi bi-trash3-fill me-1"></i> Hapus gambar saat ini
                                    </label>
                                </div>
                            </div>
                            <div class="form-text text-muted" style="font-size: 0.75rem;">Format gambar didukung: JPG, JPEG, PNG, WEBP.</div>
                        </div>
                        <!-- Deskripsi -->
                        <div class="col-md-12">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Deskripsi Produk</label>
                            <textarea class="form-control" name="description" id="product_description" rows="3" placeholder="Keterangan lengkap isi porsi, rasa, atau detail produk..." style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Bagian Tombol Aksi di Bawah Form -->
                <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.15); background: rgba(15, 23, 42, 0.95); border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="btnSubmitProduct">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const prodSlider = document.getElementById('dragScrollProductContainer');
    if (!prodSlider) return;
    
    let isDown = false;
    let startX, scrollLeft;
    
    prodSlider.addEventListener('mousedown', (e) => {
        if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input')) return;
        isDown = true; 
        prodSlider.style.cursor = 'grabbing';
        startX = e.pageX - prodSlider.offsetLeft; 
        scrollLeft = prodSlider.scrollLeft;
    });
    
    prodSlider.addEventListener('mouseleave', () => { 
        isDown = false; 
        prodSlider.style.cursor = 'grab'; 
    });
    
    prodSlider.addEventListener('mouseup', () => { 
        isDown = false; 
        prodSlider.style.cursor = 'grab'; 
    });
    
    prodSlider.addEventListener('mousemove', (e) => {
        if (!isDown) return; 
        e.preventDefault();
        const x = e.pageX - prodSlider.offsetLeft;
        prodSlider.scrollLeft = scrollLeft - ((x - startX) * 1.5);
    });
});

function openTambahProduct() {
    document.getElementById('formProduct').reset();
    document.getElementById('modalProductLabel').innerText = 'Tambah Produk Baru';
    document.getElementById('product_id').value = '';
    document.getElementById('btnSubmitProduct').className = "btn btn-success";
    document.getElementById('btnSubmitProduct').innerText = "Simpan Data";
    document.getElementById('product_action_flag').innerHTML = '<input type="hidden" name="action_add_product" value="1">';
    
    if (document.getElementById('container_hapus_image')) {
        document.getElementById('container_hapus_image').style.display = 'none';
        document.getElementById('delete_current_image').checked = false;
    }
}

function openEditProduct(data) {
    openTambahProduct();
    document.getElementById('modalProductLabel').innerText = 'Ubah Data Produk';
    document.getElementById('product_id').value = data.id;
    document.getElementById('product_tenant_id').value = data.tenant_id;
    document.getElementById('product_category_id').value = data.category_id;
    document.getElementById('product_brand_id').value = data.brand_id ? data.brand_id : '';
    document.getElementById('product_unit_id').value = data.unit_id ? data.unit_id : '';
    document.getElementById('product_sku').value = data.sku;
    document.getElementById('product_barcode').value = data.barcode;
    document.getElementById('product_name').value = data.name;
    document.getElementById('product_description').value = data.description;
    document.getElementById('product_base_price').value = data.base_price;
    document.getElementById('product_stock').value = data.stock;
    document.getElementById('product_status').value = data.status;
    document.getElementById('btnSubmitProduct').className = "btn btn-warning text-dark fw-medium";
    document.getElementById('btnSubmitProduct').innerText = "Simpan Perubahan";
    document.getElementById('product_action_flag').innerHTML = '<input type="hidden" name="action_update_product" value="1">';
    
    if (data.image && data.image !== "" && document.getElementById('container_hapus_image')) {
        document.getElementById('container_hapus_image').style.display = 'block';
    }
    
    var myModal = new bootstrap.Modal(document.getElementById('modalProduct'));
    myModal.show();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

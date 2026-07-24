<?php
// products.php
include 'db.php'; // Hubungkan ke koneksi database $conn

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$status = isset($_GET['status']) ? $_GET['status'] : "";
$msg = isset($_GET['msg']) ? $_GET['msg'] : "";
$uploadDir = "uploads/products/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

// ==========================================
// 1. PROSES CREATE (TAMBAH DATA PRODUK)
// ==========================================
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

    if (!empty($sku)) {
        $checkSku = mysqli_query($conn, "SELECT id FROM products WHERE sku = '$sku' AND deleted_at IS NULL LIMIT 1");
        if (mysqli_num_rows($checkSku) > 0) {
            header("Location: products.php?status=error&msg=" . urlencode("SKU sudah digunakan!"));
            exit();
        }
    }

    if (isset($_FILES['image']) && !empty($_FILES['image']['name'])) {
        $fileExt  = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $imageName = "prod_" . time() . "_" . uniqid() . "." . $fileExt;
        
        if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp'])) {
            move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName);
        } else {
            header("Location: products.php?status=error&msg=" . urlencode("Format gambar wajib JPG, PNG, atau WEBP."));
            exit();
        }
    }

    $query = "INSERT INTO products (tenant_id, category_id, brand_id, unit_id, sku, barcode, name, description, base_price, stock, image, status, created_at) 
              VALUES ($tenant_id, $category_id, $brand_id, $unit_id, '$sku', '$barcode', '$name', '$description', $base_price, $stock, '$imageName', $p_status, NOW())";
    
    if (mysqli_query($conn, $query)) {
        header("Location: products.php?status=success_insert");
        exit();
    } else {
        header("Location: products.php?status=error&msg=" . urlencode(mysqli_error($conn)));
        exit();
    }
}

// ==========================================
// 2. PROSES UPDATE (UBAH DATA PRODUK)
// ==========================================
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

    $checkQuery = mysqli_query($conn, "SELECT image FROM products WHERE id = $id");
    $currentData = mysqli_fetch_assoc($checkQuery);
    $oldImage = $currentData['image'];
    $imageName = $oldImage;

    if (isset($_POST['delete_current_image']) && $_POST['delete_current_image'] == '1') {
        if (!empty($oldImage) && file_exists($uploadDir . $oldImage)) {
            unlink($uploadDir . $oldImage);
        }
        $imageName = "";
    }

    if (isset($_FILES['image']) && !empty($_FILES['image']['name'])) {
        $fileExt  = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $newImageName = "prod_" . time() . "_" . uniqid() . "." . $fileExt;
        
        if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp'])) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $newImageName)) {
                if (!empty($oldImage) && file_exists($uploadDir . $oldImage)) {
                    unlink($uploadDir . $oldImage);
                }
                $imageName = $newImageName;
            }
        }
    }

    $query = "UPDATE products SET 
                tenant_id = $tenant_id, category_id = $category_id, brand_id = $brand_id, unit_id = $unit_id, 
                sku = '$sku', barcode = '$barcode', name = '$name', description = '$description', 
                base_price = $base_price, stock = $stock, image = '$imageName', status = $p_status, updated_at = NOW() 
              WHERE id = $id";
    
    if (mysqli_query($conn, $query)) {
        header("Location: products.php?status=success_update");
        exit();
    } else {
        header("Location: products.php?status=error&msg=" . urlencode(mysqli_error($conn)));
        exit();
    }
}

// ==========================================
// 3. PROSES SOFT DELETE (HAPUS DATA PRODUK)
// ==========================================
if (isset($_GET['action_restore'])) {
    $id = intval($_GET['action_restore']);

    $query = "UPDATE products SET deleted_at = NULL, updated_at = NOW() WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        $redirectView = (isset($_GET['view']) && $_GET['view'] === 'trash') ? '&view=trash' : '';
        header("Location: products.php?status=success_restore" . $redirectView);
        exit();
    } else {
        header("Location: products.php?status=error&msg=" . urlencode(mysqli_error($conn)));
        exit();
    }
}

if (isset($_GET['action_delete'])) {
    $id = intval($_GET['action_delete']);

    // Menggunakan Soft Delete (updated_at & deleted_at diisi bersamaan sesuai kolom HeidiSQL)
    $query = "UPDATE products SET updated_at = NOW(), deleted_at = NOW() WHERE id = $id";
    
    if (mysqli_query($conn, $query)) {
        header("Location: products.php?status=success_delete");
        exit();
    } else {
        header("Location: products.php?status=error&msg=" . urlencode(mysqli_error($conn)));
        exit();
    }
}

// ==========================================
// 3B. PROSES PERMANENT DELETE (HAPUS PERMANEN)
// ==========================================
if (isset($_GET['action_permanent_delete'])) {
    $id = intval($_GET['action_permanent_delete']);

    // Ambil data produk untuk menghapus file gambar
    $getData = mysqli_query($conn, "SELECT image FROM products WHERE id = $id LIMIT 1");
    if ($getData) {
        $prodData = mysqli_fetch_assoc($getData);
        
        // Hapus file gambar hanya jika TIDAK digunakan oleh produk lain
        if (!empty($prodData['image'])) {
            $checkImageUsage = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM products WHERE image = '" . mysqli_real_escape_string($conn, $prodData['image']) . "' AND id != $id");
            $usageData = mysqli_fetch_assoc($checkImageUsage);
            if ((int)$usageData['cnt'] === 0 && file_exists($uploadDir . $prodData['image'])) {
                @unlink($uploadDir . $prodData['image']);
            }
        }
    }

    // Hapus data dari database secara permanen
    $query = "DELETE FROM products WHERE id = $id";
    
    if (mysqli_query($conn, $query)) {
        header("Location: products.php?view=trash&status=success_permanent_delete");
        exit();
    } else {
        header("Location: products.php?view=trash&status=error&msg=" . urlencode(mysqli_error($conn)));
        exit();
    }
}

// ==========================================
// 4. FETCH DATA DROPDOWN & LIST PRODUK
// ==========================================
$listTenants = []; $listCategories = []; $listBrands = []; $listUnits = [];
$qTenant = mysqli_query($conn, "SELECT id, name FROM tenants ORDER BY name ASC");
while ($r = mysqli_fetch_assoc($qTenant)) { $listTenants[] = $r; }
$qCat = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
while ($r = mysqli_fetch_assoc($qCat)) { $listCategories[] = $r; }
$qBrand = mysqli_query($conn, "SELECT id, name FROM brands ORDER BY name ASC");
while ($r = mysqli_fetch_assoc($qBrand)) { $listBrands[] = $r; }
$qUnit = mysqli_query($conn, "SELECT id, name FROM units ORDER BY name ASC");
while ($r = mysqli_fetch_assoc($qUnit)) { $listUnits[] = $r; }

// Tentukan view: 'active' (default) atau 'trash'
$currentView = isset($_GET['view']) && $_GET['view'] === 'trash' ? 'trash' : 'active';

$listProducts = [];
$listTrashProducts = [];

if ($currentView === 'active') {
    $sql = "SELECT p.*, t.name AS tenant_name, c.name AS category_name, b.name AS brand_name, u.name AS unit_name 
            FROM products p 
            LEFT JOIN tenants t ON p.tenant_id = t.id
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN units u ON p.unit_id = u.id
            WHERE p.deleted_at IS NULL
            ORDER BY p.id DESC";
} else {
    $sql = "SELECT p.*, t.name AS tenant_name, c.name AS category_name, b.name AS brand_name, u.name AS unit_name 
            FROM products p 
            LEFT JOIN tenants t ON p.tenant_id = t.id
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN units u ON p.unit_id = u.id
            WHERE p.deleted_at IS NOT NULL
            ORDER BY p.deleted_at DESC";
}

$fetchQuery = mysqli_query($conn, $sql);
if ($fetchQuery) {
    while ($row = mysqli_fetch_assoc($fetchQuery)) {
        if ($currentView === 'active') {
            $listProducts[] = $row;
        } else {
            $listTrashProducts[] = $row;
        }
    }
}

// Hitung jumlah item di recycle bin
$countTrashQuery = mysqli_query($conn, "SELECT COUNT(*) AS total FROM products WHERE deleted_at IS NOT NULL");
$trashCount = 0;
if ($countTrashQuery) {
    $trashRow = mysqli_fetch_assoc($countTrashQuery);
    $trashCount = (int)$trashRow['total'];
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
                    elseif ($status == 'success_delete') echo "Data produk berhasil dipindahkan ke Recycle Bin!";
                    elseif ($status == 'success_restore') echo "Produk berhasil direstore!";
                    elseif ($status == 'success_permanent_delete') echo "Produk berhasil dihapus permanen!";
                    else echo "Operasi gagal: " . htmlspecialchars($msg);
                    ?>
                </strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- TAB NAVIGASI: Active Products vs Recycle Bin -->
        <div class="d-flex align-items-center gap-2 mb-4 pb-2" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
            <a href="products.php" class="btn btn-sm rounded-3 px-4 py-2 fw-medium <?= $currentView === 'active' ? 'btn-success text-white' : 'btn-outline-secondary text-white-50' ?>">
                <i class="bi bi-box-seam-fill me-1"></i> Produk Aktif
            </a>
            <a href="products.php?view=trash" class="btn btn-sm rounded-3 px-4 py-2 fw-medium position-relative <?= $currentView === 'trash' ? 'btn-warning text-dark' : 'btn-outline-secondary text-white-50' ?>">
                <i class="bi bi-trash-fill me-1"></i> Recycle Bin
                <?php if ($trashCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem; padding: 0.2rem 0.4rem;">
                        <?= $trashCount ?>
                    </span>
                <?php endif; ?>
            </a>
        </div>

<!-- STRUKTUR TABEL LIST DATA PRODUK (BISA DIGESER + TANPA BATANG SCROLL PUTIH) -->
<div id="dragScrollProductContainer" class="table-responsive rounded-3 drag-scroll-container" style="border: none !important; background: transparent !important; cursor: grab; box-shadow: none !important; -webkit-box-shadow: none !important; overflow-x: auto !important; scrollbar-width: none; -ms-overflow-style: none;">
    
    <!-- Gaya CSS khusus internal untuk menyembunyikan batang scrollbar putih browser -->
    <style>
        #dragScrollProductContainer::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }
    </style>

    <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 1400px !important; table-layout: fixed !important; user-select: none; border-collapse: collapse !important;">
        <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
            <tr>
                <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 80px !important;">ID</th>
                <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 100px !important;">Gambar</th>
                <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 280px !important;">Nama Produk</th>
                <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 220px !important;">Tenant / Kategori</th>
                <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 180px !important;">SKU / Brand</th>
                <th class="py-3 text-end text-white" style="background: transparent !important; border: none !important; width: 150px !important;">Harga Jual</th>
                <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 100px !important;">Stok</th>
                <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 120px !important;">Status</th>
                <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px !important;">Created At</th>
                <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px !important;">Updated At</th>
                <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px !important;">Deleted At</th>
                <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 140px !important;">Status Data</th>
                <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 140px !important;">Aksi</th>
            </tr>
        </thead>
        <tbody style="background: transparent !important;">
            <?php if ($currentView === 'active'): ?>
                <!-- ====== TABEL PRODUK AKTIF ====== -->
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
                    <td class="fw-semibold text-white" style="background: transparent !important; border: none !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <div class="text-truncate" title="<?= htmlspecialchars($row['name']) ?>"><?= htmlspecialchars($row['name']) ?></div>
                        <div class="text-muted text-truncate fw-normal" style="font-size: 0.75rem;"><?= htmlspecialchars($row['description'] ?: '-') ?></div>
                    </td>
                    
                    <!-- Kolom Tenant & Kategori -->
                    <td style="background: transparent !important; border: none !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <div class="text-white-50 text-truncate" style="font-size: 0.8rem;"><i class="bi bi-shop me-1"></i><?= htmlspecialchars($row['tenant_name']) ?></div>
                        <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-10 rounded-2 mt-1" style="font-size: 0.72rem; background: rgba(13, 110, 253, 0.12);"><?= htmlspecialchars($row['category_name']) ?></span>
                    </td>
                    
                    <!-- Kolom SKU & Brand -->
                    <td style="background: transparent !important; border: none !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <div class="font-monospace text-warning" style="font-size: 0.8rem;"><?= htmlspecialchars($row['sku'] ?: '-') ?></div>
                        <div class="text-white-50 text-truncate mt-0.5" style="font-size: 0.75rem;"><i class="bi bi-tag me-1"></i><?= htmlspecialchars($row['brand_name'] ?: 'Tanpa Merek') ?></div>
                    </td>

                    <!-- Kolom Harga Jual -->
                    <td class="text-end fw-bold text-white" style="background: transparent !important; border: none !important;">
                        Rp <?= number_format($row['base_price'], 0, ',', '.') ?>
                    </td>
                    
                    <!-- Kolom Stok & Satuan -->
                    <td class="text-center" style="background: transparent !important; border: none !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <span class="fw-semibold <?= $row['stock'] <= 5 ? 'text-danger' : 'text-white' ?>"><?= $row['stock'] ?></span>
                        <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($row['unit_name'] ?: 'Pcs') ?></div>
                    </td>
                    
                    <!-- Kolom Status Produk -->
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <?php if ((int)$row['status'] === 1): ?>
                            <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem;">Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem;">Nonaktif</span>
                        <?php endif; ?>
                    </td>

                    <!-- Kolom Audit -->
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <span class="text-white-50" style="font-size: 0.8rem;"><?= htmlspecialchars($row['created_at'] ?? '-') ?></span>
                    </td>
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <span class="text-white-50" style="font-size: 0.8rem;"><?= htmlspecialchars($row['updated_at'] ?? '-') ?></span>
                    </td>
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <span class="text-white-50" style="font-size: 0.8rem;">-</span>
                    </td>
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem;">Aktif</span>
                    </td>

                    <!-- Kolom Aksi -->
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <div class="d-flex justify-content-center gap-1">
                            <!-- Tombol Edit -->
                            <button type="button" class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Edit Produk" 
                                    onclick='openEditProduct(<?= json_encode($row) ?>)'>
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            
                            <!-- Tombol Hapus (Soft Delete) -->
                            <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Hapus Produk"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalConfirmDelete" 
                                    onclick="document.getElementById('delete_target_name').innerText = '<?php echo addslashes($row['name']); ?>'; document.getElementById('btnConfirmDeleteAction').setAttribute('href', 'products.php?action_delete=<?= $row['id'] ?>')">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="13" class="text-center py-5 text-muted shadow-none" style="background: transparent !important; border: none !important;">
                        <i class="bi bi-folder-x d-block mb-2" style="font-size: 2rem; color: rgba(148, 163, 184, 0.4);"></i>
                        Tidak ada data produk saat ini.
                    </td>
                </tr>
                <?php endif; ?>
            
            <?php else: ?>
                <!-- ====== TABEL RECYCLE BIN (PRODUK TERHAPUS) ====== -->
                <?php if (!empty($listTrashProducts)): foreach ($listTrashProducts as $row): ?>
                <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem; opacity: 0.75;">
                    <!-- Kolom ID -->
                    <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= $row['id'] ?></td>
                    
                    <!-- Kolom Gambar -->
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <?php if (!empty($row['image'])): ?>
                            <img src="uploads/products/<?= htmlspecialchars($row['image']) ?>" alt="Gambar" class="rounded-2" style="max-height: 45px; max-width: 45px; object-fit: cover; filter: grayscale(0.6);">
                        <?php else: ?>
                            <span class="text-muted" style="font-size: 0.75rem;">No Image</span>
                        <?php endif; ?>
                    </td>
                    
                    <!-- Kolom Nama & Deskripsi Pendek -->
                    <td class="fw-semibold text-white" style="background: transparent !important; border: none !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <div class="text-truncate" title="<?= htmlspecialchars($row['name']) ?>"><?= htmlspecialchars($row['name']) ?></div>
                        <div class="text-muted text-truncate fw-normal" style="font-size: 0.75rem;"><?= htmlspecialchars($row['description'] ?: '-') ?></div>
                    </td>
                    
                    <!-- Kolom Tenant & Kategori -->
                    <td style="background: transparent !important; border: none !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <div class="text-white-50 text-truncate" style="font-size: 0.8rem;"><i class="bi bi-shop me-1"></i><?= htmlspecialchars($row['tenant_name']) ?></div>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary border-opacity-25 rounded-2 mt-1" style="font-size: 0.72rem; background: rgba(108, 117, 125, 0.15);"><?= htmlspecialchars($row['category_name']) ?></span>
                    </td>
                    
                    <!-- Kolom SKU & Brand -->
                    <td style="background: transparent !important; border: none !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <div class="font-monospace text-warning" style="font-size: 0.8rem;"><?= htmlspecialchars($row['sku'] ?: '-') ?></div>
                        <div class="text-white-50 text-truncate mt-0.5" style="font-size: 0.75rem;"><i class="bi bi-tag me-1"></i><?= htmlspecialchars($row['brand_name'] ?: 'Tanpa Merek') ?></div>
                    </td>

                    <!-- Kolom Harga Jual -->
                    <td class="text-end fw-bold text-white" style="background: transparent !important; border: none !important;">
                        Rp <?= number_format($row['base_price'], 0, ',', '.') ?>
                    </td>
                    
                    <!-- Kolom Stok & Satuan -->
                    <td class="text-center" style="background: transparent !important; border: none !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <span class="fw-semibold text-white"><?= $row['stock'] ?></span>
                        <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($row['unit_name'] ?: 'Pcs') ?></div>
                    </td>
                    
                    <!-- Kolom Status Produk -->
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem;">-</span>
                    </td>

                    <!-- Kolom Audit -->
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <span class="text-white-50" style="font-size: 0.8rem;"><?= htmlspecialchars($row['created_at'] ?? '-') ?></span>
                    </td>
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <span class="text-white-50" style="font-size: 0.8rem;"><?= htmlspecialchars($row['updated_at'] ?? '-') ?></span>
                    </td>
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <span class="badge bg-danger text-white border border-danger border-opacity-50 rounded-pill px-2.5 py-1" style="font-size: 0.7rem;">Dihapus</span>
                        <div class="text-danger" style="font-size: 0.7rem; margin-top: 0.2rem;"><?= htmlspecialchars($row['deleted_at'] ?? '-') ?></div>
                    </td>
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem;">Terhapus</span>
                    </td>

                    <!-- Kolom Aksi (Restore & Permanent Delete) -->
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <div class="d-flex justify-content-center gap-1">
                            <!-- Tombol Restore -->
                            <a class="btn btn-sm btn-outline-info border-0 rounded-2 text-info" 
                               title="Restore / Pulihkan Produk"
                               href="products.php?action_restore=<?= $row['id'] ?>&view=trash">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>

                            <!-- Tombol Hapus Permanen -->
                            <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Hapus Permanen"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalConfirmPermanentDelete" 
                                    onclick="document.getElementById('permanent_delete_target_name').innerText = '<?php echo addslashes($row['name']); ?>'; document.getElementById('btnConfirmPermanentDeleteAction').setAttribute('href', 'products.php?action_permanent_delete=<?= $row['id'] ?>&view=trash')">
                                <i class="bi bi-trash3-fill"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="13" class="text-center py-5 text-muted shadow-none" style="background: transparent !important; border: none !important;">
                        <i class="bi bi-trash3 d-block mb-2" style="font-size: 2rem; color: rgba(148, 163, 184, 0.4);"></i>
                        <h6 class="text-white-50">Recycle Bin kosong</h6>
                        <p class="text-white-50 small">Tidak ada produk yang sudah dihapus.</p>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

    </div>
</main>

<!-- MODAL FORM CRUD (TAMBAH / UBAH DATA PRODUK) -->
<div class="modal fade" id="modalProduct" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form action="products.php" method="POST" id="formProduct" enctype="multipart/form-data" class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(10px); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb; border-radius: 16px;">
            
            <div class="modal-header border-0 pb-0" style="padding: 1.5rem 1.5rem 0 1.5rem;">
                <h5 class="fw-bold text-white m-0" id="modalProductLabel">Tambah Produk Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Input Hidden ID untuk Operasi Update -->
                <input type="hidden" name="id" id="product_id">
                <div id="product_action_flag"></div>
                
                <div class="row g-3">
                    <!-- Pilih Tenant -->
                    <div class="col-md-6">
                        <label class="form-label small text-white-50 fw-medium">Tenant / Toko</label>
                        <select name="tenant_id" id="product_tenant_id" class="form-select bg-dark text-white border-secondary rounded-3" style="background-color: rgba(2, 6, 23, 0.4) !important;" required>
                            <option value="">-- Pilih Tenant --</option>
                            <?php foreach ($listTenants as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Pilih Kategori -->
                    <div class="col-md-6">
                        <label class="form-label small text-white-50 fw-medium">Kategori Produk</label>
                        <select name="category_id" id="product_category_id" class="form-select bg-dark text-white border-secondary rounded-3" style="background-color: rgba(2, 6, 23, 0.4) !important;" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($listCategories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Pilih Brand -->
                    <div class="col-md-6">
                        <label class="form-label small text-white-50 fw-medium">Brand / Merek (Opsional)</label>
                        <select name="brand_id" id="product_brand_id" class="form-select bg-dark text-white border-secondary rounded-3" style="background-color: rgba(2, 6, 23, 0.4) !important;">
                            <option value="">Tanpa Merek</option>
                            <?php foreach ($listBrands as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Pilih Satuan -->
                    <div class="col-md-6">
                        <label class="form-label small text-white-50 fw-medium">Satuan / Unit</label>
                        <select name="unit_id" id="product_unit_id" class="form-select bg-dark text-white border-secondary rounded-3" style="background-color: rgba(2, 6, 23, 0.4) !important;" required>
                            <option value="">-- Pilih Satuan --</option>
                            <?php foreach ($listUnits as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- SKU & Barcode -->
                    <div class="col-md-6">
                        <label class="form-label small text-white-50 fw-medium">SKU</label>
                        <input type="text" name="sku" id="product_sku" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none;" placeholder="Contoh: SKU-FOOD-001">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-white-50 fw-medium">Barcode</label>
                        <input type="text" name="barcode" id="product_barcode" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none;" placeholder="Contoh: 899123...">
                    </div>
                    
                    <!-- Nama & Deskripsi -->
                    <div class="col-12">
                        <label class="form-label small text-white-50 fw-medium">Nama Produk</label>
                        <input type="text" name="name" id="product_name" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none;" required placeholder="Contoh: Nasi Goreng Spesial">
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-white-50 fw-medium">Deskripsi Pendek</label>
                        <textarea name="description" id="product_description" rows="2" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none;" placeholder="Keterangan isi porsi produk..."></textarea>
                    </div>

                    <!-- Harga Jual, Stok & Status -->
                    <div class="col-md-4">
                        <label class="form-label small text-white-50 fw-medium">Harga Jual (Rp)</label>
                        <input type="number" step="any" name="base_price" id="product_base_price" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none;" required placeholder="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-white-50 fw-medium">Stok Awal</label>
                        <input type="number" name="stock" id="product_stock" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none;" required value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-white-50 fw-medium">Status</label>
                        <select name="status" id="product_status" class="form-select bg-dark text-white border-secondary rounded-3" style="background-color: rgba(2, 6, 23, 0.4) !important;">
                            <option value="1">Aktif (Tersedia)</option>
                            <option value="0">Nonaktif (Habis)</option>
                        </select>
                    </div>

                    <!-- Input Gambar & Opsi Hapus Gambar Saat Ini -->
                    <div class="col-12">
                        <label class="form-label small text-white-50 fw-medium">Foto Produk (JPG, PNG, WEBP)</label>
                        <input type="file" name="image" id="product_image_file" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none;">
                        <div id="container_hapus_image" class="mt-2 form-check" style="display: none;">
                            <input type="checkbox" name="delete_current_image" value="1" id="delete_current_image" class="form-check-input">
                            <label class="form-check-label text-warning small" for="delete_current_image">Centang untuk menghapus foto produk saat ini</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer border-0 pt-0 d-flex gap-2 justify-content-end" style="padding: 0 1.5rem 1.5rem 1.5rem;">
                <button type="button" class="btn btn-sm btn-secondary rounded-3 px-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
                <button type="submit" name="action_submit" id="btnSubmitProduct" class="btn btn-sm btn-success rounded-3 px-3 py-2 fw-medium">Simpan Data</button>
            </div>
            
        </form>
    </div>
</div>

<!-- Modal Konfirmasi Hapus Produk (Soft Delete) -->
<div class="modal fade" id="modalConfirmDelete" tabindex="-1" aria-labelledby="modalConfirmDeleteLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-bg-dark border-secondary" style="background-color: #111827 !important; border-color: #374151 !important; border-radius: 16px;">
      
      <!-- Bagian Atas / Header Modal -->
      <div class="modal-header border-bottom border-secondary">
        <h5 class="modal-title text-white fw-bold d-flex align-items-center" id="modalConfirmDeleteLabel">
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
        <p class="text-white-50 fs-6 mb-1">Apakah Anda yakin ingin memindahkan produk ini ke Recycle Bin?</p>
        <!-- Tempat nama produk akan muncul secara dinamis -->
        <h6 id="delete_target_name" class="text-warning fw-bold mt-2"></h6>
        <p class="text-white-50 small mt-2">Produk masih bisa dipulihkan kembali dari Recycle Bin.</p>
      </div>
      
      <!-- Bagian Bawah / Tombol Aksi -->
      <div class="modal-footer border-top border-secondary justify-content-center">
        <button type="button" class="btn btn-sm btn-secondary px-4 rounded-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
        
        <!-- PERBAIKAN: Mengganti button menjadi tag <a> agar fungsi setAttribute('href') dari tabel bekerja sempurna -->
        <a id="btnConfirmDeleteAction" href="#" class="btn btn-sm btn-danger px-4 rounded-3 py-2 fw-bold d-inline-flex align-items-center justify-content-center">Ya, Pindahkan</a>
      </div>

    </div>
  </div>
</div>

<!-- Modal Konfirmasi Hapus Permanen -->
<div class="modal fade" id="modalConfirmPermanentDelete" tabindex="-1" aria-labelledby="modalConfirmPermanentDeleteLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-bg-dark border-secondary" style="background-color: #111827 !important; border-color: #374151 !important; border-radius: 16px;">
      
      <!-- Bagian Atas / Header Modal -->
      <div class="modal-header border-bottom border-secondary">
        <h5 class="modal-title text-white fw-bold d-flex align-items-center" id="modalConfirmPermanentDeleteLabel">
          <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Hapus Permanen
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <!-- Bagian Tengah / Isi Modal -->
      <div class="modal-body text-center p-4">
        <div class="mb-3">
          <i class="bi bi-trash3-fill text-danger" style="font-size: 3.5rem;"></i>
        </div>
        <p class="text-white-50 fs-6 mb-1">Produk ini akan dihapus secara <strong class="text-danger">PERMANEN</strong>!</p>
        <h6 id="permanent_delete_target_name" class="text-warning fw-bold mt-2"></h6>
        <p class="text-white-50 small mt-2">Data dan gambar produk akan dihapus selamanya. Tindakan ini <strong class="text-danger">tidak dapat</strong> dibatalkan.</p>
      </div>
      
      <!-- Bagian Bawah / Tombol Aksi -->
      <div class="modal-footer border-top border-secondary justify-content-center">
        <button type="button" class="btn btn-sm btn-secondary px-4 rounded-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
        <a id="btnConfirmPermanentDeleteAction" href="#" class="btn btn-sm btn-danger px-4 rounded-3 py-2 fw-bold d-inline-flex align-items-center justify-content-center" style="background-color: #dc2626 !important;">
          <i class="bi bi-trash3-fill me-1"></i> Ya, Hapus Permanen
        </a>
      </div>

    </div>
  </div>
</div>

<script>
// Variabel global penampung instans modal
let bootstrapProductModalInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    // ==========================================
    // 1. LOGIKA DRAG TO SCROLL MOUSE (PRODUK)
    // ==========================================
    const prodSlider = document.getElementById('dragScrollProductContainer');
    if (prodSlider) {
        let isDown = false;
        let startX, scrollLeft;
        
        prodSlider.addEventListener('mousedown', (e) => {
            // Cegah interupsi drag jika pengguna mengklik tombol atau input dalam tabel
            if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input') || e.target.closest('select')) return;
            
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
            // Angka 2 merupakan akselerasi sensitivitas pergeseran mouse
            prodSlider.scrollLeft = scrollLeft - ((x - startX) * 2);
        });
    }
});

// ==========================================
// 2. LOGIKA FORM MODAL TAMBAH PRODUK
// ==========================================
function openTambahProduct() {
    const formProd = document.getElementById('formProduct');
    if (formProd) formProd.reset();
    
    document.getElementById('modalProductLabel').innerText = 'Tambah Produk Baru';
    document.getElementById('product_id').value = '';
    
    // Reset dropdown pilihan ke default kosong
    document.getElementById('product_tenant_id').value = '';
    document.getElementById('product_category_id').value = '';
    document.getElementById('product_brand_id').value = '';
    document.getElementById('product_unit_id').value = '';
    
    // Setel ulang tombol submit
    const btnSubmit = document.getElementById('btnSubmitProduct');
    if (btnSubmit) {
        btnSubmit.setAttribute('name', 'action_add_product');
        btnSubmit.className = "btn btn-sm btn-success rounded-3 px-3 py-2 fw-medium";
        btnSubmit.innerText = "Simpan Data";
    }
    
    if (document.getElementById('container_hapus_image')) {
        document.getElementById('container_hapus_image').style.display = 'none';
        document.getElementById('delete_current_image').checked = false;
    }
}

// ==========================================
// 3. LOGIKA FORM MODAL EDIT PRODUK (POPULATE)
// ==========================================
function openEditProduct(data) {
    if (data) {
        document.getElementById('modalProductLabel').innerText = 'Ubah Data Produk';
        document.getElementById('product_id').value = data.id;
        document.getElementById('product_tenant_id').value = data.tenant_id;
        document.getElementById('product_category_id').value = data.category_id;
        document.getElementById('product_brand_id').value = data.brand_id ? data.brand_id : '';
        document.getElementById('product_unit_id').value = data.unit_id ? data.unit_id : '';
        document.getElementById('product_sku').value = data.sku ?? '';
        document.getElementById('product_barcode').value = data.barcode ?? '';
        document.getElementById('product_name').value = data.name ?? '';
        document.getElementById('product_description').value = data.description ?? '';
        document.getElementById('product_base_price').value = data.base_price ?? 0;
        document.getElementById('product_stock').value = data.stock ?? 0;
        document.getElementById('product_status').value = data.status ?? 1;
        
        // Ubah tombol submit menjadi mode update
        const btnSubmit = document.getElementById('btnSubmitProduct');
        if (btnSubmit) {
            btnSubmit.setAttribute('name', 'action_update_product');
            btnSubmit.className = "btn btn-sm btn-warning text-dark rounded-3 px-3 py-2 fw-semibold";
            btnSubmit.innerText = "Simpan Perubahan";
        }
        
        // Tampilkan checkbox hapus gambar jika produk memiliki gambar aktif
        if (data.image && data.image !== "" && document.getElementById('container_hapus_image')) {
            document.getElementById('container_hapus_image').style.display = 'block';
            document.getElementById('delete_current_image').checked = false;
        } else if (document.getElementById('container_hapus_image')) {
            document.getElementById('container_hapus_image').style.display = 'none';
        }
        
        // Munculkan bootstrap modal produk secara aman
        const modalProdEl = document.getElementById('modalProduct');
        if (modalProdEl) {
            const instance = bootstrap.Modal.getOrCreateInstance(modalProdEl);
            instance.show();
        }
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

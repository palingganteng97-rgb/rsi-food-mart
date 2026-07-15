<?php
//home.php
include "db.php";

// session_start sudah dipanggil di db.php

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$userName = $_SESSION['name'] ?? 'Pasien';

// --- TAMBAHKAN QUERY INI UNTUK MENGHITUNG KELUARAN JUMLAH ITEM AWAL ---
$patient_session_id = $_SESSION['patient_session_id'] ?? 1;
$countCartQuery = mysqli_query($conn, "SELECT SUM(ci.qty) AS total_items FROM cart_items ci JOIN carts c ON ci.cart_id = c.id WHERE c.patient_session_id = $patient_session_id");
$countCartData = mysqli_fetch_assoc($countCartQuery);
$initialCartCount = (int)($countCartData['total_items'] ?? 0);
// --------------------------------------------------------------------

// AMBIL DATA UTAMA PRODUK UNTUK ETALASE HOME
$listActiveProducts = [];
$sql = "SELECT p.*, c.name AS category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.status = 1 AND p.deleted_at IS NULL AND p.stock > 0
        ORDER BY p.id DESC";

$fetchQuery = mysqli_query($conn, $sql);
if ($fetchQuery) {
    while ($row = mysqli_fetch_assoc($fetchQuery)) {
        $listActiveProducts[] = $row;
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
    
    .modal-dialog { max-width: 450px !important; }
    .modal-body::-webkit-scrollbar { display: none !important; width: 0 !important; height: 0 !important; }
    .modal-body { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow: hidden !important; }
    
    .bi-clock-history, .text-white-icon { color: #ffffff !important; opacity: 1 !important; filter: drop-shadow(0 0 1px rgba(255,255,255,0.2)); }
    input[type="time"]::-webkit-calendar-picker-indicator,
    input[type="date"]::-webkit-calendar-picker-indicator {filter: invert(1) brightness(100%) contrast(100%) !important;cursor: pointer;}
    @media (min-width: 992px) { main.content-shift { margin-left: 280px; } .bottom-nav { display:none; } }

    /* ========================================================
       TAMBAHAN BARU: ANIMASI KARTU MELUNCUR TERBANG KE KERANJANG
       ======================================================== */
    .flying-cart-item {
        position: fixed;
        z-index: 9999;
        top: 0;
        left: 0;
        pointer-events: none; /* Mencegah elemen mengganggu interaksi klik user */
        object-fit: cover;
        border-radius: 14px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
        transition: all 0.75s cubic-bezier(0.25, 1, 0.5, 1);
        opacity: 1;
    }
</style>

</head>
<body>
  <?php require __DIR__ . '/sidebar.php'; ?>

<main class="content-shift page-body">
    <div class="container py-3">
        <!-- HEADER ETALASE MENU -->
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <div class="fw-bold fs-5">Etalase Menu</div>
                <div class="text-white-50" style="font-size:.9rem;">Halo, <?php echo htmlspecialchars($userName); ?></div>
            </div>
            <div class="d-none d-md-flex gap-2 align-items-center">
                <span class="text-white-50">Diet hari ini:</span>
                <span class="pill diet-pill">Sehat</span>
            </div>
        </div>

        <!-- FITUR PENCARIAN & TOMBOL FILTER DIET -->
        <div class="search-box p-3 mb-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-7">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0 text-white-50">
                            <i class="bi bi-search"></i>
                        </span>
                        <input id="searchInput" type="text" class="form-control bg-transparent text-white border-0" placeholder="Cari nama menu..." autocomplete="off" />
                        <button class="btn btn-outline-secondary rounded-3" type="button" onclick="resetFilters()">
                            <i class="bi bi-x-circle me-1"></i> Reset
                        </button>
                    </div>
                </div>
                <div class="col-12 col-md-5">
                    <div class="d-flex flex-wrap gap-2 justify-content-md-end mt-2 mt-md-0">
                        <button type="button" class="btn btn-sm diet-pill" data-filter="" data-active="true" onclick="setDietFilter('')">Semua</button>
                        <button type="button" class="btn btn-sm diet-pill" data-filter="Lunak" data-active="false" onclick="setDietFilter('Lunak')">Lunak</button>
                        <button type="button" class="btn btn-sm diet-pill" data-filter="Rendah Garam" data-active="false" onclick="setDietFilter('Rendah Garam')">Rendah Garam</button>
                        <button type="button" class="btn btn-sm diet-pill" data-filter="Diabetes" data-active="false" onclick="setDietFilter('Diabetes')">Diabetes</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- GRID INTEGRASI DAFTAR PRODUK MAKANAN SEHAT -->
        <div id="catalogGrid" class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3 mt-2 mb-5">
            <?php if (!empty($listActiveProducts)): foreach ($listActiveProducts as $prod): ?>
                <div class="col">
                    <div class="card-food h-100 d-flex flex-column text-white" 
                         data-title="<?= htmlspecialchars($prod['name']) ?>" 
                         data-diet="<?= htmlspecialchars($prod['category_name'] ?? '') ?>"
                         role="button"
                         onclick='openDetailProduct(<?= json_encode($prod) ?>)'
                         style="cursor: pointer;">
                        
                        <!-- Area Gambar Produk -->
                        <div class="food-img">
                            <?php if (!empty($prod['image']) && file_exists("uploads/products/" . $prod['image'])): ?>
                                <img src="uploads/products/<?= htmlspecialchars($prod['image']) ?>" alt="<?= htmlspecialchars($prod['name']) ?>">
                            <?php else: ?>
                                <div class="text-center p-3 text-muted">
                                    <i class="bi bi-egg-fried d-block mb-1" style="font-size: 2.5rem; color: rgba(148,163,184,.3);"></i>
                                    <span style="font-size: 0.75rem;">No Image</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Detail Info Produk -->
                        <div class="p-3 d-flex flex-column flex-grow-1 justify-content-between">
                            <div>
                                <span class="text-muted d-block mb-1" style="font-size: 0.75rem; text-transform: uppercase;">
                                    <?= htmlspecialchars($prod['category_name'] ?? 'General') ?>
                                </span>
                                <h6 class="fw-bold m-0 text-white" style="font-size: 0.95rem; line-height: 1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; text-overflow:ellipsis;">
                                    <?= htmlspecialchars($prod['name']) ?>
                                </h6>
                            </div>

                            <!-- Harga & Tombol Aksi Tambah Pesanan -->
                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2" style="border-top: 1px solid rgba(148, 163, 184, .1);">
                                <div class="fw-bold text-success" style="font-size: 1rem;">
                                    Rp <?= number_format($prod['base_price'], 0, ',', '.') ?>
                                </div>
                            <button type="button" class="btn btn-sm btn-success rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Tambah ke Keranjang" 
                                    onclick="event.stopPropagation(); tambahKeKeranjang(<?= (int)$prod['id'] ?>, '<?= addslashes(htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8')) ?>', <?= floatval($prod['base_price']) ?>, '<?= addslashes($prod['image'] ?? '') ?>', '')">
                                <i class="bi bi-plus-lg" style="font-size: 0.85rem;"></i>
                            </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="col-12 text-center py-5 w-100">
                    <div class="p-4 rounded-4" style="background: rgba(30, 41, 59, 0.4); border: 1px solid rgba(148, 163, 184, 0.15);">
                        <i class="bi bi-inboxes d-block mb-3" style="font-size: 3rem; color: #94a3b8; opacity: 0.8;"></i>
                        <h5 class="fw-semibold text-white mb-1" style="font-size: 1.1rem;">Menu Belum Tersedia</h5>
                        <p class="m-0 text-white-50" style="font-size: 0.88rem;">
                            Belum ada menu makanan sehat yang dirilis pada kategori etalase ini saat ini.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Panggil file komponen HTML modal kustom -->
<?php include 'detail_product_modal.php'; ?>

  <?php include "bottom_nav.php"; ?>

<!-- Hubungkan ke file eksternal JavaScript catalog handler -->
<script src="catalog_handler.js?v=1.1"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


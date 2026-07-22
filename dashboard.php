<?php
// dashboard.php - Halaman utama Admin (modul admin standalone)
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userName = $_SESSION['name'] ?? 'Admin';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Dashboard - RSI Food &amp; Mart</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

<style>
    :root { --bg:#0f172a; --text:#e5e7eb; --muted:#94a3b8; --green:#22c55e; }
    /* Mengunci overflow-x pada body dan html agar tidak bisa digeser ke kanan */
    html, body { background: var(--bg) !important; color: var(--text); overflow-x: hidden; width: 100%; }
    
    @media (min-width: 992px) { 
        main.content-shift { margin-left: 280px; }
        .bottom-nav { display:none; }
    }
    @media (max-width: 991.98px) {
        main.content-shift { margin-left: 0; padding-bottom: 90px !important; }
    }
    
    /* Memastikan kata-kata panjang di dalam tombol pecah baris dan tidak memaksa container melebar */
    .btn, .card, .fw-bold { word-break: break-word; overflow-wrap: break-word; }
</style>
</head>
<body>
    <?php require __DIR__ . '/sidebar.php'; ?>

<main class="content-shift p-3 p-md-4">
    <!-- Menggunakan container-fluid dengan padding 0 untuk memastikan elemen melekat sempurna pada grid -->
    <div class="container-fluid p-0" style="max-width: 1200px;">
        
        <!-- HEADER UTAMA -->
        <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148,163,184,.15); width: 100%;">
            <div class="w-100">
                <h2 class="fw-bold m-0 fs-3 fs-md-2">Dashboard Admin</h2>
                <div class="text-white-50" style="font-size:.9rem;">Halo, <?php echo htmlspecialchars($userName); ?></div>
            </div>
            <!-- Tombol aksi dikunci menggunakan w-100 w-sm-auto agar pas di layar kecil -->
            <div class="d-flex gap-2 w-100 w-sm-auto">
                <a href="orders.php" class="btn btn-success rounded-3 fw-medium d-flex align-items-center justify-content-center gap-2 flex-grow-1 flex-sm-grow-0 px-2 py-2" style="font-size: 0.85rem; min-width: 0;">
                    <i class="bi bi-receipt-cutoff"></i> <span class="text-nowrap">Lihat Pesanan</span>
                </a>
                <a href="products.php" class="btn btn-outline-success rounded-3 fw-medium d-flex align-items-center justify-content-center gap-2 flex-grow-1 flex-sm-grow-0 px-2 py-2" style="font-size: 0.85rem; min-width: 0;">
                    <i class="bi bi-bag"></i> <span class="text-nowrap">Kelola Produk</span>
                </a>
            </div>
        </div>

        <!-- GRID CARD KONTROL -->
        <div class="row g-3 m-0" style="width: 100%;">
            <!-- Card Katalog Pasien -->
            <div class="col-100 col-md-6 col-lg-4 px-0 pe-md-2 pb-2">
                <div class="card h-100" style="background: rgba(15,23,42,.55); border:1px solid rgba(148,163,184,.2); border-radius: 18px;">
                    <div class="card-body d-flex flex-column justify-content-between p-3">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <div class="text-white-50 small">Menu</div>
                                <div class="fw-bold fs-5">Katalog Pasien</div>
                            </div>
                            <i class="bi bi-shop" style="font-size: 1.6rem; color: var(--green);"></i>
                        </div>
                        <div class="mt-4">
                            <a href="home.php?preview=1" class="btn btn-success w-100 rounded-3 fw-medium py-2" style="font-size: 0.9rem;">
                                Buka Home Pasien
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Tracking / Status -->
            <div class="col-100 col-md-6 col-lg-4 px-0 px-md-1 pb-2">
                <div class="card h-100" style="background: rgba(15,23,42,.55); border:1px solid rgba(148,163,184,.2); border-radius: 18px;">
                    <div class="card-body d-flex flex-column justify-content-between p-3">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <div class="text-white-50 small">Pesanan</div>
                                <div class="fw-bold fs-5">Tracking / Status</div>
                            </div>
                            <i class="bi bi-clock-history" style="font-size: 1.6rem; color: var(--green);"></i>
                        </div>
                        <div class="mt-4">
                            <a href="orders.php" class="btn btn-outline-success w-100 rounded-3 fw-medium py-2" style="font-size: 0.9rem;">
                                Kelola Pesanan
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Master Data -->
            <div class="col-100 col-md-12 col-lg-4 px-0 ps-lg-2 pb-2">
                <div class="card h-100" style="background: rgba(15,23,42,.55); border:1px solid rgba(148,163,184,.2); border-radius: 18px;">
                    <div class="card-body d-flex flex-column justify-content-between p-3">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <div class="text-white-50 small">Master Data</div>
                                <div class="fw-bold fs-5">Kategori, Brand, Units</div>
                            </div>
                            <i class="bi bi-layers-half" style="font-size: 1.6rem; color: var(--green);"></i>
                        </div>
                        <div class="mt-4">
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="categories.php" class="btn btn-outline-success rounded-3 fw-medium flex-grow-1 text-center py-2 px-1" style="font-size: 0.85rem; min-width: 70px;">Kategori</a>
                                <a href="brands.php" class="btn btn-outline-success rounded-3 fw-medium flex-grow-1 text-center py-2 px-1" style="font-size: 0.85rem; min-width: 70px;">Brand</a>
                                <a href="units.php" class="btn btn-outline-success rounded-3 fw-medium flex-grow-1 text-center py-2 px-1" style="font-size: 0.85rem; min-width: 70px;">Units</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

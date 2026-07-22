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
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard - RSI Food &amp; Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

<style>
    :root { --bg:#0f172a; --text:#e5e7eb; --muted:#94a3b8; --green:#22c55e; }
    body { background: var(--bg) !important; color: var(--text); }
    @media (min-width: 992px) { 
        main.content-shift { margin-left: 280px; }
        .bottom-nav { display:none; }
    }
</style>
</head>
<body>
    <?php require __DIR__ . '/sidebar.php'; ?>

<main class="content-shift p-4">
    <div class="container-fluid" style="max-width: 1200px;">
        <div class="d-flex align-items-center justify-content-between mb-4 pb-3" style="border-bottom: 1px solid rgba(148,163,184,.15);">
            <div>
                <h2 class="fw-bold m-0">Dashboard Admin</h2>
                <div class="text-white-50" style="font-size:.9rem;">Halo, <?php echo htmlspecialchars($userName); ?></div>
            </div>
            <div class="d-flex gap-2">
                <a href="orders.php" class="btn btn-success rounded-3 fw-medium d-flex align-items-center gap-2">
                    <i class="bi bi-receipt-cutoff"></i> Lihat Pesanan
                </a>
                <a href="products.php" class="btn btn-outline-success rounded-3 fw-medium d-flex align-items-center gap-2">
                    <i class="bi bi-bag"></i> Kelola Produk
                </a>
            </div>
        </div>

        <div class="row g-3">
            <!-- Card Katalog Pasien -->
            <div class="col-md-6 col-lg-4">
                <div class="card" style="background: rgba(15,23,42,.55); border:1px solid rgba(148,163,184,.2); border-radius: 18px;">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <div class="text-white-50 small">Menu</div>
                                <div class="fw-bold fs-5">Katalog Pasien</div>
                            </div>
                            <i class="bi bi-shop" style="font-size: 1.6rem; color: var(--green);"></i>
                        </div>
                        <div class="mt-3">
                            <!-- PERBAIKAN: Tombol sekarang mengarah ke home.php dengan style hijau Bootstrap 5 -->
                            <a href="home.php" class="btn btn-success w-100 rounded-3 fw-medium">
                                Buka Home Pasien
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card" style="background: rgba(15,23,42,.55); border:1px solid rgba(148,163,184,.2); border-radius: 18px;">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <div class="text-white-50 small">Pesanan</div>
                                <div class="fw-bold fs-5">Tracking / Status</div>
                            </div>
                            <i class="bi bi-clock-history" style="font-size: 1.6rem; color: var(--green);"></i>
                        </div>
                        <div class="mt-3">
                            <a href="orders.php" class="btn btn-outline-success w-100 rounded-3 fw-medium">
                                Kelola Pesanan
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-lg-4">
                <div class="card" style="background: rgba(15,23,42,.55); border:1px solid rgba(148,163,184,.2); border-radius: 18px;">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <div class="text-white-50 small">Master Data</div>
                                <div class="fw-bold fs-5">Kategori, Brand, Units</div>
                            </div>
                            <i class="bi bi-layers-half" style="font-size: 1.6rem; color: var(--green);"></i>
                        </div>
                        <div class="mt-3 d-flex gap-2 flex-wrap">
                            <a href="categories.php" class="btn btn-outline-success rounded-3 fw-medium">Kategori</a>
                            <a href="brands.php" class="btn btn-outline-success rounded-3 fw-medium">Brand</a>
                            <a href="units.php" class="btn btn-outline-success rounded-3 fw-medium">Units</a>
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


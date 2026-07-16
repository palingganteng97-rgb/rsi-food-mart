<?php
// order_items.php - Menampilkan Detail Item di dalam Pesanan (Read-Only)
include 'db.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$status = isset($_GET['status']) ? $_GET['status'] : "";
$msg    = isset($_GET['msg']) ? $_GET['msg'] : "";

// Mengambil Semua Data Item Pesanan beserta Nama Produk dari Carts/Checkout
$query = "SELECT oi.*, p.name AS product_name 
          FROM order_items oi 
          JOIN products p ON oi.product_id = p.id 
          ORDER BY oi.id DESC";
$result = mysqli_query($conn, $query);
if (!$result) {
    die("Gagal mengambil data pesanan: " . mysqli_error($conn));
}
$order_items = mysqli_fetch_all($result, MYSQLI_ASSOC);
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
    
    /* MODIFIKASI: Optimasi Tabel Transparan & Penghancur Latar Putih Default Bootstrap */
    .table-transparent { background: transparent !important; }
    .table-transparent thead { background: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important; }
    .table-transparent th, .table-transparent td, .table-transparent tr { background: transparent !important; background-color: transparent !important; color: #fff !important; }
    .table-transparent tbody tr { border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; }
    .table-transparent * { color: #fff !important; }
    
    #ordersTableWrap::-webkit-scrollbar, #dragScrollUserContainer::-webkit-scrollbar, #dragScrollContainer::-webkit-scrollbar, .drag-scroll-container::-webkit-scrollbar { display: none !important; }
    #ordersTableWrap, #dragScrollUserContainer, #dragScrollContainer, .drag-scroll-container { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow-x: auto !important; cursor: grab !important; border: none !important; box-shadow: none !important; -webkit-box-shadow: none !important; }
    #ordersTableWrap:active, #dragScrollUserContainer:active, #dragScrollContainer:active, .drag-scroll-container:active { cursor: grabbing !important; }
    #ordersTableWrap table, #dragScrollUserContainer table, #dragScrollContainer table, .drag-scroll-container table { border-collapse: collapse !important; border: none !important; }
    #ordersTableWrap table th, #ordersTableWrap table td, #dragScrollUserContainer table th, #dragScrollUserContainer table td, #dragScrollContainer table th, #dragScrollContainer table td, .drag-scroll-container table th, .drag-scroll-container table td { border-left: none !important; border-right: none !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; }
    
    .text-white-element { -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }
    .modal-lg-custom { max-width: 800px !important; }
    .modal-body::-webkit-scrollbar { display: none !important; }
    .modal-body { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow: visible !important; }
    .bi-clock-history, .text-white-icon { color: #ffffff !important; opacity: 1 !important; filter: drop-shadow(0 0 1px rgba(255,255,255,0.2)); }
    input[type="time"]::-webkit-calendar-picker-indicator,
    input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1) brightness(100%) contrast(100%) !important; cursor: pointer; }
    @media (min-width: 992px) { main.content-shift { margin-left: 280px; } .bottom-nav { display:none; } }
</style>

</head>
<body>
  <?php require __DIR__ . '/sidebar.php'; ?>

<!--- MAIN KONTEN LIHAT ITEM PESANAN --->
<main class="content-shift p-4">
    <!-- Container tabel dengan tema gelap transparan -->
    <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
        
        <!-- HEADER TABEL -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
            <div>
                <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Detail Item Pesanan</h2>
            </div>
        </div>

        <!-- STRUKTUR TABEL LIST DATA ORDER ITEMS (DENGAN DRAG-SCROLL CURSOR) -->
        <div id="ordersTableWrap" class="table-responsive rounded-3" style="border: none !important; cursor: grab; user-select: none; -webkit-overflow-scrolling: touch;">
            <table class="table table-hover align-middle mb-0 text-white table-transparent" style="min-width: 1100px; width: 100% !important; table-layout: auto !important; border-collapse: collapse !important;">
                <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
                    <tr style="background: transparent !important;">
                        <th class="py-3 px-3 text-center text-white" style="background: transparent !important; width: 100px;">ID Item</th>
                        <th class="py-3 text-white" style="background: transparent !important; width: 150px;">Order ID</th>
                        <th class="py-3 text-white" style="background: transparent !important;">Nama Produk</th>
                        <th class="py-3 text-center text-white" style="background: transparent !important; width: 120px;">Qty</th>
                        <th class="py-3 text-end text-white" style="background: transparent !important; width: 180px;">Harga Satuan</th>
                        <th class="py-3 text-end text-white" style="background: transparent !important; width: 200px;">Subtotal</th>
                        <th class="py-3 text-white" style="background: transparent !important;">Catatan Pembeli</th>
                    </tr>
                </thead>
                <tbody style="background: transparent !important;">
                    <?php if (!empty($order_items)): foreach ($order_items as $row): 
                        $subtotal = (float)$row['qty'] * (float)$row['price'];
                    ?>
                        <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; font-size: 0.88rem; background: transparent !important;">
                            <!-- Kolom ID Item -->
                            <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important;">
                                <?= $row['id'] ?>
                            </td>
                            
                            <!-- Kolom Order ID -->
                            <td class="fw-semibold text-white font-monospace" style="background: transparent !important;">
                                #<?= htmlspecialchars($row['order_id']) ?>
                            </td>

                            <!-- Kolom Nama Produk -->
                            <td class="fw-semibold text-white" style="background: transparent !important;">
                                <div class="text-truncate" title="<?= htmlspecialchars($row['product_name']) ?>">
                                    <?= htmlspecialchars($row['product_name'] ?: 'Produk Telah Dihapus') ?>
                                </div>
                            </td>

                            <!-- Kolom Qty -->
                            <td class="text-center text-white" style="background: transparent !important;">
                                <span class="badge bg-dark text-white border border-secondary border-opacity-50 px-3 py-1.5" style="font-size: 0.85rem;">
                                    <?= htmlspecialchars($row['qty']) ?>
                                </span>
                            </td>

                            <!-- Kolom Harga Satuan -->
                            <td class="text-end text-white-50 font-monospace" style="background: transparent !important;">
                                Rp <?= number_format($row['price'], 0, ',', '.') ?>
                            </td>

                            <!-- Kolom Subtotal -->
                            <td class="text-end fw-bold text-success font-monospace" style="background: transparent !important;">
                                Rp <?= number_format($subtotal, 0, ',', '.') ?>
                            </td>

                            <!-- Kolom Catatan -->
                            <td class="text-white-50 small" style="background: transparent !important;">
                                <div class="text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($row['notes']) ?>">
                                    <?= !empty($row['notes']) ? htmlspecialchars($row['notes']) : '-' ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <!-- State Tampilan jika data kosong -->
                        <tr style="background: transparent !important;">
                            <td colspan="7" class="text-center py-5 shadow-none" style="background: transparent !important; color: #94a3b8 !important; border: none !important; font-size: 1rem;">
                                <i class="bi bi-inbox text-white-50 d-block mb-2" style="font-size: 2.2rem;"></i>
                                Belum ada riwayat produk yang dipesan dari keranjang belanja.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- MODAL KONFIRMASI HAPUS ITEM PESANAN -->
<div class="modal fade" id="modalConfirmDeleteOrderItem" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(10px); border: 1px solid rgba(220, 53, 69, 0.25); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-body p-4 text-center">
                <i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                <h5 class="fw-bold text-white mt-3">Hapus Item Pesanan?</h5>
                <p class="small text-white-50">Apakah Anda yakin ingin menghapus <span id="delete_item_display_name" class="fw-bold text-white"></span> dari daftar pesanan?</p>
                <div class="d-flex gap-2 justify-content-center mt-4">
                    <button type="button" class="btn btn-sm btn-secondary rounded-3 px-3" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
                    <a id="btnConfirmDeleteOrderItemAction" href="#" class="btn btn-sm btn-danger rounded-3 px-3 fw-medium">Hapus</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- LOGIKA JAVASCRIPT GLOBAL (DRAG SCROLL ONLY) -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const targets = ['#ordersTableWrap', '#dragScrollUserContainer', '#dragScrollContainer', '.drag-scroll-container'];
    
    targets.forEach(selector => {
        const slider = document.querySelector(selector);
        if (!slider) return;

        let isDown = false;
        let startX;
        let scrollLeft;

        slider.addEventListener('mousedown', (e) => {
            // PENGECUALIAN: Abaikan drag jika mengklik link, tombol atau select jika ada komponen navbar aktif
            if (e.target.closest('button') || e.target.closest('select') || e.target.closest('input') || e.target.closest('a') || e.target.closest('i')) {
                return;
            }
            
            isDown = true;
            slider.style.cursor = 'grabbing';
            startX = e.pageX - slider.offsetLeft;
            scrollLeft = slider.scrollLeft;
        });

        slider.addEventListener('mouseleave', () => {
            isDown = false;
            slider.style.cursor = 'grab';
        });

        slider.addEventListener('mouseup', () => {
            isDown = false;
            slider.style.cursor = 'grab';
        });

        slider.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            
            e.preventDefault(); // Mencegah pemblokiran teks saat digeser
            const x = e.pageX - slider.offsetLeft;
            const walk = (x - startX) * 1.5; // Sensitivitas pergeseran
            slider.scrollLeft = scrollLeft - walk;
        });
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

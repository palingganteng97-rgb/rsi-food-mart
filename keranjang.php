<?php
// keranjang.php
include 'db.php'; 

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// === LOGIKA PROSES HAPUS ITEM SINKRON DENGAN API_CART ===
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['key'])) {
    $delete_key = $_GET['key'];
    
    // Hapus data item berdasarkan key unik dari session array
    if (isset($_SESSION['cart'][$delete_key])) {
        unset($_SESSION['cart'][$delete_key]);
        
        // Redirect kembali ke keranjang.php agar URL bersih dan halaman ter-refresh
        header("Location: keranjang.php?status=success&msg=Item berhasil dihapus");
        exit();
    }
}

$status = isset($_GET['status']) ? $_GET['status'] : "";
$msg = isset($_GET['msg']) ? $_GET['msg'] : "";

// Mengambil data langsung dari session cart sesuai isi api_cart.php
$cart_items = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
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
    
    /* MODIFIKASI: Memperkecil lebar maksimal dialog agar proposional dengan detail hidangan */
    .modal-dialog { max-width: 450px !important; }
    .modal-body::-webkit-scrollbar { display: none !important; width: 0 !important; height: 0 !important; }
    
    /* PERBAIKAN UTAMA: Mengubah overflow: visible menjadi hidden agar mengunci paksa batang scroll tipis bawah gambar */
    .modal-body { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow: hidden !important; }
    
    .bi-clock-history, .text-white-icon { color: #ffffff !important; opacity: 1 !important; filter: drop-shadow(0 0 1px rgba(255,255,255,0.2)); }
    input[type="time"]::-webkit-calendar-picker-indicator,
    input[type="date"]::-webkit-calendar-picker-indicator {filter: invert(1) brightness(100%) contrast(100%) !important;cursor: pointer;}
    @media (min-width: 992px) { main.content-shift { margin-left: 280px; } .bottom-nav { display:none; } }
</style>

</head>
<body>

<div class="container my-5">
    <div class="row">
        <!-- Kolom Kiri: Daftar Produk -->
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold"><i class="bi bi-cart3 me-2 text-success"></i> Keranjang Belanja Anda</h4>
                <a href="home.php" class="btn btn-outline-light btn-sm rounded-pill px-3">← Kembali Belanja</a>
            </div>

            <?php 
            $subtotal = 0;
            $has_items = false;

            if (!empty($cart_items)): 
                foreach ($cart_items as $cartKey => $item): 
                    // Proteksi jika data session bukan array produk
                    if (!is_array($item)) {
                        continue;
                    }
                    $has_items = true;

                    $harga_satuan = isset($item['price']) ? floatval($item['price']) : 0;
                    $kuantitas = isset($item['qty']) ? intval($item['qty']) : 1;
                    $nama_menu = isset($item['name']) ? $item['name'] : 'Menu';
                    $gambar_menu = isset($item['image']) ? $item['image'] : '';

                    $total_per_item = $harga_satuan * $kuantitas;
                    $subtotal += $total_per_item;

                    // SINKRONISASI JALUR GAMBAR: Mengarah langsung ke folder uploads/products/ sesuai struktur VS Code Anda
                    $path_gambar = "uploads/products/" . $gambar_menu;

                    // Cadangan 1: Cek di sub-folder gallery jika tidak ada di folder produk utama
                    if (empty($gambar_menu) || !file_exists($path_gambar)) {
                        $path_gambar = "uploads/products/gallery/" . $gambar_menu;
                    }

                    // Cadangan 2: Jika file gambar di server tidak ada/kosong, panggil gambar ilustrasi default online
                    if (empty($gambar_menu) || !file_exists($path_gambar)) {
                        $path_gambar = "https://flaticon.com"; 
                    }
            ?>
                <!-- Item Card Produk -->
                <div class="card card-custom mb-3 rounded-3 p-3">
                    <div class="row align-items-center">
                        <!-- Gambar Produk -->
                        <div class="col-md-2 col-3">
                            <img src="<?php echo $path_gambar; ?>" class="img-fluid rounded-3" alt="Produk" style="object-fit: cover; height: 80px; width: 80px;">
                        </div>
                        
                        <!-- Detail Produk -->
                        <div class="col-md-4 col-9">
                            <h5 class="mb-1 fw-semibold text-white"><?php echo htmlspecialchars($nama_menu); ?></h5>
                            <p class="text-success small mb-1 fw-medium">Rp <?php echo number_format($harga_satuan, 0, ',', '.'); ?></p>
                            <?php if(!empty($item['notes'])): ?>
                                <span class="badge bg-secondary text-warning fw-normal small">
                                    <i class="bi bi-pencil-square me-1"></i> Catatan: <?php echo htmlspecialchars($item['notes']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Pengatur Kuantitas -->
                        <div class="col-md-3 col-6 my-2 my-md-0">
                            <div class="input-group input-group-sm" style="max-width: 130px;">
                                <button class="btn btn-outline-light" type="button">-</button>
                                <input type="number" class="form-control text-center bg-transparent text-white border-light" value="<?php echo $kuantitas; ?>" readonly>
                                <button class="btn btn-outline-light" type="button">+</button>
                            </div>
                        </div>
                        
                        <!-- Total Harga Item & Tombol Hapus -->
                        <div class="col-md-3 col-6 text-end">
                            <h5 class="text-success fw-bold mb-1">Rp <?php echo number_format($total_per_item, 0, ',', '.'); ?></h5>
                        <a href="keranjang.php?action=delete&key=<?php echo $cartKey; ?>" class="text-danger text-decoration-none p-0 small" onclick="return confirm('Apakah Anda yakin ingin menghapus item ini?')">
                            <i class="bi bi-trash3 me-1"></i> Hapus
                        </a>
                        </div>
                    </div>
                </div>
            <?php 
                endforeach; 
            endif; 

            if (!$has_items): 
            ?>
                <!-- Tampilan keranjang kosong dengan garis batas putus-putus tipis yang elegan -->
                <div class="bg-transparent text-center rounded-3 p-5" style="border: 2px dashed #334155;">
                    <i class="bi bi-basket2 text-success mb-3" style="font-size: 3rem;"></i>
                    <h5 class="text-white fw-medium mb-3">Keranjang belanja Anda masih kosong</h5>
                    <div>
                        <a href="home.php" class="btn btn-success btn-sm rounded-3 px-4 fw-medium">Mulai Belanja</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Kolom Kanan: Ringkasan Pembayaran -->
        <div class="col-lg-4">
            <!-- PERBAIKAN: Mengubah card-custom menjadi bg-transparent dengan border putus-putus senada -->
            <div class="bg-transparent rounded-3 p-4" style="border: 2px dashed #334155;">
                <h4 class="fw-bold mb-4 text-white">Ringkasan Pesanan</h4>
                
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-white-50">Subtotal Produk</span>
                    <span class="fw-semibold text-white">Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-4">
                    <span class="text-white-50">Pajak / Layanan</span>
                    <span class="fw-semibold text-white">Rp 0</span>
                </div>
                <hr class="border-secondary">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <span class="fw-bold fs-5 text-white">Total Bayar</span>
                    <span class="text-success fw-bold fs-4">Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                </div>
                
                <button class="btn btn-success w-100 py-2.5 fw-medium rounded-3" type="button" <?php echo ($subtotal == 0) ? 'disabled' : ''; ?>>
                    <i class="bi bi-credit-card-2-front me-2"></i> Lanjutkan Pemesanan
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

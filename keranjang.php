<?php
// keranjang.php
include 'db.php'; 

// 1. PERBAIKAN UTAMA: Wajib jalankan session_start() di baris pertama agar array cart terbaca
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// 2. HANDLER BARU: Logika proses tambah (+) dan kurang (-) kuantitas item keranjang
if (isset($_GET['action']) && $_GET['action'] === 'update_qty' && isset($_GET['key']) && isset($_GET['type'])) {
    $cartKey = $_GET['key'];
    $type = $_GET['type'];
    
    if (isset($_SESSION['cart'][$cartKey])) {
        if ($type === 'plus') {
            $_SESSION['cart'][$cartKey]['qty'] += 1;
        } elseif ($type === 'minus') {
            $_SESSION['cart'][$cartKey]['qty'] -= 1;
            // Jika kuantitas kurang dari 1, otomatis keluarkan hidangan dari keranjang
            if ($_SESSION['cart'][$cartKey]['qty'] < 1) {
                unset($_SESSION['cart'][$cartKey]);
            }
        }
        header("Location: keranjang.php");
        exit();
    }
}

// 3. HANDLER BARU: Logika memproses update data dari modal detail_product_modal.php
if (isset($_GET['action']) && $_GET['action'] === 'update_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cartKey = isset($_POST['cart_key']) ? $_POST['cart_key'] : '';
    $new_qty = isset($_POST['qty']) ? intval($_POST['qty']) : 1;
    $new_notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $new_variant = isset($_POST['variant']) ? $_POST['variant'] : '';
    $new_addons = isset($_POST['addons']) ? $_POST['addons'] : [];

    if (!empty($cartKey) && isset($_SESSION['cart'][$cartKey])) {
        // Melakukan update data pesanan di dalam session secara langsung
        $_SESSION['cart'][$cartKey]['qty'] = $new_qty;
        $_SESSION['cart'][$cartKey]['notes'] = $new_notes;
        
        // Opsional: Buka baris di bawah ini jika struktur session Anda mencatat varian & tambahan topping
        // $_SESSION['cart'][$cartKey]['variant'] = $new_variant;
        // $_SESSION['cart'][$cartKey]['addons'] = $new_addons;

        header("Location: keranjang.php?status=success&msg=Pesanan berhasil disesuaikan");
        exit();
    }
}

// === LOGIKA PROSES HAPUS ITEM SINKRON DENGAN API_CART ===
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['key'])) {
    $delete_key = $_GET['key'];
    
    if (isset($_SESSION['cart'][$delete_key])) {
        unset($_SESSION['cart'][$delete_key]);
        
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

                    $path_gambar = "uploads/products/" . $gambar_menu;

                    if (empty($gambar_menu) || !file_exists($path_gambar)) {
                        $path_gambar = "uploads/products/gallery/" . $gambar_menu;
                    }

                    if (empty($gambar_menu) || !file_exists($path_gambar)) {
                        $path_gambar = "uploads/products/default.png"; 
                    }
            ?>
                <!-- Item Card Bertema Premium Gelap Transparan Sesuai Etalase -->
                <div class="card mb-3 rounded-4 p-3 text-white" 
                     style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.15); backdrop-filter: blur(8px);">
                    <div class="row align-items-center">
                        <!-- Gambar Produk -->
                        <div class="col-md-2 col-3">
                            <div style="width: 80px; height: 80px; overflow: hidden; border-radius: 12px; border: 1px solid rgba(148, 163, 184, 0.1);">
                                <img src="<?php echo $path_gambar; ?>" class="w-100 h-100" style="object-fit: cover;" onerror="this.src='uploads/products/default.png'">
                            </div>
                        </div>
                        
                        <!-- Detail Produk -->
                        <div class="col-md-4 col-9">
                            <h5 class="mb-1 fw-bold text-white" style="font-size: 1.1rem;"><?php echo htmlspecialchars($nama_menu); ?></h5>
                            <p class="text-success small mb-1 fw-semibold">Rp <?php echo number_format($harga_satuan, 0, ',', '.'); ?></p>
                            <?php if(!empty($item['notes'])): ?>
                                <span class="badge bg-secondary text-warning fw-normal small" style="background: rgba(30, 41, 59, 0.7) !important; border: 1px solid rgba(148,163,184,0.1);">
                                    <i class="bi bi-pencil-square me-1"></i> Catatan: <?php echo htmlspecialchars($item['notes']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Logika Pengunci Tombol Minus saat Kuantitas bernilai 1 -->
                        <div class="col-md-3 col-6 my-2 my-md-0">
                            <div class="d-inline-flex align-items-center rounded-3 p-1" style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(148, 163, 184, 0.2);">
                                <?php if ($kuantitas <= 1): ?>
                                    <span class="btn btn-sm px-2 py-1 border-0 text-white-element" style="cursor: not-allowed; color: rgba(239, 68, 68, 0.45) !important;">
                                        <i class="bi bi-dash-lg" style="font-size: 0.85rem;"></i>
                                    </span>
                                <?php else: ?>
                                    <a href="keranjang.php?action=update_qty&key=<?php echo $cartKey; ?>&type=minus" class="btn btn-sm text-white px-2 py-1 border-0" style="box-shadow: none;">
                                        <i class="bi bi-dash-lg" style="font-size: 0.85rem;"></i>
                                    </a>
                                <?php endif; ?>

                                <span class="text-white fw-bold px-3 text-center" style="min-width: 35px; font-size: 0.95rem;">
                                    <?php echo $kuantitas; ?>
                                </span>
                                
                                <a href="keranjang.php?action=update_qty&key=<?php echo $cartKey; ?>&type=plus" class="btn btn-sm text-white px-2 py-1 border-0" style="box-shadow: none;">
                                    <i class="bi bi-plus-lg" style="font-size: 0.85rem;"></i>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Total Harga Item & Tombol Hapus Pemicu Modal -->
                        <div class="col-md-3 col-6 text-end">
                            <h5 class="text-success fw-bold mb-2" style="font-size: 1.2rem;">Rp <?php echo number_format($total_per_item, 0, ',', '.'); ?></h5>
                            
                            <div class="d-flex flex-column align-items-end gap-2">
                                <!-- PERBAIKAN: data-bs-target diselaraskan menjadi #modalDetailProduct -->
                                <button type="button" class="btn text-warning bg-transparent p-0 border-0 small fw-medium" 
                                        data-bs-toggle="modal" data-bs-target="#modalDetailProduct"
                                        onclick="loadEditModal('<?php echo isset($item['id']) ? $item['id'] : ''; ?>', '<?php echo $cartKey; ?>')" 
                                        style="box-shadow: none; font-size: 0.88rem;">
                                    <i class="bi bi-pencil-square me-1"></i> Edit Pesanan
                                </button>

                                <button type="button" class="btn text-danger bg-transparent p-0 border-0 small fw-medium" 
                                        data-bs-toggle="modal" data-bs-target="#modalConfirmDelete" 
                                        onclick="prepareDelete('<?php echo $cartKey; ?>', '<?php echo htmlspecialchars($nama_menu, ENT_QUOTES); ?>')" 
                                        style="box-shadow: none; font-size: 0.88rem;">
                                    <i class="bi bi-trash3-fill me-1"></i> Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php 
                endforeach; 
            endif; 

            if (!$has_items): 
            ?>
                <div class="bg-transparent text-center rounded-3 p-5" style="border: 2px dashed #334155;">
                    <i class="bi bi-basket2 text-success mb-3" style="font-size: 3rem;"></i>
                    <h5 class="text-white fw-medium mb-3">Keranjang belanja Anda masih kosong</h5>
                    <div>
                        <a href="home.php" class="btn btn-success btn-sm rounded-3 px-4 fw-medium">Mulai Belanja</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Kolom Kanan: Ringkasan Pembayaran (Melanjutkan Potongan Kode) -->
        <div class="col-lg-4">
            <div class="bg-transparent rounded-4 p-4" style="border: 2px dashed #334155;">
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

<style>
    /* Mengaktifkan fungsi gulir/scroll utama pada isi modal */
    #modalDetailProduct .modal-body {
        overflow-y: auto !important;
        padding: 0 !important;
        max-height: 80vh; /* Membatasi tinggi isi modal agar tidak melebihi layar HP */
    }
    
    /* DESKTOP LAYAR LEBAR (Min-width 768px) */
    @media (min-width: 768px) {
        #modalDetailProduct .scrollable-detail-column {
            max-height: 52vh !important;
            overflow-y: auto !important;
            -ms-overflow-style: none !important;  
            scrollbar-width: none !important;     
        }
        #modalDetailProduct .scrollable-detail-column::-webkit-scrollbar {
            display: none !important;
        }
        #modalDetailProduct {
            overflow-y: hidden !important;
        }
        #modalDetailProduct .modal-body {
            max-height: none;
        }
    }

    /* MOBILE / HP (Max-width 767.98px) */
    @media (max-width: 767.98px) {
        #modalDetailProduct .scrollable-detail-column {
            max-height: none !important; 
            overflow-y: visible !important;
        }
        #detail_product_carousel_inner img {
            height: 240px !important; /* Menjaga agar gambar tidak terlalu memakan tempat di HP */
        }
    }

    #carouselDetailProduct, 
    #detail_product_carousel_inner, 
    .carousel-item {
        overflow: hidden !important;
        white-space: nowrap !important;
        -ms-overflow-style: none !important;  
        scrollbar-width: none !important;     
    }
    #detail_product_carousel_inner img {
        display: block !important;
        width: 100% !important;
        max-width: 380px !important;
        height: 340px; 
        object-fit: cover !important;
        border-radius: 14px !important;
        margin: 0 auto !important;
    }
    
    /* PERBAIKAN: Mengunci posisi footer di bawah (Sticky) untuk responsivitas mobile */
    #modalDetailProduct .fixed-product-footer {
        border-top: 1px solid rgba(148, 163, 184, 0.15);
        background: rgba(15, 23, 42, 0.95) !important; /* Dibuat pekat agar konten teks di belakangnya tersamar */
        backdrop-filter: blur(8px);
        margin-top: 20px;
        padding: 15px !important; /* Memastikan ruang klik lega di HP */
        position: sticky;
        bottom: 0;
        z-index: 10;
    }
    
    @media (min-width: 1200px) {
        #modalDetailProduct .modal-dialog {
            max-width: 1100px !important; 
            width: 1100px !important;
        }
    }
</style>

<div class="modal fade" id="modalDetailProduct" aria-labelledby="modalDetailProductLabel" role="dialog" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.96) !important; backdrop-filter: blur(16px); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb; border-radius: 20px;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15); padding: 1.25rem 2rem;">
                <h5 class="modal-title fw-bold text-white" id="modalDetailProductLabel">Detail Produk</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0"> 
                <div class="row g-0">
                    
                    <!-- KONTEN KIRI: Bagian Gambar (Dipastikan berpasangan dengan benar) -->
                    <div class="col-md-5 text-center p-4 p-md-5 border-bottom border-md-0 border-md-end" style="border-color: rgba(148, 163, 184, 0.15) !important;">
                        <div class="position-relative mx-auto" style="max-width: 380px;">
                            <div id="carouselDetailProduct" class="carousel slide shadow-lg rounded-4" data-bs-ride="carousel" style="overflow: hidden !important;">
                                <div class="carousel-inner" id="detail_product_carousel_inner" style="overflow: hidden !important;">
                                    <!-- Diisi otomatis oleh JavaScript openDetailProduct -->
                                </div>
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#carouselDetailProduct" data-bs-slide="prev" id="carousel_btn_prev" 
                                    style="width: 44px; height: 44px; top: 50%; transform: translateY(-50%); left: -20px; position: absolute; background: rgba(30, 41, 59, 0.9); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 50%;">
                                <span class="carousel-control-prev-icon" aria-hidden="true" style="width: 22px; height: 22px;"></span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#carouselDetailProduct" data-bs-slide="next" id="carousel_btn_next" 
                                    style="width: 44px; height: 44px; top: 50%; transform: translateY(-50%); right: -20px; position: absolute; background: rgba(30, 41, 59, 0.9); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 50%;">
                                <span class="carousel-control-next-icon" aria-hidden="true" style="width: 22px; height: 22px;"></span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- KONTEN KANAN: Bagian Form Isian dan Tombol -->
                    <div class="col-md-7 p-4 p-md-5 d-md-flex flex-md-column justify-content-between">
                        <div class="scrollable-detail-column pe-2"> 
                            <h2 id="detail_product_name" class="fw-bold text-white mb-1" style="font-size: 2.25rem;"></h2>
                            <p id="detail_product_category" class="text-white-50 small text-uppercase mb-4" style="letter-spacing: 1.5px; opacity: 0.8;"></p>
                            
                            <div class="p-3 rounded-3 mb-4" style="background: rgba(2, 6, 23, 0.4); border: 1px solid rgba(148, 163, 184, 0.12); border-radius: 12px;">
                                <label class="small text-white-50 d-block mb-1.5" style="opacity: 0.7; font-weight: 500;">Deskripsi Hidangan:</label>
                                <span id="detail_product_description" class="text-light-50" style="font-size: 0.95rem; line-height: 1.6;"></span>
                            </div>
                            
                            <div class="mb-4">
                                <label id="label_product_variant" for="detail_product_variant_select" class="small text-white-50 d-block mb-2" style="opacity: 0.7; font-weight: 500;">Pilih Varian / Opsi:</label>
                                <select id="detail_product_variant_select" class="form-select text-white border-secondary py-2 px-3" style="background: rgba(2, 6, 23, 0.4); border-radius: 10px; font-size: 0.92rem; box-shadow: none;">
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="small text-white-50 d-block mb-2" style="opacity: 0.7; font-weight: 500;">Pilih Topping / Tambahan :</label>
                                <div id="detail_product_addons_container" class="d-flex flex-column gap-2 p-2 rounded-3" style="background: rgba(2, 6, 23, 0.4); border: 1px solid rgba(148, 163, 184, 0.12);">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="detail_product_notes_input" class="small text-white-50 d-block mb-2" style="opacity: 0.7; font-weight: 500;">Catatan Tambahan Pembeli (Opsional):</label>
                                <input type="text" id="detail_product_notes_input" class="form-control text-white border-secondary py-2.5 px-3" 
                                       style="background: rgba(2, 6, 23, 0.4); border-radius: 10px; font-size: 0.92rem; box-shadow: none;" 
                                       placeholder="Contoh: tidak usah pake sedotan, sendok plastik, pisah kuah...">
                            </div>
                            
                            <div class="mt-4 pt-4" style="border-top: 1px solid rgba(148, 163, 184, 0.15);">
                                <h5 class="fw-bold text-white mb-3 d-flex align-items-center gap-2">
                                    <i class="bi bi-chat-left-heart-fill text-warning"></i> Ulasan & Testimoni Pasien
                                </h5>
                                <div id="detail_product_reviews_container" class="d-flex flex-column gap-3">
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center fixed-product-footer mt-auto pt-3">
                            <div>
                                <span class="text-white-50 small d-block mb-1" style="opacity: 0.7;">Harga Total</span>
                                <h3 id="detail_product_price" class="fw-bold text-success m-0" style="font-size: 1.75rem;"></h3>
                            </div>
                            <button type="button" id="btn_detail_add_cart" class="btn btn-success px-4 py-2.5 fw-medium rounded-3 d-flex align-items-center gap-2" style="border-radius: 10px !important;">
                                <i class="bi bi-cart-plus-fill"></i> Tambah ke Keranjang
                            </button>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 2. Container Modal Konfirmasi Hapus -->
<div class="modal fade" id="modalConfirmDelete" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-white rounded-4 border-0 shadow-lg" 
             style="background: rgba(15, 23, 42, 0.98); border: 1px solid rgba(148, 163, 184, 0.15) !important; backdrop-filter: blur(16px);">
            <div class="modal-header border-bottom border-secondary border-opacity-25 p-3">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Keluarkan Menu</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-shadow="none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-white-50" id="delete_item_text_target">
                <!-- Kalimat konfirmasi dinamis disuntikkan di sini oleh fungsi prepareDelete -->
                Apakah Anda yakin ingin mengeluarkan menu ini dari daftar keranjang belanja Anda?
            </div>
            <div class="modal-footer border-top border-secondary border-opacity-25 p-3">
                <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-medium" data-bs-dismiss="modal">Batal</button>
                <a id="btn_execute_delete_link" href="#" class="btn btn-sm btn-danger rounded-pill px-4 fw-medium">Ya, Hapus</a>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
   JAVASCRIPT LOGIKA MODAL HAPUS DAN EDIT DENGAN DETAIL MODAL
   ======================================================== -->
<script>
/**
 * 1. Menangani fungsi modal konfirmasi hapus item
 */
function prepareDelete(cartKey, productName) {
    const textTarget = document.getElementById('delete_item_text_target');
    if (textTarget) {
        textTarget.innerHTML = `Apakah Anda yakin ingin mengeluarkan menu <b class="text-white">"${productName}"</b> dari daftar keranjang belanja Anda?`;
    }
    
    const deleteBtn = document.getElementById('btn_execute_delete_link');
    if (deleteBtn) {
        deleteBtn.href = `keranjang.php?action=delete&key=${cartKey}`;
    }
} 

/**
 * 2. PERBAIKAN: Memanggil detail_product_modal.php secara asinkronus
 */
function loadEditModal(productId, cartKey) {
    const modalContainer = document.getElementById('modalEditProductContainer');
    if (!modalContainer) {
        console.error("Eror: Elemen id 'modalEditProductContainer' tidak ditemukan di HTML.");
        return;
    }

    let modalDialog = modalContainer.querySelector('.modal-dialog');
    if (!modalDialog) {
        modalDialog = document.createElement('div');
        modalDialog.className = 'modal-dialog modal-dialog-centered';
        modalContainer.appendChild(modalDialog);
    }
    
    // State loading premium bertema gelap transparan
    modalDialog.innerHTML = `
        <div class="modal-content text-white p-4 text-center rounded-4" 
             style="background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(148, 163, 184, 0.2); backdrop-filter: blur(12px);">
            <div class="spinner-border text-success mx-auto my-3" role="status"></div>
            <p class="mb-0 small text-white-50">Mengambil detail produk...</p>
        </div>
    `;

    // Mengambil komponen modal secara dinamis dari detail_product_modal.php
    // Menyertakan ID produk, Key keranjang, dan anti-cache (Date.now)
    fetch(`detail_product_modal.php?id=${productId}&key=${cartKey}&_cb=${Date.now()}`)
        .then(response => {
            if (!response.ok) throw new Error('Gagal memuat detail_product_modal.php');
            return response.text();
        })
        .then(htmlContent => {
            // Memasukkan output HTML dari detail_product_modal.php ke dalam modal dialog
            modalDialog.innerHTML = htmlContent;
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            modalDialog.innerHTML = `
                <div class="modal-content text-white p-4 text-center rounded-4" 
                     style="background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(239, 68, 68, 0.3);">
                    <i class="bi bi-exclamation-octagon text-danger fs-2 mb-2"></i>
                    <h6 class="fw-bold mb-1">Gagal Memuat Detail</h6>
                    <p class="small text-white-50 mb-3">Silakan coba beberapa saat lagi atau cek file detail_product_modal.php Anda.</p>
                    <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3 mx-auto" data-bs-dismiss="modal">Tutup</button>
                </div>
            `;
        });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

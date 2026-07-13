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
                        
                        <!-- PERBAIKAN: Logika Pengunci Tombol Minus saat Kuantitas bernilai 1 -->
                        <div class="col-md-3 col-6 my-2 my-md-0">
                            <div class="d-inline-flex align-items-center rounded-3 p-1" style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(148, 163, 184, 0.2);">
                                <?php if ($kuantitas <= 1): ?>
                                    <!-- Warna Merah Lembut Transparan saat Terkunci -->
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
                            <!-- Harga Akumulasi Item -->
                            <h5 class="text-success fw-bold mb-2" style="font-size: 1.2rem;">Rp <?php echo number_format($total_per_item, 0, ',', '.'); ?></h5>
                            
                            <!-- Grouping Tombol Aksi Kanan -->
                            <div class="d-flex flex-column align-items-end gap-2">
                                <!-- TOMBOL EDIT: Langsung memicu modal Bootstrap secara dinamis -->
                                <button type="button" class="btn text-warning bg-transparent p-0 border-0 small fw-medium" 
                                        data-bs-toggle="modal" data-bs-target="#modalEditProductContainer"
                                        onclick="loadEditModal('<?php echo isset($item['id']) ? $item['id'] : ''; ?>', '<?php echo $cartKey; ?>')" 
                                        style="box-shadow: none; font-size: 0.88rem;">
                                    <i class="bi bi-pencil-square me-1"></i> Edit Pesanan
                                </button>

                                <!-- Tombol Hapus Item -->
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

        <!-- Kolom Kanan: Ringkasan Pembayaran -->
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
                
                <button class="btn btn-success w-100 py-2 fw-medium rounded-3" type="button" <?php echo ($subtotal == 0) ? 'disabled' : ''; ?>>
                    <i class="bi bi-credit-card-2-front me-2"></i> Lanjutkan Pemesanan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
   TAMBAHAN: MODAL KONFIRMASI HAPUS TEMATIK RSI (BOOTSTRAP 5)
   ======================================================== -->
<div class="modal fade" id="modalConfirmDelete" tabindex="-1" aria-labelledby="modalConfirmDeleteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 380px !important;">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.98); backdrop-filter: blur(16px); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb; border-radius: 20px;">
            <div class="modal-body p-4 text-center">
                <i class="bi bi-exclamation-triangle-fill text-danger d-block mb-3" style="font-size: 3rem; opacity: 0.9;"></i>
                <h5 class="fw-bold text-white mb-1" id="modalConfirmDeleteLabel">Hapus Hidangan Sehat?</h5>
                <p class="text-white-50 small mb-4" id="delete_item_text_target">Apakah Anda yakin ingin mengeluarkan menu ini dari daftar keranjang belanja Anda?</p>
                
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary w-50 py-2 fw-medium" data-bs-dismiss="modal" style="border-radius: 10px; background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.1); color: #e5e7eb;">Batal</button>
                    <a id="btn_execute_delete_link" href="#" class="btn btn-danger w-50 py-2 fw-medium" style="border-radius: 10px;">Ya, Hapus</a>
                </div>
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

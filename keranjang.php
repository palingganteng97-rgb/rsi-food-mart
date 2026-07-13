<?php
// keranjang.php - TAHAP 1: BACKEND CONTROLLER LOGIC
include 'db.php'; 

// Wajib jalankan session_start() di baris pertama agar memori cart terbaca
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteksi halaman, wajib login terlebih dahulu
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// =========================================================================
// AKSI A: Proses Tambah Menu Baru Dari home.php (Lengkap Beserta Topping)
// =========================================================================
if ($action === 'add_to_cart' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $qty        = isset($_POST['qty']) ? intval($_POST['qty']) : 1;
    $notes      = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $variant    = isset($_POST['variant']) ? $_POST['variant'] : 'Original';
    
    // Menangkap array ID item topping dari checkbox yang dicentang di modal home.php
    $addon_item_ids = isset($_POST['addons']) ? $_POST['addons'] : []; 

    if ($product_id > 0) {
        // Tarik data dasar menu makanan dari database magang_rsi_food_mart
        $query_prod = "SELECT name, base_price, image FROM products WHERE id = $product_id LIMIT 1";
        $res_prod = mysqli_query($conn, $query_prod);
        $prod_data = mysqli_fetch_assoc($res_prod);

        $name = $prod_data['name'] ?? 'Menu';
        $base_price = floatval($prod_data['base_price'] ?? 0);
        $image = $prod_data['image'] ?? 'default.png';

        // Olah data Topping (Addons) berdasarkan tabel addon_items di HeidiSQL
        $addons_list = [];
        $total_addon_price = 0;

        if (!empty($addon_item_ids)) {
            $ids_string = implode(',', array_map('intval', $addon_item_ids));
            $query_addons = "SELECT id, item_name, price FROM addon_items WHERE id IN ($ids_string)";
            $res_addons = mysqli_query($conn, $query_addons);

            while ($row = mysqli_fetch_assoc($res_addons)) {
                $addons_list[] = [
                    'id' => $row['id'],
                    'name' => $row['item_name'],
                    'price' => floatval($row['price'])
                ];
                $total_addon_price += floatval($row['price']); 
            }
        }

        // Akumulasi harga total = harga makanan dasar + total harga semua topping dicentang
        $final_price = $base_price + $total_addon_price;

        // Membuat unique cart key berbasis md5 agar menu sama dengan topping berbeda tidak saling menimpa
        $cart_key = md5($product_id . '_' . $variant . '_' . serialize($addon_item_ids));

        if (isset($_SESSION['cart'][$cart_key])) {
            $_SESSION['cart'][$cart_key]['qty'] += $qty;
        } else {
            $_SESSION['cart'][$cart_key] = [
                'id' => $product_id,
                'name' => $name,
                'price' => $final_price, 
                'base_price' => $base_price, // Disimpan sebagai cadangan saat edit / reset topping
                'image' => $image,
                'qty' => $qty,
                'notes' => $notes,
                'variant' => $variant,
                'addons' => $addons_list 
            ];
        }
    }
    header("Location: keranjang.php?status=success&msg=Menu berhasil dimasukkan ke keranjang");
    exit();
}

// =========================================================================
// AKSI B: Proses Tambah (+) & Kurang (-) Kuantitas Cepat Di List Keranjang
// =========================================================================
if ($action === 'update_qty' && isset($_GET['key']) && isset($_GET['type'])) {
    $cartKey = $_GET['key'];
    $type = $_GET['type'];
    
    if (isset($_SESSION['cart'][$cartKey])) {
        if ($type === 'plus') {
            $_SESSION['cart'][$cartKey]['qty'] += 1;
        } elseif ($type === 'minus') {
            $_SESSION['cart'][$cartKey]['qty'] -= 1;
            if ($_SESSION['cart'][$cartKey]['qty'] < 1) {
                unset($_SESSION['cart'][$cartKey]);
            }
        }
        header("Location: keranjang.php");
        exit();
    }
}

// =========================================================================
// AKSI C: Simpan Perubahan Hasil Edit Dari Form Modal
// =========================================================================
if ($action === 'update_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cartKey = isset($_POST['cart_key']) ? $_POST['cart_key'] : '';
    $new_qty = isset($_POST['qty']) ? intval($_POST['qty']) : 1;
    $new_notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $new_variant = isset($_POST['variant']) ? $_POST['variant'] : 'Original';
    $new_addon_ids = isset($_POST['addons']) ? $_POST['addons'] : [];

    if (!empty($cartKey) && isset($_SESSION['cart'][$cartKey])) {
        $product_id = $_SESSION['cart'][$cartKey]['id'];
        $base_price = isset($_SESSION['cart'][$cartKey]['base_price']) ? $_SESSION['cart'][$cartKey]['base_price'] : $_SESSION['cart'][$cartKey]['price'];

        // Ambil data detail topping baru dari database jika user mengubah centang di modal edit
        $addons_list = [];
        $total_addon_price = 0;

        if (!empty($new_addon_ids)) {
            $ids_string = implode(',', array_map('intval', $new_addon_ids));
            $query_addons = "SELECT id, item_name, price FROM addon_items WHERE id IN ($ids_string)";
            $res_addons = mysqli_query($conn, $query_addons);

            while ($row = mysqli_fetch_assoc($res_addons)) {
                $addons_list[] = [
                    'id' => $row['id'],
                    'name' => $row['item_name'],
                    'price' => floatval($row['price'])
                ];
                $total_addon_price += floatval($row['price']);
            }
        }

        // Perbarui total komponen array session keranjang secara utuh
        $_SESSION['cart'][$cartKey]['qty'] = $new_qty;
        $_SESSION['cart'][$cartKey]['notes'] = $new_notes;
        $_SESSION['cart'][$cartKey]['variant'] = $new_variant;
        $_SESSION['cart'][$cartKey]['addons'] = $addons_list;
        $_SESSION['cart'][$cartKey]['price'] = $base_price + $total_addon_price;

        header("Location: keranjang.php?status=success&msg=Pesanan berhasil diperbarui");
        exit();
    }
}

// =========================================================================
// AKSI D: Mengeluarkan / Menghapus Menu Dari Daftar Belanja
// =========================================================================
if ($action === 'delete' && isset($_GET['key'])) {
    $delete_key = $_GET['key'];
    if (isset($_SESSION['cart'][$delete_key])) {
        unset($_SESSION['cart'][$delete_key]);
        header("Location: keranjang.php?status=success&msg=Item berhasil dihapus");
        exit();
    }
}

$status = isset($_GET['status']) ? $_GET['status'] : "";
$msg = isset($_GET['msg']) ? $_GET['msg'] : "";

// Siapkan array data keranjang untuk dilempar ke HTML looping tahap 2
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

<!-- ========================================================
   TAHAP 2: VIEW LAYOUT - TAMPILAN KERANJANG DAN RINGKASAN
   ======================================================== -->
<div class="container my-5">
    <div class="row">
        <!-- Kolom Kiri: Daftar Produk Bertema Transparan Premium -->
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-white"><i class="bi bi-cart3 me-2 text-success"></i> Keranjang Belanja Anda</h4>
                <a href="home.php" class="btn btn-outline-light btn-sm rounded-pill px-3" style="border: 1px solid rgba(148, 163, 184, 0.3); background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(4px);">← Kembali Belanja</a>
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

                    $harga_total_item = isset($item['price']) ? floatval($item['price']) : 0;
                    $kuantitas = isset($item['qty']) ? intval($item['qty']) : 1;
                    $nama_menu = isset($item['name']) ? $item['name'] : 'Menu';
                    $gambar_menu = isset($item['image']) ? $item['image'] : '';

                    // Akumulasi harga item dikali jumlah kuantitas porsi
                    $total_per_item = $harga_total_item * $kuantitas;
                    $subtotal += $total_per_item;

                    $path_gambar = "uploads/products/" . $gambar_menu;
                    if (empty($gambar_menu) || !file_exists($path_gambar)) {
                        $path_gambar = "uploads/products/gallery/" . $gambar_menu;
                    }
                    if (empty($gambar_menu) || !file_exists($path_gambar)) {
                        $path_gambar = "uploads/products/default.png"; 
                    }
            ?>
                <!-- Item Card Bertema Premium Gelap Transparan (Glassmorphism) -->
                <div class="card mb-3 rounded-4 p-3 text-white" 
                     style="background: rgba(30, 41, 59, 0.35) !important; border: 1px solid rgba(148, 163, 184, 0.15) !important; backdrop-filter: blur(12px); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);">
                    <div class="row align-items-center">
                        
                        <!-- Gambar Produk -->
                        <div class="col-md-2 col-3">
                            <div style="width: 80px; height: 80px; overflow: hidden; border-radius: 12px; border: 1px solid rgba(148, 163, 184, 0.15);">
                                <img src="<?php echo $path_gambar; ?>" class="w-100 h-100" style="object-fit: cover;" onerror="this.src='uploads/products/default.png'">
                            </div>
                        </div>
                        
                        <!-- Detail Info Produk (Lengkap dengan Varian, Catatan & List Topping Addons) -->
                        <div class="col-md-4 col-9">
                            <h5 class="mb-1 fw-bold text-white" style="font-size: 1.1rem;"><?php echo htmlspecialchars($nama_menu); ?></h5>
                            
                            <!-- Menampilkan Varian / Tingkat Opsi Hidangan -->
                            <span class="badge bg-dark text-white-50 border border-secondary border-opacity-20 small p-1 px-2 fw-normal mb-1" style="font-size: 0.75rem;">
                                Varian: <?php echo htmlspecialchars($item['variant'] ?? 'Original'); ?>
                            </span>

                            <!-- MENAMPILKAN DAFTAR TOPPING / ADDONS YANG DICENTANG -->
                            <?php if (!empty($item['addons']) && is_array($item['addons'])): ?>
                                <div class="d-flex flex-wrap gap-1 my-1">
                                    <?php foreach ($item['addons'] as $addon): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20 fw-normal style-addon-badge" style="font-size: 0.75rem; padding: 3px 8px; border-radius: 6px;">
                                            <i class="bi bi-egg-fried me-1"></i> +<?php echo htmlspecialchars($addon['name']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Menampilkan Catatan Pembeli -->
                            <?php if(!empty($item['notes'])): ?>
                                <div class="mt-1">
                                    <span class="badge text-warning fw-normal small" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148,163,184,0.15);">
                                        <i class="bi bi-pencil-square me-1"></i> Catatan: <?php echo htmlspecialchars($item['notes']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Logika Pengunci & Pengatur Kuantitas Cepat List Keranjang -->
                        <div class="col-md-3 col-6 my-2 my-md-0">
                            <div class="d-inline-flex align-items-center rounded-3 p-1" style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(148, 163, 184, 0.2); height: 38px;">
                                <?php if ($kuantitas <= 1): ?>
                                    <!-- Terkunci Merah Lembut saat Qty = 1 -->
                                    <span class="btn btn-sm px-2 py-1 border-0" style="cursor: not-allowed; color: rgba(239, 68, 68, 0.4) !important;">
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
                        
                        <!-- Total Harga Item & Aksi Modifikasi Dinamis -->
                        <div class="col-md-3 col-6 text-end">
                            <h5 class="text-success fw-bold mb-2" style="font-size: 1.2rem;">Rp <?php echo number_format($total_per_item, 0, ',', '.'); ?></h5>
                            
                            <div class="d-flex flex-column align-items-end gap-1">
                                <!-- TOMBOL EDIT PESANAN: Mengirimkan objek utuh item PHP ke JavaScript -->
                                <button type="button" class="btn text-warning bg-transparent p-0 border-0 small fw-medium" 
                                        data-bs-toggle="modal" data-bs-target="#modalEditPesanan"
                                        onclick='openEditPesananFromCart(<?php echo json_encode($item); ?>, "<?php echo $cartKey; ?>")' 
                                        style="box-shadow: none; font-size: 0.85rem; opacity: 0.85;">
                                    <i class="bi bi-pencil-square me-1"></i> Edit Pesanan
                                </button>

                                <!-- TOMBOL HAPUS: Memicu konfirmasi modal aman -->
                                <button type="button" class="btn text-danger bg-transparent p-0 border-0 small fw-medium" 
                                        data-bs-toggle="modal" data-bs-target="#modalConfirmDelete" 
                                        onclick="prepareDelete('<?php echo $cartKey; ?>', '<?php echo htmlspecialchars($nama_menu, ENT_QUOTES); ?>')" 
                                        style="box-shadow: none; font-size: 0.85rem; opacity: 0.85;">
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
                <!-- State Tampilan Saat Keranjang Kosong -->
                <div class="bg-transparent text-center rounded-4 p-5" style="border: 2px dashed rgba(148, 163, 184, 0.25);">
                    <i class="bi bi-basket2 text-success mb-3" style="font-size: 3rem; opacity: 0.8;"></i>
                    <h5 class="text-white-50 fw-medium mb-3">Keranjang belanja Anda masih kosong</h5>
                    <div>
                        <a href="home.php" class="btn btn-success btn-sm rounded-pill px-4 fw-medium shadow">Mulai Belanja</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

<!-- Kolom Kanan: Ringkasan Pembayaran Pembeli -->
        <div class="col-lg-4">
            <div class="bg-transparent rounded-4 p-4" style="border: 2px dashed rgba(148, 163, 184, 0.25); backdrop-filter: blur(8px);">
                <h4 class="fw-bold mb-4 text-white">Ringkasan Pesanan</h4>
                
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-white-50">Subtotal Produk</span>
                    <span class="fw-semibold text-white">Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-4">
                    <span class="text-white-50">Pajak / Layanan</span>
                    <span class="fw-semibold text-white">Rp 0</span>
                </div>
                <hr class="border-secondary border-opacity-50">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <span class="fw-bold fs-5 text-white">Total Bayar</span>
                    <span class="text-success fw-bold fs-4">Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                </div>
                
                <button class="btn btn-success w-100 py-2.5 fw-medium rounded-3" type="button" <?php echo ($subtotal == 0) ? 'disabled' : ''; ?>>
                    <i class="bi bi-credit-card-2-front me-2"></i> Lanjutkan Pemesanan
                </button>
            </div>
        </div>
    </div> <!-- Penutup Row Utama -->
</div> <!-- Penutup Container my-5 -->

<!-- ============================================================
     CONTAINER MODAL 1: KONFIRMASI HAPUS ITEM FROM SESSION
============================================================ -->
<div class="modal fade" id="modalConfirmDelete" tabindex="-1" aria-hidden="true" aria-labelledby="modalConfirmDeleteLabel">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 450px;">
        <div class="modal-content text-white rounded-4 border-0 shadow-lg" 
             style="background: rgba(15, 23, 42, 0.96) !important; border: 1px solid rgba(148, 163, 184, 0.18) !important; backdrop-filter: blur(16px);">
            <div class="modal-header border-bottom border-secondary border-opacity-25 p-3 px-4">
                <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2" id="modalConfirmDeleteLabel">
                    <i class="bi bi-exclamation-triangle-fill"></i> Hapus Item
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
            </div>
            <div class="modal-body p-4 text-white-50 fs-6" id="delete_item_text_target" style="line-height: 1.5;">
                Apakah Anda yakin ingin mengeluarkan menu ini dari daftar keranjang belanja Anda?
            </div>
            <div class="modal-footer border-top border-secondary border-opacity-25 p-3 px-4 justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-medium" data-bs-dismiss="modal" style="font-size: 0.88rem;">Batal</button>
                <a id="btn_execute_delete_link" href="#" class="btn btn-sm btn-danger rounded-pill px-4 fw-medium shadow-sm" style="font-size: 0.88rem;">Ya, Hapus</a>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     CONTAINER MODAL 2: EDIT QUANTITY, VARIANT & ADDONS TOPPING
============================================================ -->
<style>
  #modalEditPesanan .modal-content {
    background: rgba(15, 23, 42, 0.96) !important;
    backdrop-filter: blur(16px);
    border: 1px solid rgba(148, 163, 184, 0.25);
    color: #e5e7eb;
    border-radius: 20px;
  }
  #modalEditPesanan .modal-header {
    border-bottom: 1px solid rgba(148, 163, 184, 0.15);
    padding: 1.0rem 1.25rem;
  }
</style>

<div class="modal fade" id="modalEditPesanan" aria-labelledby="modalEditPesananLabel" role="dialog" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <form id="formEditPesanan" method="POST" action="keranjang.php?action=update_item" class="w-100">
      <input type="hidden" name="cart_key" id="edit_cart_key" value="">

      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold text-white" id="modalEditPesananLabel">Edit Detail Pesanan</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">
            <div class="row align-items-center mb-4">
                <div class="col-12">
                    <div class="text-white-50 small mb-1" style="opacity:.8;">Nama Menu Hidangan</div>
                    <h3 class="fw-bold text-white m-0" id="edit_item_name">-</h3>
                </div>
            </div>

            <div class="p-3 rounded-4 mb-4" style="background: rgba(2, 6, 23, 0.4); border: 1px solid rgba(148, 163, 184, 0.12);">
                <div class="row text-center text-sm-start">
                    <div class="col-sm-6 mb-2 mb-sm-0">
                        <div class="text-white-50 small mb-1" style="opacity:.8;">Harga Satuan Utama</div>
                        <div class="fw-bold text-success fs-5" id="edit_unit_price">Rp 0</div>
                    </div>
                    <div class="col-sm-6 text-sm-end">
                        <div class="text-white-50 small mb-1" style="opacity:.8;">Total Akumulasi Item</div>
                        <div class="fw-bold text-success fs-4" id="edit_item_total">Rp 0</div>
                    </div>
                </div>
            </div>

            <!-- Isian Input Pengubah Kuantitas Porsi -->
            <div class="mb-4">
              <label for="edit_qty" class="form-label text-white-50 small fw-medium mb-2" style="opacity:.85;">Jumlah Porsi Pesanan</label>
              <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-dark border border-secondary border-opacity-25" id="btnQtyMinus" style="width:44px; height:40px; border-radius:10px;">
                  <i class="bi bi-dash-lg text-white"></i>
                </button>
                <input type="number" class="form-control text-center fw-bold fs-5 p-0" id="edit_qty" name="qty" min="1" step="1" value="1" readonly style="background: rgba(2, 6, 23, 0.4); border-radius: 10px; border: 1px solid rgba(148,163,184,0.25); color:#e5e7eb; width: 65px; height: 40px; box-shadow: none;">
                <button type="button" class="btn btn-dark border border-secondary border-opacity-25" id="btnQtyPlus" style="width:44px; height:40px; border-radius:10px;">
                  <i class="bi bi-plus-lg text-white"></i>
                </button>
              </div>
            </div>

            <!-- Opsi Pilihan Varian Tingkat Pedas -->
            <div class="mb-4">
                <label for="edit_variant" class="form-label text-white-50 small fw-medium mb-2" style="opacity:.85;">Pilih Tingkat Varian / Opsi</label>
                <select id="edit_variant" name="variant" class="form-select text-white border-secondary py-2 px-3" style="background: rgba(2, 6, 23, 0.4); border-radius: 10px; font-size: 0.92rem; box-shadow: none;">
                    <!-- Diisi dinamis oleh JavaScript menggunakan data session / query database -->
                </select>
            </div>

            <!-- Opsi Checkbox Pilihan Topping Dinamis -->
            <div class="mb-4">
                <label class="form-label text-white-50 small fw-medium mb-2" style="opacity:.85;">Pilih Tambahan Topping / Addons</label>
                <div id="edit_addons_container" class="d-flex flex-column gap-2 p-2 rounded-3" style="background: rgba(2, 6, 23, 0.4); border: 1px solid rgba(148, 163, 184, 0.12);">
                    <!-- Checkbox Topping (Sosis Ayam / Telur Ayam) akan di-render otomatis di sini -->
                </div>
            </div>

            <!-- Isian Input Catatan Pembeli -->
            <div class="mb-2">
              <label for="edit_notes" class="form-label text-white-50 small fw-medium mb-2" style="opacity:.85;">Catatan Tambahan untuk Dapur (opsional)</label>
              <input type="text" class="form-control py-2.5 px-3" id="edit_notes" name="notes" placeholder="Contoh: tidak usah pake sedotan, sendok plastik, pisah kuah..." style="background: rgba(2, 6, 23, 0.4); border-radius: 12px; border: 1px solid rgba(148,163,184,0.25); color:#e5e7eb; box-shadow: none; font-size: 0.92rem;">
            </div>
        </div>

        <!-- Footer Tombol Aksi Simpan Form -->
        <div class="modal-footer border-top border-secondary border-opacity-25 p-3 px-4">
          <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-medium" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-sm btn-success rounded-pill fw-medium px-4 shadow-sm">Simpan Perubahan</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function openEditPesananFromCart(item, cartKey) {
    if (!item) return;

    const productId = parseInt(item.id) || 0;
    const basePrice = parseFloat(item.base_price) || parseFloat(item.price) || 0;
    const currentQty = parseInt(item.qty) || 1;

    document.getElementById('edit_cart_key').value = cartKey;
    document.getElementById('edit_item_name').innerText = item.name || 'Menu';
    document.getElementById('edit_qty').value = currentQty;
    document.getElementById('edit_notes').value = item.notes || '';
    document.getElementById('edit_unit_price').innerText = 'Rp ' + basePrice.toLocaleString('id-ID');

    const variantSelect = document.getElementById('edit_variant');
    if (variantSelect && productId > 0) {
        fetch(`get_variants.php?product_id=${productId}&_cb=${Date.now()}`)
            .then(res => res.json())
            .then(variants => {
                variantSelect.innerHTML = '';
                if (!variants || variants.length === 0) {
                    variantSelect.innerHTML = `<option value="Original">Original (Bawaan)</option>`;
                } else {
                    variants.forEach(v => {
                        const isSelected = item.variant === v.name ? 'selected' : '';
                        variantSelect.innerHTML += `<option value="${v.name}" ${isSelected}>${v.name}</option>`;
                    });
                }
            }).catch(() => {
                variantSelect.innerHTML = `<option value="Original">Original</option>`;
            });
    }

    const addonsContainer = document.getElementById('edit_addons_container');
    if (addonsContainer && productId > 0) {
        addonsContainer.innerHTML = `<p class="small text-white-50 text-center m-0 py-2"><span class="spinner-border spinner-border-sm text-success me-2"></span>Memuat topping...</p>`;
        
        fetch(`get_addon_items.php?product_id=${productId}&_cb=${Date.now()}`)
            .then(res => res.json())
            .then(addons => {
                addonsContainer.innerHTML = '';
                if (!addons || addons.length === 0) {
                    addonsContainer.innerHTML = `<p class="small text-white-50 text-center m-0 py-2">Tidak ada pilihan topping tambahan.</p>`;
                    hitungTotalItemLive();
                    return;
                }

                const selectedAddonIds = item.addons ? item.addons.map(a => parseInt(a.id)) : [];

                addons.forEach(addon => {
                    const addonId = parseInt(addon.id);
                    const isChecked = selectedAddonIds.includes(addonId) ? 'checked' : '';

                    addonsContainer.innerHTML += `
                        <div class="form-check d-flex align-items-center justify-content-between p-2 rounded-2 mx-2">
                            <div>
                                <input class="form-check-input me-2 addon-checkbox-input" type="checkbox" name="addons[]" value="${addonId}" id="addon_${addonId}" data-price="${addon.price}" ${isChecked}>
                                <label class="form-check-label text-white small" for="addon_${addonId}">
                                    ${addon.item_name}
                                </label>
                            </div>
                            <span class="text-success small fw-semibold">+Rp ${parseInt(addon.price).toLocaleString('id-ID')}</span>
                        </div>
                    `;
                });

                document.querySelectorAll('.addon-checkbox-input').forEach(checkbox => {
                    checkbox.addEventListener('change', hitungTotalItemLive);
                });

                hitungTotalItemLive();
            }).catch(err => {
                console.error(err);
                addonsContainer.innerHTML = `<p class="small text-danger text-center m-0 py-2">Gagal memuat daftar topping.</p>`;
                hitungTotalItemLive();
            });
    } else {
        hitungTotalItemLive();
    }
}

function hitungTotalItemLive() {
    const qtyInput = document.getElementById('edit_qty');
    const unitPriceText = document.getElementById('edit_unit_price').innerText || 'Rp 0';
    const totalDisplay = document.getElementById('edit_item_total');

    if (!qtyInput || !totalDisplay) return;

    const qty = parseInt(qtyInput.value) || 1;
    const basePrice = parseFloat(unitPriceText.replace(/[^0-9]/g, '')) || 0;

    let totalAddonPrice = 0;
    document.querySelectorAll('.addon-checkbox-input:checked').forEach(checkbox => {
        totalAddonPrice += parseFloat(checkbox.dataset.price) || 0;
    });

    const finalTotal = (basePrice + totalAddonPrice) * qty;
    totalDisplay.innerText = 'Rp ' + finalTotal.toLocaleString('id-ID');

    syncQtyButtons();
}

function syncQtyButtons() {
    const qtyInput = document.getElementById('edit_qty');
    const minusBtn = document.getElementById('btnQtyMinus');

    if (!qtyInput || !minusBtn) return;

    const q = parseInt(qtyInput.value) || 1;
    if (q <= 1) {
        minusBtn.style.opacity = '0.35';
        minusBtn.style.pointerEvents = 'none';
    } else {
        minusBtn.style.opacity = '1';
        minusBtn.style.pointerEvents = 'auto';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const qtyInput = document.getElementById('edit_qty');
    const minusBtn = document.getElementById('btnQtyMinus');
    const plusBtn = document.getElementById('btnQtyPlus');

    if (minusBtn && plusBtn && qtyInput) {
        minusBtn.addEventListener('click', function(e) {
            e.preventDefault();
            let q = parseInt(qtyInput.value) || 1;
            if (q > 1) {
                qtyInput.value = q - 1;
                hitungTotalItemLive();
            }
        });

        plusBtn.addEventListener('click', function(e) {
            e.preventDefault();
            let q = parseInt(qtyInput.value) || 1;
            qtyInput.value = q + 1;
            hitungTotalItemLive();
        });
    }
});

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
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

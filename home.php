<?php
include "db.php";

// session_start sudah dipanggil di db.php

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$userName = $_SESSION['name'] ?? 'Pasien';

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

<!Doctype html>
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
    .modal-dialog { max-width: 800px !important; }
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

<!-- MAIN KONTEN -->
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

        <!-- GRID INTEGRASI DAFTAR PRODUK MAKANAN SEHAT (DATA DARI PRODUCTS.PHP) -->
        <div id="catalogGrid" class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3 mt-2 mb-5">
            <?php if (!empty($listActiveProducts)): foreach ($listActiveProducts as $prod): ?>
                <div class="col">
                    <!-- PERBAIKAN: Menambahkan role, cursor pointer, dan fungsi onclick manual untuk memicu detail produk -->
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
                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2" style="border-top: 1px solid rgba(148,163,184,.1);">
                                <div class="fw-bold text-success" style="font-size: 1rem;">
                                    Rp <?= number_format($prod['base_price'], 0, ',', '.') ?>
                                </div>
                                <!-- PERBAIKAN: Menambahkan event.stopPropagation() agar klik tombol keranjang tidak memicu modal terbuka -->
                                <button class="btn btn-sm btn-success rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Tambah ke Keranjang" onclick="event.stopPropagation(); tambahKeKeranjang(<?= $prod['id'] ?>, '<?= htmlspecialchars(addslashes($prod['name'])) ?>')">
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

<!-- MODAL PRODUK -->
<div class="modal fade" id="modalDetailProduct" tabindex="-1" aria-labelledby="modalDetailProductLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.2); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
                <h5 class="modal-title fw-bold text-white" id="modalDetailProductLabel">Detail Produk</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <img id="detail_product_image" src="" alt="Gambar Produk" class="img-fluid mb-3" style="max-height: 220px; border-radius: 12px; object-fit: cover;">
                <h4 id="detail_product_name" class="fw-bold text-white mb-1"></h4>
                <p id="detail_product_category" class="text-muted small text-uppercase mb-3"></p>
                <div class="p-3 rounded-3 mb-3 text-start" style="background: rgba(2, 6, 23, 0.4); border: 1px solid rgba(148, 163, 184, 0.1);">
                    <label class="small text-muted d-block mb-1">Deskripsi Hidangan:</label>
                    <span id="detail_product_description" class="text-white-50" style="font-size: 0.9rem;"></span>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2">
                    <div>
                        <span class="text-muted small d-block text-start">Harga</span>
                        <h4 id="detail_product_price" class="fw-bold text-success m-0"></h4>
                    </div>
                    <button type="button" id="btn_detail_add_cart" class="btn btn-success px-4 py-2 fw-medium rounded-3 d-flex align-items-center gap-2">
                        <i class="bi bi-cart-plus"></i> Tambah ke Keranjang
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

  <?php include "bottom_nav.php"; ?>
  
<!-- JAVASCRIPT EVENT MOUSE DRAG TO SCROLL & HANDLER MODAL -->
<script>
    let currentDietFilter = '';
    let currentQuery = '';
    let cartCount = 0;
    let detailProductModalInstance = null;

    const grid = document.getElementById('catalogGrid');

    function openDetailProduct(data) {
        document.getElementById('detail_product_name').innerText = data.name;
        document.getElementById('detail_product_category').innerText = data.category_name || 'General';
        document.getElementById('detail_product_description').innerText = data.description || 'Tidak ada deskripsi untuk menu sehat ini.';
        
        const formattedPrice = 'Rp ' + new Intl.NumberFormat('id-ID').format(data.base_price);
        document.getElementById('detail_product_price').innerText = formattedPrice;
        
        const imgElement = document.getElementById('detail_product_image');
        imgElement.src = 'uploads/products/' + (data.image ? data.image : 'default.png');
        
        const cartBtn = document.getElementById('btn_detail_add_cart');
        cartBtn.setAttribute('onclick', `tambahKeKeranjang(${data.id}, '${data.name.replace(/'/g, "\\'")}'); bootstrap.Modal.getInstance(document.getElementById('modalDetailProduct')).hide();`);

        if (!detailProductModalInstance) {
            detailProductModalInstance = new bootstrap.Modal(document.getElementById('modalDetailProduct'));
        }
        detailProductModalInstance.show();
    }

    function setDietFilter(diet){
      currentDietFilter = diet;
      document.querySelectorAll('[data-filter]').forEach(btn=>{
        const isActive = btn.getAttribute('data-filter') === diet;
        btn.dataset.active = isActive ? 'true' : 'false';
        btn.classList.toggle('btn-success', isActive);
      });
      btnStyleRefresh();
      applyFilters();
    }

    function btnStyleRefresh(){
      document.querySelectorAll('[data-filter]').forEach(btn=>{
        const active = btn.dataset.active === 'true';
        btn.classList.toggle('diet-pill', true);
        btn.style.background = active ? 'rgba(34,197,94,.92)' : 'rgba(34,197,94,.08)';
        btn.style.color = active ? '#06210f' : '#86efac';
        btn.style.borderColor = active ? 'rgba(34,197,94,.65)' : 'rgba(34,197,94,.35)';
      });
    }

    document.getElementById('searchInput').addEventListener('input', (e)=>{
      currentQuery = (e.target.value || '').trim().toLowerCase();
      applyFilters();
    });

    function applyFilters(){
      if(!grid) return;
      const cards = grid.querySelectorAll('.col');
      cards.forEach(card=>{
        const foodCard = card.querySelector('.card-food');
        if(!foodCard) return;

        const title = (foodCard.dataset.title || '').toLowerCase();
        const dietList = (foodCard.dataset.diet || '').toLowerCase();

        const matchQuery = !currentQuery || title.includes(currentQuery);
        const matchDiet = !currentDietFilter || dietList.includes(currentDietFilter.toLowerCase());

        card.style.display = (matchQuery && matchDiet) ? '' : 'none';
      });
    }

    function resetFilters(){
      currentDietFilter = '';
      currentQuery = '';
      const input = document.getElementById('searchInput');
      if(input) input.value = '';
      document.querySelectorAll('[data-filter]').forEach(btn=>{
        const isActive = btn.getAttribute('data-filter') === '';
        btn.dataset.active = isActive ? 'true' : 'false';
      });
      btnStyleRefresh();
      applyFilters();
    }

    function tambahKeKeranjang(id, title = 'Menu Sehat'){
      cartCount++;
      const counterText = document.getElementById('cartTotalText');
      if(counterText) {
          counterText.textContent = cartCount + ' item';
      }
      
      const el = document.createElement('div');
      el.className = 'toast align-items-center text-bg-success border-0 position-fixed bottom-0 end-0 m-3';
      el.role = 'alert';
      el.ariaLive = 'assertive';
      el.ariaAtomic = 'true';
      el.style.zIndex = 2000;
      el.innerHTML = `
        <div class="d-flex">
          <div class="toast-body">Ditambahkan: <strong>${title}</strong></div>
        </div>
      `;
      document.body.appendChild(el);
      const toast = new bootstrap.Toast(el, { delay: 1400 });
      toast.show();
      setTimeout(()=> el.remove(), 1600);
    }

    function openCart(){
      alert('Placeholder: tombol Lihat Keranjang belum terhubung ke halaman cart.');
    }

    btnStyleRefresh();
    applyFilters();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


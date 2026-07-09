<?php
include "db.php";

// session_start sudah dipanggil di db.php


if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$userName = $_SESSION['name'] ?? 'Pasien';


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
        @media (min-width: 992px) {
        main.content-shift { margin-left: 280px; }
        .bottom-nav { display:none; }
        }
    </style>

</head>
<body>
  <?php require __DIR__ . '/sidebar.php'; ?>

  <main class="content-shift page-body">
    <div class="container py-3">
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
              <button type="button" class="btn btn-sm diet-pill" data-filter="" data-active="true" onclick="setDietFilter('')">
                Semua
              </button>
              <button type="button" class="btn btn-sm diet-pill" data-filter="Lunak" data-active="false" onclick="setDietFilter('Lunak')">
                Lunak
              </button>
              <button type="button" class="btn btn-sm diet-pill" data-filter="Rendah Garam" data-active="false" onclick="setDietFilter('Rendah Garam')">
                Rendah Garam
              </button>
              <button type="button" class="btn btn-sm diet-pill" data-filter="Diabetes" data-active="false" onclick="setDietFilter('Diabetes')">
                Diabetes
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Bottom Navigation (Mobile) -->
  <div class="bottom-nav d-flex d-lg-none">
    <div class="container">
      <div class="d-flex align-items-center justify-content-between gap-3 py-2">
        <div>
          <div class="text-white-50" style="font-size:.82rem;">Total Pesanan</div>
          <div class="fw-bold" id="cartTotalText">0 item</div>
        </div>
        <button class="btn btn-success rounded-3 px-3" type="button" onclick="openCart()">
          <i class="bi bi-basket2 me-2"></i> Lihat Keranjang
        </button>
      </div>
    </div>
  </div>

  <script>
    let currentDietFilter = '';
    let currentQuery = '';
    let cartCount = 0;

    const grid = document.getElementById('catalogGrid');

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
      const cards = grid.querySelectorAll('.food-card');
      cards.forEach(card=>{
        const title = (card.dataset.title || '');
        const dietList = (card.dataset.diet || '').toLowerCase();

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

    function addToCart(id, title){
      cartCount++;
      document.getElementById('cartTotalText').textContent = cartCount + ' item';
      // Placeholder toast
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

    // Initial
    btnStyleRefresh();
    applyFilters();
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


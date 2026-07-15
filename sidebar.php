<?php
// sidebar.php - Reusable component (Sidebar + Mobile Topbar)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentFile = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));

$menu = [
    'home.php'      => [ 'href' => 'home.php', 'label' => 'Etalase Menu', 'icon' => 'bi-shop' ],

    'tenants_group' => [
        'label' => 'Tenants', 'icon' => 'bi-building-gear', 'class' => 'd-mobile-none',
        'sub'   => [
            'tenants.php'                => [ 'href' => 'tenants.php',                'label' => 'Data Tenant',            'icon' => 'bi-house-lock-fill' ],
            'tenant_operating_hours.php' => [ 'href' => 'tenant_operating_hours.php', 'label' => 'Tenant Operating Hours', 'icon' => 'bi-clock-history' ],
            'tenant_holidays.php'        => [ 'href' => 'tenant_holidays.php',        'label' => 'Tenant Holidays',        'icon' => 'bi-calendar-x' ],
            'tenant_settings.php'        => [ 'href' => 'tenant_settings.php',        'label' => 'Tenant Settings',        'icon' => 'bi-sliders' ],
        ]
    ],
      
    'master_group'  => [
        'label' => 'Master Data', 'icon' => 'bi-layers-half', 'class' => '',
        'sub'   => [
            'categories.php'     => [ 'href' => 'categories.php',     'label' => 'Kategori Produk', 'icon' => 'bi-grid-fill' ],
            'brands.php'         => [ 'href' => 'brands.php',         'label' => 'Brand / Merk',    'icon' => 'bi-patch-check-fill' ],
            'units.php'          => [ 'href' => 'units.php',          'label' => 'Satuan / Units',  'icon' => 'bi-calculator-fill' ],
        ]
    ],

    'marketing_group'  => [
        'label' => 'Marketing', 'icon' => 'bi-megaphone', 'class' => '',
        'sub'   => [
            'banners.php'  => [ 'href' => 'banners.php',  'label' => 'Banners',  'icon' => 'bi-card-image' ],
            'promos.php'   => [ 'href' => 'promos.php',   'label' => 'Promos',   'icon' => 'bi-tags-fill' ],
            'vouchers.php' => [ 'href' => 'vouchers.php', 'label' => 'Vouchers', 'icon' => 'bi-ticket-perforated-fill' ],
        ]
    ],

    'produk_group'  => [
        'label' => 'Produk', 'icon' => 'bi-bag-dash-fill', 'class' => '',
        'sub'   => [
            'products.php'         => [ 'href' => 'products.php',         'label' => 'Data Produk',     'icon' => 'bi-box-seam-fill' ],
            'product_images.php'   => [ 'href' => 'product_images.php',   'label' => 'Gambar Produk',   'icon' => 'bi-images' ],
            'product_variants.php' => [ 'href' => 'product_variants.php', 'label' => 'Varian Produk',   'icon' => 'bi-grid-3x3-gap-fill' ],
            'product_addons.php'   => [ 'href' => 'product_addons.php',   'label' => 'Topping Produk',  'icon' => 'bi-egg-fried' ],
            'addon_items.php'      => [ 'href' => 'addon_items.php',      'label' => 'Item Topping',    'icon' => 'bi-list-ul' ],
            'product_reviews.php'  => [ 'href' => 'product_reviews.php',  'label' => 'Ulasan Produk',   'icon' => 'bi-star-fill' ],
        ]
    ],

    'user.php'     => [ 'href' => 'user.php',    'label' => 'User', 'icon' => 'bi-person', 'class' => 'd-none d-lg-block' ], 
    'profile.php'  => [ 'href' => 'profile.php', 'label' => 'User', 'icon' => 'bi-person-bounding-box', 'class' => 'd-block d-lg-none' ], 
    
    'roles.php'       => [ 'href' => 'roles.php',       'label' => 'Roles',       'icon' => 'bi-shield-lock', 'class' => 'd-mobile-none' ], 
    'permissions.php' => [ 'href' => 'permissions.php', 'label' => 'Permissions', 'icon' => 'bi-key',         'class' => 'd-mobile-none' ], 
];

// =========================================================================
// LOGIKA OTOMATIS: Mendeteksi sub-menu aktif agar grup dropdown otomatis terbuka (show)
// =========================================================================
foreach ($menu as $key => $item) {
    if (isset($item['sub'])) {
        if (array_key_exists($currentFile, $item['sub'])) {
            $menu[$key]['class'] .= ' show active';
        }
    }
}

function activeClass(string $file, string $currentFile): string {
    return $file === $currentFile ? 'active' : '';
}
?>

<!-- Mobile Topbar + Desktop Sidebar -->
<style>
  :root { --sidebar-w: 280px; --bg: #0f172a; --text: #e5e7eb; --muted:#94a3b8; --green:#22c55e; }
  body { background: var(--bg) !important; color: var(--text); }

  /* KUNCI UTAMA: Ditambahkan !important pada flex layout agar tidak tertimpa kelas d-none bawaan */
  .sidebar-fixed { position: fixed; top: 0; left: 0; width: var(--sidebar-w); height: 100vh; background: #0b1223; border-right: 1px solid rgba(148,163,184,.25); padding-top: 1rem; display: flex !important; flex-direction: column !important; justify-content: space-between !important; z-index: 1000; }
  .sidebar-fixed .app-brand { padding: 0 1rem; display: flex; align-items: center; gap: .75rem; color: var(--text); margin-bottom: 1.25rem; flex-shrink: 0; }
  .sidebar-fixed .app-brand .logo-badge { width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(180deg, rgba(34,197,94,.25), rgba(34,197,94,.06)); border: 1px solid rgba(34,197,94,.35); display: flex; align-items: center; justify-content: center; color: var(--green); font-size: 1.25rem; }

  /* FIX: Komponen container scroll menu */
  .sidebar-scroll-container { flex-grow: 1; overflow-y: auto; scrollbar-width: none; -ms-overflow-style: none; }
  .sidebar-scroll-container::-webkit-scrollbar { display: none; }

  /* Navigasi Menu List */
  .navmenu .nav-link { color: rgba(229,231,235,.82); border-radius: 12px; padding: .7rem .85rem; display: flex; align-items: center; gap: .65rem; margin: .25rem .5rem; transition: background-color .15s ease, color .15s ease, border-color .15s ease; border: 1px solid transparent; }
  .navmenu .nav-link:hover { background: rgba(148,163,184,.12); color: var(--text); }
  .navmenu .nav-link.active { color: #052e16; background: rgba(34,197,94,.9); border-color: rgba(34,197,94,.55); }

  /* Komponen Tombol Kaki (Footer) Sidebar */
  .sidebar-footer { padding: 1rem; border-top: 1px solid rgba(148,163,184,.12); background: #0b1223; flex-shrink: 0; }
  .btn-logout { color: #f87171; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.15); width: 100%; border-radius: 12px; padding: .7rem .85rem; display: flex; align-items: center; gap: .65rem; text-decoration: none; transition: all 0.15s ease; }
  .btn-logout:hover { background: rgba(239, 68, 68, 0.2); color: #f87171; border-color: rgba(239, 68, 68, 0.3); }

  /* Komponen Responsif Layout */
  .mobile-topbar { position: sticky; top: 0; z-index: 1030; background: rgba(15,23,42,.85); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(148,163,184,.25); }
  .page-body { min-height: 100vh; padding-bottom: 84px; }
  
  @media (min-width: 992px) { 
      .page-body { padding-bottom: 0; } 
      .content-shift { margin-left: var(--sidebar-w); } 
  }
  
  /* RESPONSIVE FILTER: Sembunyikan sidebar desktop menetap, tetapi pertahankan topbar seluler tetap terlihat */
  @media (max-width: 991.98px) { 
      .desktop-sidebar, .sidebar-fixed { display: none !important; } 
      .content-shift { margin-left: 0 !important; } 
      .d-mobile-none { display: none !important; } /* Menyembunyikan item menu khusus admin di dalam offcanvas */
  }
</style>

<!-- Mobile Topbar -->
<nav class="navbar mobile-topbar d-flex d-lg-none px-3">
  <div class="d-flex align-items-center w-100 justify-content-between">
    <button class="btn btn-link text-decoration-none text-white" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Toggle navigation">
      <i class="bi bi-list fs-3"></i>
    </button>
    <div class="d-flex align-items-center gap-2">
      <!-- Mengganti ikon bi-hospital dengan logo rsi.png dari folder uploads -->
      <div class="logo-badge d-flex align-items-center justify-content-center" aria-hidden="true" style="width: 32px; height: 32px;">
        <img src="uploads/logo rsi.png" alt="Logo RSI" style="height: 100%; width: 100%; object-fit: contain;">
      </div>
      <div class="lh-tight">
        <div class="fw-bold text-white" style="font-size:.95rem;">RSI FOOD &amp; MART</div>
        <div class="text-white-50" style="font-size:.78rem;">Pemesanan Makanan Sehat</div>
      </div>
    </div>
    <div class="text-white-50" style="width:38px; text-align:right;">
      <i class="bi bi-moon-stars fs-4"></i>
    </div>
  </div>
</nav>

<!-- Desktop Sidebar (Mendukung Dropdown Opsi Tenants Bertingkat & Bebas Error) -->
<aside class="desktop-sidebar sidebar-fixed d-none d-lg-block">
  <div class="d-flex flex-column flex-grow-1 overflow-hidden">
    <div class="app-brand">
      <!-- Mengganti ikon bi-hospital dengan logo rsi.png dari folder uploads -->
      <div class="logo-badge d-flex align-items-center justify-content-center" aria-hidden="true" style="width: 32px; height: 32px;">
        <img src="uploads/logo rsi.png" alt="Logo RSI" style="height: 100%; width: 100%; object-fit: contain;">
      </div>
      <div>
        <div class="fw-bold" style="letter-spacing:.2px;">RSI FOOD &amp; MART</div>
        <div class="text-white-50" style="font-size:.82rem;">Pemesanan Makanan Sehat</div>
      </div>
    </div>
    <div class="sidebar-scroll-container">
      <div class="navmenu mt-1">
        <?php foreach ($menu as $key => $item): ?>
          <?php if (isset($item['sub'])): $isSubActive = array_key_exists($currentFile, $item['sub']); ?>
            <div class="w-100 mb-1">
              <!-- Judul Grup Menu Bertingkat -->
              <button class="nav-link w-100 border-0 text-start d-flex align-items-center gap-3 <?= $isSubActive ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#dropMenu-<?= $key ?>" aria-expanded="<?= $isSubActive ? 'true' : 'false'; ?>" style="background:transparent; color:inherit; padding: 0.6rem 1rem;">
                <i class="bi <?= $item['icon']; ?> d-inline-block text-center" style="width: 20px;"></i>
                <span class="flex-grow-1"><?= htmlspecialchars($item['label']); ?></span>
                <i class="bi bi-chevron-down small transition-arrow" style="transition: transform 0.2s; font-size: 0.75rem; opacity: 0.7;"></i>
              </button>
              <div class="collapse <?= $isSubActive ? 'show' : ''; ?> ms-3" id="dropMenu-<?= $key ?>">
                <?php foreach ($item['sub'] as $subFile => $subItem): ?>
                  <!-- Sub Menu Di Dalam Grup -->
                  <a class="nav-link d-flex align-items-center gap-3 <?= ($currentFile === $subFile) ? 'active' : ''; ?>" href="<?= htmlspecialchars($subItem['href']); ?>" style="font-size:0.85rem; padding: 0.5rem 1rem 0.5rem 15px;">
                    <i class="bi <?= $subItem['icon']; ?> d-inline-block text-center" style="width: 20px;"></i>
                    <span><?= htmlspecialchars($subItem['label']); ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php else: ?>
            <!-- Menu Utama Biasa (Etalase, User, Roles, dll) -->
            <a class="nav-link d-flex align-items-center gap-3 <?= ($currentFile === ($item['href'] ?? '')) ? 'active' : ''; ?> <?= $item['class'] ?? ''; ?>" href="<?= htmlspecialchars($item['href'] ?? '#'); ?>" style="padding: 0.6rem 1rem;">
              <i class="bi <?= htmlspecialchars($item['icon']); ?> d-inline-block text-center" style="width: 20px;"></i>
              <span><?= htmlspecialchars($item['label']); ?></span>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="sidebar-footer w-100">
      <!-- PERBAIKAN DESKTOP: Mengubah warna background transparan, teks, dan ikon menjadi merah solid (#ef4444) -->
      <button type="button" class="btn-logout d-flex align-items-center gap-3 w-100 border-0 text-start" data-bs-toggle="modal" data-bs-target="#logoutModal" style="padding: 0.6rem 1rem; background: transparent; color: #ef4444; font-weight: 600;">
          <i class="bi bi-box-arrow-left d-inline-block text-center" style="width: 20px;"></i>
          <span>Logout</span>
      </button>
  </div>
</aside>

<!-- Mobile Sidebar Offcanvas (Mendukung Dropdown Opsi Tenants & Bebas Error) -->
<div class="offcanvas offcanvas-start text-white" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel" style="background:#0b1223; border-right:1px solid rgba(148,163,184,.25);">
  <div class="offcanvas-header">
    <div class="d-flex align-items-center gap-2">
      <!-- Mengganti ikon bi-hospital dengan logo rsi.png dari folder uploads -->
      <div class="logo-badge d-flex align-items-center justify-content-center" aria-hidden="true" style="width: 32px; height: 32px;">
        <img src="uploads/logo rsi.png" alt="Logo RSI" style="height: 100%; width: 100%; object-fit: contain;">
      </div>
      <div>
        <div class="fw-bold">RSI FOOD &amp; MART</div>
        <div class="text-white-50" style="font-size:.82rem;">Menu Pasien</div>
      </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body d-flex flex-column justify-content-between p-0">
    <div class="sidebar-scroll-container py-3">
      <div class="navmenu">
        <?php foreach ($menu as $key => $item): ?>
          <!-- FILTER MOBILE: Hanya izinkan key 'home.php' dan 'profile.php' -->
          <?php if (!in_array($key, ['home.php', 'profile.php'])) { continue; } ?>

          <?php if (isset($item['sub'])): $isSubActive = array_key_exists($currentFile, $item['sub']); ?>
            <div class="w-100 mb-1 <?= $item['class'] ?? ''; ?>">
              <button class="nav-link w-100 border-0 text-start d-flex align-items-center gap-2 <?= $isSubActive ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#dropMobileMenu-<?= $key ?>" aria-expanded="<?= $isSubActive ? 'true' : 'false'; ?>" style="background:transparent; color:inherit;" data-mobile-nav="1">
                <i class="bi <?= $item['icon']; ?>"></i><span><?= htmlspecialchars($item['label']); ?></span>
                <i class="bi bi-chevron-down small transition-arrow" style="transition: transform 0.2s; font-size: 0.75rem; opacity: 0.7;"></i>
              </button>
              <div class="collapse <?= $isSubActive ? 'show' : ''; ?> ms-3" id="dropMobileMenu-<?= $key ?>">
                <?php foreach ($item['sub'] as $subFile => $subItem): ?>
                  <a class="nav-link <?= ($currentFile === $subFile) ? 'active' : ''; ?>" href="<?= htmlspecialchars($subItem['href']); ?>" style="font-size:0.85rem; padding-left:15px;" data-mobile-nav="1">
                    <i class="bi <?= $subItem['icon']; ?> me-2"></i><?= htmlspecialchars($subItem['label']); ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php else: ?>
            <a class="nav-link <?= ($currentFile === $key) ? 'active' : ''; ?> <?= $item['class'] ?? ''; ?>" href="<?= htmlspecialchars($item['href']); ?>" data-mobile-nav="1">
              <i class="bi <?= htmlspecialchars($item['icon']); ?>"></i><span><?= htmlspecialchars($item['label']); ?></span>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="sidebar-footer w-100">
        <!-- PERBAIKAN MOBILE: Mengubah warna background transparan, teks, dan ikon menjadi merah solid (#ef4444) -->
        <button type="button" class="btn-logout d-flex align-items-center gap-3 w-100 border-0 text-start" data-bs-toggle="modal" data-bs-target="#logoutModal" style="padding: 0.6rem 1rem; background: transparent; color: #ef4444; font-weight: 600;">
            <i class="bi bi-box-arrow-left"></i><span>Logout</span>
        </button>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- STRUKTUR FIX MODAL LOGOUT NEO-BRUTALISM    -->
<!-- ========================================== -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true" style="z-index: 1055;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 380px; margin-right: auto; margin-left: auto;">
        <div class="modal-content border-2 border-dark rounded-3 shadow-lg" style="background: #1e293b; color: #fff; box-shadow: 4px 4px #000 !important;">
            
            <div class="modal-header border-0 pb-0 justify-content-end">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body text-center pt-0 px-4">
                <div class="mb-3">
                    <img src="uploads/logo rsi.png" alt="Logo RSI" style="height: 65px; object-fit: contain;">
                </div>
                <h5 class="modal-title fw-bold mb-2" id="logoutModalLabel">Konfirmasi Keluar</h5>
                <p class="text-white-50 small mb-4">Apakah Anda yakin ingin keluar dari sistem keamanan aplikasi RSI FOOD &amp; MART?</p>
                
                <div class="row g-2 justify-content-center mt-3">
                    <div class="col-12">
                        <a href="logout.php" class="btn btn-danger fw-bold py-2.5 rounded-3 border-2 border-dark shadow-sm text-white w-100" style="box-shadow: 3px 3px #000 !important; background-color: #dc2626 !important; border-color: #323232 !important;">
                            <i class="bi bi-box-arrow-left me-2"></i> Ya, Keluar Sekarang
                        </a>
                    </div>
                    <div class="col-12">
                        <button type="button" class="btn btn-light fw-semibold py-2 rounded-3 border-2 border-dark w-100" data-bs-dismiss="modal" style="color: #323232;">
                            Batal
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
  (function(){
    const current = (window.location.pathname || '').split('/').pop();
    document.querySelectorAll('[href]').forEach(a=>{
      try{
        const href = (a.getAttribute('href') || '').split('/').pop();
        if (href && href === current) a.classList.add('active');
      }catch(e){}
    });
  })();
</script>

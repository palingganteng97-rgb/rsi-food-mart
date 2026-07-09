<?php
// sidebar.php - Reusable component (Sidebar + Mobile Topbar)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentFile = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
$menu = [
    'home.php'        => [ 'href' => 'home.php',        'label' => 'Etalase Menu', 'icon' => 'bi-shop' ],
    'user.php'        => [ 'href' => 'user.php',        'label' => 'User',         'icon' => 'bi-person' ],
    'roles.php'       => [ 'href' => 'roles.php',       'label' => 'Roles',        'icon' => 'bi-shield-lock' ],
    'permissions.php' => [ 'href' => 'permissions.php', 'label' => 'Permissions',  'icon' => 'bi-key' ],

    
    ];

function activeClass(string $file, string $currentFile): string {
    return $file === $currentFile ? 'active' : '';
}
?>

<!-- Mobile Topbar + Desktop Sidebar -->
<style>
  :root { --sidebar-w: 280px; --bg: #0f172a; --text: #e5e7eb; --muted:#94a3b8; --green:#22c55e; }
  body { background: var(--bg) !important; color: var(--text); }

  .sidebar-fixed {
      position: fixed;
      top: 0;
      left: 0;
      width: var(--sidebar-w);
      height: 100vh;
      background: #0b1223;
      border-right: 1px solid rgba(148,163,184,.25);
      padding-top: 1rem;
  }
  .sidebar-fixed .app-brand {
      padding: 0 1rem;
      display:flex;
      align-items:center;
      gap:.75rem;
      color: var(--text);
      margin-bottom: 1.25rem;
  }
  .sidebar-fixed .app-brand .logo-badge{
      width: 42px; height:42px;
      border-radius: 12px;
      background: linear-gradient(180deg, rgba(34,197,94,.25), rgba(34,197,94,.06));
      border: 1px solid rgba(34,197,94,.35);
      display:flex; align-items:center; justify-content:center;
      color: var(--green);
      font-size: 1.25rem;
  }
  .navmenu .nav-link {
      color: rgba(229,231,235,.82);
      border-radius: 12px;
      padding: .7rem .85rem;
      display:flex;
      align-items:center;
      gap:.65rem;
      margin: .25rem .5rem;
      transition: background-color .15s ease, color .15s ease, border-color .15s ease;
      border: 1px solid transparent;
  }
  .navmenu .nav-link:hover {
      background: rgba(148,163,184,.12);
      color: var(--text);
  }
  .navmenu .nav-link.active {
      color: #052e16;
      background: rgba(34,197,94,.9);
      border-color: rgba(34,197,94,.55);
  }

  .mobile-topbar {
      position: sticky;
      top: 0;
      z-index: 1030;
      background: rgba(15,23,42,.85);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid rgba(148,163,184,.25);
  }

  /* Reserve layout space to avoid overlap */
  .page-body {
      min-height: 100vh;
      padding-bottom: 84px; /* room for mobile bottom nav */
  }
  @media (min-width: 992px) {
      .page-body { padding-bottom: 0; }
      .content-shift { margin-left: var(--sidebar-w); }
  }
  @media (max-width: 991.98px) {
      .desktop-sidebar { display:none !important; }
      .content-shift { margin-left: 0 !important; }
  }
</style>

<!-- Mobile Topbar -->
<nav class="navbar mobile-topbar d-flex d-lg-none px-3">
  <div class="d-flex align-items-center w-100 justify-content-between">
    <button class="btn btn-link text-decoration-none text-white" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Toggle navigation">
      <i class="bi bi-list fs-3"></i>
    </button>
    <div class="d-flex align-items-center gap-2">
      <div class="logo-badge" aria-hidden="true">
        <i class="bi bi-hospital fs-5"></i>
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

<!-- Desktop Sidebar -->
<aside class="desktop-sidebar sidebar-fixed d-none d-lg-block">
  <div class="app-brand">
    <div class="logo-badge" aria-hidden="true">
      <i class="bi bi-hospital"></i>
    </div>
    <div>
      <div class="fw-bold" style="letter-spacing:.2px;">RSI FOOD &amp; MART</div>
      <div class="text-white-50" style="font-size:.82rem;">Pemesanan Makanan Sehat</div>
    </div>
  </div>

  <div class="navmenu mt-3">
    <?php foreach ($menu as $file => $item): ?>
      <a class="nav-link <?php echo activeClass($file, $currentFile); ?>" href="<?php echo htmlspecialchars($item['href']); ?>">
        <i class="bi <?php echo htmlspecialchars($item['icon']); ?>"></i>
        <span><?php echo htmlspecialchars($item['label']); ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</aside>

<!-- Mobile Sidebar Offcanvas -->
<div class="offcanvas offcanvas-start text-white" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel" style="background:#0b1223; border-right:1px solid rgba(148,163,184,.25);">
  <div class="offcanvas-header">
    <div class="d-flex align-items-center gap-2">
      <div class="logo-badge" aria-hidden="true">
        <i class="bi bi-hospital fs-5"></i>
      </div>
      <div>
        <div class="fw-bold">RSI FOOD &amp; MART</div>
        <div class="text-white-50" style="font-size:.82rem;">Menu Pasien</div>
      </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <div class="navmenu">
      <?php foreach ($menu as $file => $item): ?>
        <a class="nav-link <?php echo activeClass($file, $currentFile); ?>" href="<?php echo htmlspecialchars($item['href']); ?>" data-mobile-nav="1">
          <i class="bi <?php echo htmlspecialchars($item['icon']); ?>"></i>
          <span><?php echo htmlspecialchars($item['label']); ?></span>
        </a>
      <?php endforeach; ?>
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


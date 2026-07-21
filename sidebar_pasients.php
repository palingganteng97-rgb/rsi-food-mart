<?php
// sidebar_pasients.php - Komponen Sidebar Dinamis Modul Pasien
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mengambil nama file saat ini beserta query string
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$currentFile = basename(parse_url($currentUri, PHP_URL_PATH));

// Mengambil parameter query string jika ada
parse_str(parse_url($currentUri, PHP_URL_QUERY) ?? '', $queryParams);
$currentTenantId = $queryParams['tenant_id'] ?? '';

// Definisi Struktur Menu Khusus Modul Pasien
$menu = [
    'home.php' => [ 'href' => 'home.php', 'label' => 'Dashboard Utama', 'icon' => 'bi-grid-1x2-fill' ],
    
    'payments_group' => [
        'label' => 'Pembayaran', 'icon'  => 'bi-credit-card', 'class' => '','sub'   => [
            'payment_methods.php' => [ 'href' => 'payment_methods.php', 'label' => 'Metode Pembayaran', 'icon' => 'bi-wallet2' ],
            'payments.php'        => [ 'href' => 'payments.php', 'label' => 'Daftar Pembayaran', 'icon' => 'bi-cash-coin' ],
            'refunds.php'         => [ 'href' => 'refunds.php', 'label' => 'Pengembalian Dana (Refund)', 'icon' => 'bi-arrow-counterclockwise' ],
        ]
    ],

];

// Logika Otomatis: Mendeteksi sub-menu aktif agar grup dropdown otomatis terbuka (show)
foreach ($menu as $key => $item) {
    if (isset($item['sub'])) {
        if (array_key_exists($currentFile, $item['sub'])) {
            $menu[$key]['class'] .= ' show active';
        }
    }
}

// Fungsi pembantu untuk menentukan class aktif pada menu item tunggal maupun sub-menu
function activeClassPasien(string $file, string $currentFile): string {
    return $file === $currentFile ? 'active-menu-item' : 'text-white-50';
}
?>

<!-- HTML Render Sidebar -->
<aside class="sidebar-patients position-fixed top-0 start-0 vh-100 d-flex flex-column p-3" style="width: 280px; background-color: #0b111e; border-right: 1px solid rgba(148, 163, 184, 0.12); z-index: 1030;">
  
  <!-- Logo & Identitas Aplikasi -->
  <div class="d-flex align-items-center gap-3 pb-4 mb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.12);">
    <div class="bg-success bg-opacity-25 rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
      <i class="bi bi-heart-pulse-fill text-success fs-4"></i>
    </div>
    <div>
      <h5 class="fw-bold m-0 text-white" style="letter-spacing: 0.5px;">RSI PASIEN</h5>
      <span class="text-muted" style="font-size: 0.75rem;">Manajemen Data Pasien</span>
    </div>
  </div>

<!-- Menu Navigasi Dinamis -->
  <div class="flex-grow-1 overflow-y-auto pe-1" style="scrollbar-width: none; -ms-overflow-style: none;">
    <ul class="nav nav-pills flex-column gap-2" style="user-select: none;">
      
      <?php foreach ($menu as $key => $item): ?>
        <?php if (isset($item['sub'])): ?>
          <!-- Nav Item: Dropdown Group -->
          <li class="nav-item">
            <a class="nav-link text-white d-flex align-items-center justify-content-between px-3 py-2.5 rounded-3 transition-all hover-menu-item <?= strpos($item['class'] ?? '', 'active') !== false ? 'bg-secondary bg-opacity-10 fw-semibold' : 'text-white-50' ?>" 
               data-bs-toggle="collapse" 
               href="#collapse_<?= $key ?>" 
               role="button" 
               aria-expanded="<?= strpos($item['class'] ?? '', 'show') !== false ? 'true' : 'false' ?>">
              <div class="d-flex align-items-center gap-3">
                <i class="bi <?= $item['icon'] ?> fs-5 opacity-75"></i>
                <span><?= $item['label'] ?></span>
              </div>
              <i class="bi bi-chevron-down fs-7 transition-all dropdown-arrow"></i>
            </a>
            
            <!-- Sub Menu Container -->
            <div class="collapse <?= strpos($item['class'] ?? '', 'show') !== false ? 'show' : '' ?> ps-3 mt-1" id="collapse_<?= $key ?>">
              <ul class="nav nav-pills flex-column gap-1 sub-menu-list">
                <?php foreach ($item['sub'] as $subFile => $subItem): ?>
                  <li class="nav-item">
                    <a href="<?= $subItem['href'] ?>" class="nav-link d-flex align-items-center gap-3 px-3 py-2 rounded-3 transition-all hover-menu-item <?= activeClassPasien($subFile, $currentFile) ?>" style="font-size: 0.85rem;">
                      <i class="bi <?= $subItem['icon'] ?> fs-6 opacity-75"></i>
                      <span><?= $subItem['label'] ?></span>
                    </a>
                  </li>
                <?php endforeach; ?> <!-- PERBAIKAN: Diubah dari endphp menjadi endforeach; -->
              </ul>
            </div>
          </li>
        <?php else: ?>
          <!-- Nav Item: Menu Tunggal biasa -->
          <li class="nav-item">
            <a href="<?= $item['href'] ?>" class="nav-link text-white d-flex align-items-center gap-3 px-3 py-2.5 rounded-3 transition-all hover-menu-item <?= $currentFile === $item['href'] ? 'active-menu-item' : 'text-white-50' ?>" style="font-size: 0.9rem; font-weight: 500;">
              <i class="bi <?= $item['icon'] ?> fs-5 opacity-75"></i>
              <span><?= $item['label'] ?></span>
            </a>
          </li>
        <?php endif; ?>
      <?php endforeach; ?>

    </ul>
  </div>

  <!-- Profil Pengguna & Sistem Keluar -->
  <div class="pt-3 mt-auto" style="border-top: 1px solid rgba(148, 163, 184, 0.12);">
    <div class="d-flex align-items-center justify-content-between p-2 rounded-3" style="background-color: rgba(15, 23, 42, 0.4);">
      <div class="d-flex align-items-center gap-2">
        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width: 36px; height: 36px; font-size: 0.85rem;">
          ADM
        </div>
        <div style="max-width: 150px;">
          <div class="text-white fw-medium text-truncate" style="font-size: 0.85rem;">Administrator</div>
          <div class="text-muted text-truncate" style="font-size: 0.75rem;">Petugas Medis</div>
        </div>
      </div>
      <a href="logout.php" class="btn btn-sm btn-outline-danger border-0 p-2 rounded-2" title="Keluar">
        <i class="bi bi-box-arrow-right fs-5"></i>
      </a>
    </div>
  </div>

</aside>

<style>
  /* Menghilangkan efek scrollbar bawaan */
  .sidebar-patients .overflow-y-auto::-webkit-scrollbar { display: none; }
  .sidebar-patients .transition-all { transition: all 0.2s ease-in-out; }

  /* Efek visual ketika menu disorot oleh kursor mouse */
  .sidebar-patients .hover-menu-item:hover {
    background-color: rgba(255, 255, 255, 0.05) !important;
    color: #ffffff !important;
  }

  /* Status item menu aktif yang sedang terbuka */
  .sidebar-patients .active-menu-item {
    background-color: rgba(34, 197, 94, 0.15) !important;
    color: #22c55e !important;
    border-left: 3px solid #22c55e;
    border-radius: 0 8px 8px 0 !important;
  }
  
  /* Efek rotasi ikon panah ketika collapse dibuka */
  .sidebar-patients a[aria-expanded="true"] .dropdown-arrow {
    transform: rotate(180deg);
  }
  
  /* Garis bantu hirarki di samping submenu */
  .sidebar-patients .sub-menu-list {
    border-left: 1px solid rgba(148, 163, 184, 0.15);
    margin-left: 10px;
    padding-left: 5px;
  }
</style>

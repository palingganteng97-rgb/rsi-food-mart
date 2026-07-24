<?php
// promos.php - Modul CRUD Promos (Admin) LENGKAP
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Ambil notifikasi status dari query parameter URL
$status = isset($_GET['status']) ? (string)$_GET['status'] : '';
$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';

// ==========================================
// 1. ACTION HANDLER (PROSES POST/GET ACTIONS)
// ==========================================
$action = isset($_POST['action']) ? (string)$_POST['action'] : (isset($_GET['action']) ? (string)$_GET['action'] : '');

// Aksi Tambah Promo (Create)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenant_id = (int)($_POST['tenant_id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $discount_type = trim((string)($_POST['discount_type'] ?? 'percent'));
    $discount_value = (float)($_POST['discount_value'] ?? 0);
    $start_date = trim((string)($_POST['start_date'] ?? ''));
    $end_date = trim((string)($_POST['end_date'] ?? ''));

    if ($tenant_id === 0 || $title === '' || $start_date === '' || $end_date === '') {
        header('Location: promos.php?status=error&msg=Semua kolom wajib diisi!');
        exit;
    }

    $sqlInsert = 'INSERT INTO promos (tenant_id, title, discount_type, discount_value, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)';
    $stmtIns = $conn->prepare($sqlInsert);
    $stmtIns->bind_param('issdss', $tenant_id, $title, $discount_type, $discount_value, $start_date, $end_date);
    
    if ($stmtIns->execute()) {
        header('Location: promos.php?status=success_insert');
    } else {
        header('Location: promos.php?status=error&msg=Gagal menyimpan data promo');
    }
    exit;
}

// Aksi Ubah Promo (Update)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $tenant_id = (int)($_POST['tenant_id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $discount_type = trim((string)($_POST['discount_type'] ?? 'percent'));
    $discount_value = (float)($_POST['discount_value'] ?? 0);
    $start_date = trim((string)($_POST['start_date'] ?? ''));
    $end_date = trim((string)($_POST['end_date'] ?? ''));

    if ($id === 0 || $tenant_id === 0 || $title === '' || $start_date === '' || $end_date === '') {
        header('Location: promos.php?status=error&msg=Semua kolom wajib diisi!');
        exit;
    }

    $sqlUpdate = 'UPDATE promos SET tenant_id = ?, title = ?, discount_type = ?, discount_value = ?, start_date = ?, end_date = ? WHERE id = ?';
    $stmtUpd = $conn->prepare($sqlUpdate);
    $stmtUpd->bind_param('issdssi', $tenant_id, $title, $discount_type, $discount_value, $start_date, $end_date, $id);
    
    if ($stmtUpd->execute()) {
        header('Location: promos.php?status=success_update');
    } else {
        header('Location: promos.php?status=error&msg=Gagal memperbarui data promo');
    }
    exit;
}

// Aksi Hapus Promo (Delete)
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    $sqlDel = 'DELETE FROM promos WHERE id = ?';
    $stmtDel = $conn->prepare($sqlDel);
    $stmtDel->bind_param('i', $id);
    
    if ($stmtDel->execute()) {
        header('Location: promos.php?status=success_delete');
    } else {
        header('Location: promos.php?status=error&msg=Gagal menghapus data promo');
    }
    exit;
}

// ==========================================
// 2. LOGIKA READ & PAGINATION (DATA RETRIEVAL)
// ==========================================
$search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$where = '';
$params = [];
$types = '';

if ($search !== '') {
    // Mencari berdasarkan judul promo atau nama tenant
    $where = 'WHERE p.title LIKE ? OR t.name LIKE ?';
    $types = 'ss';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

// Hitung total baris data (menggunakan JOIN agar pencarian nama tenant sinkron)
$sqlCount = 'SELECT COUNT(*) AS total FROM promos p LEFT JOIN tenants t ON p.tenant_id = t.id ' . $where;
$stmtCount = $conn->prepare($sqlCount);
if ($where !== '') {
    $stmtCount->bind_param($types, $params[0], $params[1]);
}
$stmtCount->execute();
$resCount = $stmtCount->get_result();
$totalRows = 0;
if ($resCount) {
    $row = $resCount->fetch_assoc();
    $totalRows = (int)($row['total'] ?? 0);
}
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// Ambil data gabungan tabel promos dan tenants
$sql = 'SELECT p.*, t.name AS tenant_name FROM promos p LEFT JOIN tenants t ON p.tenant_id = t.id ' . $where . ' ORDER BY p.id DESC LIMIT ? OFFSET ?';
$stmt = $conn->prepare($sql);

if ($where !== '') {
    $stmt->bind_param($types . 'ii', $params[0], $params[1], $perPage, $offset);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$list = $stmt->get_result();
$promos = [];
if ($list) {
    while ($r = $list->fetch_assoc()) {
        $promos[] = $r;
    }
}

// Ambil daftar tenant aktif untuk kebutuhan opsi Select di form modal input
$listActiveTenants = [];
$resTenants = $conn->query('SELECT id, name FROM tenants ORDER BY name ASC');
if ($resTenants) {
    while ($t = $resTenants->fetch_assoc()) {
        $listActiveTenants[] = $t;
    }
}
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
    .text-white-element { -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }
    .modal-lg-custom { max-width: 800px !important; }
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

<main class="content-shift p-4">
  <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
    
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
      <div>
        <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;"> Promos </h2>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <form method="GET" action="promos.php" class="d-flex gap-2">
            <input type="text" name="q" class="form-control rounded-3 bg-dark text-white border-secondary" placeholder="Cari promo..." value="<?= htmlspecialchars($search) ?>" style="font-size: 0.9rem; min-width: 200px;">
            <button type="submit" class="btn btn-secondary rounded-3 px-3"><i class="bi bi-search"></i></button>
            <?php if ($search !== ''): ?>
                <a href="promos.php" class="btn btn-outline-light rounded-3">Reset</a>
            <?php endif; ?>
        </form>
        <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" onclick="openTambahPromo()">
          <i class="bi bi-tags"></i> Tambah Promo
        </button>
      </div>
    </div>

    <?php if (!empty($status)): ?>
        <div class="alert <?= ($status === 'success' || strpos($status, 'success') !== false) ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
            <strong>
                <?php 
                if ($status === 'success' && !empty($msg)) echo htmlspecialchars(urldecode($msg));
                elseif ($status == 'success_insert') echo "Data promo baru berhasil ditambahkan!";
                elseif ($status == 'success_update') echo "Data promo berhasil diperbarui!";
                elseif ($status == 'success_delete') echo "Data promo berhasil dihapus!";
                else echo "Operasi gagal: " . htmlspecialchars($msg);
                ?>
            </strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div id="dragScrollProductContainer" class="table-responsive rounded-3 drag-scroll-container" style="border: none !important; background: transparent !important; cursor: grab; box-shadow: none !important; -webkit-box-shadow: none !important;">
      <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 1000px; user-select: none; border-collapse: collapse !important;">
        <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
          <tr>
            <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 80px;"> ID</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 200px;"> Tenant</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 250px;"> Judul Promo</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;"> Tipe Diskon</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;"> Nilai Diskon</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;"> Tanggal Mulai</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;"> Tanggal Selesai</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;"> Aksi</th>
          </tr>
        </thead>
        <tbody style="background: transparent !important;">
          <?php if (!empty($promos)): 
              foreach ($promos as $row): ?>
                  <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                    <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= $row['id'] ?></td>
                    <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['tenant_name'] ?? 'ID: '.$row['tenant_id']) ?></td>
                    <td class="text-white" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['title']) ?></td>
                    <td class="text-center text-white-50" style="background: transparent !important; border: none !important;">
                        <span class="badge bg-secondary bg-opacity-25 text-info border border-info border-opacity-25 px-2.5 py-1 rounded">
                            <?= $row['discount_type'] === 'percent' ? 'Persentase' : 'Nominal' ?>
                        </span>
                    </td>
                    <td class="text-center fw-bold text-warning" style="background: transparent !important; border: none !important;">
                        <?= $row['discount_type'] === 'percent' ? number_format($row['discount_value'], 0).'%' : 'Rp '.number_format($row['discount_value'], 0, ',', '.') ?>
                    </td>
                    <td class="text-center text-white-50" style="background: transparent !important; border: none !important;"><?= date('d M Y', strtotime($row['start_date'])) ?></td>
                    <td class="text-center text-white-50" style="background: transparent !important; border: none !important;"><?= date('d M Y', strtotime($row['end_date'])) ?></td>
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                      <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-sm btn-outline-warning rounded-2" data-bs-toggle="modal" data-bs-target="#modalPromo" onclick='openEditPromo(<?= json_encode($row) ?>)'>
                          <i class="bi bi-pencil-square"></i>
                        </button>
                        <a href="promos.php?action=delete&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger rounded-2" onclick="return confirm('Apakah Anda yakin ingin menghapus data promo ini?')">
                          <i class="bi bi-trash"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
              <?php endforeach; ?>
          <?php else: ?>
              <tr>
                <td colspan="8" class="text-center py-5 text-muted italic" style="background: transparent !important; border: none !important;">Belum ada data promo yang terdaftar.</td>
              </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-center mt-4">
            <nav>
                <ul class="pagination mb-0 gap-1 shadow-sm">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link rounded-2 bg-dark text-white border-secondary" href="promos.php?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>">Prev</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                            <a class="page-link rounded-2 <?= $page == $i ? 'bg-primary border-primary text-white' : 'bg-dark text-white border-secondary' ?>" href="promos.php?page=<?= $i ?>&q=<?= urlencode($search) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link rounded-2 bg-dark text-white border-secondary" href="promos.php?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
  </div>
</main>

<!-- MODAL FORM INPUT MELEBAR DI TENGAH (WIDE MODE & BEBAS SCROLLBAR) -->
<div class="modal fade" id="modalPromo" tabindex="-1" aria-labelledby="modalPromoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.93) !important; backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.2); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
                <h5 class="modal-title fw-bold text-white" id="modalPromoLabel">Form Data Promo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formPromo" action="promos.php" method="POST">
                <input type="hidden" name="action" id="form-action" value="create">
                <input type="hidden" name="id" id="promo-id">
                
                <div class="modal-body" style="overflow: visible !important;">
                    <div class="row g-3">
                        <!-- PILIH TENANT -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Pilih Tenant <span class="text-danger">*</span></label>
                            <select class="form-select" name="tenant_id" id="promo-tenant-id" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                                <option value="" disabled selected>-- Pilih Tenant --</option>
                                <?php foreach ($listActiveTenants as $tOption): ?>
                                    <option value="<?= $tOption['id'] ?>"><?= htmlspecialchars($tOption['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- JUDUL PROMO -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Judul Promo <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="promo-title" class="form-control" maxlength="150" placeholder="Masukkan judul promosi" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>

                        <!-- TIPE DISKON -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Tipe Diskon <span class="text-danger">*</span></label>
                            <select name="discount_type" id="promo-discount-type" class="form-select" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                                <option value="percent">Persentase (%)</option>
                                <option value="nominal">Nominal Rupiah (Rp)</option>
                            </select>
                        </div>

                        <!-- NILAI DISKON -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Nilai Diskon <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="discount_value" id="promo-discount-value" class="form-control" placeholder="Contoh: 10 atau 15000" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>

                        <!-- TANGGAL MULAI -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Tanggal Mulai <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="promo-start-date" class="form-control" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>

                        <!-- TANGGAL SELESAI -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Tanggal Selesai <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" id="promo-end-date" class="form-control" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.15); background: rgba(15, 23, 42, 0.95); border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="btn-submit-modal">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus Promos -->
<div class="modal fade" id="modalConfirmDeletePromo" tabindex="-1" aria-labelledby="modalConfirmDeletePromoLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-bg-dark border-secondary" style="background-color: #111827 !important; border-color: #374151 !important;">
      
      <div class="modal-header border-bottom border-secondary">
        <h5 class="modal-title text-white fw-bold d-flex align-items-center" id="modalConfirmDeletePromoLabel">
          <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Konfirmasi Hapus
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body text-center p-4">
        <div class="mb-3">
          <i class="bi bi-tags text-danger" style="font-size: 3.5rem;"></i>
        </div>
        <p class="text-light fs-6 mb-1">Apakah Anda yakin ingin menghapus data promo berikut?</p>
        <h6 id="delete_promo_title" class="text-warning fw-bold mt-2 mb-0"></h6>
      </div>
      
      <div class="modal-footer border-top border-secondary justify-content-center">
        <button type="button" class="btn btn-secondary px-4 rounded-2" data-bs-dismiss="modal">Batal</button>
        <button type="button" id="btnConfirmDeletePromoAction" class="btn btn-danger px-4 rounded-2 fw-bold">Oke, Hapus</button>
      </div>

    </div>
  </div>
</div>

<!-- JAVASCRIPT LOGIC -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.history.replaceState && window.location.search) {
        const url = new URL(window.location.href);
        url.searchParams.delete('status');
        url.searchParams.delete('msg');
        window.history.replaceState({}, document.title, url.pathname + url.search);
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const promoSlider = document.getElementById('dragScrollProductContainer');
    if (!promoSlider) return;
    let isDown = false, startX, scrollLeft;
    
    promoSlider.addEventListener('mousedown', (e) => {
        isDown = true; 
        promoSlider.style.cursor = 'grabbing';
        startX = e.pageX - promoSlider.offsetLeft; 
        scrollLeft = promoSlider.scrollLeft;
    });
    promoSlider.addEventListener('mouseleave', () => { isDown = false; promoSlider.style.cursor = 'grab'; });
    promoSlider.addEventListener('mouseup', () => { isDown = false; promoSlider.style.cursor = 'grab'; });
    promoSlider.addEventListener('mousemove', (e) => {
        if (!isDown) return; 
        e.preventDefault();
        const x = e.pageX - promoSlider.offsetLeft;
        promoSlider.scrollLeft = scrollLeft - ((x - startX) * 1.5);
    });
});

let deletePromoUrlTarget = '';
let bootstrapDeletePromoModalInstance = null;
let bootstrapModalPromoInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    // Inisialisasi singleton modal #modalPromo — sekali saja
    const modalElement = document.getElementById('modalPromo');
    if (modalElement) {
        bootstrapModalPromoInstance = new bootstrap.Modal(modalElement, {
            backdrop: true,
            keyboard: true
        });

        // Cleanup event hidden.bs.modal — jamin backdrop & class terhapus
        modalElement.addEventListener('hidden.bs.modal', function() {
            // Hapus seluruh .modal-backdrop yang mungkin tertinggal
            document.querySelectorAll('.modal-backdrop').forEach(function(el) {
                el.remove();
            });
            // Hapus class modal-open dari body
            document.body.classList.remove('modal-open');
            // Kembalikan style inline body ke kondisi semula
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        });
    }

    const formPromo = document.getElementById('formPromo');
    if (formPromo) {
        formPromo.addEventListener('submit', function (e) {
            const startDate = document.getElementById('promo-start-date').value;
            const endDate = document.getElementById('promo-end-date').value;
            if (startDate && endDate && endDate < startDate) {
                e.preventDefault();
                alert('⚠️ Logika Salah: Tanggal Selesai tidak boleh lebih awal dari Tanggal Mulai!');
            }
        });
    }

    const btnActionDelete = document.getElementById('btnConfirmDeletePromoAction');
    if (btnActionDelete) {
        btnActionDelete.addEventListener('click', function() {
            if (deletePromoUrlTarget) {
                window.location.href = deletePromoUrlTarget;
            }
        });
    }
});

function openTambahPromo() {
    document.getElementById('formPromo').reset();
    document.getElementById('modalPromoLabel').innerText = 'Tambah Promo Baru';
    document.getElementById('promo-id').value = '';
    document.getElementById('form-action').value = 'create';
    document.getElementById('promo-tenant-id').disabled = false;
    
    const submitBtn = document.getElementById('btn-submit-modal');
    if (submitBtn) {
        submitBtn.className = "btn btn-success";
        submitBtn.innerText = "Simpan Data";
    }
    
    // Gunakan singleton instance — tanpa data-bs-toggle
    if (bootstrapModalPromoInstance) {
        bootstrapModalPromoInstance.show();
    }
}

function openEditPromo(data) {
    if (!data) return;
    document.getElementById('formPromo').reset();
    document.getElementById('modalPromoLabel').innerText = 'Perbarui Data Promo';
    document.getElementById('promo-id').value = data.id;
    document.getElementById('form-action').value = 'update';
    document.getElementById('promo-tenant-id').value = data.tenant_id;
    document.getElementById('promo-title').value = data.title || '';
    document.getElementById('promo-discount-type').value = data.discount_type || 'percent';
    document.getElementById('promo-discount-value').value = data.discount_value || 0;
    document.getElementById('promo-start-date').value = data.start_date || '';
    document.getElementById('promo-end-date').value = data.end_date || '';
    
    const submitBtn = document.getElementById('btn-submit-modal');
    if (submitBtn) {
        submitBtn.className = "btn btn-warning text-dark fw-medium";
        submitBtn.innerText = "Perbarui Data";
    }
    
    // Gunakan singleton instance — tidak membuat instance baru
    if (bootstrapModalPromoInstance) {
        bootstrapModalPromoInstance.show();
    }
}

function triggerDeletePromo(url, promoTitle) {
    deletePromoUrlTarget = url;
    const titlePlaceholder = document.getElementById('delete_promo_title');
    if (titlePlaceholder) {
        titlePlaceholder.innerText = promoTitle || '(Tanpa Judul)';
    }
    
    const modalElement = document.getElementById('modalConfirmDeletePromo');
    if (modalElement) {
        if (!bootstrapDeletePromoModalInstance) {
            bootstrapDeletePromoModalInstance = new bootstrap.Modal(modalElement);
        }
        bootstrapDeletePromoModalInstance.show();
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

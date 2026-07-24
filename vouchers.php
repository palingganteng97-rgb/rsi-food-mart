<?php
// vouchers.php
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$status = isset($_GET['status']) ? (string)$_GET['status'] : '';
$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';

// ==========================================
// 1. ACTION HANDLER (PROSES POST/GET ACTIONS)
// ==========================================
$action = isset($_POST['action']) ? (string)$_POST['action'] : (isset($_GET['action']) ? (string)$_GET['action'] : '');

// Aksi Tambah Voucher (Create)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    $discount_type = trim((string)($_POST['discount_type'] ?? 'percent'));
    $discount_value = (float)($_POST['discount_value'] ?? 0);
    $minimum_purchase = (float)($_POST['minimum_purchase'] ?? 0);
    $quota = (int)($_POST['quota'] ?? 0);
    $start_date = trim((string)($_POST['start_date'] ?? ''));
    $end_date = trim((string)($_POST['end_date'] ?? ''));
    $statusParam = isset($_POST['status']) ? (int)$_POST['status'] : 1;

    if ($code === '' || $start_date === '' || $end_date === '') {
        header('Location: vouchers.php?status=error&msg=Kolom kode dan tanggal wajib diisi!');
        exit;
    }

    $sqlInsert = 'INSERT INTO vouchers (code, discount_type, discount_value, minimum_purchase, quota, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
    $stmtIns = $conn->prepare($sqlInsert);
    $stmtIns->bind_param('ssddissi', $code, $discount_type, $discount_value, $minimum_purchase, $quota, $start_date, $end_date, $statusParam);
    
    if ($stmtIns->execute()) {
        header('Location: vouchers.php?status=success_insert');
    } else {
        header('Location: vouchers.php?status=error&msg=Gagal menyimpan data voucher');
    }
    exit;
}

// Aksi Ubah Voucher (Update)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    $discount_type = trim((string)($_POST['discount_type'] ?? 'percent'));
    $discount_value = (float)($_POST['discount_value'] ?? 0);
    $minimum_purchase = (float)($_POST['minimum_purchase'] ?? 0);
    $quota = (int)($_POST['quota'] ?? 0);
    $start_date = trim((string)($_POST['start_date'] ?? ''));
    $end_date = trim((string)($_POST['end_date'] ?? ''));
    $statusParam = isset($_POST['status']) ? (int)$_POST['status'] : 1;

    if ($id === 0 || $code === '' || $start_date === '' || $end_date === '') {
        header('Location: vouchers.php?status=error&msg=Semua kolom wajib diisi!');
        exit;
    }

    $sqlUpdate = 'UPDATE vouchers SET code = ?, discount_type = ?, discount_value = ?, minimum_purchase = ?, quota = ?, start_date = ?, end_date = ?, status = ? WHERE id = ?';
    $stmtUpd = $conn->prepare($sqlUpdate);
    $stmtUpd->bind_param('ssddissii', $code, $discount_type, $discount_value, $minimum_purchase, $quota, $start_date, $end_date, $statusParam, $id);
    
    if ($stmtUpd->execute()) {
        header('Location: vouchers.php?status=success_update');
    } else {
        header('Location: vouchers.php?status=error&msg=Gagal memperbarui data voucher');
    }
    exit;
}

// Aksi Hapus Voucher (Delete)
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    $sqlDel = 'DELETE FROM vouchers WHERE id = ?';
    $stmtDel = $conn->prepare($sqlDel);
    $stmtDel->bind_param('i', $id);
    
    if ($stmtDel->execute()) {
        header('Location: vouchers.php?status=success_delete');
    } else {
        header('Location: vouchers.php?status=error&msg=Gagal menghapus data voucher');
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
    $where = 'WHERE code LIKE ?';
    $types = 's';
    $params[] = '%' . $search . '%';
}

$sqlCount = 'SELECT COUNT(*) AS total FROM vouchers ' . $where;
$stmtCount = $conn->prepare($sqlCount);
if ($where !== '') {
    $stmtCount->bind_param($types, $params[0]);
}
$stmtCount->execute();
$resCount = $stmtCount->get_result();
$totalRows = 0;
if ($resCount) {
    $row = $resCount->fetch_assoc();
    $totalRows = (int)($row['total'] ?? 0);
}
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$sql = 'SELECT id, code, discount_type, discount_value, minimum_purchase, quota, start_date, end_date, status FROM vouchers ' . $where . ' ORDER BY id DESC LIMIT ? OFFSET ?';
$stmt = $conn->prepare($sql);

if ($where !== '') {
    $stmt->bind_param($types . 'ii', $params[0], $perPage, $offset);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$list = $stmt->get_result();
$vouchers = [];
if ($list) {
    while ($r = $list->fetch_assoc()) {
        $vouchers[] = $r;
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
  <!-- Container tabel dengan tema gelap transparan -->
  <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
    
    <!-- HEADER TABEL & TOMBOL TAMBAH VOUCHER -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
      <div>
        <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;"> Data Vouchers </h2>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <!-- Form Pencarian -->
        <form method="GET" action="vouchers.php" class="d-flex gap-2">
            <input type="text" name="q" class="form-control rounded-3 bg-dark text-white border-secondary" placeholder="Cari kode..." value="<?= htmlspecialchars($search) ?>" style="font-size: 0.9rem; min-width: 200px;">
            <button type="submit" class="btn btn-secondary rounded-3 px-3"><i class="bi bi-search"></i></button>
            <?php if ($search !== ''): ?>
                <a href="vouchers.php" class="btn btn-outline-light rounded-3">Reset</a>
            <?php endif; ?>
        </form>
        <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalVoucher" onclick="openTambahVoucher()">
          <i class="bi bi-ticket-perforated-fill"></i> Tambah Voucher
        </button>
      </div>
    </div>

    <!-- NOTIFIKASI STATUS OPERASI CRUD -->
    <?php if (!empty($status)): ?>
        <div class="alert <?= ($status === 'success' || strpos($status, 'success') !== false) ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
            <strong>
                <?php 
                if ($status === 'success' && !empty($msg)) echo htmlspecialchars(urldecode($msg));
                elseif ($status == 'success_insert') echo "Data voucher baru berhasil ditambahkan!";
                elseif ($status == 'success_update') echo "Data voucher berhasil diperbarui!";
                elseif ($status == 'success_delete') echo "Data voucher berhasil dihapus!";
                else echo "Operasi gagal: " . htmlspecialchars($msg);
                ?>
            </strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- STRUKTUR TABEL LIST DATA VOUCHER -->
    <div id="dragScrollProductContainer" class="table-responsive rounded-3 drag-scroll-container" style="border: none !important; background: transparent !important; cursor: grab; box-shadow: none !important; -webkit-box-shadow: none !important;">
      <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 1200px; user-select: none; border-collapse: collapse !important;">
        <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
          <tr>
            <th class="py-3 px-2 text-center text-white" style="background: transparent !important; border: none !important; width: 80px;">ID</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 180px;">Kode Voucher</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 140px;">Tipe Diskon</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;">Nilai Diskon</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 160px;">Min. Pembelian</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 100px;">Kuota</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 140px;">Tanggal Mulai</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 140px;">Tanggal Selesai</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 120px;">Status</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 130px;">Aksi</th>
          </tr>
        </thead>
        <tbody style="background: transparent !important;">
          <?php if (!empty($vouchers)): 
              foreach ($vouchers as $row): ?>
                  <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                    <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= $row['id'] ?></td>
                    <td class="fw-bold text-white" style="background: transparent !important; border: none !important;">
                        <span class="bg-dark px-2 py-1 rounded border border-secondary text-info" style="font-family: monospace; letter-spacing: 1px; font-size: 0.95rem;"><?= htmlspecialchars($row['code']) ?></span>
                    </td>
                    <td class="text-center text-white-50" style="background: transparent !important; border: none !important;">
                        <?= $row['discount_type'] === 'percent' ? 'Persentase' : 'Nominal' ?>
                    </td>
                    <td class="text-center fw-bold text-warning" style="background: transparent !important; border: none !important;">
                        <?= $row['discount_type'] === 'percent' ? number_format($row['discount_value'], 0).'%' : 'Rp '.number_format($row['discount_value'], 0, ',', '.') ?>
                    </td>
                    <td class="text-center text-white-50" style="background: transparent !important; border: none !important;">
                        Rp <?= number_format($row['minimum_purchase'], 0, ',', '.') ?>
                    </td>
                    <td class="text-center fw-medium text-white" style="background: transparent !important; border: none !important;"><?= number_format($row['quota'], 0) ?></td>
                    <td class="text-center text-white-50 small" style="background: transparent !important; border: none !important;"><?= date('d M Y', strtotime($row['start_date'])) ?></td>
                    <td class="text-center text-white-50 small" style="background: transparent !important; border: none !important;"><?= date('d M Y', strtotime($row['end_date'])) ?></td>
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <?php if ((int)$row['status'] === 1): ?>
                            <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-2.5 py-1 rounded-pill" style="font-size: 0.75rem;">1 (Aktif)</span>
                        <?php else: ?>
                            <span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-50 px-2.5 py-1 rounded-pill" style="font-size: 0.75rem;">0 (Nonaktif)</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                      <div class="d-flex justify-content-center gap-1">
                        <button class="btn btn-sm btn-outline-warning rounded-2" onclick='openEditVoucher(<?= json_encode($row) ?>)' title="Edit">
                          <i class="bi bi-pencil-square"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger rounded-2" onclick="triggerDeleteVoucher('vouchers.php?action=delete&id=<?= $row['id'] ?>', '<?= htmlspecialchars($row['code']) ?>')" title="Hapus">
                          <i class="bi bi-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
              <?php endforeach; ?>
          <?php else: ?>
              <tr>
                <td colspan="10" class="text-center py-5 text-muted italic" style="background: transparent !important; border: none !important;">Belum ada data voucher yang terdaftar.</td>
              </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINATION CONTROL -->
    <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-center mt-4">
            <nav>
                <ul class="pagination mb-0 gap-1 shadow-sm">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link rounded-2 bg-dark text-white border-secondary" href="vouchers.php?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>">Prev</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                            <a class="page-link rounded-2 <?= $page == $i ? 'bg-primary border-primary text-white' : 'bg-dark text-white border-secondary' ?>" href="vouchers.php?page=<?= $i ?>&q=<?= urlencode($search) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link rounded-2 bg-dark text-white border-secondary" href="vouchers.php?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
  </div>
</main>

<!-- MODAL FORM INPUT MELEBAR DI TENGAH (WIDE MODE & BEBAS SCROLLBAR) -->
<div class="modal fade" id="modalVoucher" tabindex="-1" aria-labelledby="modalVoucherLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.93) !important; backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.2); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
                <h5 class="modal-title fw-bold text-white" id="modalVoucherLabel">Form Data Voucher</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formVoucher" action="vouchers.php" method="POST">
                <input type="hidden" name="action" id="form-action" value="create">
                <input type="hidden" name="id" id="voucher-id">
                
                <div class="modal-body" style="overflow: visible !important;">
                    <div class="row g-3">
                        <!-- KODE VOUCHER -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Kode Voucher <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="voucher-code" class="form-control" maxlength="50" placeholder="Contoh: DISKONMANTAP" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important; text-transform: uppercase;" required>
                        </div>

                        <!-- TIPE DISKON -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Tipe Diskon <span class="text-danger">*</span></label>
                            <select name="discount_type" id="voucher-discount-type" class="form-select" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                                <option value="percent">Persentase (%)</option>
                                <option value="nominal">Nominal Rupiah (Rp)</option>
                            </select>
                        </div>

                        <!-- NILAI DISKON -->
                        <div class="col-md-4">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Nilai Diskon <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="discount_value" id="voucher-discount-value" class="form-control" placeholder="10 atau 15000" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>

                        <!-- MINIMUM PEMBELIAN -->
                        <div class="col-md-4">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Min. Pembelian <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="minimum_purchase" id="voucher-minimum-purchase" class="form-control" placeholder="0" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>

                        <!-- QUOTA -->
                        <div class="col-md-4">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Kuota Pemakaian <span class="text-danger">*</span></label>
                            <input type="number" name="quota" id="voucher-quota" class="form-control" placeholder="100" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>

                        <!-- TANGGAL MULAI -->
                        <div class="col-md-4">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Tanggal Mulai <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="voucher-start-date" class="form-control" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>

                        <!-- TANGGAL SELESAI -->
                        <div class="col-md-4">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Tanggal Selesai <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" id="voucher-end-date" class="form-control" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>

                        <!-- STATUS -->
                        <div class="col-md-4">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Status <span class="text-danger">*</span></label>
                            <select name="status" id="voucher-status" class="form-select" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                                <option value="1" selected>1 (Aktif)</option>
                                <option value="0">0 (Nonaktif)</option>
                            </select>
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

<!-- MODAL KONFIRMASI HAPUS VOUCHER -->
<div class="modal fade" id="modalConfirmDeleteVoucher" tabindex="-1" aria-labelledby="modalConfirmDeleteVoucherLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-bg-dark border-secondary" style="background-color: #111827 !important; border-color: #374151 !important;">
      
      <div class="modal-header border-bottom border-secondary">
        <h5 class="modal-title text-white fw-bold d-flex align-items-center" id="modalConfirmDeleteVoucherLabel">
          <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Konfirmasi Hapus
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body text-center p-4">
        <div class="mb-3">
          <i class="bi bi-ticket-perforated-fill text-danger" style="font-size: 3.5rem;"></i>
        </div>
        <p class="text-light fs-6 mb-1">Apakah Anda yakin ingin menghapus data voucher berikut?</p>
        <h6 id="delete_voucher_code" class="text-warning fw-bold mt-2 mb-0" style="font-family: monospace; letter-spacing: 1px;"></h6>
      </div>
      
      <div class="modal-footer border-top border-secondary justify-content-center">
        <button type="button" class="btn btn-secondary px-4 rounded-2" data-bs-dismiss="modal">Batal</button>
        <button type="button" id="btnConfirmDeleteVoucherAction" class="btn btn-danger px-4 rounded-2 fw-bold">Oke, Hapus</button>
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
    const voucherSlider = document.getElementById('dragScrollProductContainer');
    if (!voucherSlider) return;
    let isDown = false, startX, scrollLeft;
    
    voucherSlider.addEventListener('mousedown', (e) => {
        isDown = true; 
        voucherSlider.style.cursor = 'grabbing';
        startX = e.pageX - voucherSlider.offsetLeft; 
        scrollLeft = voucherSlider.scrollLeft;
    });
    voucherSlider.addEventListener('mouseleave', () => { isDown = false; voucherSlider.style.cursor = 'grab'; });
    voucherSlider.addEventListener('mouseup', () => { isDown = false; voucherSlider.style.cursor = 'grab'; });
    voucherSlider.addEventListener('mousemove', (e) => {
        if (!isDown) return; 
        e.preventDefault();
        const x = e.pageX - voucherSlider.offsetLeft;
        voucherSlider.scrollLeft = scrollLeft - ((x - startX) * 1.5);
    });
});

let deleteVoucherUrlTarget = '';

document.addEventListener('DOMContentLoaded', function() {
    const formVoucher = document.getElementById('formVoucher');
    if (formVoucher) {
        formVoucher.addEventListener('submit', function (e) {
            const startDate = document.getElementById('voucher-start-date').value;
            const endDate = document.getElementById('voucher-end-date').value;
            if (startDate && endDate && endDate < startDate) {
                e.preventDefault();
                alert('⚠️ Logika Salah: Tanggal Selesai tidak boleh lebih awal dari Tanggal Mulai!');
            }
        });
    }

    const btnActionDelete = document.getElementById('btnConfirmDeleteVoucherAction');
    if (btnActionDelete) {
        btnActionDelete.addEventListener('click', function() {
            if (deleteVoucherUrlTarget) {
                window.location.href = deleteVoucherUrlTarget;
            }
        });
    }

    // ============================================================
    // GLOBAL CLEANUP: Safety net untuk semua modal di halaman ini
    // Memastikan backdrop, class modal-open, dan style body
    // selalu dikembalikan ke kondisi normal saat modal ditutup.
    // ============================================================
    function cleanupModalBackdrop() {
        // Hapus semua elemen .modal-backdrop yang mungkin tertinggal
        document.querySelectorAll('.modal-backdrop').forEach(function(el) {
            el.remove();
        });
        // Hapus class modal-open dari body
        document.body.classList.remove('modal-open');
        // Kembalikan style overflow dan padding-right ke kondisi semula
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }

    // Daftarkan hidden.bs.modal untuk setiap modal yang ada
    var allModals = document.querySelectorAll('.modal');
    allModals.forEach(function(modalEl) {
        // Hindari pendaftaran event listener ganda
        if (!modalEl.hasAttribute('data-cleanup-registered')) {
            modalEl.setAttribute('data-cleanup-registered', 'true');
            modalEl.addEventListener('hidden.bs.modal', function() {
                cleanupModalBackdrop();
            });
        }
    });
});

function openTambahVoucher() {
    document.getElementById('formVoucher').reset();
    document.getElementById('modalVoucherLabel').innerText = 'Tambah Voucher Baru';
    document.getElementById('voucher-id').value = '';
    document.getElementById('form-action').value = 'create';
    
    const submitBtn = document.getElementById('btn-submit-modal');
    if (submitBtn) {
        submitBtn.className = "btn btn-success";
        submitBtn.innerText = "Simpan Data";
    }
}

function openEditVoucher(data) {
    if (!data) return;
    document.getElementById('formVoucher').reset();
    document.getElementById('modalVoucherLabel').innerText = 'Perbarui Data Voucher';
    document.getElementById('voucher-id').value = data.id;
    document.getElementById('form-action').value = 'update';
    document.getElementById('voucher-code').value = data.code || '';
    document.getElementById('voucher-discount-type').value = data.discount_type || 'percent';
    document.getElementById('voucher-discount-value').value = data.discount_value || 0;
    document.getElementById('voucher-minimum-purchase').value = data.minimum_purchase || 0;
    document.getElementById('voucher-quota').value = data.quota || 0;
    document.getElementById('voucher-start-date').value = data.start_date || '';
    document.getElementById('voucher-end-date').value = data.end_date || '';
    document.getElementById('voucher-status').value = data.status;
    
    const submitBtn = document.getElementById('btn-submit-modal');
    if (submitBtn) {
        submitBtn.className = "btn btn-warning text-dark fw-medium";
        submitBtn.innerText = "Perbarui Data";
    }
    
    const modalElement = document.getElementById('modalVoucher');
    if (modalElement) {
        // Gunakan getInstance untuk menghindari dual-instance
        var modalInstance = bootstrap.Modal.getInstance(modalElement);
        if (!modalInstance) {
            modalInstance = new bootstrap.Modal(modalElement);
        }
        modalInstance.show();
    }
}

function triggerDeleteVoucher(url, voucherCode) {
    deleteVoucherUrlTarget = url;
    const codePlaceholder = document.getElementById('delete_voucher_code');
    if (codePlaceholder) {
        codePlaceholder.innerText = voucherCode || '(Tanpa Kode)';
    }
    
    const modalElement = document.getElementById('modalConfirmDeleteVoucher');
    if (modalElement) {
        // Gunakan getInstance untuk menghindari dual-instance
        var modalInstance = bootstrap.Modal.getInstance(modalElement);
        if (!modalInstance) {
            modalInstance = new bootstrap.Modal(modalElement);
        }
        modalInstance.show();
    }
}
</script>

<style>
    .modal-body {
        overflow-y: auto !important;
        max-height: calc(100vh - 210px) !important;
    }
    .modal-dialog-scrollable .modal-content {
        max-height: 100% !important;
        overflow: hidden !important;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. PROSES KLIK TOMBOL KONFIRMASI STATUS (Aksi Admin jika ada perubahan manual)
$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_id = intval($_POST['id'] ?? 0);
    $new_status = trim($_POST['status'] ?? 'SUCCESS');

    if ($payment_id > 0) {
        $stmt = $conn->prepare("UPDATE payments SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $payment_id);
        if ($stmt->execute()) {
            header("Location: payments.php?status=success_update");
        } else {
            header("Location: payments.php?status=error&msg=" . urlencode($stmt->error));
        }
        exit();
    }
}

// 2. LOGIKA FILTER PENCARIAN & STATUS (Untuk Form Admin)
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$filter_status = isset($_GET['status_filter']) ? trim((string)$_GET['status_filter']) : '';

// Query dasar dengan JOIN ke tabel orders dan payment_methods
$sql = "SELECT p.*, o.order_number, pm.name AS method_name, pm.provider 
        FROM payments p
        LEFT JOIN orders o ON p.order_id = o.id
        LEFT JOIN payment_methods pm ON p.payment_method_id = pm.id
        WHERE 1=1";

// Tambah kondisi jika admin mencari nomor invoice/transaksi
if ($search !== '') {
    $search_clean = mysqli_real_escape_string($conn, $search);
    $sql .= " AND (o.order_number LIKE '%$search_clean%' OR p.transaction_number LIKE '%$search_clean%')";
}

// Tambah kondisi jika admin memfilter berdasarkan status (SUCCESS/PENDING/FAILED)
if ($filter_status !== '') {
    $status_clean = mysqli_real_escape_string($conn, $filter_status);
    $sql .= " AND p.status = '$status_clean'";
}

$sql .= " ORDER BY p.id DESC";

// 3. EKSEKUSI DATA LIST UNTUK DIULANG DI TABEL HTML
$payments = [];
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
}

// Menangkap status alert dari redirect checkout_process.php
$msg_status = isset($_GET['status']) ? (string)$_GET['status'] : '';
$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';
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
    body, body.modal-open { background:var(--bg) !important; color:var(--text); overflow:auto !important; padding-right:0px !important; pointer-events:auto !important; }
    .modal-backdrop, .modal-backdrop.show { display:none !important; opacity:0 !important; visibility:hidden !important; pointer-events:none !important; }
    .bottom-nav { position: fixed; left:0; right:0; bottom:0; z-index: 1035; background: rgba(15,23,42,.88); backdrop-filter: blur(10px); border-top: 1px solid rgba(148,163,184,.25); display:block; }
    @media (min-width: 992px) { main.content-shift { margin-left: 280px; } .bottom-nav { display:none; } }
    #dragScrollPaymentsContainer::-webkit-scrollbar { display: none !important; }
    #dragScrollPaymentsContainer { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow-x: auto !important; cursor: grab !important; box-shadow: none !important; border: none !important; -webkit-box-shadow: none !important; }
    #dragScrollPaymentsContainer:active { cursor: grabbing !important; }
    #dragScrollPaymentsContainer table, #dragScrollPaymentsContainer table tr, #dragScrollPaymentsContainer table td, #dragScrollPaymentsContainer table th { background: transparent !important; background-color: transparent !important; color: #e5e7eb !important; border: none !important; }
    #dragScrollPaymentsContainer table th { background-color: rgba(15, 23, 42, 0.8) !important; }
    #dragScrollPaymentsContainer table td { border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; }
    #dragScrollPaymentsContainer table tbody tr:hover { background-color: rgba(148, 163, 184, 0.05) !important; }
    .text-white-element { user-select: none; }
</style>

</head>
<body>
  <?php require __DIR__ . '/sidebar.php'; ?>

<main class="content-shift p-4" style="background: transparent !important; pointer-events: auto !important;">
  <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25); position: relative; z-index: 10;">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
      <div>
        <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Payments</h2>
        <div class="text-white-50" style="font-size:.9rem;">Kelola status pembayaran &amp; lihat transaksi</div>
      </div>
      <div></div>
    </div>

    <?php if (!empty($status)): ?>
      <div class="alert <?= strpos($status, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
        <strong>
          <?php 
            if ($status === 'success_create' || $status === 'success_update') echo "Operasi pembayaran berhasil!";
            elseif ($status === 'error') echo "Operasi gagal: " . htmlspecialchars($msg);
            else echo "Operasi: " . htmlspecialchars($status);
          ?>
        </strong>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <form method="GET" class="mb-4" style="max-width: 920px;">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-6">
          <label class="form-label mb-1" style="color:#94a3b8; font-weight:500;">Cari Order / Transaksi</label>
          <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control rounded-3" placeholder="Contoh: INV-2026... atau transaksi..." style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" />
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label mb-1" style="color:#94a3b8; font-weight:500;">Filter Status</label>
          <select name="status_filter" class="form-select rounded-3" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
            <option value="" <?= $filter_status === '' ? 'selected' : '' ?>>Semua</option>
            <?php foreach (['PENDING','SUCCESS','FAILED','UNPAID','PAID','REFUNDED'] as $st): ?>
              <option value="<?= $st ?>" <?= $filter_status === $st ? 'selected' : '' ?>><?= $st ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-2 d-grid">
          <button class="btn btn-success rounded-3 fw-medium py-2" type="submit"><i class="bi bi-search me-2"></i> Cari</button>
        </div>
      </div>
    </form>
    <div id="dragScrollPaymentsContainer" class="table-responsive rounded-3" style="border: none !important; background: transparent !important; background-color: transparent !important; box-shadow: none !important; -webkit-box-shadow: none !important;">
      <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; background-color: transparent !important; color: #e5e7eb !important; min-width: 900px; user-select: none; border-collapse: collapse !important; border: none !important;">
        <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
          <tr style="background: transparent !important; background-color: transparent !important;">
            <th class="py-3 px-3 text-center text-white" style="background: transparent !important; background-color: transparent !important; border: none !important; width: 90px;">ID</th>
            <th class="py-3 text-white" style="background: transparent !important; background-color: transparent !important; border: none !important; width: 200px;">Order</th>
            <th class="py-3 text-white" style="background: transparent !important; background-color: transparent !important; border: none !important; width: 240px;">Transaction</th>
            <th class="py-3 text-white" style="background: transparent !important; background-color: transparent !important; border: none !important; width: 220px;">Method</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; background-color: transparent !important; border: none !important; width: 140px;">Status</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; background-color: transparent !important; border: none !important; width: 200px;">Aksi</th>
          </tr>
        </thead>
        <tbody style="background: transparent !important; background-color: transparent !important;">
          <?php if (!empty($payments)): ?>
            <?php foreach ($payments as $row): ?>
              <?php 
                $ps = strtoupper(trim((string)($row['status'] ?? '')));
                $badgeHtml = '<span class="badge bg-secondary bg-opacity-25 text-white border border-secondary border-opacity-50 px-3 py-1.5 rounded-pill">' . htmlspecialchars($row['status'] ?? '-') . '</span>';
                if (in_array($ps, ['SUCCESS','PAID','REFUNDED'], true)) {
                  $badgeHtml = '<span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-3 py-1.5 rounded-pill">' . htmlspecialchars($row['status'] ?? $ps) . '</span>';
                } elseif (in_array($ps, ['PENDING','UNPAID'], true)) {
                  $badgeHtml = '<span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-50 px-3 py-1.5 rounded-pill">' . htmlspecialchars($row['status'] ?? $ps) . '</span>';
                } elseif (in_array($ps, ['FAILED'], true)) {
                  $badgeHtml = '<span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-50 px-3 py-1.5 rounded-pill">' . htmlspecialchars($row['status'] ?? $ps) . '</span>';
                }
                $methodLabel = trim((string)($row['method_name'] ?? ''));
                $provider = trim((string)($row['provider'] ?? ''));
                if ($provider !== '' && $methodLabel !== '') $methodLabel = $methodLabel . ' (' . $provider . ')';
              ?>
              <tr style="background: transparent !important; background-color: transparent !important; font-size: 0.88rem;">
                <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; background-color: transparent !important; border: none !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important;"><?= (int)($row['id'] ?? 0) ?></td>
                <td class="fw-semibold text-white" style="background: transparent !important; background-color: transparent !important; border: none !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important;">#<?= htmlspecialchars($row['order_number'] ?? '-') ?></td>
                <td class="text-white-50" style="background: transparent !important; background-color: transparent !important; border: none !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important;"><?= htmlspecialchars($row['transaction_number'] ?? '-') ?></td>
                <td class="text-white-50" style="background: transparent !important; background-color: transparent !important; border: none !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important;"><?= htmlspecialchars($methodLabel !== '' ? $methodLabel : '-') ?></td>
                <td class="text-center" style="background: transparent !important; background-color: transparent !important; border: none !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important;"><?= $badgeHtml ?></td>
                <td class="text-center" style="background: transparent !important; background-color: transparent !important; border: none !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important;">
                  <form method="POST" action="payments.php?action=update_status" class="d-flex flex-column flex-md-row gap-2 justify-content-center align-items-center" style="pointer-events:auto; background: transparent !important; background-color: transparent !important;">
                    <input type="hidden" name="id" value="<?= (int)($row['id'] ?? 0) ?>" />
                    <select name="status" class="form-select form-select-sm" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important; min-width: 150px;">
                      <?php foreach (['SUCCESS','PENDING','FAILED'] as $st): ?>
                        <option value="<?= $st ?>" <?= strtoupper(trim((string)($row['status'] ?? ''))) === $st ? 'selected' : '' ?>><?= $st ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-success px-3 fw-medium">Update</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr style="background: transparent !important; background-color: transparent !important;">
              <td colspan="6" class="text-center text-white-50 py-4" style="background: transparent !important; background-color: transparent !important; border: none !important;">Tidak ada data transaksi pembayaran ditemukan.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</main>

<script>
    function bersihkanMacet() {
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }
    document.addEventListener('DOMContentLoaded', bersihkanMacet);
    window.addEventListener('load', bersihkanMacet);
    setInterval(bersihkanMacet, 200);
</script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


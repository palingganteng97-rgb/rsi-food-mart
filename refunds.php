<?php
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

// 1. PROSES PENGAJUAN REFUND BARU (CREATE)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_id = intval($_POST['payment_id'] ?? 0);
    $amount     = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.0;
    $reason     = trim($_POST['reason'] ?? '');
    $status     = trim($_POST['status'] ?? 'PENDING'); // Default status pengajuan awal

    if ($payment_id > 0 && $amount > 0) {
        $stmt = $conn->prepare("INSERT INTO refunds (payment_id, amount, reason, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("idss", $payment_id, $amount, $reason, $status);
        
        if ($stmt->execute()) {
            // Opsional: Update juga status di tabel payments menjadi 'REFUNDED' atau 'REFUND_PENDING' jika diperlukan
            header("Location: refunds.php?status=success_create");
        } else {
            header("Location: refunds.php?status=error&msg=" . urlencode($stmt->error));
        }
        exit();
    }
}

// 2. PROSES UPDATE STATUS REFUND OLEH ADMIN (UPDATE)
if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = intval($_POST['id'] ?? 0);
    $new_status = trim($_POST['status'] ?? 'APPROVED'); // APPROVED / REJECTED / PENDING

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE refunds SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $id);
        
        if ($stmt->execute()) {
            header("Location: refunds.php?status=success_update");
        } else {
            header("Location: refunds.php?status=error&msg=" . urlencode($stmt->error));
        }
        exit();
    }
}

// 3. PROSES HAPUS RIWAYAT REFUND (DELETE)
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id'] ?? 0);

    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM refunds WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            header("Location: refunds.php?status=success_delete");
        } else {
            header("Location: refunds.php?status=error&msg=" . urlencode($stmt->error));
        }
        exit();
    }
}

// 4. PROSES AMBIL DATA RIWAYAT REFUND DENGAN JOIN RELASI (READ)
$refunds = [];
// Melakukan JOIN ke tabel payments dan orders agar admin bisa melihat Nomor Invoice & Nomor Transaksi asli
$sql = "SELECT r.*, p.transaction_number, o.order_number 
        FROM refunds r
        LEFT JOIN payments p ON r.payment_id = p.id
        LEFT JOIN orders o ON p.order_id = o.id
        ORDER BY r.id DESC";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $refunds[] = $row;
    }
}

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
  body { background:var(--bg) !important; color:var(--text); }
  #dragScrollRefundsContainer::-webkit-scrollbar { display: none !important; }
  #dragScrollRefundsContainer { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow-x: auto !important; cursor: grab !important; box-shadow: none !important; border: none !important; -webkit-box-shadow: none !important; }
  #dragScrollRefundsContainer:active { cursor: grabbing !important; }
  #dragScrollRefundsContainer table { border-collapse: collapse !important; border: none !important; }
  #dragScrollRefundsContainer table th, #dragScrollRefundsContainer table td { border-left: none !important; border-right: none !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; }
  .text-white-element { user-select: none; }
  @media (min-width: 992px) { main.content-shift { margin-left: 280px; } }
</style>

</head>
<body>
  <?php require __DIR__ . '/sidebar.php'; ?>

<main class="content-shift p-4">
  <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
      <div>
        <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Refunds</h2>
        <div class="text-white-50" style="font-size:.9rem;">Riwayat pengembalian dana</div>
      </div>
      <div>
        <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalRefund" onclick="openTambahRefund()">
          <i class="bi bi-plus-circle"></i> Ajukan Refund
        </button>
      </div>
    </div>

    <?php if (!empty($msg_status)): ?>
      <div class="alert <?= strpos($msg_status, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
        <strong>
          <?php 
            if ($msg_status === 'success_create' || $msg_status === 'success_update') echo "Operasi refund berhasil!";
            elseif ($msg_status === 'success_delete') echo "Data refund berhasil dihapus!";
            elseif ($msg_status === 'error') echo "Operasi gagal: " . htmlspecialchars($msg);
            else echo "Operasi: " . htmlspecialchars($msg_status);
          ?>
        </strong>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <!-- Perbaikan Utama: Menambahkan kelas dragscroll dari dragscroll.js dan mengubah cursor menjadi grab -->
    <div id="dragScrollRefundsContainer" class="table-responsive rounded-3 dragscroll" style="overflow-x: auto !important; border: none !important; background: transparent !important; cursor: grab;">
      <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 980px; user-select: none; border-collapse: collapse !important;">
        <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
          <tr>
            <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 90px;">ID</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 190px;">Order</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 240px;">Transaction</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 160px;">Amount</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 260px;">Reason</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;">Status</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 200px;">Aksi</th>
          </tr>
        </thead>
        <tbody style="background: transparent !important;">
          <?php if (!empty($refunds)): ?>
            <?php foreach ($refunds as $row): ?>
              <?php 
                $rs = strtoupper(trim((string)($row['status'] ?? '')));
                $badge = '<span class="badge bg-secondary bg-opacity-25 text-white border border-secondary border-opacity-50 px-3 py-1.5 rounded-pill">'.htmlspecialchars($row['status'] ?? '-').'</span>';
                if (in_array($rs, ['APPROVED','SUCCESS'], true)) {
                  $badge = '<span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-3 py-1.5 rounded-pill">'.htmlspecialchars($row['status'] ?? $rs).'</span>';
                } elseif (in_array($rs, ['REJECTED','FAILED'], true)) {
                  $badge = '<span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-50 px-3 py-1.5 rounded-pill">'.htmlspecialchars($row['status'] ?? $rs).'</span>';
                } elseif (in_array($rs, ['PENDING'], true)) {
                  $badge = '<span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-50 px-3 py-1.5 rounded-pill">'.htmlspecialchars($row['status'] ?? $rs).'</span>';
                }
                $amountVal = $row['amount'] ?? 0;
              ?>
              <tr style="background: transparent !important; font-size: 0.88rem;">
                <td class="text-center fw-semibold" style="color: #94a3b8 !important;"><?= (int)($row['id'] ?? 0) ?></td>
                <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;">#<?= htmlspecialchars($row['order_number'] ?? '-') ?></td>
                <td class="text-white-50" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['transaction_number'] ?? '-') ?></td>
                <td class="text-center text-white-50" style="background: transparent !important; border: none !important;">Rp <?= number_format((float)$amountVal, 0, ',', '.') ?></td>
                <td class="text-white-50" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['reason'] ?? '-') ?></td>
                <td class="text-center" style="background: transparent !important; border: none !important;"><?= $badge ?></td>
                <td class="text-center" style="background: transparent !important; border: none !important;">
                  <div class="d-flex flex-column gap-2 align-items-center">
                    <form method="POST" action="refunds.php?action=update_status" class="d-flex gap-2 justify-content-center align-items-center" style="pointer-events:auto;">
                      <input type="hidden" name="id" value="<?= (int)($row['id'] ?? 0) ?>" />
                      <select name="status" class="form-select form-select-sm" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important; min-width: 150px;">
                        <?php foreach (['PENDING','APPROVED','REJECTED'] as $st): ?>
                          <option value="<?= $st ?>" <?= strtoupper(trim((string)($row['status'] ?? ''))) === $st ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button type="submit" class="btn btn-sm btn-warning rounded-2"><i class="bi bi-check2-circle"></i></button>
                    </form>
                    <a href="refunds.php?action=delete&id=<?= (int)($row['id'] ?? 0) ?>" class="btn btn-sm btn-outline-danger rounded-2" onclick="return confirm('Apakah Anda yakin ingin menghapus refund ini?')">
                      <i class="bi bi-trash"></i>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" class="text-center py-5 text-white-50 fst-italic" style="background: transparent !important; border: none !important;">
                <i class="bi bi-exclamation-circle d-block fs-3 mb-2 opacity-50"></i>
                Belum ada data refund.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</main>

<!-- Modal Refund (Create) -->
<div class="modal fade" id="modalRefund" tabindex="-1" aria-labelledby="modalRefundLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background: rgba(15, 23, 42, 0.93) !important; backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.2); color: #e5e7eb; border-radius: 16px;">
      <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
        <h5 class="modal-title fw-bold text-white" id="modalRefundLabel">Form Ajukan Refund</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form id="formRefund" action="refunds.php" method="POST">
        <input type="hidden" name="action" id="refund-action" value="create">

        <div class="modal-body" style="overflow: visible !important;">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Payment ID <span class="text-danger">*</span></label>
              <input type="number" name="payment_id" id="refund-payment-id" class="form-control" placeholder="ID pembayaran" min="1" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
            </div>

            <div class="col-md-6">
              <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Amount <span class="text-danger">*</span></label>
              <input type="number" step="0.01" name="amount" id="refund-amount" class="form-control" placeholder="Nominal refund" min="0" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
            </div>

            <div class="col-md-12">
              <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Reason</label>
              <input type="text" name="reason" id="refund-reason" class="form-control" maxlength="255" placeholder="Alasan refund" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
            </div>

            <div class="col-md-12">
              <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Status</label>
              <select name="status" id="refund-status" class="form-select" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
                <option value="PENDING" selected>PENDING</option>
                <option value="APPROVED">APPROVED</option>
                <option value="REJECTED">REJECTED</option>
              </select>
            </div>
          </div>
        </div>

        <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.15); background: rgba(15, 23, 42, 0.95); border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success" id="btnSubmitRefund">Ajukan Refund</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // 1. Fungsi Handler Modal Form
  function openTambahRefund() {
    const form = document.getElementById('formRefund');
    if (form) form.reset();
    
    const actionInput = document.getElementById('refund-action');
    if (actionInput) actionInput.value = 'create';
    // Catatan: status default PENDING sudah terpilih otomatis di HTML
  }

  // 2. Fungsi Logika Geser Tabel Menggunakan Kursor (Drag-to-Scroll)
  document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('dragScrollRefundsContainer');
    if (!container) return;

    let isDown = false;
    let startX;
    let scrollLeft;

    container.addEventListener('mousedown', (e) => {
      // Mencegah gangguan geser jika menekan elemen interaktif (dropdown/tombol/tautan)
      if (e.target.closest('select') || e.target.closest('button') || e.target.closest('a')) return;
      
      isDown = true;
      startX = e.pageX - container.offsetLeft;
      scrollLeft = container.scrollLeft;
    });

    container.addEventListener('mouseleave', () => {
      isDown = false;
    });

    container.addEventListener('mouseup', () => {
      isDown = false;
    });

    container.addEventListener('mousemove', (e) => {
      if (!isDown) return;
      e.preventDefault();
      
      const x = e.pageX - container.offsetLeft;
      const walk = (x - startX) * 1.5; // Mengontrol tingkat sensitivitas pergeseran tabel
      container.scrollLeft = scrollLeft - walk;
    });
  });
</script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


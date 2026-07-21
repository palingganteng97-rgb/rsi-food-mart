<?php
// refund_patients.php - Halaman refund untuk pasien
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ========================================================================
// PROTEKSI SESSION PASIEN — Menggunakan Prepared Statement (Aman dari SQL Injection)
// ========================================================================
$patient_session_id = isset($_SESSION['patient_session_id']) ? intval($_SESSION['patient_session_id']) : 0;

if ($patient_session_id <= 0) {
    // Tidak ada session pasien — redirect ke halaman scan QR (index.php)
    $_SESSION['flash_message'] = "Silakan scan ulang QR Code untuk memulai sesi.";
    header('Location: index.php');
    exit;
}

// Validasi ke database menggunakan prepared statement
$stmtCheck = $conn->prepare("SELECT id, patient_name FROM patient_sessions WHERE id = ? LIMIT 1");
if ($stmtCheck) {
    $stmtCheck->bind_param("i", $patient_session_id);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result();

    if ($resCheck->num_rows === 0) {
        // Session ID tidak ditemukan di database (misal: DB di-reset)
        // Hapus session yang sudah tidak valid
        unset($_SESSION['patient_session_id']);
        
        // Set flash message agar user tahu penyebab redirect
        $_SESSION['flash_message'] = "Sesi Anda telah berakhir karena data session di database tidak ditemukan. Silakan scan ulang QR Code.";
        
        // Redirect ke index.php (splash screen → scan QR ulang)
        header('Location: index.php');
        exit;
    }

    $psData = $resCheck->fetch_assoc();
    $stmtCheck->close();
} else {
    // Jika prepared statement gagal (misal: koneksi DB error), redirect aman
    header('Location: index.php');
    exit;
}

$patientName = $psData['patient_name'] ?? 'Pasien';

function money($val) {
    return 'Rp ' . number_format((float)$val, 0, ',', '.');
}

// ========================================================================
// PROSES AJUKAN REFUND
// ========================================================================
$alertType = '';
$alertMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajukan_refund') {
    $payment_id = intval($_POST['payment_id'] ?? 0);
    $reason     = trim($_POST['reason'] ?? '');

    if ($payment_id <= 0) {
        $alertType = 'danger';
        $alertMsg  = 'Pembayaran tidak valid.';
    } elseif (empty($reason)) {
        $alertType = 'danger';
        $alertMsg  = 'Alasan refund wajib diisi.';
    } else {
        // Validasi: Pastikan payment milik pasien ini dan statusnya paid
        $cekPay = mysqli_query($conn, "
            SELECT p.id, p.amount, p.status, o.patient_session_id
            FROM payments p
            JOIN orders o ON p.order_id = o.id
            WHERE p.id = $payment_id AND o.patient_session_id = $patient_session_id
            LIMIT 1
        ");
        if ($cekPay && $payRow = mysqli_fetch_assoc($cekPay)) {
            $payStatus = strtoupper(trim($payRow['status'] ?? ''));
            if (!in_array($payStatus, ['PAID', 'SUCCESS'])) {
                $alertType = 'danger';
                $alertMsg  = 'Pembayaran belum lunas. Tidak dapat mengajukan refund.';
            } else {
                // Cek apakah sudah pernah refund untuk payment_id ini
                $cekRefund = mysqli_query($conn, "SELECT id FROM refunds WHERE payment_id = $payment_id LIMIT 1");
                if ($cekRefund && mysqli_num_rows($cekRefund) > 0) {
                    $alertType = 'warning';
                    $alertMsg  = 'Refund sudah pernah diajukan untuk pembayaran ini.';
                } else {
                    $amount = floatval($payRow['amount'] ?? 0);
                    $reasonEsc = mysqli_real_escape_string($conn, $reason);
                    $status = 'pending';

                    $ins = mysqli_query($conn, "INSERT INTO refunds (payment_id, amount, reason, status, created_at) VALUES ($payment_id, $amount, '$reasonEsc', '$status', NOW())");
                    if ($ins) {
                        $alertType = 'success';
                        $alertMsg  = 'Pengajuan refund berhasil dikirim. Silakan tunggu persetujuan admin.';
                    } else {
                        $alertType = 'danger';
                        $alertMsg  = 'Gagal mengajukan refund: ' . mysqli_error($conn);
                    }
                }
            }
        } else {
            $alertType = 'danger';
            $alertMsg  = 'Data pembayaran tidak ditemukan atau bukan milik Anda.';
        }
    }
}

// ========================================================================
// AMBIL DATA PESANAN YANG SUDAH DIBAYAR UNTUK PASIEN INI
// ========================================================================
$paidOrders = [];
$paidQuery = mysqli_query($conn, "
    SELECT o.id AS order_id, o.order_number, o.grand_total, o.payment_status, o.status AS order_status, o.created_at,
           p.id AS payment_id, p.amount AS payment_amount, p.status AS payment_status
    FROM orders o
    JOIN payments p ON p.order_id = o.id
    WHERE o.patient_session_id = $patient_session_id
    ORDER BY o.id DESC
");
if ($paidQuery) {
    while ($row = mysqli_fetch_assoc($paidQuery)) {
        $paidOrders[] = $row;
    }
}

// ========================================================================
// AMBIL RIWAYAT REFUND PASIEN INI
// ========================================================================
$refunds = [];
$refundQuery = mysqli_query($conn, "
    SELECT r.*, p.transaction_number, o.order_number
    FROM refunds r
    JOIN payments p ON r.payment_id = p.id
    JOIN orders o ON p.order_id = o.id
    WHERE o.patient_session_id = $patient_session_id
    ORDER BY r.id DESC
");
if ($refundQuery) {
    while ($row = mysqli_fetch_assoc($refundQuery)) {
        $refunds[] = $row;
    }
}

// ========================================================================
// HITUNG STATISTIK
// ========================================================================
$totalRefunds = count($refunds);
$countPending = 0;
$countApproved = 0;
$countRejected = 0;
foreach ($refunds as $rf) {
    $s = strtolower(trim($rf['status'] ?? ''));
    if ($s === 'pending') $countPending++;
    elseif (in_array($s, ['approved', 'success'])) $countApproved++;
    elseif (in_array($s, ['rejected', 'failed'])) $countRejected++;
}

// Map payment_id => refund status untuk cek duplikasi
$refundedPaymentMap = [];
foreach ($refunds as $rf) {
    $refundedPaymentMap[(int)$rf['payment_id']] = strtolower(trim($rf['status'] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Refund Saya - RSI Food &amp; Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
    <style>
        :root { --bg:#0f172a; --text:#e5e7eb; --muted:#94a3b8; --green:#22c55e; }
        body { background:var(--bg) !important; color:var(--text); }
        .stat-card { background: rgba(30,41,59,0.35); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border:1px solid rgba(148,163,184,.15); border-radius:20px; padding:1.25rem; box-shadow:0 8px 32px rgba(0,0,0,0.2); transition: transform .15s ease, border-color .15s ease; }
        .stat-card:hover { transform: translateY(-2px); border-color: rgba(34,197,94,.35); }
        .stat-icon { width:46px; height:46px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; }
        .table-custom { background: transparent !important; color: #e5e7eb !important; }
        .table-custom th { background: transparent !important; color: #94a3b8 !important; border-bottom: 1px solid rgba(255,255,255,.08) !important; font-size:.8rem; text-transform:uppercase; font-weight:700; }
        .table-custom td { background: transparent !important; border-bottom: 1px solid rgba(148,163,184,.08) !important; vertical-align:middle; }
        .table-custom tbody tr { background: transparent !important; }
        .table-custom tbody tr:hover { background: rgba(255,255,255,.04) !important; }
        .badge-custom { padding:.35em .75em; border-radius:999px; font-weight:500; font-size:.8rem; }
        .btn-refund { border-radius:999px; padding:.45rem 1.1rem; font-weight:500; transition: all .2s ease; }
        .btn-refund:hover { transform: translateY(-1px); box-shadow:0 4px 12px rgba(234,179,8,0.25); }
        .modal-content-custom { background: rgba(15,23,42,.96); backdrop-filter:blur(16px); border:1px solid rgba(148,163,184,.2); border-radius:18px; color:#e5e7eb; }
        .form-control-custom { background: rgba(2,6,23,.4); border:1px solid rgba(148,163,184,.25); color:#e5e7eb; border-radius:12px; }
        .form-control-custom:focus { background: rgba(2,6,23,.5); border-color:rgba(34,197,94,.5); color:#e5e7eb; box-shadow:0 0 0 .2rem rgba(34,197,94,.15); }
        .form-control-custom::placeholder { color: rgba(148,163,184,.4); }
        .text-white-50 { color: rgba(255,255,255,.6) !important; }
        @media (max-width: 991.98px) { .content-shift { margin-left:0 !important; } }
    </style>
</head>
<body>
<?php include 'sidebar_pasients.php'; ?>

<main class="page-body content-shift">
    <div class="container py-4">

        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h3 class="fw-bold text-white mb-1">
                    <i class="bi bi-arrow-return-left text-success me-2"></i>Riwayat Refund Saya
                </h3>
                <div class="text-white-50" style="font-size:.9rem;">Halo, <?= htmlspecialchars($patientName); ?> — Kelola pengajuan refund Anda</div>
            </div>
        </div>

        <!-- Alert -->
        <?php if ($alertType !== '' && $alertMsg !== ''): ?>
        <div class="alert alert-<?= $alertType ?> alert-dismissible fade show rounded-3 border-0" style="background:<?= $alertType === 'success' ? 'rgba(34,197,94,.15)' : ($alertType === 'danger' ? 'rgba(239,68,68,.15)' : 'rgba(245,158,11,.15)') ?>; color:#e5e7eb;">
            <i class="bi <?= $alertType === 'success' ? 'bi-check-circle-fill' : ($alertType === 'danger' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill') ?> me-2"></i>
            <?= htmlspecialchars($alertMsg) ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistik Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: rgba(255,255,255,.08);">
                        <i class="bi bi-receipt text-white"></i>
                    </div>
                    <div>
                        <div class="text-white-50 small mb-1">Total Pengajuan</div>
                        <div class="fw-bold fs-4 text-white"><?= $totalRefunds ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: rgba(234,179,8,.15);">
                        <i class="bi bi-clock-history text-warning"></i>
                    </div>
                    <div>
                        <div class="text-white-50 small mb-1">Pending</div>
                        <div class="fw-bold fs-4 text-warning"><?= $countPending ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: rgba(34,197,94,.15);">
                        <i class="bi bi-check-circle text-success"></i>
                    </div>
                    <div>
                        <div class="text-white-50 small mb-1">Disetujui</div>
                        <div class="fw-bold fs-4 text-success"><?= $countApproved ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: rgba(239,68,68,.15);">
                        <i class="bi bi-x-circle text-danger"></i>
                    </div>
                    <div>
                        <div class="text-white-50 small mb-1">Ditolak</div>
                        <div class="fw-bold fs-4 text-danger"><?= $countRejected ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Riwayat Refund -->
        <div class="stat-card mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="fw-bold text-white m-0"><i class="bi bi-clock-history text-success me-2"></i>Riwayat Refund</h5>
            </div>
            <?php if (empty($refunds)): ?>
                <div class="text-center py-5 text-white-50">
                    <i class="bi bi-inbox d-block mb-2" style="font-size:2.5rem; opacity:.5;"></i>
                    <span>Belum ada pengajuan refund.</span>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="py-3">Order</th>
                            <th class="py-3">Tanggal</th>
                            <th class="py-3 text-end">Jumlah</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Alasan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($refunds as $rf): 
                            $rfStatus = strtolower(trim($rf['status'] ?? ''));
                            $badgeClass = 'bg-secondary';
                            $badgeText = $rf['status'] ?? '-';
                            if (in_array($rfStatus, ['approved', 'success'])) {
                                $badgeClass = 'bg-success';
                                $badgeText = 'Disetujui';
                            } elseif (in_array($rfStatus, ['rejected', 'failed'])) {
                                $badgeClass = 'bg-danger';
                                $badgeText = 'Ditolak';
                            } elseif ($rfStatus === 'pending') {
                                $badgeClass = 'bg-warning text-dark';
                                $badgeText = 'Pending';
                            }
                        ?>
                        <tr>
                            <td class="fw-semibold text-white">#<?= htmlspecialchars($rf['order_number'] ?? '-') ?></td>
                            <td class="text-white-50 small"><?= htmlspecialchars($rf['created_at'] ?? '-') ?></td>
                            <td class="fw-bold text-success text-end"><?= money($rf['amount'] ?? 0) ?></td>
                            <td><span class="badge <?= $badgeClass ?> badge-custom"><?= $badgeText ?></span></td>
                            <td class="text-white-50" style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($rf['reason'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tabel Pesanan yang Sudah Dibayar -->
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="fw-bold text-white m-0"><i class="bi bi-receipt text-success me-2"></i>Pesanan Saya</h5>
            </div>
            <?php if (empty($paidOrders)): ?>
                <div class="text-center py-5 text-white-50">
                    <i class="bi bi-basket d-block mb-2" style="font-size:2.5rem; opacity:.5;"></i>
                    <span>Belum ada pesanan.</span>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="py-3">Order</th>
                            <th class="py-3">Tanggal</th>
                            <th class="py-3 text-end">Total</th>
                            <th class="py-3">Status</th>
                            <th class="py-3 text-center">Refund</th>
                            <th class="py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paidOrders as $ord): 
                            $payStatus = strtoupper(trim($ord['payment_status'] ?? ''));
                            $orderStatus = strtolower(trim($ord['order_status'] ?? ''));
                            $paymentId = (int)($ord['payment_id'] ?? 0);
                            
                            // Badge status pembayaran
                            $psBadge = 'bg-secondary';
                            $psLabel = $payStatus;
                            if ($payStatus === 'PAID' || $payStatus === 'SUCCESS') { $psBadge = 'bg-success'; $psLabel = 'Lunas'; }
                            elseif ($payStatus === 'UNPAID' || $payStatus === 'PENDING') { $psBadge = 'bg-warning text-dark'; $psLabel = 'Belum Bayar'; }
                            elseif ($payStatus === 'FAILED') { $psBadge = 'bg-danger'; $psLabel = 'Gagal'; }
                            elseif ($payStatus === 'REFUNDED') { $psBadge = 'bg-info'; $psLabel = 'Refunded'; }
                            
                            // Status refund sudah ada?
                            $hasRefund = isset($refundedPaymentMap[$paymentId]);
                            $refundStatus = $hasRefund ? $refundedPaymentMap[$paymentId] : '';
                            
                            $canRefund = in_array($payStatus, ['PAID', 'SUCCESS']) && !$hasRefund;
                            $refundBadge = '';
                            if ($hasRefund) {
                                $rs = $refundStatus;
                                if (in_array($rs, ['approved', 'success'])) $refundBadge = '<span class="badge bg-success badge-custom">Disetujui</span>';
                                elseif (in_array($rs, ['rejected', 'failed'])) $refundBadge = '<span class="badge bg-danger badge-custom">Ditolak</span>';
                                else $refundBadge = '<span class="badge bg-warning text-dark badge-custom">Pending</span>';
                            }
                        ?>
                        <tr>
                            <td class="fw-semibold text-white">#<?= htmlspecialchars($ord['order_number'] ?? '-') ?></td>
                            <td class="text-white-50 small"><?= htmlspecialchars($ord['created_at'] ?? '-') ?></td>
                            <td class="fw-bold text-success text-end"><?= money($ord['grand_total'] ?? 0) ?></td>
                            <td><span class="badge <?= $psBadge ?> badge-custom"><?= $psLabel ?></span></td>
                            <td class="text-center"><?= $hasRefund ? $refundBadge : '<span class="text-white-50 small">—</span>' ?></td>
                            <td class="text-center">
                                <?php if ($canRefund): ?>
                                    <button type="button" class="btn btn-sm btn-warning btn-refund" 
                                            onclick="openModalRefund(<?= $paymentId ?>, <?= floatval($ord['grand_total'] ?? 0) ?>, '<?= htmlspecialchars(addslashes($ord['order_number'] ?? '')) ?>')">
                                        <i class="bi bi-arrow-return-left me-1"></i>Ajukan Refund
                                    </button>
                                <?php elseif ($hasRefund): ?>
                                    <span class="text-white-50 small"><i class="bi bi-check-circle me-1"></i>Sudah diajukan</span>
                                <?php else: ?>
                                    <span class="text-white-50 small" style="opacity:.5;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- Modal Ajukan Refund -->
<div class="modal fade" id="modalAjukanRefund" tabindex="-1" aria-labelledby="modalAjukanRefundLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:500px;">
        <div class="modal-content modal-content-custom">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-white" id="modalAjukanRefundLabel">
                    <i class="bi bi-arrow-return-left text-warning me-2"></i>Ajukan Refund
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="refund_patients.php">
                <input type="hidden" name="action" value="ajukan_refund">
                <input type="hidden" name="payment_id" id="refund_payment_id" value="0">
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label text-white-50 small fw-medium">Nomor Order</label>
                        <div class="fw-bold text-white fs-5" id="refund_order_number_display">-</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-white-50 small fw-medium">Jumlah Refund</label>
                        <input type="text" class="form-control form-control-custom fw-bold fs-5 text-success" id="refund_amount_display" readonly>
                    </div>
                    <div class="mb-2">
                        <label for="refund_reason" class="form-label text-white-50 small fw-medium">Alasan Refund <span class="text-danger">*</span></label>
                        <textarea name="reason" id="refund_reason" class="form-control form-control-custom" rows="4" placeholder="Tuliskan alasan Anda mengajukan refund..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold">
                        <i class="bi bi-send me-1"></i>Ajukan Refund
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openModalRefund(paymentId, amount, orderNumber) {
        document.getElementById('refund_payment_id').value = paymentId;
        document.getElementById('refund_order_number_display').textContent = '#' + orderNumber;
        document.getElementById('refund_amount_display').value = 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
        document.getElementById('refund_reason').value = '';
        
        const modal = new bootstrap.Modal(document.getElementById('modalAjukanRefund'));
        modal.show();
    }

    // Bersihkan modal backdrop jika macet
    function bersihkanMacet() {
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }
    document.addEventListener('hidden.bs.modal', bersihkanMacet);
    setInterval(() => {
        const adaModal = document.querySelector('.modal.show');
        if (!adaModal && document.querySelector('.modal-backdrop')) {
            bersihkanMacet();
        }
    }, 300);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


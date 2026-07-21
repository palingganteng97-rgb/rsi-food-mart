<?php
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function money($val) {
    return 'Rp ' . number_format((float)$val, 0, ',', '.');
}

// =========================================================================
// Validasi session pasien (sama seperti di home.php)
// =========================================================================
$isPatient = isset($_SESSION['patient_session_id']) && $_SESSION['patient_session_id'] > 0;

if (!$isPatient) {
    // Jika tidak ada session pasien, arahkan ke halaman entry awal (index.php)
    header("Location: index.php");
    exit;
}

$patient_session_id = intval($_SESSION['patient_session_id']);

// Ambil order_id dari URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// =========================================================================
// MODE DETAIL: Jika ada parameter ?id=
// =========================================================================
if ($order_id > 0) {
    // Ambil data orders — pastikan hanya milik session pasien ini
    $order = null;
    $stmtOrder = $conn->prepare("SELECT id, order_number, grand_total, payment_status, status, created_at, cancel_reason FROM orders WHERE id = ? AND patient_session_id = ? LIMIT 1");
    if ($stmtOrder) {
        $stmtOrder->bind_param('ii', $order_id, $patient_session_id);
        $stmtOrder->execute();
        $resOrder = $stmtOrder->get_result();
        $order = $resOrder ? $resOrder->fetch_assoc() : null;
    }

    // Jika order tidak ditemukan atau bukan milik session ini, tampilkan pesan error
    if (!$order) {
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Detail Pesanan - RSI Food &amp; Mart</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
        </head>
        <body class="bg-dark text-white" style="background: #0f172a; min-height: 100vh;">
            <?php include "sidebar_pasients.php"; ?>
            <main class="page-body content-shift">
                <div class="container py-5">
                    <div class="row justify-content-center">
                        <div class="col-lg-6">
                            <div class="bg-transparent rounded-4 p-5 text-center" style="border: 2px dashed rgba(148, 163, 184, 0.25);">
                                <i class="bi bi-exclamation-circle text-warning mb-3" style="font-size: 3rem; opacity: 0.8;"></i>
                                <h4 class="fw-bold text-white mb-2">Pesanan Tidak Ditemukan</h4>
                                <p class="text-white-50 mb-4">Pesanan dengan ID tersebut tidak ditemukan atau bukan milik Anda.</p>
                                <a href="riwayat_pesanan.php" class="btn btn-success rounded-pill px-4 fw-medium shadow-sm">
                                    <i class="bi bi-arrow-left me-2"></i>Kembali ke Riwayat Pesanan
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
        exit;
    }

    // Ambil data order_items
    $items = [];
    $stmtItems = $conn->prepare("SELECT product_id, qty, price, notes FROM order_items WHERE order_id = ? ORDER BY id ASC");
    if ($stmtItems) {
        $stmtItems->bind_param('i', $order_id);
        $stmtItems->execute();
        $resItems = $stmtItems->get_result();
        if ($resItems) {
            while ($row = $resItems->fetch_assoc()) {
                // Ambil nama produk
                $pid = intval($row['product_id'] ?? 0);
                $pname = 'Produk #' . $pid;
                if ($pid > 0) {
                    $pStmt = $conn->prepare("SELECT name FROM products WHERE id = ? LIMIT 1");
                    if ($pStmt) {
                        $pStmt->bind_param('i', $pid);
                        $pStmt->execute();
                        $pRes = $pStmt->get_result();
                        if ($pRes && $pRes->num_rows > 0) {
                            $pRow = $pRes->fetch_assoc();
                            $pname = $pRow['name'] ?? $pname;
                        }
                        $pStmt->close();
                    }
                }
                $row['product_name'] = $pname;
                $items[] = $row;
            }
        }
    }

    // Ambil data payments
    $payment = null;
    $stmtPay = $conn->prepare("SELECT id, payment_method_id, amount, transaction_number, status, paid_at FROM payments WHERE order_id = ? LIMIT 1");
    if ($stmtPay) {
        $stmtPay->bind_param('i', $order_id);
        $stmtPay->execute();
        $resPay = $stmtPay->get_result();
        $payment = $resPay ? $resPay->fetch_assoc() : null;
    }

    // Ambil nama metode pembayaran
    $payment_method_name = '-';
    if ($payment && intval($payment['payment_method_id'] ?? 0) > 0) {
        $pmid = intval($payment['payment_method_id']);
        $pmStmt = $conn->prepare("SELECT name, provider FROM payment_methods WHERE id = ? LIMIT 1");
        if ($pmStmt) {
            $pmStmt->bind_param('i', $pmid);
            $pmStmt->execute();
            $pmRes = $pmStmt->get_result();
            if ($pmRes && $pmRes->num_rows > 0) {
                $pmRow = $pmRes->fetch_assoc();
                $pmName = $pmRow['name'] ?? '-';
                $pmProvider = $pmRow['provider'] ?? '';
                $payment_method_name = $pmProvider !== '' ? $pmName . ' (' . $pmProvider . ')' : $pmName;
            }
            $pmStmt->close();
        }
    }

    $paymentStatus = $payment['status'] ?? 'PENDING';
    $orderStatus = $order['status'] ?? 'pending';
    $totalQty = 0;
    foreach ($items as $it) {
        $totalQty += intval($it['qty'] ?? 0);
    }

    // DEFINISI AWAL $isCancelled — berdasarkan status order dari database
    // Digunakan di blok info order (cancel_reason) SEBELUM tombol aksi
    $orderStatusLower = strtolower($orderStatus ?? '');
    $isCancelled = ($orderStatusLower === 'cancelled');

    // =========================================================================
    // PROSES BATALKAN PESANAN (hanya jika status masih PENDING)
    // =========================================================================
    $cancelSuccess = isset($_GET['cancelled']) && $_GET['cancelled'] === '1';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order']) && $_POST['cancel_order'] === '1') {
        // Pastikan order masih bisa dibatalkan (hanya jika status pesanan masih pending)
        $canCancel = false;
        $orderStatusLower = strtolower($orderStatus ?? '');

        if ($orderStatusLower === 'pending') {
            $canCancel = true;
        }

        // Ambil alasan pembatalan dari form
        $alasanCancel = isset($_POST['alasan_cancel']) ? trim($_POST['alasan_cancel']) : '';

        if ($canCancel && $alasanCancel !== '') {
            $stmtCancel = $conn->prepare("UPDATE orders SET status = 'cancelled', cancel_reason = ? WHERE id = ? AND patient_session_id = ?");
            if ($stmtCancel) {
                $stmtCancel->bind_param('sii', $alasanCancel, $order_id, $patient_session_id);
                $stmtCancel->execute();

                // INSERT ke tabel order_status_histories untuk mencatat log pembatalan
                $stmtHist = $conn->prepare("INSERT INTO order_status_histories (order_id, status, changed_by, notes, created_at) VALUES (?, 'cancelled', 'Customer', ?, NOW())");
                if ($stmtHist) {
                    $stmtHist->bind_param('is', $order_id, $alasanCancel);
                    $stmtHist->execute();
                    $stmtHist->close();
                }

                $stmtCancel->close();

                // Redirect (PRG) untuk mencegah resubmission form
                header("Location: riwayat_pesanan.php?id=" . $order_id . "&cancelled=1&reason=" . urlencode($alasanCancel));
                exit;
            }
        } else {
            // Jika tidak bisa dibatalkan atau alasan kosong, redirect tanpa parameter sukses
            header("Location: riwayat_pesanan.php?id=" . $order_id . "&error=cannot_cancel");
            exit;
        }
    }

    // Tampilkan badge status
    function statusBadge($status, $type = 'payment') {
        $status = strtoupper($status);
        if ($status === 'PAID' || $status === 'COMPLETED' || $status === 'DELIVERED') {
            return 'bg-success bg-opacity-25 text-success border-success border-opacity-50';
        } elseif ($status === 'PENDING' || $status === 'pending') {
            return 'bg-warning bg-opacity-25 text-warning border-warning border-opacity-50';
        } elseif ($status === 'PROCESSING' || $status === 'PREPARING') {
            return 'bg-info bg-opacity-25 text-info border-info border-opacity-50';
        } elseif ($status === 'CANCELLED' || $status === 'FAILED' || $status === 'REFUND') {
            return 'bg-danger bg-opacity-25 text-danger border-danger border-opacity-50';
        }
        return 'bg-secondary bg-opacity-25 text-secondary border-secondary border-opacity-50';
    }
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Detail Pesanan - RSI Food &amp; Mart</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
        <style>
            :root { --bg:#0f172a; --text:#e5e7eb; --muted:#94a3b8; --green:#22c55e; }
            body { background:var(--bg) !important; color:#ffffff !important; }
            /* Paksa semua teks di halaman detail menjadi putih terang */
            body, main, .container, .card, .modal, .alert, p, span, div, strong, em, small, label, h1, h2, h3, h4, h5, h6 {
                color: #ffffff !important;
            }
            /* Kecuali teks yang sengaja dibuat muted/abu-abu */
            .text-white-50, .text-muted, .text-secondary { color: #94a3b8 !important; }
            /* Teks sukses, warning, danger tetap pakai warna aslinya */
            .text-success { color: #22c55e !important; }
            .text-warning { color: #f59e0b !important; }
            .text-danger { color: #ef4444 !important; }
            .text-info { color: #38bdf8 !important; }
            /* Alert pada mode gelap */
            .alert { background: rgba(30, 30, 36, 0.9) !important; color: #ffffff !important; }
            .alert-success { color: #22c55e !important; }
            .alert-warning { color: #f59e0b !important; }
            .alert-danger { color: #ef4444 !important; }
            /* Tombol outline light */
            .btn-outline-light { color: #ffffff !important; border-color: rgba(255,255,255,0.3) !important; }
        </style>
    </head>
    <body class="bg-dark text-white" style="min-height: 100vh; padding: 2rem 0;">

    <?php include "sidebar_pasients.php"; ?>

    <main class="page-body content-shift">
        <div class="container">
        <div class="mb-4">
                <a href="riwayat_pesanan.php" class="btn btn-outline-light btn-sm rounded-pill px-3" style="border: 1px solid rgba(148, 163, 184, 0.3); background: rgba(255, 255, 255, 0.05);">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Riwayat
                </a>
            </div>

            <?php if ($cancelSuccess):
                $cancelReason = isset($_GET['reason']) ? urldecode($_GET['reason']) : '';
            ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert" style="max-width: 580px; margin: 0 auto 1rem auto; background: rgba(34, 197, 94, 0.2); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3) !important;">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill fs-5"></i>
                    <span class="fw-semibold">Pesanan berhasil dibatalkan.</span>
                </div>
                <?php if ($cancelReason !== ''): ?>
                <div class="mt-2" style="border-top: 1px solid rgba(34,197,94,0.2); padding-top: 8px;">
                    <small><i class="bi bi-chat-quote me-1"></i>Alasan: <em><?php echo h($cancelReason); ?></em></small>
                </div>
                <?php endif; ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php elseif (isset($_GET['error']) && $_GET['error'] === 'cannot_cancel'): ?>
            <div class="alert alert-warning alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert" style="max-width: 580px; margin: 0 auto 1rem auto; background: rgba(255, 193, 7, 0.2); color: #ffc107; border: 1px solid rgba(255, 193, 7, 0.3) !important;">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <span class="fw-semibold">Pesanan tidak dapat dibatalkan karena status sudah berubah.</span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="card mx-auto border-0 p-4 p-md-5 rounded-4 shadow-lg" style="max-width: 580px; background: rgba(30, 41, 59, 0.35); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.15);">
                
                <!-- Icon dinamis berdasarkan status -->
                <div class="mb-4 text-center">
                    <?php if ($paymentStatus === 'PAID' && in_array($orderStatus, ['completed', 'delivered'])): ?>
                        <i class="bi bi-check-circle-fill text-success display-1 d-block"></i>
                    <?php elseif ($orderStatus === 'cancelled'): ?>
                        <i class="bi bi-x-circle-fill text-danger display-1 d-block"></i>
                    <?php else: ?>
                        <i class="bi bi-hourglass-split text-warning display-1 d-block"></i>
                    <?php endif; ?>
                </div>

                <div class="text-center mb-3">
                    <h2 class="fw-bold text-white mb-2">Detail Pesanan</h2>
                    <p class="text-white-50 small">Informasi lengkap pesanan Anda</p>
                </div>

                <!-- Info Order -->
                <div class="p-3 rounded-3 mb-3" style="background: rgba(0,0,0,0.3); border: 1px solid rgba(148,163,184,0.15);">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-white-50 small">Nomor Pesanan</span>
                        <span class="fw-bold text-white font-monospace">#<?php echo h($order['order_number'] ?? '-'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-white-50 small">Tanggal</span>
                        <span class="fw-semibold text-white small"><?php echo h(date('d M Y H:i', strtotime($order['created_at'] ?? 'now'))); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-white-50 small">Status Pembayaran</span>
                        <span class="badge <?php echo statusBadge($paymentStatus); ?> px-3 py-1 rounded-pill"><?php echo h($paymentStatus); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-white-50 small">Status Pesanan</span>
                        <span class="badge <?php echo statusBadge($orderStatus, 'order'); ?> px-3 py-1 rounded-pill"><?php echo h(ucfirst($orderStatus)); ?></span>
                    </div>
                    <?php if ($isCancelled && !empty($order['cancel_reason'])): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-white-50 small">Alasan Pembatalan</span>
                        <span class="fw-semibold text-white-50 text-end small" style="max-width: 60%;"><?php echo h($order['cancel_reason']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-white-50 small">Metode Pembayaran</span>
                        <span class="fw-semibold text-white"><?php echo h($payment_method_name); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-white-50 small">Total Item</span>
                        <span class="fw-semibold text-white"><?php echo intval($totalQty); ?> item</span>
                    </div>
                    <hr style="border-color: rgba(148,163,184,0.15);">
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold text-white">Total Bayar</span>
                        <span class="fw-bold text-success fs-5"><?php echo money($order['grand_total'] ?? 0); ?></span>
                    </div>
                </div>

                <!-- Daftar Item -->
                <?php if (!empty($items)): ?>
                <div class="mb-3">
                    <div class="text-white-50 small fw-bold mb-2">Pesanan Anda:</div>
                    <?php foreach ($items as $it): ?>
                    <div class="d-flex justify-content-between align-items-center py-1 px-2 rounded-2 mb-1" style="background: rgba(0,0,0,0.2);">
                        <div>
                            <span class="text-white small"><?php echo h($it['product_name'] ?? 'Produk'); ?></span>
                            <?php if (!empty($it['notes'])): ?>
                            <br><span class="text-white-50" style="font-size:0.7rem;"><?php echo h($it['notes']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="text-end">
                            <span class="text-white-50 small">x<?php echo intval($it['qty'] ?? 0); ?></span>
                            <span class="text-success small ms-2"><?php echo money($it['price'] ?? 0); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="d-flex gap-2 mt-3">
                    <?php
                    $payStatusUpper = strtoupper($paymentStatus ?? '');
                    $orderStatusLower = strtolower($orderStatus ?? '');
                    $canCancel = ($orderStatusLower === 'pending');
                    $isCancelled = ($orderStatusLower === 'cancelled');
                    ?>

                    <?php if ($canCancel): ?>
                    <!-- Tombol Batalkan Pesanan (aktif, hanya jika status masih PENDING) -->
                    <button type="button" class="btn btn-danger rounded-pill px-4 fw-bold flex-fill shadow-sm" data-bs-toggle="modal" data-bs-target="#cancelModal">
                        <i class="bi bi-x-circle me-2"></i>Batalkan Pesanan
                    </button>
                    <?php elseif ($isCancelled): ?>
                    <!-- Tombol Pesanan Dibatalkan (disabled, jika status CANCELLED) -->
                    <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold flex-fill shadow-sm" disabled style="opacity: 0.6; cursor: not-allowed;">
                        <i class="bi bi-x-circle me-2"></i>Pesanan Dibatalkan
                    </button>
                    <?php endif; ?>

                    <a href="home.php" class="btn btn-warning rounded-pill px-4 fw-bold flex-fill shadow-sm">
                        <i class="bi bi-shop me-2"></i>Kembali ke Etalase
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Konfirmasi Batalkan Pesanan -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 text-white shadow-lg" style="background: #0b1223; border: 1px solid rgba(148, 163, 184, 0.15) !important;">
                <div class="modal-header border-secondary border-opacity-25">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill fs-4 text-danger"></i>
                        <h5 class="modal-title fw-bold m-0" id="cancelModalLabel">Konfirmasi Pembatalan</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="cancelForm" onsubmit="return validateCancelReason()">
                    <div class="modal-body py-4">
                        <div class="text-center mb-3">
                            <i class="bi bi-x-circle-fill text-danger display-4 d-block mb-3"></i>
                            <p class="fw-semibold mb-1">Apakah Anda yakin ingin membatalkan pesanan ini?</p>
                            <p class="text-white-50 small mb-3">Tindakan ini tidak dapat dibatalkan. Pesanan dengan nomor <strong class="text-white">#<?php echo h($order['order_number'] ?? '-'); ?></strong> akan dibatalkan.</p>
                        </div>
                        <hr style="border-color: rgba(148,163,184,0.15);">
                        <div class="mb-0">
                            <label for="alasan_cancel" class="form-label text-white-50 small fw-semibold">
                                <i class="bi bi-pencil-square me-1"></i>Alasan Pembatalan <span class="text-danger">*</span>
                            </label>
                            <textarea name="alasan_cancel" id="alasan_cancel" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3" rows="3" placeholder="Tuliskan alasan mengapa Anda membatalkan pesanan ini..." required style="resize: vertical;"></textarea>
                            <div id="alasanError" class="text-danger small mt-1 d-none">
                                <i class="bi bi-exclamation-circle me-1"></i>Alasan pembatalan wajib diisi!
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary border-opacity-25 d-flex gap-2">
                        <button type="button" class="btn btn-outline-light rounded-pill px-4 fw-medium" data-bs-dismiss="modal">
                            <i class="bi bi-arrow-left me-1"></i>Kembali
                        </button>
                        <input type="hidden" name="cancel_order" value="1" />
                        <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm" id="btnConfirmCancel">
                            <i class="bi bi-check2-circle me-1"></i>Ya, Batalkan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Validasi JavaScript: alasan pembatalan tidak boleh kosong
    function validateCancelReason() {
        var alasan = document.getElementById('alasan_cancel');
        var errorDiv = document.getElementById('alasanError');
        if (alasan.value.trim() === '') {
            alasan.classList.add('is-invalid');
            errorDiv.classList.remove('d-none');
            // Fokus ke textarea
            alasan.focus();
            return false;
        }
        alasan.classList.remove('is-invalid');
        errorDiv.classList.add('d-none');
        return true;
    }

    // Reset error saat modal ditutup
    document.addEventListener('DOMContentLoaded', function() {
        var cancelModalEl = document.getElementById('cancelModal');
        if (cancelModalEl) {
            cancelModalEl.addEventListener('hidden.bs.modal', function() {
                var alasan = document.getElementById('alasan_cancel');
                var errorDiv = document.getElementById('alasanError');
                if (alasan) {
                    alasan.classList.remove('is-invalid');
                    alasan.value = '';
                }
                if (errorDiv) {
                    errorDiv.classList.add('d-none');
                }
            });
        }
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// =========================================================================
// MODE LIST: Tidak ada parameter ?id= — Tampilkan daftar semua pesanan
// =========================================================================

// Ambil semua orders milik patient session ini
$orders = [];
$stmtOrders = $conn->prepare("SELECT id, order_number, grand_total, payment_status, status, created_at FROM orders WHERE patient_session_id = ? ORDER BY created_at DESC");
if ($stmtOrders) {
    $stmtOrders->bind_param('i', $patient_session_id);
    $stmtOrders->execute();
    $resOrders = $stmtOrders->get_result();
    if ($resOrders) {
        while ($row = $resOrders->fetch_assoc()) {
            $orders[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - RSI Food &amp; Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
    <style>
        :root { --bg:#0f172a; --text:#e5e7eb; --muted:#94a3b8; --green:#22c55e; }
        body { background:var(--bg) !important; color:var(--text); }
        .bottom-nav { position:fixed; left:0; right:0; bottom:0; z-index:1035; background:rgba(15,23,42,.88); backdrop-filter:blur(10px); border-top:1px solid rgba(148,163,184,.25); }
        @media (min-width:992px) { main.content-shift { margin-left:280px; } .bottom-nav { display:none; } }
        .card-order { background: rgba(30, 41, 59, 0.35) !important; border: 1px solid rgba(148, 163, 184, 0.15) !important; backdrop-filter: blur(12px); border-radius: 16px; transition: transform .15s ease, border-color .15s ease; }
        .card-order:hover { transform: translateY(-2px); border-color: rgba(34, 197, 94, 0.35) !important; }
    </style>
</head>
<body class="bg-dark text-white">

<?php include "sidebar_pasients.php"; ?>

<main class="page-body content-shift">
    <div class="container py-4">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold text-white">
                <i class="bi bi-credit-card-2-front me-2 text-success"></i> Riwayat Pesanan
            </h4>
            <a href="home.php" class="btn btn-outline-light btn-sm rounded-pill px-3" style="border: 1px solid rgba(148, 163, 184, 0.3); background: rgba(255, 255, 255, 0.05);">
                <i class="bi bi-shop me-1"></i> Etalase
            </a>
        </div>

        <?php if (count($orders) === 0): ?>
            <!-- Empty State -->
            <div class="bg-transparent text-center rounded-4 p-5" style="border: 2px dashed rgba(148, 163, 184, 0.25);">
                <i class="bi bi-inbox text-success mb-3" style="font-size: 3rem; opacity: 0.8;"></i>
                <h5 class="text-white-50 fw-medium mb-3">Belum ada riwayat pesanan</h5>
                <p class="text-white-50 small mb-4">Anda belum melakukan pemesanan apapun. Silakan pesan menu terlebih dahulu.</p>
                <div>
                    <a href="home.php" class="btn btn-success btn-sm rounded-pill px-4 fw-medium shadow">Mulai Belanja</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Daftar Pesanan -->
            <div class="row g-3">
                <?php foreach ($orders as $ord):
                    $ordId = intval($ord['id'] ?? 0);
                    $ordNumber = h($ord['order_number'] ?? '-');
                    $ordTotal = money($ord['grand_total'] ?? 0);
                    $ordDate = date('d M Y H:i', strtotime($ord['created_at'] ?? 'now'));
                    $payStatus = $ord['payment_status'] ?? 'unpaid';
                    $ordStatus = $ord['status'] ?? 'pending';

                    // Tentukan badge status
                    $payBadgeClass = 'bg-warning bg-opacity-25 text-warning border-warning border-opacity-50';
                    $payBadgeText = strtoupper($payStatus);
                    if (strtoupper($payStatus) === 'PAID') {
                        $payBadgeClass = 'bg-success bg-opacity-25 text-success border-success border-opacity-50';
                    } elseif (strtoupper($payStatus) === 'UNPAID') {
                        $payBadgeClass = 'bg-warning bg-opacity-25 text-warning border-warning border-opacity-50';
                    } elseif (in_array(strtoupper($payStatus), ['FAILED', 'REFUND'])) {
                        $payBadgeClass = 'bg-danger bg-opacity-25 text-danger border-danger border-opacity-50';
                    }

                    $orderBadgeClass = 'bg-warning bg-opacity-25 text-warning border-warning border-opacity-50';
                    $orderBadgeText = ucfirst($ordStatus);
                    if (in_array($ordStatus, ['completed', 'delivered'])) {
                        $orderBadgeClass = 'bg-success bg-opacity-25 text-success border-success border-opacity-50';
                    } elseif (in_array($ordStatus, ['processing', 'preparing'])) {
                        $orderBadgeClass = 'bg-info bg-opacity-25 text-info border-info border-opacity-50';
                    } elseif ($ordStatus === 'cancelled') {
                        $orderBadgeClass = 'bg-danger bg-opacity-25 text-danger border-danger border-opacity-50';
                    }
                ?>
                    <div class="col-12">
                        <a href="riwayat_pesanan.php?id=<?php echo $ordId; ?>" class="text-decoration-none">
                            <div class="card-order p-3">
                                <div class="row align-items-center">
                                    <div class="col-8">
                                        <div class="fw-bold text-white mb-1 font-monospace" style="font-size: 0.95rem;">
                                            #<?php echo $ordNumber; ?>
                                        </div>
                                        <div class="text-white-50 small mb-2">
                                            <i class="bi bi-calendar3 me-1"></i> <?php echo $ordDate; ?>
                                        </div>
                                        <div class="d-flex flex-wrap gap-1">
                                            <span class="badge <?php echo $payBadgeClass; ?> px-2 py-1 rounded-pill" style="font-size:0.7rem;">
                                                Pembayaran: <?php echo $payBadgeText; ?>
                                            </span>
                                            <span class="badge <?php echo $orderBadgeClass; ?> px-2 py-1 rounded-pill" style="font-size:0.7rem;">
                                                Status: <?php echo $orderBadgeText; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div class="text-success fw-bold" style="font-size: 1.1rem;"><?php echo $ordTotal; ?></div>
                                        <div class="text-white-50 small mt-1">
                                            <i class="bi bi-chevron-right"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


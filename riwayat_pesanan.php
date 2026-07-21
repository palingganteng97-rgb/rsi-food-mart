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
    $stmtOrder = $conn->prepare("SELECT id, order_number, grand_total, payment_status, status, created_at FROM orders WHERE id = ? AND patient_session_id = ? LIMIT 1");
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
            body { background:var(--bg) !important; color:var(--text); }
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
                    <a href="home.php" class="btn btn-warning rounded-pill px-4 fw-bold flex-fill shadow-sm">
                        <i class="bi bi-shop me-2"></i>Kembali ke Etalase
                    </a>
                    <a href="refund_patients.php" class="btn btn-outline-light rounded-pill px-3 fw-medium">
                        <i class="bi bi-arrow-return-left me-1"></i>Refund
                    </a>
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


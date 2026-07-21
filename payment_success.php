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

// Ambil order_id dari URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Jika tidak ada order_id, redirect ke home
if ($order_id <= 0) {
    header("Location: home.php");
    exit();
}

// Ambil data orders
$order = null;
$stmtOrder = $conn->prepare("SELECT id, order_number, grand_total, payment_status, status, created_at FROM orders WHERE id = ? LIMIT 1");
if ($stmtOrder) {
    $stmtOrder->bind_param('i', $order_id);
    $stmtOrder->execute();
    $resOrder = $stmtOrder->get_result();
    $order = $resOrder ? $resOrder->fetch_assoc() : null;
}

// Jika order tidak ditemukan, redirect
if (!$order) {
    header("Location: home.php");
    exit();
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Diproses - RSI Food &amp; Mart</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

</head>
<body class="bg-dark text-white d-flex align-items-center justify-content-center" style="min-height: 100vh; background: linear-gradient(135deg, #0f172a, #1e293b); padding: 2rem 0;">

<div class="container">
    <div class="card mx-auto border-0 p-4 p-md-5 rounded-4 shadow-lg" style="max-width: 580px; background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid rgba(255,255,255,0.1);">
        
        <!-- Icon -->
        <div class="mb-4 text-center">
            <i class="bi bi-hourglass-split text-warning display-1 d-block"></i>
        </div>

        <div class="text-center mb-3">
            <h2 class="fw-bold text-white mb-2">Pesanan Dibuat!</h2>
            <p class="text-white-50 small">Terima kasih. Pesanan Anda telah tersimpan.</p>
        </div>

        <!-- Info Order -->
        <div class="p-3 rounded-3 mb-3" style="background: rgba(0,0,0,0.3); border: 1px solid rgba(148,163,184,0.15);">
            <div class="d-flex justify-content-between mb-2">
                <span class="text-white-50 small">Nomor Pesanan</span>
                <span class="fw-bold text-white font-monospace">#<?php echo h($order['order_number'] ?? '-'); ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-white-50 small">Status Pembayaran</span>
                <span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-50 px-3 py-1 rounded-pill"><?php echo h($paymentStatus); ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-white-50 small">Status Pesanan</span>
                <span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-50 px-3 py-1 rounded-pill"><?php echo h(ucfirst($orderStatus)); ?></span>
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

        <!-- Kotak Instruksi -->
        <div class="p-3 mb-4 rounded-3 text-start small border border-warning border-opacity-50" style="background: rgba(245, 158, 11, 0.15);">
            <div class="fw-bold text-warning mb-2"><i class="bi bi-info-circle me-1"></i> Langkah Selanjutnya:</div>
            <div class="text-white fw-semibold" style="line-height: 1.5;">
                Silakan lakukan pembayaran langsung ke petugas/kasir <span class="text-warning fw-bold">RSI Food &amp; Mart</span>. Pesanan Anda akan segera diproses setelah admin menyetujui pembayaran Anda.
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="home.php" class="btn btn-warning rounded-pill px-4 fw-bold flex-fill shadow-sm">
                <i class="bi bi-shop me-2"></i>Kembali ke Etalase
            </a>
            <a href="order_detail.php?order_id=<?php echo intval($order_id); ?>" class="btn btn-outline-light rounded-pill px-3 fw-medium">
                <i class="bi bi-eye me-1"></i>Detail
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// order_detail.php - detail pesanan pasien (tracking sederhana)

include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id <= 0) {
    header('Location: index.php');
    exit;
}

// Orders
$sqlOrder = "SELECT id, order_number, grand_total, payment_status, status, created_at FROM orders WHERE id = ? LIMIT 1";
$stmtOrder = $conn->prepare($sqlOrder);
if (!$stmtOrder) {
    die('Query orders gagal: ' . $conn->error);
}
$stmtOrder->bind_param('i', $order_id);
$stmtOrder->execute();
$resOrder = $stmtOrder->get_result();
$order = $resOrder ? $resOrder->fetch_assoc() : null;

if (!$order) {
    header('Location: index.php');
    exit;
}

// Order items with product names
$sqlItems = "SELECT oi.product_id, oi.qty, oi.price, oi.notes, p.name AS product_name
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id
             WHERE oi.order_id = ? ORDER BY oi.id ASC";
$stmtItems = $conn->prepare($sqlItems);
if (!$stmtItems) {
    die('Query order_items gagal: ' . $conn->error);
}
$stmtItems->bind_param('i', $order_id);
$stmtItems->execute();
$resItems = $stmtItems->get_result();
$items = [];
if ($resItems) {
    while ($row = $resItems->fetch_assoc()) {
        $items[] = $row;
    }
}

function money($val) {
    return 'Rp ' . number_format((float)$val, 0, ',', '.');
}

function statusLabel($status) {
    $s = strtolower((string)$status);
    if (in_array($s, ['pending', 'unpaid'])) return ['🟡', 'Pending'];
    if (in_array($s, ['paid', 'sudah dibayar'])) return ['🟢', 'Paid'];
    if (in_array($s, ['processing', 'diproses'])) return ['🟠', 'Processing'];
    if (in_array($s, ['done', 'selesai'])) return ['✅', 'Selesai'];
    if (in_array($s, ['cancel', 'canceled'])) return ['🔴', 'Batal'];
    return ['🔵', (string)$status];
}

list($statusEmoji, $statusText) = statusLabel($order['status'] ?? '');
$totalQty = 0;
foreach ($items as $it) {
    $totalQty += (int)($it['qty'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Detail Pesanan - RSI Food &amp; Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
</head>
<body style="background:#0f172a; color:#e5e7eb;">
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-8">

            <!-- Card Utama -->
            <div class="card w-100" style="background: rgba(2,6,23,.40); border:1px solid rgba(148,163,184,.22); border-radius:18px;">
                <div class="card-body">

                    <!-- Header: Icon + Judul + Tanggal -->
                    <div class="text-center mb-4">
                        <div style="width:54px; height:54px; border-radius:18px; background: rgba(34,197,94,.12); border:1px solid rgba(34,197,94,.35); display:flex; align-items:center; justify-content:center; margin:0 auto;">
                            <i class="bi bi-receipt" style="font-size:1.9rem; color:#4ade80;"></i>
                        </div>
                        <h3 class="fw-bold mt-3 mb-1 text-white">Pesanan #<?php echo htmlspecialchars($order['order_number'] ?? ''); ?></h3>
                        <div class="text-white-50" style="font-size:.92rem;">
                            <?php echo htmlspecialchars($order['created_at'] ?? ''); ?>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="mb-3">
                        <div class="text-white-50" style="font-size:.85rem;">Status</div>
                        <div class="fw-bold fs-5 text-white">
                            <?php echo $statusEmoji . ' ' . htmlspecialchars($statusText); ?>
                        </div>
                        <?php
                        $paymentStatus = $order['payment_status'] ?? '';
                        $paymentLabel = (strtolower((string)$paymentStatus) === 'unpaid') ? 'CASH (Unpaid)' : (string)$paymentStatus;
                        ?>
                        <?php if (!empty($paymentStatus)): ?>
                            <div class="text-white-50" style="font-size:.82rem; margin-top:6px;">
                                Pembayaran: <?php echo htmlspecialchars($paymentLabel); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <hr style="border-color: rgba(148,163,184,.18);" />

                    <!-- Daftar Pesanan -->
                    <div class="mb-2">
                        <div class="fw-bold mb-2 text-white">Pesanan</div>
                        <?php if (empty($items)): ?>
                            <div class="text-white-50">Tidak ada item.</div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($items as $it):
                                    $productName = !empty($it['product_name']) ? $it['product_name'] : ('Produk #' . $it['product_id']);
                                    $qty = (int)($it['qty'] ?? 0);
                                    $notes = trim((string)($it['notes'] ?? ''));
                                ?>
                                    <div class="d-flex justify-content-between align-items-center py-2 px-3 rounded-3" style="background: rgba(15,23,42,0.5); border: 1px solid rgba(148,163,184,0.1);">
                                        <div>
                                            <span class="text-white fw-semibold" style="font-size:.95rem;"><?php echo htmlspecialchars($productName); ?></span>
                                            <?php if ($notes !== ''): ?>
                                                <br><span class="text-white-50 small"><?php echo htmlspecialchars($notes); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="fw-bold text-success text-end" style="min-width:70px;">
                                            x<?php echo $qty; ?><br><span class="text-white-50 small"><?php echo money($it['price'] ?? 0); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Ringkasan: Jumlah Item + Total Pembayaran -->
                    <div class="d-flex justify-content-between align-items-center mt-3 gap-3">
                        <div>
                            <div class="text-white-50" style="font-size:.85rem;">Jumlah item</div>
                            <div class="fw-bold fs-5 text-white"><?php echo $totalQty; ?></div>
                        </div>
                        <div>
                            <div class="text-white-50" style="font-size:.85rem;">Total pembayaran</div>
                            <div class="fw-bold fs-5" style="color:#22c55e;"><?php echo money($order['grand_total'] ?? 0); ?></div>
                        </div>
                    </div>

                    <!-- Info Menunggu -->
                    <div class="mt-4 p-3 rounded-3" style="background: rgba(15,23,42,.55); border:1px solid rgba(148,163,184,.18);">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-hourglass-split" style="color:#fbbf24;"></i>
                            <div>
                                <div class="fw-semibold text-white"><?php echo !empty($order['status']) ? 'Menunggu diproses...' : 'Menunggu...' ?></div>
                                <div class="text-white-50" style="font-size:.85rem;">Lihat pembaruan status melalui halaman ini.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="mt-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <a href="payment_success.php?id=<?php echo intval($order_id); ?>" class="btn btn-success">Detail Pembayaran</a>
                        <a href="home.php" class="btn btn-outline-light">Kembali ke Etalase</a>
                    </div>

                </div><!-- /card-body -->
            </div><!-- /card -->

        </div><!-- /col -->
    </div><!-- /row -->
</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

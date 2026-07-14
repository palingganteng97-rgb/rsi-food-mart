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

// Order items
$sqlItems = "SELECT order_id, product_id, qty, price, notes FROM order_items WHERE order_id = ? ORDER BY id ASC";
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
    // warna sederhana (berdasarkan teks status yang umum di sistem)
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
    <title>Detail Pesanan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
</head>
<body style="background:#0f172a; color:#e5e7eb;">

<div class="container py-4" style="max-width: 720px;">

    <div class="text-center mb-4">
        <div style="width:54px; height:54px; border-radius:18px; background: rgba(34,197,94,.12); border:1px solid rgba(34,197,94,.35); display:flex; align-items:center; justify-content:center; margin:0 auto;">
            <i class="bi bi-receipt" style="font-size:1.9rem; color:#4ade80;"></i>
        </div>
        <h3 class="fw-bold mt-3 mb-1">Pesanan #<?php echo htmlspecialchars($order['order_number'] ?? ''); ?></h3>
        <div class="text-white-50" style="font-size:.92rem;">
            <?php echo htmlspecialchars($order['created_at'] ?? ''); ?>
        </div>
    </div>

    <div class="card" style="background: rgba(2,6,23,.40); border:1px solid rgba(148,163,184,.22); border-radius:18px;">
        <div class="card-body">

            <div class="mb-3">
                <div class="text-white-50" style="font-size:.85rem;">Status</div>
                <div class="fw-bold fs-5">
                    <?php echo $statusEmoji . ' ' . htmlspecialchars($statusText); ?>
                </div>
                <?php if (!empty($order['payment_status'])): ?>
                    <div class="text-white-50" style="font-size:.82rem; margin-top:6px;">
                        Payment: <?php echo htmlspecialchars($order['payment_status']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <hr style="border-color: rgba(148,163,184,.18);" />

            <div class="mb-2">
                <div class="fw-bold mb-2">Pesanan</div>
                <?php if (empty($items)): ?>
                    <div class="text-white-50">Tidak ada item.</div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($items as $it):
                            $pid = $it['product_id'] ?? '';
                            $qty = (int)($it['qty'] ?? 0);
                            // notes mungkin ada; tetap tampilkan ringkas
                            $notes = trim((string)($it['notes'] ?? ''));
                            $label = 'Produk ID ' . $pid;
                            if ($notes !== '') {
                                $label .= ' (' . htmlspecialchars($notes) . ')';
                            }
                        ?>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-white" style="font-size:.95rem;">
                                    <?php echo htmlspecialchars($label); ?>
                                </div>
                                <div class="fw-semibold" style="min-width:70px; text-align:right;">
                                    x<?php echo $qty; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row g-3 mt-3">
                <div class="col-6">
                    <div class="text-white-50" style="font-size:.85rem;">Jumlah item</div>
                    <div class="fw-bold fs-5"><?php echo $totalQty; ?></div>
                </div>
                <div class="col-6">
                    <div class="text-white-50" style="font-size:.85rem;">Total pembayaran</div>
                    <div class="fw-bold fs-5" style="color:#22c55e;"><?php echo money($order['grand_total'] ?? 0); ?></div>
                </div>
            </div>

            <div class="mt-4 p-3 rounded-3" style="background: rgba(15,23,42,.55); border:1px solid rgba(148,163,184,.18);">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-hourglass-split" style="color:#fbbf24;"></i>
                    <div>
                        <div class="fw-semibold"><?php echo !empty($order['status']) ? 'Menunggu diproses...' : 'Menunggu...' ?></div>
                        <div class="text-white-50" style="font-size:.85rem;">Lihat pembaruan status melalui halaman ini.</div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <a href="index.php" class="btn btn-outline-light w-100">Kembali</a>
                <a href="keranjang.php" class="btn btn-success w-100">Keranjang</a>
            </div>

        </div>
    </div>
</div>

</body>
</html>


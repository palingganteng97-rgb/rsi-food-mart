<?php
// order_success.php - ringkasan pesanan pasien setelah checkout

include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id <= 0) {
    header('Location: index.php');
    exit;
}

// Ambil data orders
$sqlOrder = "SELECT order_number, grand_total, status, created_at FROM orders WHERE id = ? LIMIT 1";
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

// Ambil data order_items
$sqlItems = "SELECT product_id, qty, price FROM order_items WHERE order_id = ? ORDER BY id ASC";
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Pesanan Berhasil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
</head>
<body style="background:#0f172a; color:#e5e7eb;">

<div class="container py-4" style="max-width: 720px;">
    <div class="d-flex align-items-start gap-3 mb-4">
        <div style="width:48px; height:48px; border-radius:16px; background: rgba(34,197,94,.12); border:1px solid rgba(34,197,94,.35); display:flex; align-items:center; justify-content:center;">
            <i class="bi bi-check2-circle" style="font-size:1.8rem; color:#4ade80;"></i>
        </div>
        <div>
            <h3 class="fw-bold mb-1">Pesanan Berhasil Dibuat</h3>
            <div class="text-white-50" style="font-size:.92rem;">Silakan simpan ringkasan ini.</div>
        </div>
    </div>

    <div class="card" style="background: rgba(2,6,23,.40); border:1px solid rgba(148,163,184,.22); border-radius:18px;">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="text-white-50" style="font-size:.85rem;">Nomor Pesanan</div>
                    <div class="fw-semibold fs-5">#<?php echo htmlspecialchars($order['order_number'] ?? ''); ?></div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="text-white-50" style="font-size:.85rem;">Status</div>
                    <div class="fw-semibold fs-5"><?php echo htmlspecialchars($order['status'] ?? ''); ?></div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="text-white-50" style="font-size:.85rem;">Total</div>
                    <div class="fw-bold fs-5" style="color:#22c55e;"><?php echo money($order['grand_total'] ?? 0); ?></div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="text-white-50" style="font-size:.85rem;">Waktu</div>
                    <div class="fw-semibold fs-6"><?php echo htmlspecialchars($order['created_at'] ?? ''); ?></div>
                </div>
            </div>

            <hr style="border-color: rgba(148,163,184,.18);" />

            <div class="d-flex align-items-center justify-content-between mb-2">
                <div>
                    <div class="fw-bold">Item Pesanan</div>
                    <div class="text-white-50" style="font-size:.85rem;"><?php echo count($items); ?> item</div>
                </div>
            </div>

            <?php if (empty($items)): ?>
                <div class="text-white-50">Tidak ada item.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-borderless align-middle" style="--bs-table-color:#e5e7eb;">
                        <thead>
                            <tr>
                                <th>Produk (ID)</th>
                                <th style="width:90px;">Qty</th>
                                <th style="width:180px;">Harga</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($it['product_id'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($it['qty'] ?? ''); ?></td>
                                    <td><?php echo money($it['price'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="d-flex gap-2 mt-4">
                <a href="index.php" class="btn btn-outline-light w-100">Kembali ke Etalase</a>
                <a href="keranjang.php" class="btn btn-success w-100">Lihat Keranjang</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>


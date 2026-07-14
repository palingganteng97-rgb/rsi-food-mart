<?php
// cart_item.php - detail item keranjang
// Detail item dibaca dari database (carts/cart_items).

include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$key = isset($_GET['key']) ? (string)$_GET['key'] : '';

$item = null;

// Ambil dari database: cart_items + carts + produk
$sqlCart = "SELECT id FROM carts WHERE patient_session_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1";
$stmtCart = $conn->prepare($sqlCart);
$stmtCart->bind_param('ii', $_SESSION['patient_session_id'], $_SESSION['tenant_id']);
$stmtCart->execute();
$resCart = $stmtCart->get_result();
$cartRow = $resCart ? $resCart->fetch_assoc() : null;

if ($cartRow && isset($cartRow['id']) && $key !== '') {
    $cart_id = (int)$cartRow['id'];

    $sqlItem = "SELECT ci.product_id, ci.qty, ci.price, ci.notes, p.name, p.image
                FROM cart_items ci
                JOIN products p ON p.id = ci.product_id
                WHERE ci.cart_id = ? AND ci.id = ?
                LIMIT 1";
    $stmtItem = $conn->prepare($sqlItem);
    $stmtItem->bind_param('ii', $cart_id, $key);
    $stmtItem->execute();
    $resItem = $stmtItem->get_result();
    $item = $resItem ? $resItem->fetch_assoc() : null;
}

function money($val) {
    return 'Rp ' . number_format((float)$val, 0, ',', '.');
}

if (!$item || !is_array($item)) {
    header('Location: carts.php?status=error&msg=Item tidak ditemukan');
    exit;
}

$productName = $item['name'] ?? 'Menu';
$qty = isset($item['qty']) ? (int)$item['qty'] : 1;
$price = isset($item['price']) ? (float)$item['price'] : 0;
$notes = isset($item['notes']) ? (string)$item['notes'] : '';
$variant = isset($item['variant']) ? (string)$item['variant'] : 'Original';
$image = isset($item['image']) ? (string)$item['image'] : '';

$addons = isset($item['addons']) && is_array($item['addons']) ? $item['addons'] : [];

$path_gambar = $image ? ("uploads/products/" . $image) : '';
if (empty($image) || !file_exists($path_gambar)) {
    $path_gambar = $image ? ("uploads/products/gallery/" . $image) : '';
}
if (empty($image) || !file_exists($path_gambar)) {
    $path_gambar = 'uploads/products/default.png';
}

$totalPerItem = $price * $qty;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Detail Cart Item</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
</head>
<body style="background:#0f172a; color:#e5e7eb;">

<div class="container py-4" style="max-width: 720px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-white"><i class="bi bi-bag me-2 text-success"></i> Detail Item</h4>
        <a href="carts.php" class="btn btn-outline-light btn-sm rounded-pill px-3">← Kembali</a>
    </div>

    <div class="card" style="background: rgba(2,6,23,.40); border:1px solid rgba(148,163,184,.22); border-radius:18px;">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-4">
                    <div style="width:100%; height:110px; overflow:hidden; border-radius:14px; border:1px solid rgba(148,163,184,.18);">
                        <img src="<?php echo htmlspecialchars($path_gambar); ?>" alt="<?php echo htmlspecialchars($productName); ?>" style="width:100%; height:100%; object-fit:cover;" onerror="this.src='uploads/products/default.png'">
                    </div>
                </div>
                <div class="col-8">
                    <div class="fw-bold fs-4 text-white mb-1"><?php echo htmlspecialchars($productName); ?></div>
                    <div class="text-white-50" style="font-size:.9rem;">Varian: <?php echo htmlspecialchars($variant); ?></div>
                    <div class="text-white-50" style="font-size:.9rem;">Qty: <span class="text-white fw-semibold"><?php echo $qty; ?></span></div>
                </div>
            </div>

            <hr style="border-color: rgba(148,163,184,.18);" />

            <div class="mb-3">
                <div class="text-white-50" style="font-size:.85rem;">Harga per item</div>
                <div class="fw-semibold text-success fs-5"><?php echo money($price); ?></div>
            </div>

            <div class="mb-3">
                <div class="text-white-50" style="font-size:.85rem;">Total</div>
                <div class="fw-bold text-success fs-4"><?php echo money($totalPerItem); ?></div>
            </div>

            <?php if (!empty($addons)): ?>
                <div class="mb-3">
                    <div class="fw-bold mb-2">Topping</div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($addons as $ad): ?>
                            <?php
                                $adName = $ad['name'] ?? '';
                                $adId = $ad['id'] ?? '';
                                $adPrice = isset($ad['price']) ? (float)$ad['price'] : 0;
                            ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20 fw-normal">
                                + <?php echo htmlspecialchars($adName); ?> (<?php echo money($adPrice); ?>)
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty(trim($notes))): ?>
                <div class="p-3 rounded-3" style="background: rgba(15,23,42,.55); border:1px solid rgba(148,163,184,.18);">
                    <div class="fw-bold mb-1"><i class="bi bi-chat-left-text me-2"></i> Catatan</div>
                    <div class="text-white-50"><?php echo htmlspecialchars($notes); ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>


<?php
// carts.php - halaman keranjang sementara (delivery-style)

include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['patient_session_id']) || empty($_SESSION['patient_session_id'])) {
    header("Location: index.php");
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$patient_session_id = (int)$_SESSION['patient_session_id'];
$tenant_id = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 1;

if ($action === 'add_to_cart' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $qty        = isset($_POST['qty']) ? intval($_POST['qty']) : 1;
    $notes      = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $variant    = isset($_POST['variant']) ? $_POST['variant'] : 'Original';
    $addon_item_ids = isset($_POST['addons']) ? $_POST['addons'] : [];

    if ($product_id > 0) {
        $query_prod = "SELECT name, base_price, image FROM products WHERE id = $product_id LIMIT 1";
        $res_prod = mysqli_query($conn, $query_prod);
        $prod_data = mysqli_fetch_assoc($res_prod);
        $name = $prod_data['name'] ?? 'Menu';
        $base_price = floatval($prod_data['base_price'] ?? 0);
        $image = $prod_data['image'] ?? 'default.png';
        $addons_list = [];
        $total_addon_price = 0;

        if (!empty($addon_item_ids)) {
            $ids_string = implode(',', array_map('intval', $addon_item_ids));
            $query_addons = "SELECT id, item_name, price FROM addon_items WHERE id IN ($ids_string)";
            $res_addons = mysqli_query($conn, $query_addons);

            while ($row = mysqli_fetch_assoc($res_addons)) {
                $addons_list[] = [
                    'id' => $row['id'],
                    'name' => $row['item_name'],
                    'price' => floatval($row['price'])
                ];
                $total_addon_price += floatval($row['price']);
            }
        }

        $final_price = $base_price + $total_addon_price;

$sqlCart = "SELECT id FROM carts WHERE patient_session_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1";
$stmtCart = $conn->prepare($sqlCart);
$stmtCart->bind_param('ii', $patient_session_id, $tenant_id);
$stmtCart->execute();
$resCart = $stmtCart->get_result();

if ($resCart && $resCart->num_rows > 0) {
    $cartRow = $resCart->fetch_assoc();
    $cart_id = (int)$cartRow['id'];
} else {
    $sqlInsertCart = "INSERT INTO carts (patient_session_id, tenant_id) VALUES (?, ?)";
    $stmtInsertCart = $conn->prepare($sqlInsertCart);
    $stmtInsertCart->bind_param('ii', $patient_session_id, $tenant_id);
    $stmtInsertCart->execute();
    $cart_id = (int)$conn->insert_id;
}

$sqlCheckItem = "SELECT id FROM cart_items WHERE cart_id = ? AND product_id = ? AND notes = ? LIMIT 1";
$stmtCheck = $conn->prepare($sqlCheckItem);
$notesKey = $notes;
$stmtCheck->bind_param('iis', $cart_id, $product_id, $notesKey);
$stmtCheck->execute();
$resItem = $stmtCheck->get_result();

if ($resItem && $resItem->num_rows > 0) {
    $row = $resItem->fetch_assoc();
    $item_id = (int)$row['id'];
    $sqlUpd = "UPDATE cart_items SET qty = qty + ?, price = ? WHERE id = ?";
    $stmtUpd = $conn->prepare($sqlUpd);
$qtyInt = (int)$qty;
    $stmtUpd->bind_param('idi', $qtyInt, $final_price, $item_id);
    $stmtUpd->execute();
} else {
    $sqlIns = "INSERT INTO cart_items (cart_id, product_id, qty, price, notes) VALUES (?, ?, ?, ?, ?)";
    $stmtIns = $conn->prepare($sqlIns);
    $qtyInt = (int)$qty;
    $stmtIns->bind_param('iiids', $cart_id, $product_id, $qtyInt, $final_price, $notesKey);
    $stmtIns->execute();
}

    }
    header("Location: carts.php?status=success&msg=Menu berhasil dimasukkan ke keranjang");
    exit();
}

if ($action === 'update_qty' && isset($_GET['key']) && isset($_GET['type'])) {
    $cartItemId = (int)$_GET['key'];
    $type = $_GET['type'];

    if ($cartItemId > 0) {
        $cart_sql = "SELECT id FROM carts WHERE patient_session_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1";
        $stmtCart = $conn->prepare($cart_sql);
        $stmtCart->bind_param('ii', $patient_session_id, $tenant_id);
        $stmtCart->execute();
        $resCart = $stmtCart->get_result();
        $cart_row = $resCart ? $resCart->fetch_assoc() : null;

        if ($cart_row && isset($cart_row['id'])) {
            $cart_id = (int)$cart_row['id'];

            mysqli_begin_transaction($conn);
            try {
                if ($type === 'plus') {
                    $sql = "UPDATE cart_items SET qty = qty + 1 WHERE id = ? AND cart_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ii', $cartItemId, $cart_id);
                    $stmt->execute();
                } elseif ($type === 'minus') {
                    $sql = "UPDATE cart_items SET qty = qty - 1 WHERE id = ? AND cart_id = ? AND qty > 1";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ii', $cartItemId, $cart_id);
                    $stmt->execute();

                    $sql2 = "DELETE FROM cart_items WHERE id = ? AND cart_id = ? AND qty <= 0";
                    $stmt2 = $conn->prepare($sql2);
                    $stmt2->bind_param('ii', $cartItemId, $cart_id);
                    $stmt2->execute();
                }

                mysqli_commit($conn);
            } catch (Throwable $e) {
                mysqli_rollback($conn);
            }
        }
    }

    header("Location: carts.php");
    exit();
}

if ($action === 'update_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cartItemId = isset($_POST['cart_key']) ? (int)$_POST['cart_key'] : 0;
    $new_qty = isset($_POST['qty']) ? intval($_POST['qty']) : 1;
    $new_notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $new_variant = isset($_POST['variant']) ? $_POST['variant'] : 'Original';
    $new_addon_ids = isset($_POST['addons']) ? $_POST['addons'] : [];

    if ($cartItemId > 0 && $new_qty > 0) {
        $cart_sql = "SELECT id FROM carts WHERE patient_session_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1";
        $stmtCart = $conn->prepare($cart_sql);
        $stmtCart->bind_param('ii', $patient_session_id, $tenant_id);
        $stmtCart->execute();
        $resCart = $stmtCart->get_result();
        $cart_row = $resCart ? $resCart->fetch_assoc() : null;

        if ($cart_row && isset($cart_row['id'])) {
            $cart_id = (int)$cart_row['id'];

            $sqlCurrent = "SELECT product_id, price FROM cart_items WHERE id = ? AND cart_id = ? LIMIT 1";
            $stmtCurrent = $conn->prepare($sqlCurrent);
            $stmtCurrent->bind_param('ii', $cartItemId, $cart_id);
            $stmtCurrent->execute();
            $resCurrent = $stmtCurrent->get_result();
            $cur = $resCurrent ? $resCurrent->fetch_assoc() : null;

            if ($cur) {
                $product_id = (int)$cur['product_id'];
                $current_total_price = (float)$cur['price'];
                $new_price = $current_total_price;

                mysqli_begin_transaction($conn);
                try {
                    $sqlUpd = "UPDATE cart_items SET qty = ?, price = ?, notes = ? WHERE id = ? AND cart_id = ?";
                    $stmtUpd = $conn->prepare($sqlUpd);
                    $stmtUpd->bind_param('idssi', $new_qty, $new_price, $new_notes, $cartItemId, $cart_id);
                    $stmtUpd->execute();
                    mysqli_commit($conn);
                } catch (Throwable $e) {
                    mysqli_rollback($conn);
                }
            }
        }
    }

    header("Location: carts.php?status=success&msg=Pesanan berhasil diperbarui");
    exit();
}

if ($action === 'delete' && isset($_GET['key'])) {
    $cartItemId = (int)$_GET['key'];
    if ($cartItemId > 0) {
        $cart_sql = "SELECT id FROM carts WHERE patient_session_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1";
        $stmtCart = $conn->prepare($cart_sql);
        $stmtCart->bind_param('ii', $patient_session_id, $tenant_id);
        $stmtCart->execute();
        $resCart = $stmtCart->get_result();
        $cart_row = $resCart ? $resCart->fetch_assoc() : null;

        if ($cart_row && isset($cart_row['id'])) {
            $cart_id = (int)$cart_row['id'];
            $sqlDel = "DELETE FROM cart_items WHERE id = ? AND cart_id = ?";
            $stmtDel = $conn->prepare($sqlDel);
            $stmtDel->bind_param('ii', $cartItemId, $cart_id);
            $stmtDel->execute();
        }
    }

    header("Location: carts.php?status=success&msg=Item berhasil dihapus");
    exit();
}

$status = isset($_GET['status']) ? $_GET['status'] : "";
$msg = isset($_GET['msg']) ? $_GET['msg'] : "";
$sqlCart = "SELECT id FROM carts WHERE patient_session_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1";
$stmtCart = $conn->prepare($sqlCart);
$stmtCart->bind_param('ii', $patient_session_id, $tenant_id);
$stmtCart->execute();
$resCart = $stmtCart->get_result();
$cart = $resCart ? $resCart->fetch_assoc() : null;
$cart_items = [];
if ($cart && isset($cart['id'])) {
    $cart_id = (int)$cart['id'];
    $sqlItems = "SELECT ci.id, ci.product_id, ci.qty, ci.price, ci.notes, p.name, p.image, ci.notes
                  FROM cart_items ci
                  JOIN products p ON p.id = ci.product_id
                  WHERE ci.cart_id = ?
                  ORDER BY ci.id ASC";
    $stmtItems = $conn->prepare($sqlItems);
    $stmtItems->bind_param('i', $cart_id);
    $stmtItems->execute();
    $resItems = $stmtItems->get_result();

    if ($resItems) {
        while ($row = $resItems->fetch_assoc()) {
            $cart_items[(string)$row['id']] = [
                'id' => (int)$row['product_id'],
                'name' => $row['name'],
                'price' => (float)$row['price'],
                'image' => $row['image'],
                'qty' => (int)$row['qty'],
                'notes' => $row['notes'] ?? '',
                'variant' => 'Original',
                'addons' => []
            ];
        }
    }
}
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
    .content-bg { background: transparent; }
    .search-box { background: rgba(2,6,23,.35); border:1px solid rgba(148,163,184,.25); border-radius: 18px; }
    .diet-pill { border:1px solid rgba(34,197,94,.35); background: rgba(34,197,94,.08); color:#86efac; }
    .diet-pill[data-active="true"] { background: rgba(34,197,94,.92); color:#06210f; border-color: rgba(34,197,94,.65); }
    .card-food { background: rgba(2,6,23,.40); border:1px solid rgba(148,163,184,.22); border-radius: 18px; overflow:hidden; transition: transform .15s ease, border-color .15s ease; }
    .card-food:hover { transform: translateY(-2px); border-color: rgba(34,197,94,.35); }
    .food-img { height: 150px; background: linear-gradient(180deg, rgba(34,197,94,.10), rgba(2,6,23,.0)); display:flex; align-items:center; justify-content:center; color: rgba(148,163,184,.8); position: relative; }
    .food-img img { width:100%; height:100%; object-fit: cover; }
    .price-badge { display:inline-flex; align-items:center; gap:.35rem; padding:.35rem .7rem; background: rgba(15,23,42,.55); border:1px solid rgba(148,163,184,.25); border-radius: 999px; color: var(--text); }
    .bottom-nav { position: fixed; left:0; right:0; bottom:0; z-index: 1035; background: rgba(15,23,42,.88); backdrop-filter: blur(10px); border-top: 1px solid rgba(148,163,184,.25); display:block; }
    @media (min-width: 992px) { main.content-shift { margin-left: 280px; } .bottom-nav { display:none; } }
  </style>

</head>
<body>

<div class="container my-5">
    <div class="row">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-white"><i class="bi bi-cart3 me-2 text-success"></i> Keranjang Belanja Anda</h4>
                <a href="home.php" class="btn btn-outline-light btn-sm rounded-pill px-3" style="border: 1px solid rgba(148, 163, 184, 0.3); background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(4px);">← Kembali Belanja</a>
            </div>

            <?php
            $subtotal = 0;
            $has_items = false;

            if (!empty($cart_items)):
                foreach ($cart_items as $cartKey => $item):
                    if (!is_array($item)) continue;
                    $has_items = true;

                    $harga_total_item = isset($item['price']) ? floatval($item['price']) : 0;
                    $kuantitas = isset($item['qty']) ? intval($item['qty']) : 1;
                    $nama_menu = isset($item['name']) ? $item['name'] : 'Menu';
                    $gambar_menu = isset($item['image']) ? $item['image'] : '';

                    $total_per_item = $harga_total_item * $kuantitas;
                    $subtotal += $total_per_item;

                    $path_gambar = "uploads/products/" . $gambar_menu;
                    if (empty($gambar_menu) || !file_exists($path_gambar)) {
                        $path_gambar = "uploads/products/gallery/" . $gambar_menu;
                    }
                    if (empty($gambar_menu) || !file_exists($path_gambar)) {
                        $path_gambar = "uploads/products/default.png";
                    }
            ?>

                <div class="card mb-3 rounded-4 p-3 text-white" style="background: rgba(30, 41, 59, 0.35) !important; border: 1px solid rgba(148, 163, 184, 0.15) !important; backdrop-filter: blur(12px); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);">
                    <div class="row align-items-center">
                        <div class="col-md-2 col-3">
                            <div style="width: 80px; height: 80px; overflow: hidden; border-radius: 12px; border: 1px solid rgba(148, 163, 184, 0.15);">
                                <img src="<?php echo $path_gambar; ?>" class="w-100 h-100" style="object-fit: cover;" onerror="this.src='uploads/products/default.png'">
                            </div>
                        </div>

                        <div class="col-md-4 col-9">
                            <h5 class="mb-1 fw-bold text-white" style="font-size: 1.1rem;"><?php echo htmlspecialchars($nama_menu); ?></h5>
                            <span class="badge bg-dark text-white-50 border border-secondary border-opacity-20 small p-1 px-2 fw-normal mb-1" style="font-size: 0.75rem;">
                                Varian: <?php echo htmlspecialchars($item['variant'] ?? 'Original'); ?>
                            </span>

                            <?php if (!empty($item['addons']) && is_array($item['addons'])): ?>
                                <div class="d-flex flex-wrap gap-1 my-1">
                                    <?php foreach ($item['addons'] as $addon): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20 fw-normal" style="font-size: 0.75rem; padding: 3px 8px; border-radius: 6px;">
                                            <i class="bi bi-egg-fried me-1"></i> +<?php echo htmlspecialchars($addon['name']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if(!empty($item['notes'])): ?>
                                <div class="mt-1">
                                    <span class="badge text-warning fw-normal small" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148,163,184,0.15);">
                                        <i class="bi bi-pencil-square me-1"></i> Catatan: <?php echo htmlspecialchars($item['notes']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-3 col-6 my-2 my-md-0">
                            <div class="d-inline-flex align-items-center rounded-3 p-1" style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(148, 163, 184, 0.2); height: 38px;">
                                <?php if ($kuantitas <= 1): ?>
                                    <span class="btn btn-sm px-2 py-1 border-0" style="cursor: not-allowed; color: rgba(239, 68, 68, 0.4) !important;">
                                        <i class="bi bi-dash-lg" style="font-size: 0.85rem;"></i>
                                    </span>
                                <?php else: ?>
                                    <a href="carts.php?action=update_qty&key=<?php echo $cartKey; ?>&type=minus" class="btn btn-sm text-white px-2 py-1 border-0" style="box-shadow: none;">
                                        <i class="bi bi-dash-lg" style="font-size: 0.85rem;"></i>
                                    </a>
                                <?php endif; ?>

                                <span class="text-white fw-bold px-3 text-center" style="min-width: 35px; font-size: 0.95rem;">
                                    <?php echo $kuantitas; ?>
                                </span>

                                <a href="carts.php?action=update_qty&key=<?php echo $cartKey; ?>&type=plus" class="btn btn-sm text-white px-2 py-1 border-0" style="box-shadow: none;">
                                    <i class="bi bi-plus-lg" style="font-size: 0.85rem;"></i>
                                </a>
                            </div>
                        </div>

                        <div class="col-md-3 col-6 text-end">
                            <h5 class="text-success fw-bold mb-2" style="font-size: 1.2rem;">Rp <?php echo number_format($total_per_item, 0, ',', '.'); ?></h5>
                            <div class="d-flex flex-column align-items-end gap-1">
                                <button type="button" class="btn text-warning bg-transparent p-0 border-0 small" 
                                        data-bs-toggle="modal" data-bs-target="#modalEditPesanan"
                                        onclick='openEditPesananFromCart(<?php echo json_encode($item); ?>, "<?php echo $cartKey; ?>")' style="box-shadow: none; font-size: 0.85rem; opacity: 0.85;">
                                    <i class="bi bi-pencil-square me-1"></i> Edit Pesanan
                                </button>

                                <button type="button" class="btn text-danger bg-transparent p-0 border-0 small"
                                        data-bs-toggle="modal" data-bs-target="#modalConfirmDelete"
                                        onclick="prepareDelete('<?php echo $cartKey; ?>', '<?php echo htmlspecialchars($nama_menu, ENT_QUOTES); ?>')" style="box-shadow: none; font-size: 0.85rem; opacity: 0.85;">
                                    <i class="bi bi-trash3-fill me-1"></i> Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            <?php
                endforeach;
            endif;
            ?>

            <?php if (!$has_items): ?>
                <div class="bg-transparent text-center rounded-4 p-5" style="border: 2px dashed rgba(148, 163, 184, 0.25);">
                    <i class="bi bi-basket2 text-success mb-3" style="font-size: 3rem; opacity: 0.8;"></i>
                    <h5 class="text-white-50 fw-medium mb-3">Keranjang belanja Anda masih kosong</h5>
                    <div>
                        <a href="home.php" class="btn btn-success btn-sm rounded-pill px-4 fw-medium shadow">Mulai Belanja</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="bg-transparent rounded-4 p-4" style="border: 2px dashed rgba(148, 163, 184, 0.25); backdrop-filter: blur(8px);">
                <h4 class="fw-bold mb-4 text-white">Ringkasan Pesanan</h4>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-white-50">Subtotal Produk</span>
                    <span class="fw-semibold text-white">Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-4">
                    <span class="text-white-50">Pajak / Layanan</span>
                    <span class="fw-semibold">Rp 0</span>
                </div>
                <hr class="border-secondary border-opacity-50">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <span class="fw-bold fs-5 text-white">Total Bayar</span>
                    <span class="text-success fw-bold fs-4">Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                </div>

                <form method="POST" action="checkout_process.php">
                    <button class="btn btn-success w-100 py-2.5 fw-medium rounded-3" type="submit" <?php echo ($subtotal == 0) ? 'disabled' : ''; ?> name="checkout" value="1">
                        <i class="bi bi-credit-card-2-front me-2"></i> Lanjutkan Pemesanan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Confirm Delete -->
<div class="modal fade" id="modalConfirmDelete" tabindex="-1" aria-hidden="true" aria-labelledby="modalConfirmDeleteLabel">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 450px;">
        <div class="modal-content text-white rounded-4 border-0 shadow-lg" style="background: rgba(15, 23, 42, 0.96) !important; border: 1px solid rgba(148, 163, 184, 0.18) !important; backdrop-filter: blur(16px);">
            <div class="modal-header border-bottom border-secondary border-opacity-25 p-3 px-4">
                <h5 class="modal-title fw-bold text-danger" id="modalConfirmDeleteLabel">
                    <i class="bi bi-exclamation-triangle-fill"></i> Hapus Item
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
            </div>
            <div class="modal-body p-4 text-white-50 fs-6" id="delete_item_text_target" style="line-height: 1.5;">
                Apakah Anda yakin ingin mengeluarkan menu ini dari daftar keranjang belanja Anda?
            </div>
            <div class="modal-footer border-top border-secondary border-opacity-25 p-3 px-4 justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-medium" data-bs-dismiss="modal" style="font-size: 0.88rem;">Batal</button>
                <a id="btn_execute_delete_link" href="#" class="btn btn-sm btn-danger rounded-pill px-4 fw-medium shadow-sm" style="font-size: 0.88rem;">Ya, Hapus</a>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Edit Pesanan (fungsi JS diambil dari keranjang.php) -->
<style>
  #modalEditPesanan .modal-content {
    background: rgba(15, 23, 42, 0.96) !important;
    backdrop-filter: blur(16px);
    border: 1px solid rgba(148, 163, 184, 0.25);
    color: #e5e7eb;
    border-radius: 20px;
  }
  #modalEditPesanan .modal-header {
    border-bottom: 1px solid rgba(148, 163, 184, 0.15);
    padding: 1.0rem 1.25rem;
  }
</style>

<div class="modal fade" id="modalEditPesanan" aria-labelledby="modalEditPesananLabel" role="dialog" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <form id="formEditPesanan" method="POST" action="carts.php?action=update_item" class="w-100">
      <input type="hidden" name="cart_key" id="edit_cart_key" value="">

      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold text-white" id="modalEditPesananLabel">Edit Detail Pesanan</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">
            <div class="row align-items-center mb-4">
                <div class="col-12">
                    <div class="text-white-50 small mb-1" style="opacity:.8;">Nama Menu Hidangan</div>
                    <h3 class="fw-bold text-white m-0" id="edit_item_name">-</h3>
                </div>
            </div>

            <div class="p-3 rounded-4 mb-4" style="background: rgba(2, 6, 23, 0.4); border: 1px solid rgba(148, 163, 184, 0.12);">
                <div class="row text-center text-sm-start">
                    <div class="col-sm-6 mb-2 mb-sm-0">
                        <div class="text-white-50 small mb-1" style="opacity:.8;">Harga Satuan Utama</div>
                        <div class="fw-bold text-success fs-5" id="edit_unit_price">Rp 0</div>
                    </div>
                    <div class="col-sm-6 text-sm-end">
                        <div class="text-white-50 small mb-1" style="opacity:.8;">Total Akumulasi Item</div>
                        <div class="fw-bold text-success fs-4" id="edit_item_total">Rp 0</div>
                    </div>
                </div>
            </div>

            <div class="mb-4">
              <label for="edit_qty" class="form-label text-white-50 small fw-medium mb-2" style="opacity:.85;">Jumlah Porsi Pesanan</label>
              <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-dark border border-secondary border-opacity-25" id="btnQtyMinus" style="width:44px; height:40px; border-radius:10px;">
                  <i class="bi bi-dash-lg text-white"></i>
                </button>
                <input type="number" class="form-control text-center fw-bold fs-5 p-0" id="edit_qty" name="qty" min="1" step="1" value="1" readonly style="background: rgba(2, 6, 23, 0.4); border-radius: 10px; border: 1px solid rgba(148,163,184,0.25); color:#e5e7eb; width: 65px; height: 40px; box-shadow: none;">
                <button type="button" class="btn btn-dark border border-secondary border-opacity-25" id="btnQtyPlus" style="width:44px; height:40px; border-radius:10px;">
                  <i class="bi bi-plus-lg text-white"></i>
                </button>
              </div>
            </div>

            <div class="mb-4">
                <label for="edit_variant" class="form-label text-white-50 small fw-medium mb-2" style="opacity:.85;">Pilih Tingkat Varian / Opsi</label>
                <select id="edit_variant" name="variant" class="form-select text-white border-secondary py-2 px-3" style="background: rgba(2, 6, 23, 0.4); border-radius: 10px; font-size: 0.92rem;">
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label text-white-50 small fw-medium mb-2" style="opacity:.85;">Pilih Tambahan Topping / Addons</label>
                <div id="edit_addons_container" class="d-flex flex-column gap-2 p-2 rounded-3" style="background: rgba(2, 6, 23, 0.4); border: 1px solid rgba(148, 163, 184, 0.12);"></div>
            </div>

            <div class="mb-2">
              <label for="edit_notes" class="form-label text-white-50 small fw-medium mb-2" style="opacity:.85;">Catatan Tambahan untuk Dapur (opsional)</label>
              <input type="text" class="form-control py-2.5 px-3" id="edit_notes" name="notes" placeholder="Catatan..." style="background: rgba(2, 6, 23, 0.4); border-radius: 12px; border: 1px solid rgba(148,163,184,0.25); color:#e5e7eb; box-shadow: none; font-size: 0.92rem;">
            </div>
        </div>

        <div class="modal-footer border-top border-secondary border-opacity-25 p-3 px-4">
          <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-medium" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-sm btn-success rounded-pill fw-medium px-4 shadow-sm">Simpan Perubahan</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function openEditPesananFromCart(item, cartKey) {
    if (!item) return;

    const productId = parseInt(item.id) || 0;
    const basePrice = parseFloat(item.base_price) || parseFloat(item.price) || 0;
    const currentQty = parseInt(item.qty) || 1;

    document.getElementById('edit_cart_key').value = cartKey;
    document.getElementById('edit_item_name').innerText = item.name || 'Menu';
    document.getElementById('edit_qty').value = currentQty;
    document.getElementById('edit_notes').value = item.notes || '';
    document.getElementById('edit_unit_price').innerText = 'Rp ' + basePrice.toLocaleString('id-ID');

    const variantSelect = document.getElementById('edit_variant');
    if (variantSelect && productId > 0) {
        fetch(`get_variants.php?product_id=${productId}&_cb=${Date.now()}`)
            .then(res => res.json())
            .then(variants => {
                variantSelect.innerHTML = '';
                if (!variants || variants.length === 0) {
                    variantSelect.innerHTML = `<option value="Original">Original (Bawaan)</option>`;
                } else {
                    variants.forEach(v => {
                        const isSelected = item.variant === v.name ? 'selected' : '';
                        variantSelect.innerHTML += `<option value="${v.name}" ${isSelected}>${v.name}</option>`;
                    });
                }
            }).catch(() => {
                variantSelect.innerHTML = `<option value="Original">Original</option>`;
            });
    }

    const addonsContainer = document.getElementById('edit_addons_container');
    if (addonsContainer && productId > 0) {
        addonsContainer.innerHTML = `<p class="small text-white-50 text-center m-0 py-2"><span class="spinner-border spinner-border-sm text-success me-2"></span>Memuat topping...</p>`;

        fetch(`get_addon_items.php?product_id=${productId}&_cb=${Date.now()}`)
            .then(res => res.json())
            .then(addons => {
                addonsContainer.innerHTML = '';
                if (!addons || addons.length === 0) {
                    addonsContainer.innerHTML = `<p class="small text-white-50 text-center m-0 py-2">Tidak ada pilihan topping tambahan.</p>`;
                    hitungTotalItemLive();
                    return;
                }

                const selectedAddonIds = item.addons ? item.addons.map(a => parseInt(a.id)) : [];

                addons.forEach(addon => {
                    const addonId = parseInt(addon.id);
                    const isChecked = selectedAddonIds.includes(addonId) ? 'checked' : '';

                    addonsContainer.innerHTML += `
                        <div class="form-check d-flex align-items-center justify-content-between p-2 rounded-2 mx-2">
                            <div>
                                <input class="form-check-input me-2 addon-checkbox-input" type="checkbox" name="addons[]" value="${addonId}" id="addon_${addonId}" data-price="${addon.price}" ${isChecked}>
                                <label class="form-check-label text-white small" for="addon_${addonId}">${addon.item_name}</label>
                            </div>
                            <span class="text-success small fw-semibold">+Rp ${parseInt(addon.price).toLocaleString('id-ID')}</span>
                        </div>
                    `;
                });

                document.querySelectorAll('.addon-checkbox-input').forEach(checkbox => {
                    checkbox.addEventListener('change', hitungTotalItemLive);
                });

                hitungTotalItemLive();
            }).catch(err => {
                addonsContainer.innerHTML = `<p class="small text-danger text-center m-0 py-2">Gagal memuat daftar topping.</p>`;
                hitungTotalItemLive();
            });
    } else {
        hitungTotalItemLive();
    }
}

function hitungTotalItemLive() {
    const qtyInput = document.getElementById('edit_qty');
    const unitPriceText = document.getElementById('edit_unit_price').innerText || 'Rp 0';
    const totalDisplay = document.getElementById('edit_item_total');

    if (!qtyInput || !totalDisplay) return;

    const qty = parseInt(qtyInput.value) || 1;
    const basePrice = parseFloat(unitPriceText.replace(/[^0-9]/g, '')) || 0;

    let totalAddonPrice = 0;
    document.querySelectorAll('.addon-checkbox-input:checked').forEach(checkbox => {
        totalAddonPrice += parseFloat(checkbox.dataset.price) || 0;
    });

    const finalTotal = (basePrice + totalAddonPrice) * qty;
    totalDisplay.innerText = 'Rp ' + finalTotal.toLocaleString('id-ID');

    syncQtyButtons();
}

function syncQtyButtons() {
    const qtyInput = document.getElementById('edit_qty');
    const minusBtn = document.getElementById('btnQtyMinus');

    if (!qtyInput || !minusBtn) return;

    const q = parseInt(qtyInput.value) || 1;
    if (q <= 1) {
        minusBtn.style.opacity = '0.35';
        minusBtn.style.pointerEvents = 'none';
    } else {
        minusBtn.style.opacity = '1';
        minusBtn.style.pointerEvents = 'auto';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const qtyInput = document.getElementById('edit_qty');
    const minusBtn = document.getElementById('btnQtyMinus');
    const plusBtn = document.getElementById('btnQtyPlus');

    if (minusBtn && plusBtn && qtyInput) {
        minusBtn.addEventListener('click', function(e) {
            e.preventDefault();
            let q = parseInt(qtyInput.value) || 1;
            if (q > 1) {
                qtyInput.value = q - 1;
                hitungTotalItemLive();
            }
        });

        plusBtn.addEventListener('click', function(e) {
            e.preventDefault();
            let q = parseInt(qtyInput.value) || 1;
            qtyInput.value = q + 1;
            hitungTotalItemLive();
        });
    }
});

function prepareDelete(cartKey, productName) {
    const textTarget = document.getElementById('delete_item_text_target');
    if (textTarget) {
        textTarget.innerHTML = `Apakah Anda yakin ingin mengeluarkan menu <b class="text-white">"${productName}"</b> dari daftar keranjang belanja Anda?`;
    }
    const deleteBtn = document.getElementById('btn_execute_delete_link');
    if (deleteBtn) {
        deleteBtn.href = `carts.php?action=delete&key=${cartKey}`;
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


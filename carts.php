<?php
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = 1;

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function money_id(float $v): string {
    return 'Rp ' . number_format($v, 0, ',', '.');
}

function as_int($v, int $default = 0): int {
    $i = filter_var($v, FILTER_VALIDATE_INT);
    return $i === false ? $default : (int)$i;
}

function as_float($v, float $default = 0.0): float {
    $f = filter_var($v, FILTER_VALIDATE_FLOAT);
    return $f === false ? $default : (float)$f;
}

function resolve_product_image_path(string $image): string {
    $image = trim($image);
    if ($image === '') return 'uploads/products/default.png';

    $p1 = 'uploads/products/' . $image;
    if (file_exists($p1)) return $p1;

    $p2 = 'uploads/products/gallery/' . $image;
    if (file_exists($p2)) return $p2;

    return 'uploads/products/default.png';
}

function ensure_active_cart(mysqli $conn, int $tenant_id): int {
    $stmt = $conn->prepare('SELECT id FROM carts WHERE tenant_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $tenant_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        return (int)($row['id'] ?? 0);
    }

    $stmtIns = $conn->prepare('INSERT INTO carts (tenant_id) VALUES (?)');
    $stmtIns->bind_param('i', $tenant_id);
    $stmtIns->execute();
    return (int)$conn->insert_id;
}

// AMBIL DATA TRANSAKSI & PARSING FORMAT STRING NOTES (Bebas Error Database)
function fetch_cart_items(mysqli $conn, int $cart_id): array {
    $sql = "SELECT ci.id AS cart_item_id,
                   ci.product_id,
                   ci.qty,
                   ci.price AS base_price_only,
                   ci.notes,
                   p.name AS product_name,
                   p.image AS product_image
            FROM cart_items ci
            JOIN products p ON p.id = ci.product_id
            WHERE ci.cart_id = ?
            ORDER BY ci.id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $cart_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $items = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cart_item_id = (int)($row['cart_item_id'] ?? 0);
            if ($cart_item_id <= 0) continue;

            $product_id = (int)($row['product_id'] ?? 0);
            $raw_notes = (string)($row['notes'] ?? '');
            
            // Variabel bawaan default parsing string
            $variant = 'Original';
            $selected_addon_ids = [];
            $pure_notes = $raw_notes;

            // Jika teks notes mengandung format data terstruktur buatan kita
            if (strpos($raw_notes, 'Varian:') !== false) {
                $parts = explode('|', $raw_notes);
                $pure_notes = '';
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (strpos($part, 'Varian:') === 0) {
                        $variant = trim(substr($part, 7));
                    } elseif (strpos($part, 'ToppingID:') === 0) {
                        $id_string = trim(substr($part, 10));
                        if ($id_string !== '') {
                            $selected_addon_ids = array_map('intval', explode(',', $id_string));
                        }
                    } elseif (strpos($part, 'Catatan:') === 0) {
                        $pure_notes = trim(substr($part, 8));
                    }
                }
            }

            // Ambil rincian data nama & harga topping live berdasarkan ID pilihan diatas
            $addons_list = [];
            $addon_sum = 0.0;
            if (!empty($selected_addon_ids)) {
                $ids_string = implode(',', $selected_addon_ids);
                $addon_res = $conn->query("SELECT id, item_name, price FROM addon_items WHERE id IN ($ids_string) ORDER BY item_name ASC");
                if ($addon_res) {
                    while ($a_row = $addon_res->fetch_assoc()) {
                        $price = as_float($a_row['price'] ?? 0, 0);
                        $addon_sum += $price;
                        $addons_list[] = [
                            'id' => (int)$a_row['id'],
                            'item_name' => (string)$a_row['item_name'],
                            'price' => $price,
                        ];
                    }
                }
            }

            $base_price_only = as_float($row['base_price_only'] ?? 0, 0);
            $unit_price = $base_price_only + $addon_sum;

            $items[$cart_item_id] = [
                'cart_item_id' => $cart_item_id,
                'product_id' => $product_id,
                'qty' => as_int($row['qty'] ?? 1, 1),
                'base_price_only' => $base_price_only,
                'unit_price' => $unit_price,
                'notes' => $pure_notes,
                'variant' => $variant,
                'addons' => $addons_list,
                'selected_addon_ids' => $selected_addon_ids,
                'product_name' => (string)($row['product_name'] ?? 'Menu'),
                'product_image' => (string)($row['product_image'] ?? ''),
            ];
        }
    }
    return $items;
}

// HITUNG TOTAL REALTIME DARI STRUKTUR NOTES PARSING
function fetch_grand_total(mysqli $conn, int $cart_id): float {
    $items = fetch_cart_items($conn, $cart_id);
    $grand_total = 0.0;
    foreach ($items as $item) {
        $grand_total += ($item['unit_price'] * $item['qty']);
    }
    return $grand_total;
}

function fetch_addon_items_by_product(mysqli $conn, int $product_id): array {
    $sql = "SELECT ai.id, ai.item_name, ai.price
            FROM addon_items ai
            INNER JOIN product_addons pa ON ai.addon_id = pa.id
            WHERE pa.product_id = ?
            ORDER BY ai.item_name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $out[] = [
                'id' => (int)($row['id'] ?? 0),
                'item_name' => (string)($row['item_name'] ?? ''),
                'price' => as_float($row['price'] ?? 0, 0),
            ];
        }
    }
    return $out;
}

$action = isset($_GET['action']) ? (string)$_GET['action'] : '';
$cart_id = ensure_active_cart($conn, $tenant_id);

if ($action === 'qty' && isset($_GET['key'], $_GET['type'])) {
    $cart_item_id = as_int($_GET['key'], 0);
    $type = (string)$_GET['type'];

    if ($cart_item_id > 0 && in_array($type, ['plus', 'minus'], true)) {
        mysqli_begin_transaction($conn);
        try {
            if ($type === 'plus') {
                $stmt = $conn->prepare('UPDATE cart_items SET qty = qty + 1 WHERE id = ? AND cart_id = ?');
                $stmt->bind_param('ii', $cart_item_id, $cart_id);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare('UPDATE cart_items SET qty = qty - 1 WHERE id = ? AND cart_id = ? AND qty > 1');
                $stmt->bind_param('ii', $cart_item_id, $cart_id);
                $stmt->execute();

                $stmtDel = $conn->prepare('DELETE FROM cart_items WHERE id = ? AND cart_id = ? AND qty <= 0');
                $stmtDel->bind_param('ii', $cart_item_id, $cart_id);
                $stmtDel->execute();
            }
            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
        }
    }
    header('Location: carts.php');
    exit;
}

if ($action === 'delete' && isset($_GET['key'])) {
    $cart_item_id = as_int($_GET['key'], 0);
    if ($cart_item_id > 0) {
        $stmt = $conn->prepare('DELETE FROM cart_items WHERE id = ? AND cart_id = ?');
        $stmt->bind_param('ii', $cart_item_id, $cart_id);
        $stmt->execute();
    }
    header('Location: carts.php');
    exit;
}

// PROSES SIMPAN VARIANT & CHECK/UNCHECK TOPPING GABUNG KEMBALI KE NOTES
if ($action === 'update_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart_item_id = as_int($_POST['cart_key'] ?? 0, 0);
    $new_qty = as_int($_POST['qty'] ?? 1, 1);
    $user_notes = isset($_POST['notes']) ? trim((string)$_POST['notes']) : '';

    $new_variant = isset($_POST['variant']) ? trim((string)$_POST['variant']) : 'Original';
    $new_addon_ids = isset($_POST['addons']) ? $_POST['addons'] : [];
    if (!is_array($new_addon_ids)) $new_addon_ids = [];

    // Menyusun string terformat untuk dimasukkan kembali ke kolom notes
    $final_components = [];
    $final_components[] = "Varian: " . $new_variant;
    
    if (!empty($new_addon_ids)) {
        $clean_ids = array_map('intval', $new_addon_ids);
        $final_components[] = "ToppingID: " . implode(',', $clean_ids);
    }
    if ($user_notes !== '') {
        $final_components[] = "Catatan: " . $user_notes;
    }
    
    $compiled_notes = implode(' | ', $final_components);

    if ($cart_item_id > 0 && $new_qty > 0) {
        $stmt = $conn->prepare('UPDATE cart_items SET qty = ?, notes = ? WHERE id = ? AND cart_id = ?');
        $stmt->bind_param('isii', $new_qty, $compiled_notes, $cart_item_id, $cart_id);
        $stmt->execute();
    }

    header('Location: carts.php');
    exit;
}

$cart_items = fetch_cart_items($conn, $cart_id);
$grand_total = fetch_grand_total($conn, $cart_id);

// AMBIL VOUCHER AKTIF
$active_vouchers = [];
$voucher_query = "SELECT id, code, discount_type, discount_value, minimum_purchase, quota 
                  FROM vouchers 
                  WHERE status = 1 
                    AND quota > 0 
                    AND start_date <= CURDATE() 
                    AND end_date >= CURDATE() 
                  ORDER BY id DESC";
$voucher_res = $conn->query($voucher_query);
if ($voucher_res) {
    while ($v = $voucher_res->fetch_assoc()) {
        $active_vouchers[] = $v;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Keranjang Belanja - RSI Food &amp; Mart</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
<style>
    :root { --bg:#0f172a; --text:#e5e7eb; --muted:#94a3b8; --green:#22c55e; }
    body, body.modal-open { background:var(--bg) !important; color:var(--text); overflow:auto !important; padding-right:0px !important; pointer-events:auto !important; }
    .modal-backdrop, .modal-backdrop.show { display:none !important; opacity:0 !important; visibility:hidden !important; pointer-events:none !important; }
    .bottom-nav { position:fixed; left:0; right:0; bottom:0; z-index:1035; background:rgba(15,23,42,.88); backdrop-filter:blur(10px); border-top:1px solid rgba(148,163,184,.25); }
    @media (min-width:992px) { main.content-shift { margin-left:280px; } .bottom-nav { display:none; } }
    #modalEditPesanan .modal-content { background:rgba(15,23,42,0.96) !important; backdrop-filter:blur(16px); border:1px solid rgba(148,163,184,0.25); color:#e5e7eb; border-radius:20px; }
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

      <?php if (count($cart_items) === 0): ?>
        <div class="bg-transparent text-center rounded-4 p-5" style="border: 2px dashed rgba(148, 163, 184, 0.25);">
          <i class="bi bi-basket2 text-success mb-3" style="font-size: 3rem; opacity: 0.8;"></i>
          <h5 class="text-white-50 fw-medium mb-3">Keranjang belanja Anda masih kosong</h5>
          <div>
            <a href="home.php" class="btn btn-success btn-sm rounded-pill px-4 fw-medium shadow">Mulai Belanja</a>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($cart_items as $cart_item_id => $item):
            $qty = (int)$item['qty'];
            $unit_price = (float)$item['unit_price'];
            $subtotal_item = $unit_price * $qty;
            $product_image_path = resolve_product_image_path($item['product_image']);
        ?>

          <div class="card mb-3 rounded-4 p-3 text-white" style="background: rgba(30, 41, 59, 0.35) !important; border: 1px solid rgba(148, 163, 184, 0.15) !important; backdrop-filter: blur(12px);">
            <div class="row align-items-center">
              <div class="col-md-2 col-3">
                <div style="width: 80px; height: 80px; overflow: hidden; border-radius: 12px; border: 1px solid rgba(148, 163, 184, 0.15);">
                  <img src="<?php echo h($product_image_path); ?>" class="w-100 h-100" style="object-fit: cover;" onerror="this.src='uploads/products/default.png'">
                </div>
              </div>

              <div class="col-md-4 col-9">
                <h5 class="mb-1 fw-bold" style="font-size: 1.1rem;"><?php echo h($item['product_name']); ?></h5>

                <div class="d-flex flex-wrap gap-1 mb-2">
                  <span class="badge bg-dark text-white-50 border border-secondary border-opacity-20 small fw-normal" style="font-size:0.75rem;">Varian: <?php echo h($item['variant']); ?></span>
                  <?php if (!empty($item['addons'])): ?>
                    <?php foreach ($item['addons'] as $ad): ?>
                      <span class="badge bg-success text-white border border-success border-opacity-20 small fw-normal" style="font-size:0.75rem;">+ <?php echo h($ad['item_name']); ?></span>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>

                <?php if (trim($item['notes']) !== ''): ?>
                  <div class="mt-1">
                    <div class="cart-note">
                      Catatan:
                      <span class="ms-1 badge text-warning fw-normal small" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148,163,184,0.15);">
                        <i class="bi bi-pencil-square me-1"></i><?php echo h($item['notes']); ?>
                      </span>
                    </div>
                  </div>
                <?php endif; ?>
              </div>

              <div class="col-md-3 col-6 my-2 my-md-0">
                <div class="d-inline-flex align-items-center rounded-3 p-1" style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(148, 163, 184, 0.2); height: 38px;">
                  <?php if ($qty <= 1): ?>
                    <span class="btn btn-sm px-2 py-1 border-0" style="cursor:not-allowed; color: rgba(239, 68, 68, 0.4) !important;">
                      <i class="bi bi-dash-lg" style="font-size:0.85rem;"></i>
                    </span>
                  <?php else: ?>
                    <a href="carts.php?action=qty&type=minus&key=<?php echo (int)$cart_item_id; ?>" class="btn btn-sm text-white px-2 py-1 border-0" style="box-shadow:none;">
                      <i class="bi bi-dash-lg" style="font-size:0.85rem;"></i>
                    </a>
                  <?php endif; ?>

                  <span class="text-white fw-bold px-3 text-center" style="min-width:35px; font-size:0.95rem;"><?php echo (int)$qty; ?></span>

                  <a href="carts.php?action=qty&type=plus&key=<?php echo (int)$cart_item_id; ?>" class="btn btn-sm text-white px-2 py-1 border-0" style="box-shadow:none;">
                    <i class="bi bi-plus-lg" style="font-size:0.85rem;"></i>
                  </a>
                </div>
              </div>

              <div class="col-md-3 col-6 text-end">
                <h5 class="text-success fw-bold mb-2" style="font-size:1.2rem;"><?php echo money_id($subtotal_item); ?></h5>

                <div class="d-flex flex-column align-items-end gap-1">
                  <button
                    type="button"
                    class="btn text-warning bg-transparent p-0 border-0 small"
                    data-bs-toggle="modal"
                    data-bs-target="#modalEditPesanan"
                    onclick='openEditPesanan(<?php echo json_encode([
                        'cart_item_id' => $cart_item_id,
                        'product_id' => (int)$item['product_id'],
                        'qty' => (int)$item['qty'],
                        'variant' => $item['variant'],
                        'notes' => $item['notes'],
                        'base_price_only' => (float)$item['base_price_only'],
                        'selected_addon_ids' => $item['selected_addon_ids'],
                        'product_name' => $item['product_name'],
                        'product_image' => $item['product_image'],
                    ], JSON_UNESCAPED_UNICODE); ?>)'
                    style="box-shadow:none; font-size:0.85rem; opacity:0.9;">
                    <i class="bi bi-pencil-square me-1"></i> Edit Pesanan
                  </button>
                  
                  <!-- Tombol pemicu Modal Konfirmasi Hapus Item -->
                  <button type="button" class="btn text-danger bg-transparent p-0 border-0 small mt-1" data-bs-toggle="modal" data-bs-target="#modalHapusItem<?= $cart_item_id; ?>" style="box-shadow:none; font-size:0.85rem;">
                    <i class="bi bi-trash me-1"></i> Hapus Item
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Modal Konfirmasi Hapus Item Berdasarkan ID Unik -->
          <div class="modal fade" id="modalHapusItem<?= $cart_item_id; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
              <div class="modal-content text-white rounded-4 border-0" style="background: #111827; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);">
                <div class="modal-header border-0 pb-0">
                  <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i> Hapus Item
                  </h5>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
                </div>
                <div class="modal-body py-3">
                  <p class="text-white-50 m-0" style="font-size: 0.95rem; line-height: 1.5;">Apakah Anda yakin ingin mengeluarkan menu <strong><?= h($item['product_name']); ?></strong> ini dari daftar keranjang belanja Anda?</p>
                </div>
                <div class="modal-footer border-0 pt-0 d-flex gap-2">
                  <button type="button" class="btn btn-sm rounded-pill px-4 fw-medium text-white border-0" data-bs-dismiss="modal" style="background: #1f2937;">Batal</button>
                  <a href="carts.php?action=delete&key=<?= $cart_item_id; ?>" class="btn btn-danger btn-sm rounded-pill px-4 fw-medium shadow-sm">Ya, Hapus</a>
                </div>
              </div>
            </div>
          </div>

        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="col-lg-4">
      <div class="bg-transparent rounded-4 p-4" style="border: 2px dashed rgba(148, 163, 184, 0.25); backdrop-filter: blur(8px);">
        <h4 class="fw-bold mb-4 text-white">Ringkasan Pesanan</h4>

        <div class="d-flex justify-content-between mb-2">
          <span class="text-white-50">Subtotal Produk</span>
          <span class="fw-semibold text-white" id="summary_subtotal"><?php echo money_id($grand_total); ?></span>
        </div>

        <!-- VOUCHER SECTION -->
        <?php if (!empty($active_vouchers)): ?>
        <div class="mb-3 p-3 rounded-4" style="background: rgba(2,6,23,.35); border: 1px solid rgba(148,163,184,.12);">
          <label class="form-label text-white-50 small fw-medium mb-2" style="opacity:.85;">
            <i class="bi bi-ticket-perforated text-warning me-1"></i> Pilih Voucher Diskon
          </label>
          <select id="voucher_select" class="form-select text-white border-secondary py-2 px-3" style="background:rgba(2,6,23,0.4); border-radius:10px; font-size:0.9rem;">
            <option value="">-- Tidak pakai voucher --</option>
            <?php foreach ($active_vouchers as $v): 
              $v_id = (int)$v['id'];
              $v_code = htmlspecialchars($v['code']);
              $v_type = $v['discount_type'];
              $v_val = (float)$v['discount_value'];
              $v_min = (float)$v['minimum_purchase'];
              $v_quota = (int)$v['quota'];
              $label_diskon = $v_type === 'percent' ? $v_val.'%' : 'Rp '.number_format($v_val,0,',','.');
              $label_min = $v_min > 0 ? ' (min. Rp '.number_format($v_min,0,',','.').')' : '';
            ?>
              <option value="<?= $v_id ?>" 
                data-type="<?= $v_type ?>" 
                data-value="<?= $v_val ?>" 
                data-minimum="<?= $v_min ?>">
                <?= $v_code ?> — <?= $label_diskon . $label_min ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div id="voucher_info" class="small mt-2" style="display:none;"></div>
        </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between mb-2" id="discount_row" style="display:none;">
          <span class="text-white-50">Diskon Voucher</span>
          <span class="fw-semibold text-warning" id="summary_discount">- Rp 0</span>
        </div>

        <div class="d-flex justify-content-between mb-4">
          <span class="text-white-50">Pajak / Layanan</span>
          <span class="fw-semibold">Rp 0</span>
        </div>

        <hr class="border-secondary border-opacity-50">

        <div class="d-flex justify-content-between align-items-center mb-4">
          <span class="fw-bold fs-5 text-white">Total Bayar</span>
          <span class="text-success fw-bold fs-4" id="summary_grand_total"><?php echo money_id($grand_total); ?></span>
        </div>

        <?php
        // Ambil daftar metode pembayaran untuk modal pemilihan
        $pm = [];
        $pmRes = $conn->query("SELECT id, name, provider, is_online FROM payment_methods ORDER BY id DESC");
        if ($pmRes) {
            while ($row = $pmRes->fetch_assoc()) {
                $pm[] = $row;
            }
        }
        ?>

        <form method="POST" action="checkout_process.php" id="formCheckout">
          <input type="hidden" name="payment_method_id" id="payment_method_id" value="">
          <input type="hidden" name="voucher_id" id="checkout_voucher_id" value="">
          <input type="hidden" name="discount_amount" id="checkout_discount_amount" value="0">
          <a href="#" class="btn btn-success w-100 rounded-3 py-2 fw-medium d-flex align-items-center justify-content-center gap-2"
             data-bs-toggle="modal" data-bs-target="#modalPilihMetodePembayaran">
              <i class="bi bi-wallet2"></i> Lanjutkan Pemesanan
          </a>
        </form>

        <!-- MODAL: Pilih Metode Pembayaran -->
        <div class="modal fade" id="modalPilihMetodePembayaran" tabindex="-1" aria-labelledby="modalPilihMetodePembayaranLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered" style="max-width: 560px;">
            <div class="modal-content text-white rounded-4 border-0" style="background: #111827; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);">
              <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="modalPilihMetodePembayaranLabel">
                  <i class="bi bi-cash-stack me-2 text-success"></i> Pilih Metode Pembayaran
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow:none;"></button>
              </div>

              <div class="modal-body py-3">
                <?php if (count($pm) === 0): ?>
                  <div class="text-white-50 text-center py-3">
                    Tidak ada metode pembayaran. Silakan hubungi admin.
                  </div>
                <?php else: ?>
                  <div class="d-flex flex-column gap-2">
                    <?php foreach ($pm as $method):
                      $mid = (int)($method['id'] ?? 0);
                      $mname = (string)($method['name'] ?? 'Metode');
                      $provider = trim((string)($method['provider'] ?? ''));
                      $label = $provider !== '' ? ($mname . ' (' . $provider . ')') : $mname;
                    ?>
                      <label class="d-flex align-items-center justify-content-between gap-3 p-3 rounded-3" style="background: rgba(2,6,23,.35); border: 1px solid rgba(148,163,184,.18); cursor: pointer;">
                        <span class="d-flex flex-column">
                          <span class="fw-semibold"><?php echo h($label); ?></span>
                          <span class="text-white-50 small">ID: <?php echo h($mid); ?></span>
                        </span>
                        <span>
                          <input class="form-check-input" type="radio" name="pm_radio" value="<?php echo h($mid); ?>" <?php echo $mid === (int)$pm[0]['id'] ? 'checked' : ''; ?> onclick="setPaymentMethod(<?php echo h($mid); ?>)" />
                        </span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>

              <div class="modal-footer border-0 pt-0 d-flex gap-2">
                <button type="button" class="btn btn-sm rounded-pill px-4 fw-medium text-white border-0" data-bs-dismiss="modal" style="background:#1f2937; box-shadow:none;">Batal</button>
                <button type="button" class="btn btn-sm btn-success rounded-pill px-4 fw-medium shadow-sm"
                        onclick="submitCheckoutWithSelectedMethod()">
                  Lanjut
                </button>
              </div>
            </div>
          </div>
        </div>

        <script>
          function setPaymentMethod(id) {
            const el = document.getElementById('payment_method_id');
            if (el) el.value = String(id);
          }

          function submitCheckoutWithSelectedMethod() {
            const el = document.getElementById('payment_method_id');
            if (!el || !el.value) {
              alert('Pilih metode pembayaran terlebih dahulu.');
              return;
            }
            const form = document.getElementById('formCheckout');
            if (!form) return;
            form.submit();
          }

          document.addEventListener('DOMContentLoaded', function() {
            // set default metode pembayaran sesuai pilihan radio pertama
            const radio = document.querySelector('input[name="pm_radio"]:checked');
            if (radio) setPaymentMethod(radio.value);
          });
        </script>

      </div>
    </div>
  </div>
</div>

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
          <div class="p-3 rounded-4 mb-4" style="background:rgba(2,6,23,0.4); border:1px solid rgba(148,163,184,0.12);">
            <div class="row text-center text-sm-start">
              <div class="col-sm-6 mb-2 mb-sm-0">
                <div class="text-white-50 small mb-1" style="opacity:.8;">Harga Satuan (base + addon)</div>
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
              <button type="button" class="btn btn-dark border border-secondary border-opacity-25" id="btnQtyMinus" style="width:44px; height:40px; border-radius:10px;"><i class="bi bi-dash-lg text-white"></i></button>
              <input type="number" class="form-control text-center fw-bold fs-5 p-0" id="edit_qty" name="qty" min="1" step="1" value="1" readonly style="background:rgba(2,6,23,0.4); border-radius:10px; border:1px solid rgba(148,163,184,0.25); color:#e5e7eb; width:65px; height:40px; box-shadow:none;">
              <button type="button" class="btn btn-dark border border-secondary border-opacity-25" id="btnQtyPlus" style="width:44px; height:40px; border-radius:10px;"><i class="bi bi-plus-lg text-white"></i></button>
            </div>
          </div>
          <div class="mb-4">
            <label for="edit_variant" class="form-label text-white-50 small fw-medium mb-2" style="opacity:.85;">Pilih Tingkat Varian / Opsi</label>
            <select id="edit_variant" name="variant" class="form-select text-white border-secondary py-2 px-3" style="background:rgba(2,6,23,0.4); border-radius:10px; font-size:0.92rem;"></select>
          </div>
          <div class="mb-4">
            <label class="form-label text-white-50 small fw-medium mb-2" style="opacity:.85;">Pilih Tambahan Topping / Addons</label>
            <div id="edit_addons_container" class="d-flex flex-column gap-2 p-2 rounded-3" style="background:rgba(2,6,23,0.4); border:1px solid rgba(148,163,184,0.12);"></div>
          </div>
          <div class="mb-2">
            <label for="edit_notes" class="form-label text-white-50 small fw-medium mb-2" style="opacity:.85;">Catatan Tambahan untuk Dapur (opsional)</label>
            <input type="text" class="form-control py-2.5 px-3" id="edit_notes" name="notes" placeholder="Catatan..." style="background:rgba(2,6,23,0.4); border-radius:12px; border:1px solid rgba(148,163,184,0.25); color:#e5e7eb; box-shadow:none; font-size:0.92rem;">
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
  let __edit_state = null;
  function moneyToNumberId(str) {
    if (!str) return 0;
    return parseFloat(String(str).replace(/[^0-9]/g, '')) || 0;
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
  function computeSelectedAddonTotal() {
    let total = 0;
    document.querySelectorAll('.addon-checkbox-input:checked').forEach(cb => {
      total += parseFloat(cb.dataset.price) || 0;
    });
    return total;
  }
  function updateTotalsLive() {
    if (!__edit_state) return;
    const qtyInput = document.getElementById('edit_qty');
    const totalDisplay = document.getElementById('edit_item_total');
    if (!qtyInput || !totalDisplay) return;
    const qty = parseInt(qtyInput.value) || 1;
    const basePrice = __edit_state.base_price;
    const addonTotal = computeSelectedAddonTotal();
    const unit = basePrice + addonTotal;
    document.getElementById('edit_unit_price').innerText = 'Rp ' + unit.toLocaleString('id-ID');
    totalDisplay.innerText = 'Rp ' + (unit * qty).toLocaleString('id-ID');
    syncQtyButtons();
  }
  function openEditPesanan(item) {
    if (!item) return;
    __edit_state = {
      cart_item_id: item.cart_item_id,
      product_id: item.product_id,
      qty: item.qty || 1,
      variant: item.variant || 'Original',
      notes: item.notes || '',
      base_price: parseFloat(item.base_price_only) || 0,
      selected_addon_ids: Array.isArray(item.selected_addon_ids) ? item.selected_addon_ids.map(x => parseInt(x)).filter(x => !isNaN(x)) : []
    };
    document.getElementById('edit_cart_key').value = item.cart_item_id;
    document.getElementById('edit_item_name').innerText = item.product_name || '-';
    document.getElementById('edit_qty').value = __edit_state.qty;
    document.getElementById('edit_notes').value = __edit_state.notes;
    const variantSelect = document.getElementById('edit_variant');
    variantSelect.innerHTML = '<option value="Original">Original (Bawaan)</option>';
    fetch(`get_variants.php?product_id=${__edit_state.product_id}&_cb=${Date.now()}`)
      .then(r => r.json())
      .then(variants => {
        if (Array.isArray(variants) && variants.length > 0) {
          variantSelect.innerHTML = '';
          variantSelect.innerHTML += '<option value="Original">Original (Bawaan)</option>';
          variants.forEach(v => {
            const opt = document.createElement('option');
            opt.value = v.name;
            opt.textContent = v.name;
            variantSelect.appendChild(opt);
          });
        }
      })
      .catch(() => {});
    const addonsContainer = document.getElementById('edit_addons_container');
    addonsContainer.innerHTML = '<p class="small text-white-50 text-center m-0 py-2"><span class="spinner-border spinner-border-sm text-success me-2"></span>Memuat topping...</p>';
    fetch(`get_addon_items.php?product_id=${__edit_state.product_id}&_cb=${Date.now()}`)
      .then(res => res.json())
      .then(addons => {
        addonsContainer.innerHTML = '';
        if (!Array.isArray(addons) || addons.length === 0) {
          addonsContainer.innerHTML = '<p class="small text-white-50 text-center m-0 py-2">Tidak ada pilihan topping tambahan.</p>';
          updateTotalsLive();
          return;
        }
        const selectedIds = Array.isArray(__edit_state.selected_addon_ids) ? __edit_state.selected_addon_ids : [];
        addons.forEach(a => {
          const addonId = parseInt(a.id);
          const price = parseFloat(a.price) || 0;
          const safeName = a.item_name || '';
          const checked = selectedIds.includes(addonId);
          addonsContainer.innerHTML += `
            <div class="form-check d-flex align-items-center justify-content-between p-2 rounded-2 mx-2" style="background: rgba(2,6,23,.25); border: 1px solid rgba(148,163,184,.10);">
              <div>
                <input class="form-check-input me-2 addon-checkbox-input" type="checkbox" name="addons[]" value="${addonId}" data-price="${price}" id="addon_${addonId}" ${checked ? 'checked' : ''}>
                <label class="form-check-label text-white small" for="addon_${addonId}">${safeName}</label>
              </div>
              <span class="text-success small fw-semibold">+Rp ${Math.round(price).toLocaleString('id-ID')}</span>
            </div>
          `;
        });
        document.querySelectorAll('.addon-checkbox-input').forEach(cb => {
          cb.addEventListener('change', updateTotalsLive);
        });
        updateTotalsLive();
      })
      .catch(() => {
        addonsContainer.innerHTML = '<p class="small text-danger text-center m-0 py-2">Gagal memuat daftar topping.</p>';
        updateTotalsLive();
      });
    syncQtyButtons();
    updateTotalsLive();
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
          updateTotalsLive();
        }
      });
      plusBtn.addEventListener('click', function(e) {
        e.preventDefault();
        let q = parseInt(qtyInput.value) || 1;
        qtyInput.value = q + 1;
        updateTotalsLive();
      });
    }
    syncQtyButtons();
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
  function bersihkanMacet() {
    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
  }
  document.addEventListener('hidden.bs.modal', bersihkanMacet);
  setInterval(() => {
    let adaModalAktif = false;
    document.querySelectorAll('.modal').forEach(m => {
      if (m.classList.contains('show')) adaModalAktif = true;
    });
    if (!adaModalAktif && document.querySelector('.modal-backdrop')) {
      bersihkanMacet();
    }
  }, 300);

  // FUNGSI UNTUK PROSES CHECKOUT PASIEN VIA FORM POST
  function submitCheckoutWithSelectedMethod() {
    const el = document.getElementById('payment_method_id');
    if (!el || !el.value) {
      alert('Pilih metode pembayaran terlebih dahulu.');
      return;
    }
    const form = document.getElementById('formCheckout');
    if (form) {
      form.submit();
    }
  }

  // ===== VOUCHER LOGIC =====
  (function() {
    const voucherSelect = document.getElementById('voucher_select');
    if (!voucherSelect) return; // no vouchers available

    const originalGrandTotal = <?= json_encode($grand_total) ?>;
    const subtotalDisplay = document.getElementById('summary_subtotal');
    const grandTotalDisplay = document.getElementById('summary_grand_total');
    const discountRow = document.getElementById('discount_row');
    const discountDisplay = document.getElementById('summary_discount');
    const voucherInfo = document.getElementById('voucher_info');
    const hiddenVoucherId = document.getElementById('checkout_voucher_id');
    const hiddenDiscountAmt = document.getElementById('checkout_discount_amount');

    function formatMoney(amount) {
      return 'Rp ' + Math.round(amount).toLocaleString('id-ID');
    }

    function updateVoucher() {
      const selected = voucherSelect.options[voucherSelect.selectedIndex];
      if (!selected || !selected.value) {
        // No voucher selected
        discountRow.style.display = 'none';
        voucherInfo.style.display = 'none';
        grandTotalDisplay.textContent = formatMoney(originalGrandTotal);
        if (hiddenVoucherId) hiddenVoucherId.value = '';
        if (hiddenDiscountAmt) hiddenDiscountAmt.value = '0';
        return;
      }

      const type = selected.dataset.type;
      const value = parseFloat(selected.dataset.value) || 0;
      const minimum = parseFloat(selected.dataset.minimum) || 0;
      const voucherId = selected.value;

      // Check minimum purchase
      if (originalGrandTotal < minimum) {
        voucherInfo.style.display = 'block';
        voucherInfo.className = 'small mt-2 text-danger';
        voucherInfo.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Minimal belanja ' + formatMoney(minimum) + ' untuk menggunakan voucher ini.';
        discountRow.style.display = 'none';
        grandTotalDisplay.textContent = formatMoney(originalGrandTotal);
        if (hiddenVoucherId) hiddenVoucherId.value = '';
        if (hiddenDiscountAmt) hiddenDiscountAmt.value = '0';
        return;
      }

      // Calculate discount
      let discount = 0;
      if (type === 'percent') {
        discount = (originalGrandTotal * value) / 100;
      } else {
        // nominal
        discount = Math.min(value, originalGrandTotal);
      }

      const newTotal = originalGrandTotal - discount;

      // Update UI
      discountRow.style.display = 'flex';
      discountDisplay.textContent = '- ' + formatMoney(discount);
      grandTotalDisplay.textContent = formatMoney(newTotal);
      voucherInfo.style.display = 'block';
      voucherInfo.className = 'small mt-2 text-success';
      voucherInfo.innerHTML = '<i class="bi bi-check-circle me-1"></i> Diskon ' + formatMoney(discount) + ' diterapkan!';
      
      if (hiddenVoucherId) hiddenVoucherId.value = voucherId;
      if (hiddenDiscountAmt) hiddenDiscountAmt.value = discount.toFixed(2);
    }

    voucherSelect.addEventListener('change', updateVoucher);
  })();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
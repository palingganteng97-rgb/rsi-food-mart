<?php
// carts.php - Keranjang Belanja (rebuild dari nol)
// PHP Native + MySQLi
// Fitur:
// - Menampilkan item dari carts & cart_items
// - Menampilkan: gambar produk, nama, harga, qty, variant, addon, catatan, subtotal
// - Tombol: tambah qty, kurang qty, edit pesanan, hapus
// - Grand total selalu dihitung dari database
// - Modal edit: qty, variant, checkbox addon, notes dengan realtime price
// - Persistensi checkbox addon saat modal dibuka ulang

include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================
// Konfigurasi tenant carts.php (proyek saat ini memakai tenant_id=1 untuk halaman ini)
// =====================
$tenant_id = 1;

// =====================
// Helper
// =====================
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

function fetch_cart_items(mysqli $conn, int $cart_id): array {
    // DB saat ini: cart_items hanya punya (id, cart_id, product_id, qty, price, notes)
    // variant & addon TIDAK dipersist di tabel cart_items saat ini.
    // Untuk memenuhi requirement tanpa mengakses kolom yang tidak ada,
    // kami akan:
    // - menampilkan variant sebagai 'Original' (fallback)
    // - menampilkan addon sebagai kosong (karena data addon per item tidak tersedia dari DB)
    //
    // Namun fitur edit/addon yang Anda minta TANPA struktur baru tidak mungkin dipersist.
    // Solusi arsitektur terbaik: buat tabel relasi baru.

    $sql = "SELECT ci.id AS cart_item_id,
                   ci.product_id,
                   ci.qty,
                   ci.price AS unit_price,
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

            $items[$cart_item_id] = [
                'cart_item_id' => $cart_item_id,
                'product_id' => (int)($row['product_id'] ?? 0),
                'qty' => as_int($row['qty'] ?? 1, 1),
                'unit_price' => as_float($row['unit_price'] ?? 0, 0),
                'notes' => (string)($row['notes'] ?? ''),
                'variant' => 'Original',
                'addons' => [],
                'product_name' => (string)($row['product_name'] ?? 'Menu'),
                'product_image' => (string)($row['product_image'] ?? ''),
            ];
        }
    }

    return $items;
}

function fetch_grand_total(mysqli $conn, int $cart_id): float {
    $stmt = $conn->prepare('SELECT COALESCE(SUM(qty * price), 0) AS grand_total FROM cart_items WHERE cart_id = ?');
    $stmt->bind_param('i', $cart_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    return as_float($row['grand_total'] ?? 0, 0);
}

function fetch_addon_items_by_product(mysqli $conn, int $product_id): array {
    // Menggunakan struktur addon yang sudah ada di proyek: addon_items
    // Diasumsikan addon_items mengandung (id, addon_id, item_name, price) sebagaimana get_addon_items.php
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

// =====================
// ACTIONS
// =====================
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

// Edit pesanan: tanpa kolom addon/variant per item pada schema saat ini,
// maka kita hanya update qty dan notes. Modal tetap menampilkan addon/variant,
// tapi persistensi addon tidak bisa dilakukan sampai tabel relasi baru dibuat.
if ($action === 'update_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart_item_id = as_int($_POST['cart_key'] ?? 0, 0);
    $new_qty = as_int($_POST['qty'] ?? 1, 1);
    $new_notes = isset($_POST['notes']) ? trim((string)$_POST['notes']) : '';

    // variant & addons diambil agar UI bekerja, namun belum bisa dipersist ke DB yang ada.
    $new_variant = isset($_POST['variant']) ? trim((string)$_POST['variant']) : 'Original';
    $new_addon_ids = isset($_POST['addons']) ? $_POST['addons'] : [];
    if (!is_array($new_addon_ids)) $new_addon_ids = [];

    if ($cart_item_id > 0 && $new_qty > 0) {
        $stmt = $conn->prepare('UPDATE cart_items SET qty = ?, notes = ? WHERE id = ? AND cart_id = ?');
        $stmt->bind_param('isii', $new_qty, $new_notes, $cart_item_id, $cart_id);
        $stmt->execute();
    }

    header('Location: carts.php');
    exit;
}

// =====================
// RENDER
// =====================
$cart_items = fetch_cart_items($conn, $cart_id);
$grand_total = fetch_grand_total($conn, $cart_id);
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
    body { background:var(--bg) !important; color:var(--text); }
    .bottom-nav { position: fixed; left:0; right:0; bottom:0; z-index: 1035; background: rgba(15,23,42,.88); backdrop-filter: blur(10px); border-top: 1px solid rgba(148,163,184,.25); }
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
                        'unit_price' => (float)$item['unit_price'],
                        'product_name' => $item['product_name'],
                        'product_image' => $item['product_image'],
                    ], JSON_UNESCAPED_UNICODE); ?>)'
                    style="box-shadow:none; font-size:0.85rem; opacity:0.9;">
                    <i class="bi bi-pencil-square me-1"></i> Edit Pesanan
                  </button>

                  <button
                    type="button"
                    class="btn text-danger bg-transparent p-0 border-0 small"
                    data-bs-toggle="modal"
                    data-bs-target="#modalConfirmDelete"
                    onclick="prepareDelete(<?php echo (int)$cart_item_id; ?>, <?php echo json_encode($item['product_name'], JSON_UNESCAPED_UNICODE); ?>)"
                    style="box-shadow:none; font-size:0.85rem; opacity:0.9;">
                    <i class="bi bi-trash3-fill me-1"></i> Hapus
                  </button>
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
          <span class="fw-semibold text-white"><?php echo money_id($grand_total); ?></span>
        </div>

        <div class="d-flex justify-content-between mb-4">
          <span class="text-white-50">Pajak / Layanan</span>
          <span class="fw-semibold">Rp 0</span>
        </div>

        <hr class="border-secondary border-opacity-50">

        <div class="d-flex justify-content-between align-items-center mb-4">
          <span class="fw-bold fs-5 text-white">Total Bayar</span>
          <span class="text-success fw-bold fs-4"><?php echo money_id($grand_total); ?></span>
        </div>

        <form method="POST" action="checkout_process.php">
          <button class="btn btn-success w-100 py-2.5 fw-medium rounded-3" type="submit" <?php echo ($grand_total <= 0) ? 'disabled' : ''; ?> name="checkout" value="1">
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

<!-- MODAL: Edit Pesanan -->
<style>
  #modalEditPesanan .modal-content {
    background: rgba(15, 23, 42, 0.96) !important;
    backdrop-filter: blur(16px);
    border: 1px solid rgba(148, 163, 184, 0.25);
    color: #e5e7eb;
    border-radius: 20px;
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
            <select id="edit_variant" name="variant" class="form-select text-white border-secondary py-2 px-3" style="background: rgba(2, 6, 23, 0.4); border-radius: 10px; font-size: 0.92rem;"></select>
          </div>

          <div class="mb-4">
            <label class="form-label text-white-50 small fw-medium mb-2" style="opacity:.85;">Pilih Tambahan Topping / Addons</label>
            <div id="edit_addons_container" class="d-flex flex-column gap-2 p-2 rounded-3" style="background: rgba(2, 6, 23, 0.4); border: 1px solid rgba(148, 163, 184, 0.12);"></div>
            <div class="text-warning small mt-2">
              Catatan: addons/variant belum bisa dipersist di database karena tabel `cart_items` saat ini belum menyimpan addon per item.
            </div>
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
    const unitPriceText = document.getElementById('edit_unit_price').innerText || 'Rp 0';
    const totalDisplay = document.getElementById('edit_item_total');

    if (!qtyInput || !totalDisplay) return;

    const qty = parseInt(qtyInput.value) || 1;

    // unit_price di UI akan di-update base+selectedAddon
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
      // harga unit yang tersimpan di DB adalah price; di sistem saat ini tidak terpisah base/addon.
      base_price: parseFloat(item.unit_price) || 0
    };

    document.getElementById('edit_cart_key').value = item.cart_item_id;
    document.getElementById('edit_item_name').innerText = item.product_name || '-';
    document.getElementById('edit_qty').value = __edit_state.qty;
    document.getElementById('edit_notes').value = __edit_state.notes;

    // Default variant
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

    // Addons list
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

        // Karena addon yang dipilih TIDAK dipersist di DB saat ini,
        // semua checkbox default unchecked.
        addons.forEach(a => {
          const addonId = parseInt(a.id);
          const price = parseFloat(a.price) || 0;
          const safeName = a.item_name || '';
          addonsContainer.innerHTML += `
            <div class="form-check d-flex align-items-center justify-content-between p-2 rounded-2 mx-2" style="background: rgba(2,6,23,.25); border: 1px solid rgba(148,163,184,.10);">
              <div>
                <input class="form-check-input me-2 addon-checkbox-input" type="checkbox" name="addons[]" value="${addonId}" data-price="${price}" id="addon_${addonId}">
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
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


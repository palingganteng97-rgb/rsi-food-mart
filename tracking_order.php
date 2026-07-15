<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($orderId <= 0) {
    header('Location: home.php');
    exit;
}

$stmt = $conn->prepare('SELECT id, order_number, patient_session_id, tenant_id, grand_total, payment_status, status, created_at FROM orders WHERE id = ?');
$stmt->bind_param('i', $orderId);
$stmt->execute();
$order = ($stmt->get_result())->fetch_assoc();

if (!$order) {
    header('Location: home.php');
    exit;
}

$sqlItems = 'SELECT oi.qty, oi.price, oi.notes, p.name AS product_name
              FROM order_items oi
              LEFT JOIN products p ON p.id = oi.product_id
              WHERE oi.order_id = ?
              ORDER BY oi.id ASC';
$stmtItems = $conn->prepare($sqlItems);
$stmtItems->bind_param('i', $orderId);
$stmtItems->execute();
$resItems = $stmtItems->get_result();
$items = [];
while ($row = $resItems ? $resItems->fetch_assoc() : false) {
    if (!$row) break;
    $items[] = $row;
}

$sqlHist = 'SELECT status, changed_by, notes, created_at
             FROM order_status_histories
             WHERE order_id = ?
             ORDER BY id ASC';
$stmtHist = $conn->prepare($sqlHist);
$stmtHist->bind_param('i', $orderId);
$stmtHist->execute();
$resHist = $stmtHist->get_result();
$histories = [];
while ($row = $resHist ? $resHist->fetch_assoc() : false) {
    if (!$row) break;
    $histories[] = $row;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function badgeOrder($st): string {
    $map = [
        'pending' => ['bg'=>'warning','text'=>'warning'],
        'accepted' => ['bg'=>'primary','text'=>'primary'],
        'preparing' => ['bg'=>'info','text'=>'info'],
        'ready' => ['bg'=>'success','text'=>'success'],
        'picked_up' => ['bg'=>'success','text'=>'success'],
        'delivering' => ['bg'=>'primary','text'=>'primary'],
        'completed' => ['bg'=>'success','text'=>'success'],
        'cancelled' => ['bg'=>'danger','text'=>'danger'],
    ];
    $k = $map[$st] ?? ['bg'=>'secondary','text'=>'secondary'];
    return '<span class="badge bg-'.$k['bg'].' bg-opacity-25 text-'.$k['text'].' border border-'.$k['text'].' border-opacity-50 px-3 py-2 rounded-pill" style="font-size:0.85rem;">'.h($st).'</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Tracking Pesanan</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <style>
    :root { --bg:#0f172a; --text:#e5e7eb; }
    body { background: var(--bg) !important; color: var(--text); }
  </style>
</head>
<body>
  <?php require __DIR__ . '/sidebar.php'; ?>
  <main class="content-shift p-4">
    <div class="container" style="max-width: 980px;">

      <div class="mb-4">
        <h2 class="fw-bold">Tracking Pesanan</h2>
        <div class="text-white-50" style="font-family:monospace; letter-spacing:0.4px;">#<?php echo h($order['order_number']); ?></div>
      </div>

      <div class="row g-3">
        <div class="col-lg-7">
          <div class="card" style="background: rgba(15,23,42,.65); border:1px solid rgba(148,163,184,.25); border-radius: 18px;">
            <div class="card-body p-4">
              <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                  <div class="text-white-50 small">Status Pesanan</div>
                  <div><?php echo badgeOrder((string)$order['status']); ?></div>
                </div>
                <div>
                  <div class="text-white-50 small">Grand Total</div>
                  <div class="fw-bold text-success" style="font-size: 1.5rem;">Rp <?php echo number_format((float)$order['grand_total'],0,',','.'); ?></div>
                </div>
              </div>

              <hr class="border-white" style="opacity:.15;">

              <div class="text-uppercase text-white-50 small fw-bold mb-2">Menu yang dipesan</div>
              <div class="table-responsive">
                <table class="table table-dark table-hover align-middle" style="background: rgba(2,6,23,.25);">
                  <thead>
                    <tr>
                      <th>Menu</th>
                      <th class="text-center">Qty</th>
                      <th class="text-end">Harga</th>
                      <th class="text-end">Subtotal</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($items)): foreach ($items as $it):
                      $qty = (int)($it['qty'] ?? 0);
                      $price = (float)($it['price'] ?? 0);
                      $subtotal = $qty * $price;
                    ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?php echo h($it['product_name'] ?? '-'); ?></div>
                          <?php if (!empty($it['notes'])): ?>
                            <div class="text-white-50 small">Catatan: <?php echo h($it['notes']); ?></div>
                          <?php endif; ?>
                        </td>
                        <td class="text-center fw-semibold"><?php echo h($qty); ?></td>
                        <td class="text-end">Rp <?php echo number_format($price,0,',','.'); ?></td>
                        <td class="text-end fw-bold">Rp <?php echo number_format($subtotal,0,',','.'); ?></td>
                      </tr>
                    <?php endforeach; else: ?>
                      <tr><td colspan="4" class="text-center text-white-50 py-5">Belum ada item</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="card" style="background: rgba(15,23,42,.65); border:1px solid rgba(148,163,184,.25); border-radius: 18px;">
            <div class="card-body p-4">
              <div class="text-uppercase text-white-50 small fw-bold mb-3">Timeline Status</div>

              <?php if (!empty($histories)): foreach ($histories as $idx => $hrow): ?>
                <div class="d-flex gap-3 mb-3">
                  <div class="mt-1" style="width:12px;height:12px;border-radius:999px;background:rgba(34,197,94,.9);"></div>
                  <div>
                    <div class="fw-semibold"><?php echo h($hrow['status'] ?? ''); ?></div>
                    <?php if (!empty($hrow['notes'])): ?>
                      <div class="text-white-50 small"><?php echo h($hrow['notes']); ?></div>
                    <?php endif; ?>
                    <div class="text-white-50 small"><?php echo !empty($hrow['created_at']) ? h(date('d M Y H:i', strtotime((string)$hrow['created_at']))) : ''; ?></div>
                  </div>
                </div>
              <?php endforeach; else: ?>
                <div class="text-white-50 text-center py-5">Belum ada history.</div>
              <?php endif; ?>

              <div class="mt-3">
                <a href="home.php" class="btn btn-outline-light w-100 rounded-3 py-2.5 fw-medium">
                  <i class="bi bi-shop me-2"></i>Kembali ke Home
                </a>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


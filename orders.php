<?php
// orders.php - Admin Pesanan (orders)
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$perPage = 10;
$allowedOrderStatuses = ['pending','accepted','preparing','ready','picked_up','delivering','completed','cancelled'];

$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$paymentStatus = isset($_GET['payment_status']) ? trim((string)$_GET['payment_status']) : '';
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$action = isset($_GET['action']) ? (string)$_GET['action'] : '';

// -----------------------------
// Helpers
// -----------------------------
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function badgePayment(string $ps): string {
    $ps = trim($ps);
    $map = [
        'paid' => ['bg' => 'success', 'text' => 'success'],
        'unpaid' => ['bg' => 'danger', 'text' => 'danger'],
        'pending' => ['bg' => 'warning', 'text' => 'warning'],
        'failed' => ['bg' => 'danger', 'text' => 'danger'],
        'refunded' => ['bg' => 'info', 'text' => 'info'],
        'partial' => ['bg' => 'primary', 'text' => 'primary'],
    ];
    $k = $map[$ps] ?? ['bg' => 'secondary', 'text' => 'secondary'];
    $label = $ps !== '' ? $ps : '-';
    return '<span class="badge bg-'.$k['bg'].' bg-opacity-25 text-'.$k['text'].' border border-'.$k['text'].' border-opacity-50 px-3 py-2 rounded-pill" style="font-size:0.78rem;">'.h(strtoupper($label)).'</span>';
}

function badgeOrder(string $st): string {
    $st = trim($st);
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
    $label = $st !== '' ? $st : '-';
    return '<span class="badge bg-'.$k['bg'].' bg-opacity-25 text-'.$k['text'].' border border-'.$k['text'].' border-opacity-50 px-3 py-2 rounded-pill" style="font-size:0.78rem;">'.h($label).'</span>';
}

function formatRp($v): string {
    return 'Rp ' . number_format((float)$v, 0, ',', '.');
}

// -----------------------------
// Detail endpoint (modal)
// -----------------------------
if ($action === 'detail' && isset($_GET['id'])) {
    $orderId = (int)$_GET['id'];
    if ($orderId <= 0) {
        http_response_code(400);
        echo 'Invalid order id';
        exit;
    }

    $sqlO = 'SELECT id, order_number, patient_session_id, tenant_id, subtotal, discount, delivery_fee, grand_total, payment_status, status, created_at FROM orders WHERE id = ?';
    $stmtO = $conn->prepare($sqlO);
    $stmtO->bind_param('i', $orderId);
    $stmtO->execute();
    $resO = $stmtO->get_result();
    $order = $resO ? $resO->fetch_assoc() : null;

    if (!$order) {
        http_response_code(404);
        echo 'Order not found';
        exit;
    }

    $sqlItems = 'SELECT oi.id, oi.product_id, oi.qty, oi.price, oi.notes,
                         p.name AS product_name,
                         oi.variant AS variant_name
                  FROM order_items oi
                  LEFT JOIN products p ON p.id = oi.product_id
                  WHERE oi.order_id = ?
                  ORDER BY oi.id ASC';
    $stmtItems = $conn->prepare($sqlItems);
    $stmtItems->bind_param('i', $orderId);
    $stmtItems->execute();
    $resItems = $stmtItems->get_result();

    $items = [];
    if ($resItems) {
        while ($row = $resItems->fetch_assoc()) {
            $items[] = $row;
        }
    }

    $createdAt = $order['created_at'] ?? '';
    $createdLabel = $createdAt ? date('d M Y H:i', strtotime((string)$createdAt)) : '-';

    $orderNumber = $order['order_number'] ?? $order['id'];
    $patientSessionId = $order['patient_session_id'] ?? '-';
    $tenantId = $order['tenant_id'] ?? '-';

    $subtotal = (float)($order['subtotal'] ?? 0);
    $discount = (float)($order['discount'] ?? 0);
    $deliveryFee = (float)($order['delivery_fee'] ?? 0);
    $grandTotal = (float)($order['grand_total'] ?? 0);

    echo '<div class="p-2">';
    echo '  <div class="row g-3">';

    echo '    <div class="col-md-6">';
    echo '      <div class="text-muted small">Nomor Pesanan</div>';
    echo '      <div class="fw-bold text-white" style="font-family:monospace; letter-spacing:0.5px;">#'.h($orderNumber).'</div>';
    echo '    </div>';

    echo '    <div class="col-md-6">';
    echo '      <div class="text-muted small">Tanggal Dibuat</div>';
    echo '      <div class="fw-semibold text-white">'.h($createdLabel).'</div>';
    echo '    </div>';

    echo '    <div class="col-md-6">';
    echo '      <div class="text-muted small">Patient Session ID</div>';
    echo '      <div class="fw-semibold text-white" style="font-family:monospace;">'.h($patientSessionId).'</div>';
    echo '    </div>';

    echo '    <div class="col-md-6">';
    echo '      <div class="text-muted small">Tenant ID</div>';
    echo '      <div class="fw-semibold text-white" style="font-family:monospace;">'.h($tenantId).'</div>';
    echo '    </div>';

    echo '    <div class="col-md-6">';
    echo '      <div class="text-muted small">Payment Status</div>';
    echo '      <div>'.badgePayment((string)($order['payment_status'] ?? '')).'</div>';
    echo '    </div>';

    echo '    <div class="col-md-6">';
    echo '      <div class="text-muted small">Status Pesanan</div>';
    echo '      <div>'.badgeOrder((string)($order['status'] ?? '')).'</div>';
    echo '    </div>';

    echo '    <div class="col-12"><hr class="text-white" style="opacity:0.12;"></div>';

    echo '    <div class="col-md-4">';
    echo '      <div class="text-muted small">Subtotal</div>';
    echo '      <div class="fw-bold text-warning">'.formatRp($subtotal).'</div>';
    echo '    </div>';

    echo '    <div class="col-md-4">';
    echo '      <div class="text-muted small">Diskon</div>';
    echo '      <div class="fw-bold text-info">'.formatRp($discount).'</div>';
    echo '    </div>';

    echo '    <div class="col-md-4">';
    echo '      <div class="text-muted small">Ongkir</div>';
    echo '      <div class="fw-bold text-primary">'.formatRp($deliveryFee).'</div>';
    echo '    </div>';

    echo '    <div class="col-md-12">';
    echo '      <div class="text-muted small">Grand Total</div>';
    echo '      <div class="fw-bold text-success" style="font-size:1.25rem;">'.formatRp($grandTotal).'</div>';
    echo '    </div>';

    echo '    <div class="col-12 mt-2">';
    echo '      <div class="text-uppercase text-white-50" style="font-weight:700; font-size:0.8rem;">Daftar menu yang dipesan</div>';
    echo '      <div class="table-responsive mt-2">';
    echo '        <table class="table table-dark table-hover align-middle" style="background: rgba(15,23,42,.25); border-color: rgba(148,163,184,.2);">';
    echo '          <thead><tr>';
    echo '            <th>Menu</th><th>Variant</th><th class="text-center">Qty</th><th class="text-end">Harga</th><th class="text-end">Subtotal Item</th><th>Catatan</th>';
    echo '          </tr></thead>';
    echo '          <tbody>';

    if (!empty($items)) {
        foreach ($items as $it) {
            $qty = (int)($it['qty'] ?? 0);
            $price = (float)($it['price'] ?? 0);
            $subtotalItem = $qty * $price;
            $productName = (string)($it['product_name'] ?? '-');
            $variant = (string)($it['variant_name'] ?? ($it['variant'] ?? 'Original'));
            $notes = (string)($it['notes'] ?? '');
            echo '<tr>';
            echo '  <td class="fw-semibold">'.h($productName).'</td>';
            echo '  <td>'.h($variant).'</td>';
            echo '  <td class="text-center fw-semibold">'.h($qty).'</td>';
            echo '  <td class="text-end">'.formatRp($price).'</td>';
            echo '  <td class="text-end fw-bold">'.formatRp($subtotalItem).'</td>';
            echo '  <td>'.(trim($notes) !== '' ? '<span class="text-white-50 small">'.h($notes).'</span>' : '-').'</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="6" class="text-center text-white-50">Tidak ada item.</td></tr>';
    }

    echo '          </tbody>';
    echo '        </table>';
    echo '      </div>';
    echo '    </div>';

    echo '  </div>';
    echo '</div>';
    exit;
}

// -----------------------------
// Update status endpoint
// -----------------------------
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $newStatus = isset($_POST['status']) ? trim((string)$_POST['status']) : '';

    if ($orderId > 0 && in_array($newStatus, $allowedOrderStatuses, true)) {
        $sql = 'UPDATE orders SET status = ? WHERE id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $newStatus, $orderId);
        $stmt->execute();

        $sqlHist = 'INSERT INTO order_status_histories (order_id, status, changed_by, notes, created_at) VALUES (?, ?, ?, ?, NOW())';
        $stmtHist = $conn->prepare($sqlHist);
        $changedBy = (int)$_SESSION['user_id'];
        $notesHist = 'Status diubah';
        $stmtHist->bind_param('isis', $orderId, $newStatus, $changedBy, $notesHist);
        $stmtHist->execute();
    }

    header('Location: orders.php?status=success_update');
    exit;
}

// -----------------------------
// List orders with search/filter/pagination
// -----------------------------
$where = [];
$types = '';
$params = [];

if ($q !== '') {
    $where[] = '(orders.order_number LIKE ? OR orders.id LIKE ?)';
    $types .= 'ss';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if ($status !== '' && in_array($status, $allowedOrderStatuses, true)) {
    $where[] = 'orders.status = ?';
    $types .= 's';
    $params[] = $status;
}

if ($paymentStatus !== '') {
    $where[] = 'orders.payment_status = ?';
    $types .= 's';
    $params[] = $paymentStatus;
}

$whereSql = '';
if (count($where) > 0) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

$sqlCount = 'SELECT COUNT(*) AS total FROM orders ' . $whereSql;
$stmtCount = $conn->prepare($sqlCount);
if ($whereSql !== '') {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$resCount = $stmtCount->get_result();
$totalRows = $resCount ? (int)($resCount->fetch_assoc()['total'] ?? 0) : 0;
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// created_at exists as requested
$sql = 'SELECT id, order_number, patient_session_id, tenant_id, subtotal, discount, delivery_fee, grand_total, payment_status, status, created_at
        FROM orders ' . $whereSql . '
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?';

$stmt = $conn->prepare($sql);
if ($whereSql !== '') {
    $bindTypes = $types . 'ii';
    $bindParams = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($bindTypes, ...$bindParams);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$res = $stmt->get_result();
$orders = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $orders[] = $row;
    }
}

$qsBase = 'q=' . urlencode($q) . '&status=' . urlencode($status) . '&payment_status=' . urlencode($paymentStatus);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin - Pesanan</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
</head>
<body>
  <?php require __DIR__ . '/sidebar.php'; ?>

  <main class="content-shift p-4">
    <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">

      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
        <div>
          <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Pesanan</h2>
        </div>

        <div class="d-flex flex-wrap gap-2">
          <form method="GET" action="orders.php" class="d-flex flex-wrap gap-2 align-items-center">
            <input type="text" name="q" value="<?php echo h($q); ?>" class="form-control rounded-3 bg-dark text-white border-secondary" placeholder="Cari nomor pesanan..." style="min-width: 220px; font-size:0.9rem;" />

            <select name="status" class="form-select rounded-3 bg-dark text-white border-secondary" style="min-width: 170px; font-size:0.9rem;">
              <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>Semua Status</option>
              <?php foreach ($allowedOrderStatuses as $st): ?>
                <option value="<?php echo h($st); ?>" <?php echo $status === $st ? 'selected' : ''; ?>><?php echo h($st); ?></option>
              <?php endforeach; ?>
            </select>

            <input type="text" name="payment_status" value="<?php echo h($paymentStatus); ?>" class="form-control rounded-3 bg-dark text-white border-secondary" placeholder="Filter payment_status (mis: unpaid/paid)" style="min-width: 220px; font-size:0.9rem;" />

            <button type="submit" class="btn btn-secondary rounded-3 px-3"><i class="bi bi-search"></i></button>
          </form>
        </div>
      </div>

      <?php if (isset($_GET['status']) && $_GET['status'] === 'success_update'): ?>
        <div class="alert alert-success border-0 rounded-3 mb-4" role="alert" style="background: rgba(34, 197, 94, 0.12) !important; color: #86efac !important;">
          <i class="bi bi-check-circle-fill me-2"></i> Status pesanan diperbarui.
        </div>
      <?php endif; ?>

      <div class="table-responsive rounded-3" style="border: 1px solid rgba(148, 163, 184, 0.15) !important; background: transparent !important;">
        <table class="table table-hover align-middle mb-0" style="background: transparent !important; color: #e5e7eb !important; min-width: 1300px;">
          <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15,23,42,.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
            <tr>
              <th class="py-3 px-3 text-center" style="width: 140px;">No. Pesanan</th>
              <th class="py-3 text-center" style="width: 220px;">patient_session_id</th>
              <th class="py-3 text-center" style="width: 120px;">tenant_id</th>
              <th class="py-3 text-center" style="width: 120px;">Subtotal</th>
              <th class="py-3 text-center" style="width: 120px;">Diskon</th>
              <th class="py-3 text-center" style="width: 120px;">Ongkir</th>
              <th class="py-3 text-center" style="width: 150px;">Grand Total</th>
              <th class="py-3 text-center" style="width: 180px;">Payment Status</th>
              <th class="py-3 text-center" style="width: 190px;">Status Pesanan</th>
              <th class="py-3 text-center" style="width: 190px;">Tanggal Dibuat</th>
              <th class="py-3 text-center" style="width: 150px;">Detail</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($orders)): ?>
              <?php foreach ($orders as $o):
                $createdLabel = !empty($o['created_at']) ? date('d M Y H:i', strtotime((string)$o['created_at'])) : '-';
              ?>
                <tr style="border-bottom: 1px solid rgba(148,163,184,.12) !important;">
                  <td class="text-center fw-semibold" style="color:#94a3b8 !important;">#<?php echo h($o['order_number'] ?? $o['id']); ?></td>
                  <td class="text-center" style="font-family:monospace;"><?php echo h($o['patient_session_id'] ?? '-'); ?></td>
                  <td class="text-center" style="font-family:monospace;"><?php echo h($o['tenant_id'] ?? '-'); ?></td>
                  <td class="text-center fw-semibold text-warning"><?php echo formatRp($o['subtotal'] ?? 0); ?></td>
                  <td class="text-center fw-semibold text-info"><?php echo formatRp($o['discount'] ?? 0); ?></td>
                  <td class="text-center fw-semibold text-primary"><?php echo formatRp($o['delivery_fee'] ?? 0); ?></td>
                  <td class="text-center fw-bold text-success"><?php echo formatRp($o['grand_total'] ?? 0); ?></td>
                  <td class="text-center"><?php echo badgePayment((string)($o['payment_status'] ?? '')); ?></td>
                  <td class="text-center">
                    <form method="POST" action="orders.php" onsubmit="return confirm('Ubah status pesanan ini?');" class="d-flex justify-content-center">
                      <input type="hidden" name="action" value="update_status" />
                      <input type="hidden" name="order_id" value="<?php echo (int)$o['id']; ?>" />
                      <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary" style="min-width: 210px;" onchange="this.form.submit()">
                        <?php foreach ($allowedOrderStatuses as $st): ?>
                          <option value="<?php echo h($st); ?>" <?php echo ((string)($o['status'] ?? '')) === $st ? 'selected' : ''; ?>><?php echo h($st); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </form>
                  </td>
                  <td class="text-center text-white-50 small"><?php echo h($createdLabel); ?></td>
                  <td class="text-center">
                    <button class="btn btn-sm btn-outline-warning rounded-2" data-bs-toggle="modal" data-bs-target="#modalOrderDetail" onclick="loadOrderDetail(<?php echo (int)$o['id']; ?>)">
                      <i class="bi bi-eye"></i> Detail
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="11" class="text-center py-5 text-white-50">Belum ada pesanan.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-center mt-4">
          <ul class="pagination mb-0 gap-1 shadow-sm">
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
              <a class="page-link rounded-2 bg-dark text-white border-secondary" href="orders.php?page=<?php echo $page-1; ?>&<?php echo $qsBase; ?>">Prev</a>
            </li>
            <?php for ($i=1;$i<=$totalPages;$i++): ?>
              <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                <a class="page-link rounded-2 <?php echo $page === $i ? 'bg-primary border-primary text-white' : 'bg-dark text-white border-secondary'; ?>" href="orders.php?page=<?php echo $i; ?>&<?php echo $qsBase; ?>">'.$i.'</a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
              <a class="page-link rounded-2 bg-dark text-white border-secondary" href="orders.php?page=<?php echo $page+1; ?>&<?php echo $qsBase; ?>">Next</a>
            </li>
          </ul>
        </div>
      <?php endif; ?>

    </div>
  </main>

  <div class="modal fade" id="modalOrderDetail" tabindex="-1" aria-labelledby="modalOrderDetailLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
      <div class="modal-content" style="background: rgba(15, 23, 42, 0.93) !important; border: 1px solid rgba(148, 163, 184, 0.2); color:#e5e7eb; border-radius: 16px;">
        <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
          <h5 class="modal-title fw-bold" id="modalOrderDetailLabel">Detail Pesanan</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="orderDetailBody" style="min-height:240px;">
          <div class="text-center py-5 text-white-50">Memuat detail...</div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function loadOrderDetail(orderId) {
      const body = document.getElementById('orderDetailBody');
      if (body) body.innerHTML = '<div class="text-center py-5 text-white-50">Memuat detail...</div>';
      fetch('orders.php?action=detail&id=' + encodeURIComponent(orderId))
        .then(r => r.text())
        .then(html => { if (body) body.innerHTML = html; })
        .catch(() => { if (body) body.innerHTML = '<div class="text-center py-5 text-danger">Gagal memuat detail.</div>'; });
    }
  </script>
</body>
</html>


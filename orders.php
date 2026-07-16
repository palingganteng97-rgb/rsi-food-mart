<?php
// orders.php - Manajemen dan Riwayat Pesanan Pelanggan
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =========================================================================
// 1. GERBANG VALIDASI AKSES (ADMIN VS PASIEN MANDIRI VIA QR)
// =========================================================================
$isAdmin   = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$isPatient = isset($_SESSION['patient_session_id']) && $_SESSION['patient_session_id'] > 0;
$patient_session_id = $isPatient ? (int)$_SESSION['patient_session_id'] : 0;

if (!$isAdmin && !$isPatient) {
    header('Location: login.php');
    exit;
}

// Konfigurasi Halaman & Variabel Dasar
$perPage = 10;
$allowedOrderStatuses = ['pending', 'accepted', 'preparing', 'ready', 'picked_up', 'delivering', 'completed', 'cancelled'];

$action        = isset($_GET['action']) ? (string)$_GET['action'] : '';
$q             = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$status        = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$paymentStatus = isset($_GET['payment_status']) ? trim((string)$_GET['payment_status']) : '';
$page          = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset        = ($page - 1) * $perPage;

// Fungsi Helper untuk Sanitasi dan Format Mata Uang
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($v) { return 'Rp ' . number_format((float)$v, 0, ',', '.'); }

function badgePayment($ps) {
    $ps = trim((string)$ps);
    $map = ['paid' => ['bg' => 'success', 'text' => 'success'], 'unpaid' => ['bg' => 'danger', 'text' => 'danger'], 'failed' => ['bg' => 'danger', 'text' => 'danger']];
    $k = $map[$ps] ?? ['bg' => 'secondary', 'text' => 'secondary'];
    $label = $ps !== '' ? $ps : '-';
    return '<span class="badge bg-' . $k['bg'] . ' bg-opacity-25 text-' . $k['text'] . ' border border-' . $k['text'] . ' border-opacity-50 px-3 py-2 rounded-pill" style="font-size:.78rem;">' . h(strtoupper($label)) . '</span>';
}

function badgeOrder($st) {
    $st = trim((string)$st);
    $map = ['pending' => ['bg' => 'warning', 'text' => 'warning'], 'accepted' => ['bg' => 'primary', 'text' => 'primary'], 'preparing' => ['bg' => 'info', 'text' => 'info'], 'ready' => ['bg' => 'success', 'text' => 'success'], 'picked_up' => ['bg' => 'success', 'text' => 'success'], 'delivering' => ['bg' => 'primary', 'text' => 'primary'], 'completed' => ['bg' => 'success', 'text' => 'success'], 'cancelled' => ['bg' => 'danger', 'text' => 'danger']];
    $k = $map[$st] ?? ['bg' => 'secondary', 'text' => 'secondary'];
    $label = $st !== '' ? $st : '-';
    return '<span class="badge bg-' . $k['bg'] . ' bg-opacity-25 text-' . $k['text'] . ' border border-' . $k['text'] . ' border-opacity-50 px-3 py-2 rounded-pill" style="font-size:.78rem;">' . h($label) . '</span>';
}

$writeBackParams = function() use ($q, $status, $paymentStatus, $page) {
    $qs = http_build_query(['q' => $q, 'status' => $status, 'payment_status' => $paymentStatus, 'page' => $page]);
    return $qs !== '' ? '?' . $qs : '';
};

// =========================================================================
// 2. PROSES UPDATE STATUS PESANAN (KHUSUS ADMIN)
// =========================================================================
if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    if (!$isAdmin) {
        header('Location: orders.php');
        exit;
    }
    
    $orderId = (int)$_POST['id'];
    $newStatus = trim((string)$_POST['status']);
    $ok = ($orderId > 0 && in_array($newStatus, $allowedOrderStatuses, true));
    
    if ($ok) {
        $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $newStatus, $orderId);
        $stmt->execute();
        
        // Catat otomatis riwayat ke tabel order_status_histories
        $user_id = (int)$_SESSION['user_id'];
        $log_notes = mysqli_real_escape_string($conn, "Status pesanan diperbarui oleh Admin.");
        $stmt_log = $conn->prepare('INSERT INTO order_status_histories (order_id, status, changed_by, notes, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt_log->bind_param('isis', $orderId, $newStatus, $user_id, $log_notes);
        $stmt_log->execute();
    }
    header('Location: orders.php' . $writeBackParams());
    exit;
}

// =========================================================================
// 3. RESPONS AJAX: RINCIAN DETAIL PESANAN DI MODAL (READ-ONLY)
// =========================================================================
if ($action === 'detail' && isset($_GET['id'])) {
    $orderId = (int)$_GET['id'];
    if ($orderId <= 0) { http_response_code(400); echo 'Invalid order id'; exit; }
    
    $stmt = $conn->prepare('SELECT id, order_number, patient_session_id, tenant_id, subtotal, discount, delivery_fee, grand_total, payment_status, status, created_at FROM orders WHERE id = ?');
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $res = $stmt->get_result();
    $order = $res ? $res->fetch_assoc() : null;
    if (!$order) { http_response_code(404); echo 'Order not found'; exit; }

    // Proteksi data: Pasien tidak boleh mengintip pesanan milik ID sesi orang lain via manipulasi URL AJAX
    if (!$isAdmin && (int)$order['patient_session_id'] !== $patient_session_id) {
        http_response_code(403); echo 'Akses ditolak'; exit;
    }

    $stmt2 = $conn->prepare('SELECT oi.id, oi.product_id, oi.qty, oi.price, oi.notes, p.name AS product_name, oi.variant AS variant_name FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ? ORDER BY oi.id ASC');
    $stmt2->bind_param('i', $orderId);
    $stmt2->execute();
    $items = []; $r2 = $stmt2->get_result();
    while ($row = $r2 ? $r2->fetch_assoc() : null) { $items[] = $row; }

    $createdAt = $order['created_at'] ?? '';
    $createdLabel = $createdAt ? date('d M Y H:i', strtotime((string)$createdAt)) : '-';
    $subtotal = $order['subtotal'] ?? 0; $discount = $order['discount'] ?? 0; $deliveryFee = $order['delivery_fee'] ?? 0; $grandTotal = $order['grand_total'] ?? 0;

    echo '<div class="p-2">';
    echo '<div class="row g-3">';
    echo '<div class="col-md-6"><div class="text-muted small">Nomor Pesanan</div><div class="fw-bold text-white font-monospace">#' . h($order['order_number'] ?? $order['id']) . '</div></div>';
    echo '<div class="col-md-6"><div class="text-muted small">Tanggal Dibuat</div><div class="fw-semibold text-white">' . h($createdLabel) . '</div></div>';
    echo '<div class="col-md-6"><div class="text-muted small">Patient Session ID</div><div class="fw-semibold text-white font-monospace">' . h($order['patient_session_id'] ?? '-') . '</div></div>';
    echo '<div class="col-md-6"><div class="text-muted small">Tenant ID</div><div class="fw-semibold text-white font-monospace">' . h($order['tenant_id'] ?? '-') . '</div></div>';
    echo '<div class="col-md-6"><div class="text-muted small">Payment Status</div><div>' . badgePayment((string)($order['payment_status'] ?? '')) . '</div></div>';
    echo '<div class="col-md-6"><div class="text-muted small">Status Pesanan</div><div>' . badgeOrder((string)($order['status'] ?? '')) . '</div></div>';
    echo '<div class="col-12"><hr class="text-white" style="opacity:.12;"></div>';
    echo '<div class="col-md-4"><div class="text-muted small">Subtotal</div><div class="fw-bold text-warning">' . money($subtotal) . '</div></div>';
    echo '<div class="col-md-4"><div class="text-muted small">Diskon</div><div class="fw-bold text-info">' . money($discount) . '</div></div>';
    echo '<div class="col-md-4"><div class="text-muted small">Ongkir</div><div class="fw-bold text-primary">' . money($deliveryFee) . '</div></div>';
    echo '<div class="col-12"><div class="text-muted small">Grand Total</div><div class="fw-bold text-success" style="font-size:1.25rem;">' . money($grandTotal) . '</div></div>';
    echo '<div class="col-12 mt-2">';
    echo '<div class="text-uppercase text-white-50 font-weight-700" style="font-size:.8rem;">Daftar menu yang dipesan</div>';
    echo '<div class="table-responsive mt-2">';
    echo '<table class="table table-hover align-middle mb-0 table-transparent">';
    echo '<thead><tr><th class="text-white py-3 px-2">Menu</th><th class="text-white py-3 px-2">Variant</th><th class="text-white text-center py-3">Qty</th><th class="text-white text-end py-3">Harga</th><th class="text-white text-end py-3">Subtotal Item</th><th class="text-white py-3">Catatan</th></tr></thead>';
    echo '<tbody>';
    if (!empty($items)) {
        foreach ($items as $it) {
            $qty = (int)($it['qty'] ?? 0); $price = (float)($it['price'] ?? 0); $subtotalItem = $qty * $price;
            $productName = (string)($it['product_name'] ?? '-');
            $variant = (string)($it['variant_name'] ?? 'Original');
            $notes = trim((string)($it['notes'] ?? ''));
            echo '<tr style="border-bottom: 1px solid rgba(148,163,184,.12) !important;">';
            echo '<td class="fw-semibold text-white">' . h($productName) . '</td>';
            echo '<td class="text-white">' . h($variant) . '</td>';
            echo '<td class="text-center fw-semibold text-white">' . h($qty) . '</td>';
            echo '<td class="text-end text-white">' . h(money($price)) . '</td>';
            echo '<td class="text-end fw-semibold text-warning">' . h(money($subtotalItem)) . '</td>';
            echo '<td class="text-white-50 small">' . ($notes !== '' ? h($notes) : '-') . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="6" class="text-center text-white-50 py-3">Tidak ada item pesanan</td></tr>';
    }
    echo '</tbody></table></div></div></div></div></div>';
    exit;
}

// =========================================================================
// =========================================================================
// 4. LOGIKA FILTER DATA DAN KEAMANAN PRIVASI (ADMIN VS PASIEN MANDIRI)
// =========================================================================
$where = [];
$binds = [];
$types = '';

// KONDISI UTAMA: Jika user adalah pasien, KUNCI agar hanya melihat pesanan miliknya sendiri
if (!$isAdmin && $isPatient) {
    $where[] = "patient_session_id = ?";
    $binds[] = $patient_session_id;
    $types  .= 'i';
}

// Filter Kotak Pencarian Teks Global (Khusus Admin)
if ($q !== '') {
    $where[] = "(order_number LIKE ? OR patient_session_id LIKE ? OR tenant_id LIKE ?)";
    $likeQuery = '%' . $q . '%';
    $binds[] = $likeQuery; 
    $binds[] = $likeQuery; 
    $binds[] = $likeQuery;
    $types  .= 'sss';
}

// Filter Status Dropdown
if ($status !== '') { 
    $where[] = "status = ?"; 
    $binds[] = $status; 
    $types  .= 's'; 
}
if ($paymentStatus !== '') { 
    $where[] = "payment_status = ?"; 
    $binds[] = $paymentStatus; 
    $types  .= 's'; 
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Hitung Pagination Total Records
$countSql = "SELECT COUNT(*) AS total FROM orders $whereClause";
$countStmt = $conn->prepare($countSql);
if (!empty($binds)) { 
    $countStmt->bind_param($types, ...$binds); 
}
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $perPage);

// Eksekusi Ambil List Data Utama Orders
$sql = "SELECT id, order_number, patient_session_id, tenant_id, subtotal, discount, delivery_fee, grand_total, payment_status, status, created_at 
        FROM orders $whereClause ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

$finalTypes = $types . 'ii';
$finalBinds = array_merge($binds, [$perPage, $offset]);

// PERBAIKAN: Memastikan variabel menggunakan tanda $ secara lengkap
$stmt->bind_param($finalTypes, ...$finalBinds);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
if ($result) {
    while ($row = $result->fetch_assoc()) { 
        $orders[] = $row; 
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin Pesanan - RSI Food &amp; Mart</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
<style>
:root{--bg:#0f172a;--text:#e5e7eb;--muted:#94a3b8;}
body{background:var(--bg) !important;color:var(--text);}
.table-transparent thead th,.table-transparent tbody td{color:#fff !important;}
.table-transparent{background:transparent !important;}
.table-transparent thead{background:rgba(15,23,42,.65) !important; border-bottom:1px solid rgba(148,163,184,.25) !important;}
.table-transparent td,.table-transparent th{background:transparent !important; border-color:rgba(148,163,184,.12) !important;}
.table-transparent tbody tr{border-bottom:1px solid rgba(148,163,184,.12) !important;}
.table-transparent *{color:#fff !important;}
.text-white*{color:#fff !important;}
#ordersTableWrap::-webkit-scrollbar {display: none}
#ordersTableWrap {-ms-overflow-style: none;scrollbar-width: none;}
</style>

</head>
<body>
<?php require __DIR__ . '/sidebar.php'; ?>
<main class="content-shift p-4">
<div class="container-fluid rounded-4 p-4" style="background: rgba(15, 23, 42, 0.55) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3 pb-3" style="border-bottom:1px solid rgba(148,163,184,.15) !important;">
<div>
<h2 class="fw-bold m-0 text-white" style="font-size:2rem;">Admin Pesanan</h2>
<div class="text-white-50 small">Kelola status pesanan dan lihat detail item</div>
</div>
</div>

<form method="GET" class="mb-3">
<div class="row g-2 align-items-end">
<div class="col-12 col-md-5">
<label class="form-label text-white-50 small mb-1">Pencarian</label>
<input type="text" name="q" value="<?php echo h($q); ?>" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" placeholder="order_number / patient_session_id / tenant_id" />
</div>
<div class="col-12 col-md-3">
<label class="form-label text-white-50 small mb-1">Status Pesanan</label>
<select name="status" class="form-select bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" style="color-scheme:dark;">
<option value="" <?php echo $status===''?'selected':''; ?>>Semua</option>
<?php foreach($allowedOrderStatuses as $st){ echo '<option value="'.h($st).'" '.($status===$st?'selected':'').'>'.h($st).'</option>'; } ?>
</select>
</div>
<div class="col-12 col-md-3">
<label class="form-label text-white-50 small mb-1">Payment Status</label>
<select name="payment_status" class="form-select bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" style="color-scheme:dark;">
<option value="" <?php echo $paymentStatus===''?'selected':''; ?>>Semua</option>
<?php foreach(['unpaid','paid','failed'] as $ps){ echo '<option value="'.h($ps).'" '.($paymentStatus===$ps?'selected':'').'>'.h($ps).'</option>'; } ?>
</select>
</div>
<div class="col-12 col-md-1 d-grid">
<button class="btn btn-success rounded-3 py-2 fw-semibold" type="submit">Cari</button>
</div>
</div>
</form>

<!-- Tambahkan properti style cursor, user-select, dan touch di elemen pembungkus -->
<div class="table-responsive" id="ordersTableWrap" style="border-radius:16px; cursor: grab; user-select: none; -webkit-overflow-scrolling: touch;">
<table class="table table-hover align-middle mb-0 table-transparent" style="min-width:1200px;">
<thead class="text-uppercase" style="font-size:.8rem; font-weight:800;">
<tr>
<th class="py-3 px-2 text-center">ID</th>
<th class="py-3">Order</th>
<th class="py-3">Patient Session</th>
<th class="py-3">Tenant</th>
<th class="py-3 text-end">Total</th>
<th class="py-3 text-center">Payment</th> <!-- Ditambahkan text-center agar sejajar dengan isi -->
<th class="py-3 text-center">Status</th> <!-- Ditambahkan text-center agar sejajar dengan isi -->
<th class="py-3 text-center">Aksi</th>
</tr>
</thead>
<tbody>
<?php if(empty($orders)): ?>
<tr><td colspan="8" class="text-center text-white-50 py-5">Data pesanan tidak ditemukan</td></tr>
<?php else: foreach($orders as $o): ?>
<tr style="background: transparent !important;">
<td class="text-center fw-semibold text-white" style="font-family:monospace;"><?php echo h($o['id']); ?></td>
<td class="text-white">
<div class="fw-semibold" style="font-family:monospace;">#<?php echo h($o['order_number']); ?></div>
<div class="text-white-50 small"><?php echo h(date('d M Y H:i',strtotime((string)($o['created_at']??'')))); ?></div>
</td>
<td class="text-white-50" style="font-family:monospace;"><?php echo h($o['patient_session_id']); ?></td>
<td class="text-white-50" style="font-family:monospace;"><?php echo h($o['tenant_id']); ?></td>
<td class="text-end fw-bold text-success"><?php echo h(money($o['grand_total'])); ?></td>
<td class="text-center">{PAY}</td>
<td class="text-center">{ORD}</td>
<td class="text-center">
<!-- Tambahkan pointer-events: auto agar input/tombol di dalam tabel tidak macet saat diklik -->
<div class="d-flex flex-column flex-md-row gap-2 justify-content-center align-items-center" style="pointer-events: auto;">
<button type="button" class="btn btn-sm btn-outline-light rounded-3" data-bs-toggle="modal" data-bs-target="#modalOrderDetail" data-order-id="<?php echo h($o['id']); ?>"><i class="bi bi-eye"></i></button>
<form method="POST" action="orders.php?<?php echo h(http_build_query(['q'=>$q,'status'=>$status,'payment_status'=>$paymentStatus,'page'=>$page,'action'=>'update_status'])); ?>" class="d-flex gap-1 align-items-center">
<input type="hidden" name="id" value="<?php echo h($o['id']); ?>" />
<select name="status" class="form-select form-select-sm bg-dark bg-opacity-25 text-white border-secondary border-opacity-50" style="min-width:160px; color-scheme:dark;">
<?php foreach($allowedOrderStatuses as $st){ $sel=((string)$o['status']===$st)?'selected':''; echo '<option value="'.h($st).'" '.$sel.'>'.h($st).'</option>'; } ?>
</select>
<button type="submit" class="btn btn-sm btn-success rounded-3 px-3"><i class="bi bi-check2-circle"></i></button>
</form>
</div>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>

<?php
// Base query string untuk menjaga parameter filter tetap terbawa saat paging
$qsBase = http_build_query(['q' => $q, 'status' => $status, 'payment_status' => $paymentStatus, 'page' => $page]);
$qsBase = $qsBase !== '' ? ('?' . $qsBase) : '';


$prev = $page > 1 ? $page - 1 : 1;
$next = $page < $totalPages ? $page + 1 : $totalPages;

if (!function_exists('pageLink')) {
    function pageLink(int $pageNum, string $qsBase = ''): string {
        $pageNum = max(1, $pageNum);
        $sep = ($qsBase !== '' && str_contains($qsBase, '?')) ? '&' : (strpos($qsBase, '?') !== false ? '&' : '?');
        // $qsBase biasanya sudah berupa "?q=...&..." atau kosong
        return ($qsBase !== '')
            ? rtrim($qsBase, '?&') . (str_contains($qsBase, '?') ? '&' : '?') . 'page=' . $pageNum
            : '?page=' . $pageNum;
    }
}
?>
<nav class="mt-4" aria-label="Pagination">
<ul class="pagination justify-content-center mb-0" style="--bs-pagination-color: var(--text); --bs-pagination-bg: transparent; --bs-pagination-border-color: rgba(148,163,184,.2);">
<li class="page-item <?php echo $page<=1?'disabled':''; ?>"><a class="page-link" href="<?php echo $page<=1?'#':pageLink($prev,$qsBase); ?>" tabindex="<?php echo $page<=1?-1:0; ?>">&laquo;</a></li>

<?php
$start=max(1,$page-2);$end=min($totalPages,$page+2);
for($p=$start;$p<=$end;$p++){
  echo '<li class="page-item '.($p===$page?'active':'').'"><a class="page-link" style="background:'.($p===$page?'rgba(34,197,94,.25)':'transparent').' !important; border-color:'.($p===$page?'rgba(34,197,94,.55)':'rgba(148,163,184,.2)').'" href="'.h(pageLink($p,$qsBase)).'">'.h($p).'</a></li>';

}
?>
<li class="page-item <?php echo $page>=$totalPages?'disabled':''; ?>"><a class="page-link" href="<?php echo $page>=$totalPages?'#':pageLink($next,$qsBase); ?>" tabindex="<?php echo $page>=$totalPages?-1:0; ?>">&raquo;</a></li>
</ul>
</nav>
</div>
</main>

<div class="modal fade" id="modalOrderDetail" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width:1140px;">
<div class="modal-content border-0 rounded-4 text-white shadow-lg" style="background:#0b1223; border:1px solid rgba(148,163,184,.15) !important;">
<div class="modal-header border-secondary border-opacity-25">
<div class="d-flex align-items-center gap-2">
<i class="bi bi-receipt-cutoff fs-4" style="color:#22c55e;"></i>
<h5 class="modal-title fw-bold m-0">Detail Pesanan</h5>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body"><div id="orderDetailBody" class="text-white"></div></div>
</div>
</div>
</div>

<script>
(function(){
  const modalEl=document.getElementById('modalOrderDetail');
  if(!modalEl) return;
  modalEl.addEventListener('show.bs.modal',function(ev){
    const btn=ev.relatedTarget;
    if(!btn) return;
    const orderId=btn.getAttribute('data-order-id');
    const body=document.getElementById('orderDetailBody');
    if(body) body.innerHTML='<div class="text-white-50 py-4 text-center">Memuat detail...</div>';
    if(!orderId) return;
    fetch('orders.php?action=detail&id='+encodeURIComponent(orderId))
      .then(r=>r.text())
      .then(html=>{ if(body) body.innerHTML=html; })
      .catch(()=>{ if(body) body.innerHTML='<div class="text-danger py-4 text-center">Gagal memuat detail</div>'; });
  });
})();

document.addEventListener('DOMContentLoaded', function () {
    const slider = document.getElementById('ordersTableWrap');
    let isDown = false;
    let startX;
    let scrollLeft;

    if (!slider) return;

    slider.addEventListener('mousedown', (e) => {
        // Jangan aktifkan drag jika pengguna sedang berinteraksi dengan tombol, form, atau select status
        if (e.target.closest('button') || e.target.closest('select') || e.target.closest('input')) {
            return;
        }
        isDown = true;
        slider.style.cursor = 'grabbing';
        startX = e.pageX - slider.offsetLeft;
        scrollLeft = slider.scrollLeft;
    });

    slider.addEventListener('mouseleave', () => {
        isDown = false;
        slider.style.cursor = 'grab';
    });

    slider.addEventListener('mouseup', () => {
        isDown = false;
        slider.style.cursor = 'grab';
    });

    slider.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - slider.offsetLeft;
        const walk = (x - startX) * 1.5; // Sesuaikan sensitivitas pergeseran (1.5)
        slider.scrollLeft = scrollLeft - walk;
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

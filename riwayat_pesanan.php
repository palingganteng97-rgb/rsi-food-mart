<?php
/**
 * riwayat_pesanan.php
 * 
 * Halaman riwayat pesanan pasien (Patient Order History).
 * - Tanpa parameter ?id= : Menampilkan daftar pesanan (List View)
 * - Dengan parameter ?id= : Menampilkan detail pesanan (Detail View) via include
 * 
 * Refactored: Detail View dipisahkan ke riwayat_pesanan_detail.php
 */

include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =========================================================================
// AUTH: Hanya untuk pasien yang sudah login
// =========================================================================
$isPatient = isset($_SESSION['patient_session_id']) && $_SESSION['patient_session_id'] > 0;
$patient_session_id = $isPatient ? (int)$_SESSION['patient_session_id'] : 0;

if (!$isPatient) {
    header('Location: login.php');
    exit;
}

// =========================================================================
// HELPER FUNCTIONS
// =========================================================================
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($v) { return 'Rp ' . number_format((float)$v, 0, ',', '.'); }

function statusBadge($status) {
    $status = trim((string)$status);
    switch (strtoupper($status)) {
        case 'PAID': case 'SUCCESS':
            return 'bg-success bg-opacity-25 text-success border-success border-opacity-50';
        case 'UNPAID': case 'PENDING':
            return 'bg-warning bg-opacity-25 text-warning border-warning border-opacity-50';
        case 'FAILED':
            return 'bg-danger bg-opacity-25 text-danger border-danger border-opacity-50';
        default:
            return 'bg-secondary bg-opacity-25 text-secondary border-secondary border-opacity-50';
    }
}

function orderStatusBadge($status) {
    $status = trim((string)$status);
    switch (strtolower($status)) {
        case 'pending':
            return 'bg-warning bg-opacity-25 text-warning border-warning border-opacity-50';
        case 'accepted': case 'preparing':
            return 'bg-info bg-opacity-25 text-info border-info border-opacity-50';
        case 'ready': case 'picked_up': case 'delivering':
            return 'bg-primary bg-opacity-25 text-primary border-primary border-opacity-50';
        case 'completed':
            return 'bg-success bg-opacity-25 text-success border-success border-opacity-50';
        case 'cancelled':
            return 'bg-danger bg-opacity-25 text-danger border-danger border-opacity-50';
        default:
            return 'bg-secondary bg-opacity-25 text-secondary border-secondary border-opacity-50';
    }
}

function deliveryStatusBadge($status) {
    $status = trim((string)$status);
    $greenList  = ['Terkirim', 'Diambil'];
    $redList    = ['Gagal Kirim', 'Dibatalkan'];
    $yellowList = ['Pending', 'Dalam Perjalanan', 'Sedang Diantar'];
    $blueList   = ['Diproses', 'Dikembalikan'];

    if (in_array($status, $greenList)) {
        return 'bg-success bg-opacity-25 text-success border-success border-opacity-50';
    } elseif (in_array($status, $redList)) {
        return 'bg-danger bg-opacity-25 text-danger border-danger border-opacity-50';
    } elseif (in_array($status, $yellowList)) {
        return 'bg-warning bg-opacity-25 text-warning border-warning border-opacity-50';
    } elseif (in_array($status, $blueList)) {
        return 'bg-info bg-opacity-25 text-info border-info border-opacity-50';
    }
    return 'bg-secondary bg-opacity-25 text-secondary border-secondary border-opacity-50';
}

// =========================================================================
// HITUNG NOTIFIKASI BELUM TERBACA (untuk sidebar)
// =========================================================================
$unreadCount = 0;
$countQuery = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_type = 'patient' AND user_reference = ? AND is_read = 0");
if ($countQuery) {
    $countQuery->bind_param("i", $patient_session_id);
    $countQuery->execute();
    $resCount = $countQuery->get_result()->fetch_assoc();
    $unreadCount = (int)($resCount['cnt'] ?? 0);
    $countQuery->close();
}

// =========================================================================
// HANDLER: CANCEL ORDER (POST)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order']) && isset($_GET['id'])) {
    $orderId = (int)$_GET['id'];
    $alasan  = trim((string)($_POST['alasan_cancel'] ?? ''));

    if ($orderId > 0 && $alasan !== '') {
        // Verifikasi bahwa order milik pasien ini dan statusnya masih pending
        $stmt = $conn->prepare("SELECT id, status, order_number FROM orders WHERE id = ? AND patient_session_id = ? LIMIT 1");
        $stmt->bind_param("ii", $orderId, $patient_session_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $orderData = $res->fetch_assoc();
        $stmt->close();

        if ($orderData && strtolower($orderData['status']) === 'pending') {
            $stmtUpd = $conn->prepare("UPDATE orders SET status = 'cancelled', cancel_reason = ? WHERE id = ?");
            $stmtUpd->bind_param("si", $alasan, $orderId);
            $stmtUpd->execute();
            $stmtUpd->close();

            // Catat ke order_status_histories
            $userId = 0; // dibatalkan oleh pasien
            $logNotes = mysqli_real_escape_string($conn, "Pesanan dibatalkan oleh pasien. Alasan: " . $alasan);
            $stmtLog = $conn->prepare("INSERT INTO order_status_histories (order_id, status, changed_by, notes, created_at) VALUES (?, 'cancelled', ?, ?, NOW())");
            $stmtLog->bind_param("iis", $orderId, $userId, $logNotes);
            $stmtLog->execute();
            $stmtLog->close();

            header("Location: riwayat_pesanan.php?id=" . $orderId . "&cancel_success=1&reason=" . urlencode($alasan));
            exit;
        } else {
            header("Location: riwayat_pesanan.php?id=" . $orderId . "&error=cannot_cancel");
            exit;
        }
    } else {
        header("Location: riwayat_pesanan.php?id=" . (int)$_GET['id']);
        exit;
    }
}

// =========================================================================
// HANDLER: NOTIFICATION / STATUS ALERT PARAMS
// =========================================================================
$cancelSuccess = isset($_GET['cancel_success']) && $_GET['cancel_success'] === '1';
$cancelReason  = isset($_GET['reason']) ? urldecode($_GET['reason']) : '';

// =========================================================================
// DETAIL VIEW (?id=...)
// =========================================================================
if (isset($_GET['id'])) {
    $orderId = (int)$_GET['id'];

    if ($orderId <= 0) {
        http_response_code(400);
        echo 'Invalid order id';
        exit;
    }

    // --- Query Order ---
    $stmt = $conn->prepare("SELECT id, order_number, patient_session_id, tenant_id, subtotal, discount, delivery_fee, grand_total, payment_status, status, created_at, cancel_reason FROM orders WHERE id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $res = $stmt->get_result();
    $order = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$order) {
        http_response_code(404);
        echo 'Order not found';
        exit;
    }

    // Verifikasi kepemilikan
    if ((int)$order['patient_session_id'] !== $patient_session_id) {
        http_response_code(403);
        echo 'Akses ditolak';
        exit;
    }

    // --- Query Items ---
    $stmt2 = $conn->prepare("SELECT oi.id, oi.product_id, oi.qty, oi.price, oi.notes, p.name AS product_name FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ? ORDER BY oi.id ASC");
    $stmt2->bind_param("i", $orderId);
    $stmt2->execute();
    $items = [];
    $r2 = $stmt2->get_result();
    while ($row = $r2 ? $r2->fetch_assoc() : null) {
        $items[] = $row;
    }
    $stmt2->close();

    // --- Query Payment ---
    $stmt3 = $conn->prepare("SELECT p.status AS payment_status, p.amount, pm.name AS method_name FROM payments p LEFT JOIN payment_methods pm ON p.payment_method_id = pm.id WHERE p.order_id = ? ORDER BY p.id DESC LIMIT 1");
    $stmt3->bind_param("i", $orderId);
    $stmt3->execute();
    $r3 = $stmt3->get_result();
    $paymentData = $r3 ? $r3->fetch_assoc() : null;
    $stmt3->close();

    $paymentStatus = $paymentData['payment_status'] ?? ($order['payment_status'] ?? 'UNPAID');
    $payment_method_name = $paymentData['method_name'] ?? 'Tidak diketahui';

    // --- Query Delivery ---
    $stmt4 = $conn->prepare("SELECT d.status AS delivery_status, d.delivery_time, d.proof_photo, c.name AS courier_name, c.phone AS courier_phone FROM deliveries d LEFT JOIN couriers c ON d.courier_id = c.id WHERE d.order_id = ? ORDER BY d.id DESC LIMIT 1");
    $stmt4->bind_param("i", $orderId);
    $stmt4->execute();
    $r4 = $stmt4->get_result();
    $deliveryData = $r4 ? $r4->fetch_assoc() : null;
    $stmt4->close();

    $deliveryStatus     = $deliveryData['delivery_status'] ?? '';
    $deliveryTime       = $deliveryData['delivery_time'] ?? '';
    $deliveryProofPhoto = $deliveryData['proof_photo'] ?? '';
    $courierData = [];
    if (!empty($deliveryData['courier_name'])) {
        $courierData['name']  = $deliveryData['courier_name'];
        $courierData['phone'] = $deliveryData['courier_phone'] ?? '-';
    }

    // --- Derived Variables ---
    $orderStatus = $order['status'] ?? '';
    $totalQty = 0;
    foreach ($items as $it) {
        $totalQty += intval($it['qty'] ?? 0);
    }
    $isCancelled = (strtolower($orderStatus) === 'cancelled');

    // --- Include Detail View & Stop ---
    include 'riwayat_pesanan_detail.php';
    exit; // Pastikan tidak lanjut ke List View
}

// =========================================================================
// LIST VIEW (tidak ada ?id=)
// =========================================================================

// --- Konfigurasi Pagination & Search ---
$perPage = 10;
$q       = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$status  = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset  = ($page - 1) * $perPage;

// --- Build Query ---
$where   = [];
$binds   = [];
$types   = '';

// Filter by patient session
$where[] = "patient_session_id = ?";
$binds[] = $patient_session_id;
$types  .= 'i';

// Search
if ($q !== '') {
    $where[] = "(order_number LIKE ?)";
    $likeQ = '%' . $q . '%';
    $binds[] = $likeQ;
    $types  .= 's';
}

// Filter status
if ($status !== '') {
    $where[] = "status = ?";
    $binds[] = $status;
    $types  .= 's';
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// --- Count total ---
$countSql = "SELECT COUNT(*) AS total FROM orders $whereClause";
$countStmt = $conn->prepare($countSql);
if (!empty($binds)) {
    $countStmt->bind_param($types, ...$binds);
}
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = max(1, ceil($totalRows / $perPage));
$countStmt->close();

// --- Fetch orders ---
$sql = "SELECT id, order_number, grand_total, payment_status, status, created_at 
        FROM orders $whereClause ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$finalTypes = $types . 'ii';
$finalBinds = array_merge($binds, [$perPage, $offset]);
$stmt->bind_param($finalTypes, ...$finalBinds);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - RSI Food &amp; Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
    <style>
        :root { --bg:#0f172a; --text:#e5e7eb; --muted:#94a3b8; --green:#22c55e; }
        body { background:var(--bg) !important; color:var(--text); }
        .card-order { background: rgba(30, 41, 59, 0.35); border: 1px solid rgba(148, 163, 184, 0.15); border-radius: 16px; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); transition: transform .15s ease, border-color .15s ease; }
        .card-order:hover { transform: translateY(-2px); border-color: rgba(34,197,94,.35); }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        .search-box { background: rgba(2,6,23,.35); border:1px solid rgba(148,163,184,.25); border-radius: 18px; }
        @media (min-width: 992px) { main.content-shift { margin-left: 280px; } }
    </style>
</head>
<body>

<?php include "sidebar_pasients.php"; ?>

<main class="page-body content-shift">
    <div class="container py-3">

        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h4 class="fw-bold m-0">Riwayat Pesanan</h4>
                <div class="text-white-50" style="font-size:.9rem;">Daftar pesanan yang telah Anda buat</div>
            </div>
        </div>

        <!-- Notification Alert -->
        <?php if (isset($_GET['status']) && $_GET['status'] === 'review_success'): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert" style="background: rgba(34, 197, 94, 0.2); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3) !important;">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <span class="fw-semibold">Ulasan berhasil dikirim. Terima kasih atas ulasan Anda!</span>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php elseif (isset($_GET['status']) && $_GET['status'] === 'review_failed'): ?>
        <div class="alert alert-warning alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert" style="background: rgba(255, 193, 7, 0.2); color: #ffc107; border: 1px solid rgba(255, 193, 7, 0.3) !important;">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <span class="fw-semibold">Ulasan gagal dikirim. Silakan coba lagi.</span>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Search & Filter -->
        <form method="GET" class="search-box p-3 mb-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0 text-white-50">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="q" value="<?php echo h($q); ?>" class="form-control bg-transparent text-white border-0" placeholder="Cari nomor pesanan..." />
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <select name="status" class="form-select bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3">
                        <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="accepted" <?php echo $status === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                        <option value="preparing" <?php echo $status === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                        <option value="ready" <?php echo $status === 'ready' ? 'selected' : ''; ?>>Ready</option>
                        <option value="picked_up" <?php echo $status === 'picked_up' ? 'selected' : ''; ?>>Picked Up</option>
                        <option value="delivering" <?php echo $status === 'delivering' ? 'selected' : ''; ?>>Delivering</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-12 col-md-3 d-grid">
                    <button class="btn btn-success rounded-3 fw-semibold" type="submit">
                        <i class="bi bi-filter me-1"></i> Filter
                    </button>
                </div>
            </div>
        </form>

        <!-- Order Cards -->
        <?php if (empty($orders)): ?>
        <div class="text-center py-5">
            <i class="bi bi-inboxes d-block mb-3" style="font-size: 3rem; color: #94a3b8; opacity: 0.8;"></i>
            <h5 class="fw-semibold text-white mb-1">Belum Ada Pesanan</h5>
            <p class="text-white-50">Anda belum memiliki riwayat pesanan. Silakan pesan menu terlebih dahulu.</p>
            <a href="home.php" class="btn btn-warning rounded-pill px-4 fw-semibold mt-2">
                <i class="bi bi-shop me-2"></i>Pesan Sekarang
            </a>
        </div>
        <?php else: ?>
            <?php foreach ($orders as $o): 
                $os = strtolower($o['status'] ?? '');
                $ps = strtoupper($o['payment_status'] ?? '');
            ?>
            <a href="riwayat_pesanan.php?id=<?php echo (int)$o['id']; ?>" class="text-decoration-none">
                <div class="card-order p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-bold text-white font-monospace mb-1">
                                #<?php echo h($o['order_number'] ?? $o['id']); ?>
                            </div>
                            <div class="text-white-50 small">
                                <i class="bi bi-clock me-1"></i>
                                <?php echo h(date('d M Y H:i', strtotime($o['created_at'] ?? 'now'))); ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-success fs-6"><?php echo money($o['grand_total'] ?? 0); ?></div>
                            <div class="mt-1">
                                <span class="badge <?php echo statusBadge($ps); ?> px-2 py-1 rounded-pill" style="font-size:0.7rem;">
                                    <?php echo h($ps ?: '-'); ?>
                                </span>
                                <span class="badge <?php echo orderStatusBadge($os); ?> px-2 py-1 rounded-pill" style="font-size:0.7rem;">
                                    <?php echo h(ucfirst($os) ?: '-'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 text-end">
                        <span class="text-white-50 small">
                            Detail <i class="bi bi-chevron-right"></i>
                        </span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="mt-4" aria-label="Pagination">
                <ul class="pagination justify-content-center mb-0">
                    <?php
                    $qsBase = http_build_query(['q' => $q, 'status' => $status]);
                    $qsBase = $qsBase !== '' ? '?' . $qsBase : '';
                    $pageLink = function($p) use ($qsBase) {
                        $sep = ($qsBase !== '') ? (str_contains($qsBase, '?') ? '&' : '?') : '?';
                        return ($qsBase !== '')
                            ? rtrim($qsBase, '?&') . (str_contains($qsBase, '?') ? '&' : '?') . 'page=' . $p
                            : '?page=' . $p;
                    };
                    $prev = max(1, $page - 1);
                    $next = min($totalPages, $page + 1);
                    ?>
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $page <= 1 ? '#' : $pageLink($prev); ?>">&laquo;</a>
                    </li>
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    for ($p = $start; $p <= $end; $p++):
                    ?>
                    <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo $pageLink($p); ?>" 
                           style="background:<?php echo $p === $page ? 'rgba(34,197,94,.25)' : 'transparent'; ?> !important;
                                  border-color:<?php echo $p === $page ? 'rgba(34,197,94,.55)' : 'rgba(148,163,184,.2)'; ?>">
                            <?php echo $p; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $page >= $totalPages ? '#' : $pageLink($next); ?>">&raquo;</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</main>

<?php include "bottom_nav.php"; ?>

<?php include 'modal-logout.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


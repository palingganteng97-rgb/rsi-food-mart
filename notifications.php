<?php
// notifications.php

include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detect user type
$isAdmin = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$isPatient = isset($_SESSION['patient_session_id']) && !empty($_SESSION['patient_session_id']);

if (!$isAdmin && !$isPatient) {
    header('Location: login.php');
    exit;
}

// Determine user type and reference
$userType = $isAdmin ? 'admin' : 'patient';
$userReference = $isAdmin ? (int)$_SESSION['user_id'] : (int)$_SESSION['patient_session_id'];
$userName = $isAdmin ? ($_SESSION['name'] ?? 'Admin') : ($_SESSION['patient_name'] ?? 'Pasien');

// Handle AJAX mark as read for individual notification
if (isset($_GET['action']) && $_GET['action'] === 'mark_read' && isset($_GET['id'])) {
    $nid = (int)$_GET['id'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_type = ? AND user_reference = ?");
    $stmt->bind_param("isi", $nid, $userType, $userReference);
    $stmt->execute();
    header("Location: notifications.php");
    exit;
}

// Handle mark all as read
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_type = ? AND user_reference = ? AND is_read = 0");
    $stmt->bind_param("si", $userType, $userReference);
    $stmt->execute();
    header("Location: notifications.php");
    exit;
}

// Pagination
$perPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Count total
$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_type = ? AND user_reference = ?");
$countStmt->bind_param("si", $userType, $userReference);
$countStmt->execute();
$totalResult = $countStmt->get_result();
$totalNotifications = 0;
if ($row = $totalResult->fetch_assoc()) {
    $totalNotifications = (int)$row['total'];
}
$totalPages = max(1, ceil($totalNotifications / $perPage));

// Get notifications
$stmt = $conn->prepare("
    SELECT id, user_type, user_reference, title, message, link, is_read, created_at 
    FROM notifications 
    WHERE user_type = ? AND user_reference = ? 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bind_param("siii", $userType, $userReference, $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $row['id'] = (int)$row['id'];
    $row['is_read'] = (int)$row['is_read'];
    $notifications[] = $row;
}

// Count unread
$unreadStmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_type = ? AND user_reference = ? AND is_read = 0");
$unreadStmt->bind_param("si", $userType, $userReference);
$unreadStmt->execute();
$unreadResult = $unreadStmt->get_result();
$unreadCount = 0;
if ($row = $unreadResult->fetch_assoc()) {
    $unreadCount = (int)$row['total'];
}

function timeAgo($datetime): string {
    if (empty($datetime)) return '-';
    $timestamp = strtotime($datetime);
    $now = time();
    $diff = $now - $timestamp;
    if ($diff < 0) return 'baru saja';
    if ($diff < 60) return 'baru saja';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    if ($diff < 604800) return floor($diff / 86400) . ' hari lalu';
    if ($diff < 2592000) return floor($diff / 604800) . ' minggu lalu';
    if ($diff < 31536000) return floor($diff / 2592000) . ' bulan lalu';
    return floor($diff / 31536000) . ' tahun lalu';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Notifikasi - RSI Food &amp; Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
<style>
    :root { --bg:#0f172a; --text:#e5e7eb; --muted:#94a3b8; --green:#22c55e; }
    *, *::before, *::after { box-sizing: border-box; }
    body { background:var(--bg)!important; color:var(--text); overflow-x: hidden; }
    @media (min-width:992px) {main.content-shift { margin-left:280px; }}
    .notification-card { background:rgba(15,23,42,.55); border:1px solid rgba(148,163,184,.15); border-radius:16px; transition:all .15s ease; }
    .notification-card:hover { background:rgba(15,23,42,.75); border-color:rgba(34,197,94,.35); }
    .notification-card.unread { background:rgba(34,197,94,.04); border-left:4px solid var(--green); }
    .notification-card.unread .notif-title { color:#fff; font-weight:600; }
    .notification-card.read .notif-title { color:var(--muted); }
    .time-badge { font-size:.75rem; color:#64748b; }
    .pagination-custom .page-link { background:transparent; color:var(--text); border-color:rgba(148,163,184,.2); }
    .pagination-custom .page-link:hover { background:rgba(148,163,184,.1); color:#fff; }
    .pagination-custom .page-item.active .page-link { background:rgba(34,197,94,.25); border-color:rgba(34,197,94,.55); color:#fff; }
    .pagination-custom .page-item.disabled .page-link { opacity:.4; }
    .container-fluid { padding-left: max(12px, env(safe-area-inset-left)); padding-right: max(12px, env(safe-area-inset-right)); }
    @media (max-width: 575.98px) {
        main.content-shift.p-4 { padding: 1rem 0.5rem !important; }
    }
</style>
</head>
<body>
    <?php 
    if ($isAdmin) {
        require __DIR__ . '/sidebar.php';
    } else {
        require __DIR__ . '/sidebar_pasients.php';
    }
    ?>

    <main class="content-shift p-4">
        <div class="container-fluid" style="max-width: 900px;">
            <!-- Header -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148,163,184,0.15);">
                <div>
                    <h2 class="fw-bold m-0 text-white d-flex align-items-center gap-2">
                        <i class="bi bi-bell-fill text-success"></i> Notifikasi
                    </h2>
                    <?php if ($unreadCount > 0): ?>
                        <div class="text-white-50 small mt-1">
                            <span class="badge bg-success rounded-pill"><?= $unreadCount ?></span> belum dibaca
                        </div>
                    <?php else: ?>
                        <div class="text-white-50 small mt-1">Semua notifikasi sudah dibaca</div>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($unreadCount > 0): ?>
                        <a href="notifications.php?action=mark_all_read" class="btn btn-outline-success rounded-3 fw-medium d-flex align-items-center gap-2" onclick="return confirm('Tandai semua notifikasi sudah dibaca?')">
                            <i class="bi bi-check2-all"></i> Baca Semua
                        </a>
                    <?php endif; ?>
                    <button class="btn btn-outline-secondary rounded-3 fw-medium d-flex align-items-center gap-2" onclick="window.location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Segarkan
                    </button>
                </div>
            </div>

            <!-- Notification List -->
            <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 80px; height: 80px; background: rgba(148,163,184,0.1);">
                        <i class="bi bi-bell-slash text-white-50" style="font-size: 2rem;"></i>
                    </div>
                    <h5 class="fw-semibold text-white mb-1">Belum Ada Notifikasi</h5>
                    <p class="text-white-50 small">Notifikasi akan muncul di sini ketika ada aktivitas baru.</p>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($notifications as $n): ?>
                        <a href="<?= !empty($n['link']) ? htmlspecialchars($n['link']) : 'notifications.php?action=mark_read&id=' . $n['id'] ?>" 
                           class="notification-card <?= $n['is_read'] ? 'read' : 'unread' ?> d-flex align-items-start gap-3 p-3 text-decoration-none"
                           <?php if (!empty($n['link'])): ?>
                           onclick="markNotifRead(<?= $n['id'] ?>)"
                           <?php endif; ?>>
                            
                            <!-- Icon -->
                            <div class="flex-shrink-0 mt-1">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 42px; height: 42px; background: <?= $n['is_read'] ? 'rgba(148,163,184,0.12)' : 'rgba(34,197,94,0.2)' ?>;">
                                    <i class="bi <?= $n['is_read'] ? 'bi-bell text-white-50' : 'bi-bell-fill text-success' ?>" style="font-size: 1.1rem;"></i>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="flex-grow-1 min-width-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <strong class="notif-title text-truncate d-block" style="font-size: 0.9rem;">
                                        <?= htmlspecialchars($n['title']) ?>
                                    </strong>
                                    <span class="time-badge flex-shrink-0 ms-2"><?= timeAgo($n['created_at']) ?></span>
                                </div>
                                <p class="mb-0 mt-1" style="font-size: 0.82rem; color: #94a3b8; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?= htmlspecialchars($n['message']) ?>
                                </p>
                                <?php if (!empty($n['link'])): ?>
                                    <small class="text-success mt-1 d-inline-block" style="font-size: 0.75rem;">
                                        <i class="bi bi-box-arrow-up-right me-1"></i> Buka halaman terkait
                                    </small>
                                <?php endif; ?>
                            </div>

                            <!-- Unread indicator dot -->
                            <?php if (!$n['is_read']): ?>
                                <div class="flex-shrink-0 mt-2">
                                    <div class="rounded-circle" style="width: 8px; height: 8px; background: #22c55e;"></div>
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center pagination-custom mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>" tabindex="<?= $page <= 1 ? -1 : 0 ?>">&laquo;</a>
                        </li>
                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        for ($p = $start; $p <= $end; $p++): 
                        ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>" tabindex="<?= $page >= $totalPages ? -1 : 0 ?>">&raquo;</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Mark notification as read via AJAX when clicking notification with link
        function markNotifRead(id) {
            if (!id) return;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'mark_notification_read.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('id=' + id);
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


<?php
// get_notifications.php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detect user type from session
$userType = '';
$userReference = 0;

// Check for admin user first
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $userType = 'admin';
    $userReference = (int)$_SESSION['user_id'];
}
// Check for patient session
elseif (isset($_SESSION['patient_session_id']) && !empty($_SESSION['patient_session_id'])) {
    $userType = 'patient';
    $userReference = (int)$_SESSION['patient_session_id'];
}

// DEBUG: Log session info
error_log("[NOTIF_DEBUG] get_notifications.php called. Session: user_id=" . ($_SESSION['user_id'] ?? 'not set') . ", patient_session_id=" . ($_SESSION['patient_session_id'] ?? 'not set'));
error_log("[NOTIF_DEBUG] Detected: userType=$userType, userReference=$userReference");

// Allow override via GET parameter
if (isset($_GET['type']) && in_array($_GET['type'], ['admin', 'patient'])) {
    $userType = $_GET['type'];
}

// Validate we have a user
if (empty($userType) || $userReference <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated',
        'notifications' => [],
        'unread_count' => 0,
        'total_unread' => 0
    ]);
    exit;
}

$sinceId = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;

// Build query
if ($sinceId > 0) {
    // Only get new notifications since last check
    $stmt = $conn->prepare("
        SELECT id, user_type, user_reference, title, message, link, is_read, created_at 
        FROM notifications 
        WHERE user_type = ? AND user_reference = ? AND id > ? 
        ORDER BY id DESC 
        LIMIT 20
    ");
    $stmt->bind_param("sii", $userType, $userReference, $sinceId);
} else {
    // Get latest unread notifications
    $stmt = $conn->prepare("
        SELECT id, user_type, user_reference, title, message, link, is_read, created_at 
        FROM notifications 
        WHERE user_type = ? AND user_reference = ? 
        ORDER BY id DESC 
        LIMIT 10
    ");
    $stmt->bind_param("si", $userType, $userReference);
}

$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
$unreadCount = 0;
$maxId = 0;

while ($row = $result->fetch_assoc()) {
    $row['id'] = (int)$row['id'];
    $row['is_read'] = (int)$row['is_read'];
    $row['user_reference'] = (int)$row['user_reference'];
    
    // Format created_at as relative time for display
    $row['time_ago'] = timeAgo($row['created_at']);
    $row['created_at_raw'] = $row['created_at'];
    
    if (!$row['is_read']) {
        $unreadCount++;
    }
    
    if ($row['id'] > $maxId) {
        $maxId = $row['id'];
    }
    
    $notifications[] = $row;
}

// Get total unread count
$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_type = ? AND user_reference = ? AND is_read = 0");
$countStmt->bind_param("si", $userType, $userReference);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalUnread = 0;
if ($countRow = $countResult->fetch_assoc()) {
    $totalUnread = (int)$countRow['total'];
}

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'unread_count' => $unreadCount,
    'total_unread' => $totalUnread,
    'max_id' => $maxId,
    'user_type' => $userType,
    'user_reference' => $userReference
]);

/**
 * Convert timestamp to relative time string (Indonesian)
 */
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


<?php
/**
 * mark_notification_read.php
 * 
 * AJAX endpoint to mark one or all notifications as read.
 * 
 * POST Parameters:
 *   - id (int, optional): Specific notification ID to mark as read
 *   - all (bool, optional): If true, mark ALL unread notifications as read for current user
 * 
 * Returns JSON:
 * {
 *   "success": true,
 *   "message": "Notifikasi ditandai sudah dibaca",
 *   "updated": 1
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detect user type from session
$userType = '';
$userReference = 0;

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $userType = 'admin';
    $userReference = (int)$_SESSION['user_id'];
} elseif (isset($_SESSION['patient_session_id']) && !empty($_SESSION['patient_session_id'])) {
    $userType = 'patient';
    $userReference = (int)$_SESSION['patient_session_id'];
}

if (empty($userType) || $userReference <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ]);
    exit;
}

// Determine action
$notificationId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$markAll = isset($_POST['all']) && ($_POST['all'] === 'true' || $_POST['all'] === '1');

$updated = 0;

if ($markAll) {
    // Mark ALL notifications as read for this user
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_type = ? AND user_reference = ? AND is_read = 0");
    $stmt->bind_param("si", $userType, $userReference);
    if ($stmt->execute()) {
        $updated = $stmt->affected_rows;
    }
} elseif ($notificationId > 0) {
    // Mark specific notification as read - verify it belongs to the current user
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_type = ? AND user_reference = ?");
    $stmt->bind_param("isi", $notificationId, $userType, $userReference);
    if ($stmt->execute()) {
        $updated = $stmt->affected_rows;
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Parameter id atau all diperlukan'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => $updated > 0 ? 'Notifikasi ditandai sudah dibaca' : 'Tidak ada notifikasi yang perlu diubah',
    'updated' => $updated
]);
?>


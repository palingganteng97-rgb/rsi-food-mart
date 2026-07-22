<?php
///notification_helper.php

if (!function_exists('createNotification')) {
    /**
     * Create a notification record in the database
     * 
     * @param string  $userType      'admin' or 'patient'
     * @param int     $userReference ID from users table (if admin) or patient_sessions table (if patient)
     * @param string  $title         Short notification title (max 150 chars)
     * @param string  $message       Notification body text
     * @param string  $link          Optional URL path to redirect when clicked (e.g. 'orders.php?id=15')
     * @return int|false             Inserted notification ID, or false on failure
     */
    function createNotification(string $userType, int $userReference, string $title, string $message, string $link = ''): int|false {
        global $conn;
        
        // Validate connection
        if (!isset($conn) || !$conn) {
            error_log("Notification helper: No database connection available.");
            return false;
        }
        
        // Validate user_type
        $allowedTypes = ['admin', 'patient'];
        if (!in_array($userType, $allowedTypes)) {
            error_log("Notification helper: Invalid user_type '$userType'. Must be 'admin' or 'patient'.");
            return false;
        }
        
        // Validate user_reference
        if ($userReference <= 0) {
            error_log("Notification helper: Invalid user_reference '$userReference'.");
            return false;
        }
        
        // Trim and truncate title
        $title = trim($title);
        if (strlen($title) > 150) {
            $title = substr($title, 0, 147) . '...';
        }
        if (empty($title)) {
            $title = 'Notifikasi';
        }
        
        // Trim message
        $message = trim($message);
        if (empty($message)) {
            $message = $title;
        }
        
        // Trim link
        $link = trim($link);
        
        // Insert using prepared statement (SQL injection safe)
        $stmt = $conn->prepare("INSERT INTO notifications (user_type, user_reference, title, message, link, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
        
        if (!$stmt) {
            error_log("Notification helper: Prepare failed - " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("siiss", $userType, $userReference, $title, $message, $link);
        
        if ($stmt->execute()) {
            $insertId = (int)$stmt->insert_id;
            $stmt->close();
            return $insertId;
        } else {
            error_log("Notification helper: Execute failed - " . $stmt->error);
            $stmt->close();
            return false;
        }
    }
}

if (!function_exists('createNotificationForAllAdmins')) {
    /**
     * Create a notification for ALL active admin users
     * 
     * @param string $title   Notification title
     * @param string $message Notification body
     * @param string $link    Optional URL
     * @return array          Array of inserted IDs
     */
    function createNotificationForAllAdmins(string $title, string $message, string $link = ''): array {
        global $conn;
        $insertedIds = [];
        
        if (!isset($conn) || !$conn) {
            return $insertedIds;
        }
        
        $query = "SELECT id FROM users WHERE status = 1 AND deleted_at IS NULL";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $id = createNotification('admin', (int)$row['id'], $title, $message, $link);
                if ($id !== false) {
                    $insertedIds[] = $id;
                }
            }
        }
        
        return $insertedIds;
    }
}
?>


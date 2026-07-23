<?php
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<pre style='background:#1e293b; color:#e5e7eb; padding:20px; border-radius:8px; font-size:14px; line-height:1.6;'>";
echo "<h2 style='color:#22c55e;'>🔍 DEBUG: NOTIFIKASI PASIEN — PIPELINE AUDIT</h2>\n\n";
echo "<hr style='border-color:#334155;'>";
echo "<h3 style='color:#fbbf24;'>📋 LANGKAH 1: Record Notifications Terbaru</h3>\n";

$notifQuery = "
    SELECT n.id, n.user_type, n.user_reference, n.title, n.message, n.link, n.is_read, n.created_at,
           o.order_number, o.patient_session_id AS orders_patient_session_id
    FROM notifications n
    LEFT JOIN orders o ON n.link LIKE CONCAT('%id=', o.id, '%') AND n.user_type = 'patient'
    ORDER BY n.id DESC
    LIMIT 20
";
$notifResult = $conn->query($notifQuery);

if ($notifResult && $notifResult->num_rows > 0) {
    echo "<table style='border-collapse:collapse; width:100%;'>";
    echo "<tr style='background:#334155;'>";
    echo "<th style='padding:8px; border:1px solid #475569; text-align:left;'>ID</th>";
    echo "<th style='padding:8px; border:1px solid #475569; text-align:left;'>user_type</th>";
    echo "<th style='padding:8px; border:1px solid #475569; text-align:left;'>user_reference</th>";
    echo "<th style='padding:8px; border:1px solid #475569; text-align:left;'>title</th>";
    echo "<th style='padding:8px; border:1px solid #475569; text-align:left;'>message</th>";
    echo "<th style='padding:8px; border:1px solid #475569; text-align:left;'>link</th>";
    echo "<th style='padding:8px; border:1px solid #475569; text-align:left;'>is_read</th>";
    echo "<th style='padding:8px; border:1px solid #475569; text-align:left;'>created_at</th>";
    echo "<th style='padding:8px; border:1px solid #475569; text-align:left;'>order_number</th>";
    echo "<th style='padding:8px; border:1px solid #475569; text-align:left;'>orders.patient_session_id</th>";
    echo "</tr>";

    while ($row = $notifResult->fetch_assoc()) {
        $highlight = ($row['user_type'] === 'patient') ? 'style="background:rgba(34,197,94,0.08);"' : '';
        echo "<tr $highlight>";
        echo "<td style='padding:8px; border:1px solid #475569;'>{$row['id']}</td>";
        echo "<td style='padding:8px; border:1px solid #475569;'>{$row['user_type']}</td>";
        echo "<td style='padding:8px; border:1px solid #475569; font-weight:bold;'>{$row['user_reference']}</td>";
        echo "<td style='padding:8px; border:1px solid #475569;'>{$row['title']}</td>";
        echo "<td style='padding:8px; border:1px solid #475569;'>{$row['message']}</td>";
        echo "<td style='padding:8px; border:1px solid #475569; color:#60a5fa;'>{$row['link']}</td>";
        echo "<td style='padding:8px; border:1px solid #475569;'>{$row['is_read']}</td>";
        echo "<td style='padding:8px; border:1px solid #475569;'>{$row['created_at']}</td>";
        echo "<td style='padding:8px; border:1px solid #475569;'>{$row['order_number']}</td>";
        echo "<td style='padding:8px; border:1px solid #475569; font-weight:bold;'>" . ($row['orders_patient_session_id'] ?? 'N/A (no match via link)') . "</td>";
        echo "</tr>";
    }
    echo "</table>\n";
    echo "<p style='color:#94a3b8;'>Total: {$notifResult->num_rows} records</p>\n";
} else {
    echo "<p style='color:#f87171;'>❌ TIDAK ADA RECORD di tabel notifications atau tidak ada patient notifications.</p>\n";
    
    // Check table structure
    $descResult = $conn->query("DESCRIBE notifications");
    if ($descResult) {
        echo "<p style='color:#fbbf24;'>Tabel notifications structure:</p>\n";
        while ($row = $descResult->fetch_assoc()) {
            echo "  {$row['Field']} | {$row['Type']} | Null:{$row['Null']} | Default:{$row['Default']}\n";
        }
    }
}
echo "\n<hr style='border-color:#334155;'>";
echo "<h3 style='color:#fbbf24;'>🔑 LANGKAH 2: Session Info</h3>\n";

echo "Session ID: " . session_id() . "\n";
echo "\$_SESSION contents:\n";
print_r($_SESSION);
$patientSessionId = isset($_SESSION['patient_session_id']) ? (int)$_SESSION['patient_session_id'] : 0;
echo "\n\n<strong>patient_session_id dari SESSION: " . ($patientSessionId ?: 'TIDAK ADA') . "</strong>\n";
echo "\n<hr style='border-color:#334155;'>";
echo "<h3 style='color:#fbbf24;'>⚖️ LANGKAH 3: Perbandingan user_reference vs \$_SESSION['patient_session_id']</h3>\n";

$patientNotifs = $conn->query("SELECT id, user_reference, title, message FROM notifications WHERE user_type = 'patient' ORDER BY id DESC LIMIT 10");
if ($patientNotifs && $patientNotifs->num_rows > 0) {
    while ($n = $patientNotifs->fetch_assoc()) {
        $match = ($n['user_reference'] == $patientSessionId) ? '✅ MATCH' : '❌ MISMATCH';
        echo "Notif ID {$n['id']}: user_reference={$n['user_reference']} vs session={$patientSessionId} → {$match}\n";
        
        if ($n['user_reference'] != $patientSessionId) {
            echo "  → PENYEBAB: user_reference di database ({$n['user_reference']}) berbeda dengan session ({$patientSessionId})\n";
            echo "  → Cek apakah pasien login dengan session_id yang benar\n";
        }
    }
} else {
    echo "<p style='color:#f87171;'>❌ Tidak ada notifikasi dengan user_type='patient'</p>\n";
}

echo "\n<hr style='border-color:#334155;'>";
echo "<h3 style='color:#fbbf24;'>🗄️ LANGKAH 4: Test Query Langsung (seperti get_notifications.php)</h3>\n";
$testStmt1 = $conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_type = 'patient' AND user_reference = ?");
$testStmt1->bind_param("i", $patientSessionId);
$testStmt1->execute();
$testRes1 = $testStmt1->get_result()->fetch_assoc();
echo "Query: SELECT COUNT(*) FROM notifications WHERE user_type='patient' AND user_reference={$patientSessionId}\n";
echo "Result: total = " . ($testRes1['total'] ?? 0) . " records\n";

if ($patientSessionId > 0) {
    $testStmt2 = $conn->prepare("SELECT id, user_type, user_reference, title, message, link, is_read, created_at FROM notifications WHERE user_type = 'patient' AND user_reference = ? ORDER BY id DESC LIMIT 10");
    $testStmt2->bind_param("i", $patientSessionId);
    $testStmt2->execute();
    $testRes2 = $testStmt2->get_result();
    
    echo "\nFull data query:\n";
    echo "SELECT id, user_type, user_reference, title, message, link, is_read, created_at FROM notifications WHERE user_type='patient' AND user_reference={$patientSessionId} ORDER BY id DESC LIMIT 10\n";
    echo "Rows found: " . $testRes2->num_rows . "\n";
    
    if ($testRes2->num_rows > 0) {
        while ($row = $testRes2->fetch_assoc()) {
            echo "  ID={$row['id']} | type={$row['user_type']} | ref={$row['user_reference']} | is_read={$row['is_read']} | title={$row['title']}\n";
        }
    }
}

echo "\n<hr style='border-color:#334155;'>";
echo "<h3 style='color:#fbbf24;'>🌐 LANGKAH 5: Simulasi get_notifications.php</h3>\n";
$userType = 'patient';
$userReference = $patientSessionId;
$stmt = $conn->prepare("
    SELECT id, user_type, user_reference, title, message, link, is_read, created_at 
    FROM notifications 
    WHERE user_type = ? AND user_reference = ? 
    ORDER BY id DESC 
    LIMIT 10
");
$stmt->bind_param("si", $userType, $userReference);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];
$unreadCount = 0;
while ($row = $result->fetch_assoc()) {
    $row['id'] = (int)$row['id'];
    $row['is_read'] = (int)$row['is_read'];
    $row['user_reference'] = (int)$row['user_reference'];
    $row['time_ago'] = 'just now';
    if (!$row['is_read']) {
        $unreadCount++;
    }
    $notifications[] = $row;
}

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_type = ? AND user_reference = ? AND is_read = 0");
$countStmt->bind_param("si", $userType, $userReference);
$countStmt->execute();
$countRow = $countStmt->get_result()->fetch_assoc();
$totalUnread = (int)($countRow['total'] ?? 0);
$jsonResponse = [
    'success' => true,
    'notifications' => $notifications,
    'unread_count' => $unreadCount,
    'total_unread' => $totalUnread,
    'max_id' => count($notifications) > 0 ? max(array_column($notifications, 'id')) : 0,
    'user_type' => $userType,
    'user_reference' => $userReference
];

echo "JSON Response that get_notifications.php would return:\n";
echo json_encode($jsonResponse, JSON_PRETTY_PRINT);
echo "\n\n<hr style='border-color:#334155;'>";
echo "<h3 style='color:#fbbf24;'>📊 SUMMARY</h3>\n";
echo "1. Notifications table: " . ($conn->query("SELECT COUNT(*) AS c FROM notifications WHERE user_type='patient'")->fetch_assoc()['c'] ?? 0) . " patient records\n";
echo "2. Session patient_session_id: " . ($patientSessionId ?: 'EMPTY') . "\n";
echo "3. Match? ";
if ($patientSessionId > 0) {
    $dbRef = $conn->query("SELECT user_reference FROM notifications WHERE user_type='patient' ORDER BY id DESC LIMIT 1")->fetch_assoc();
    if ($dbRef && $dbRef['user_reference'] == $patientSessionId) {
        echo "✅ MATCH\n";
    } else {
        echo "❌ MISMATCH - DB has user_reference=" . ($dbRef['user_reference'] ?? 'N/A') . "\n";
    }
} else {
    echo "❌ No patient session!\n";
}
echo "4. Query returns " . ($testRes1['total'] ?? 0) . " records for this session\n";
echo "5. JSON has " . count($notifications) . " notifications\n";
echo "</pre>";
?>

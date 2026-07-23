<?php
/**
 * AUDIT PIPELINE - Patient Notification System
 * 
 * Verifikasi:
 * 1. INSERT notifications (deliveries.php -> notification_helper.php)
 * 2. SELECT notifications (get_notifications.php & notifications.php)
 * 3. Kesamaan user_reference == patient_session_id
 * 4. Debug session & database consistency
 */

include 'db.php';

echo "=================================================================\n";
echo "  AUDIT PIPA NOTIFIKASI PASIEN\n";
echo "  RSI FOOD & MART\n";
echo "=================================================================\n\n";

// =========================================================================
// 1. CEK STRUKTUR TABEL
// =========================================================================
echo "--- 1. STRUKTUR TABEL ---\n\n";

echo "=== notifications ===\n";
$r = $conn->query("DESCRIBE notifications");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        echo "  {$row['Field']} | {$row['Type']} | Null:{$row['Null']} | Default:{$row['Default']}\n";
    }
} else {
    echo "  ERROR: " . $conn->error . "\n";
}

echo "\n=== orders (relevant columns) ===\n";
$r = $conn->query("DESCRIBE orders");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        echo "  {$row['Field']} | {$row['Type']} | Null:{$row['Null']} | Default:{$row['Default']}\n";
    }
} else {
    echo "  ERROR: " . $conn->error . "\n";
}

// =========================================================================
// 2. CEK ISI TABEL NOTIFICATIONS
// =========================================================================
echo "\n--- 2. ISI TABEL NOTIFICATIONS (5 record terbaru) ---\n\n";

$r = $conn->query("SELECT n.* FROM notifications n ORDER BY n.id DESC LIMIT 5");
if ($r && $r->num_rows > 0) {
    while ($row = $r->fetch_assoc()) {
        echo "  ID: {$row['id']} | Type: {$row['user_type']} | Ref: {$row['user_reference']}\n";
        $t = substr($row['title'] ?? '', 0, 80);
        $m = substr($row['message'] ?? '', 0, 100);
        echo "  Title: \"$t\"\n";
        echo "  Message: \"$m\"\n";
        echo "  Link: {$row['link']}\n";
        echo "  is_read: {$row['is_read']} | created_at: {$row['created_at']}\n";
        echo "  ---\n";
    }
} else {
    echo "  [KOSONG] Tidak ada record di tabel notifications\n";
    if ($r) echo "  num_rows = {$r->num_rows}\n";
    else echo "  ERROR: " . $conn->error . "\n";
}

// Total count
$r2 = $conn->query("SELECT COUNT(*) as total FROM notifications");
$totalNotif = $r2 ? $r2->fetch_assoc()['total'] : 0;
echo "  Total notifications: $totalNotif\n";

// =========================================================================
// 3. CEK PATIENT NOTIFICATIONS - user_type = 'patient'
// =========================================================================
echo "\n--- 3. PATIENT NOTIFICATIONS (user_type = 'patient') ---\n\n";

$r = $conn->query("SELECT n.* FROM notifications n WHERE n.user_type = 'patient' ORDER BY n.id DESC LIMIT 5");
if ($r && $r->num_rows > 0) {
    while ($row = $r->fetch_assoc()) {
        echo "  ID: {$row['id']} | Type: {$row['user_type']} | Ref: {$row['user_reference']}\n";
        echo "  Title: \"{$row['title']}\" | Message: \"" . substr($row['message'] ?? '', 0, 80) . "\"\n";
        echo "  Link: {$row['link']}\n";
        echo "  is_read: {$row['is_read']} | created_at: {$row['created_at']}\n";
        echo "  ---\n";
    }
} else {
    echo "  [KOSONG] Tidak ada notifikasi dengan user_type='patient'\n";
    if ($r) echo "  num_rows = {$r->num_rows}\n";
    else echo "  ERROR: " . $conn->error . "\n";
}

$r2 = $conn->query("SELECT COUNT(*) as total FROM notifications WHERE user_type = 'patient'");
$totalPatient = $r2 ? $r2->fetch_assoc()['total'] : 0;
echo "  Total patient notifications: $totalPatient\n";

// =========================================================================
// 4. CEK ISI TABEL orders
// =========================================================================
echo "\n--- 4. ISI TABEL orders (5 record terbaru dengan patient_session_id) ---\n\n";

$r = $conn->query("SELECT o.id, o.order_number, o.patient_session_id, o.status, o.created_at FROM orders o ORDER BY o.id DESC LIMIT 5");
if ($r && $r->num_rows > 0) {
    while ($row = $r->fetch_assoc()) {
        echo "  Order ID: {$row['id']} | #{$row['order_number']}\n";
        echo "  patient_session_id: {$row['patient_session_id']} | Status: {$row['status']}\n";
        echo "  created_at: {$row['created_at']}\n";
        echo "  ---\n";
    }
} else {
    echo "  [KOSONG] Tidak ada record di tabel orders\n";
}

// =========================================================================
// 5. VERIFIKASI KESESUAIAN NILAI
// =========================================================================
echo "\n--- 5. VERIFIKASI: user_reference vs orders.patient_session_id ---\n\n";

$r = $conn->query("
    SELECT n.id AS notif_id, n.user_reference, o.patient_session_id, o.id AS order_id, o.order_number,
           CASE WHEN n.user_reference = o.patient_session_id THEN 'OK' ELSE 'MISMATCH' END AS status_match
    FROM notifications n
    JOIN orders o ON n.link LIKE CONCAT('%id=', o.id, '%')
    WHERE n.user_type = 'patient'
    ORDER BY n.id DESC
    LIMIT 20
");

if ($r && $r->num_rows > 0) {
    $allMatch = true;
    while ($row = $r->fetch_assoc()) {
        $icon = ($row['status_match'] === 'OK') ? 'OK' : 'XX';
        echo "  [$icon] Notif:{$row['notif_id']} | Order:#{$row['order_number']}\n";
        echo "    user_reference={$row['user_reference']} | orders.patient_session_id={$row['patient_session_id']}\n";
        if ($row['user_reference'] != $row['patient_session_id']) {
            $allMatch = false;
        }
        echo "\n";
    }
    if ($allMatch) {
        echo "  >> SEMUA user_reference COCOK dengan orders.patient_session_id\n";
    } else {
        echo "  >> ADA MISMATCH! user_reference tidak sama dengan patient_session_id\n";
    }
} else {
    echo "  [SKIP] Tidak dapat melakukan join\n";
    echo "  Info: " . ($r ? "no link match" : $conn->error) . "\n";
}

// Alternative verification: extract order_id from link
echo "\n--- 5b. VERIFIKASI LANGSUNG (extract order_id dari link) ---\n\n";
$r = $conn->query("SELECT id, user_reference, link, title, message FROM notifications WHERE user_type = 'patient' ORDER BY id DESC LIMIT 10");
if ($r && $r->num_rows > 0) {
    while ($row = $r->fetch_assoc()) {
        $link = $row['link'] ?? '';
        $orderId = 0;
        if (preg_match('/id=(\d+)/', $link, $m)) {
            $orderId = (int)$m[1];
        }
        if ($orderId > 0) {
            $q = $conn->query("SELECT patient_session_id FROM orders WHERE id = $orderId LIMIT 1");
            $psid = $q ? (int)$q->fetch_assoc()['patient_session_id'] : 0;
            $match = ($row['user_reference'] == $psid) ? 'OK' : 'XX';
            echo "  [$match] Notif:{$row['id']} | ref={$row['user_reference']} | order_id={$orderId} | orders.psid={$psid}\n";
            echo "    Title: \"{$row['title']}\" | Link: $link\n\n";
        } else {
            echo "  [--] Notif:{$row['id']} | link tanpa id= (" . ($link ?: 'KOSONG') . ")\n\n";
        }
    }
} else {
    echo "  Tidak ada patient notifications\n";
}

// =========================================================================
// 6. CEK SESSION INFORMATION
// =========================================================================
echo "\n--- 6. INFORMASI SESSION ---\n\n";
echo "  Session ID: " . session_id() . "\n";
echo "  SESSION[patient_session_id]: " . ($_SESSION['patient_session_id'] ?? 'NOT SET') . "\n";
echo "  SESSION[user_id]: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";

// =========================================================================
// 7. TEST QUERY get_notifications.php
// =========================================================================
echo "\n--- 7. TEST QUERY (seperti get_notifications.php) ---\n\n";

$patientSessionId = isset($_SESSION['patient_session_id']) ? (int)$_SESSION['patient_session_id'] : 0;
if ($patientSessionId > 0) {
    $stmt = $conn->prepare("
        SELECT id, user_type, user_reference, title, message, link, is_read, created_at 
        FROM notifications 
        WHERE user_type = ? AND user_reference = ? 
        ORDER BY id DESC 
        LIMIT 10
    ");
    $userType = 'patient';
    $stmt->bind_param("si", $userType, $patientSessionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "  Query: SELECT ... FROM notifications WHERE user_type='patient' AND user_reference=$patientSessionId\n";
    echo "  Parameter: userType='patient'(string), userReference=$patientSessionId(int)\n";
    echo "  Rows found: " . $result->num_rows . "\n\n";

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $m = substr($row['message'] ?? '', 0, 60);
            echo "  ID={$row['id']} | Type={$row['user_type']} | Ref={$row['user_reference']} | is_read={$row['is_read']}\n";
            echo "  Title: \"{$row['title']}\" | Message: \"$m\"\n\n";
        }
    } else {
        echo "  [KOSONG] Query tidak mengembalikan data untuk session=$patientSessionId\n";
        
        $debugQ = $conn->query("SELECT DISTINCT user_reference FROM notifications WHERE user_type='patient' ORDER BY id DESC LIMIT 5");
        echo "  DISTINCT user_reference values in patient notifications:\n";
        if ($debugQ && $debugQ->num_rows > 0) {
            while ($dr = $debugQ->fetch_assoc()) {
                echo "    - user_reference: {$dr['user_reference']}\n";
            }
        } else {
            echo "    (none)\n";
        }
    }
    
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_type = 'patient' AND user_reference = ? AND is_read = 0");
    $countStmt->bind_param("i", $patientSessionId);
    $countStmt->execute();
    $countRow = $countStmt->get_result()->fetch_assoc();
    echo "  Unread count for session=$patientSessionId: " . ($countRow['total'] ?? 0) . "\n";
    
} else {
    echo "  [SKIP] Tidak ada patient_session_id di session saat ini.\n";
    echo "  Buka halaman pasien via browser dulu, lalu jalankan ulang.\n";
    
    echo "\n  DAFTAR user_reference di notifications:\n";
    $debugQ = $conn->query("SELECT DISTINCT n.user_reference, COUNT(*) as cnt FROM notifications n WHERE n.user_type='patient' GROUP BY n.user_reference ORDER BY cnt DESC LIMIT 5");
    if ($debugQ && $debugQ->num_rows > 0) {
        while ($dr = $debugQ->fetch_assoc()) {
            echo "    - user_reference: {$dr['user_reference']} (total: {$dr['cnt']} notif)\n";
        }
    } else {
        echo "    (tidak ada data)\n";
    }
    
    echo "\n  DAFTAR patient_session_id di orders:\n";
    $debugQ2 = $conn->query("SELECT DISTINCT patient_session_id FROM orders ORDER BY id DESC LIMIT 5");
    if ($debugQ2 && $debugQ2->num_rows > 0) {
        while ($dr = $debugQ2->fetch_assoc()) {
            echo "    - patient_session_id: {$dr['patient_session_id']}\n";
        }
    } else {
        echo "    (tidak ada data)\n";
    }
}

// =========================================================================
// 8. DUPLIKASI CHECK
// =========================================================================
echo "\n--- 8. CEK DUPLIKASI NOTIFIKASI ---\n\n";
$dupQ = $conn->query("
    SELECT user_reference, message, link, COUNT(*) as cnt 
    FROM notifications 
    WHERE user_type = 'patient' 
    GROUP BY user_reference, message, link 
    HAVING cnt > 1 
    ORDER BY cnt DESC 
    LIMIT 5
");
if ($dupQ && $dupQ->num_rows > 0) {
    echo "  DUPLIKASI DITEMUKAN:\n";
    while ($row = $dupQ->fetch_assoc()) {
        echo "    user_reference={$row['user_reference']} | message=\"{$row['message']}\" | cnt={$row['cnt']}\n";
    }
} else {
    echo "  Tidak ada duplikasi notifikasi.\n";
}

// =========================================================================
// 9. SIMULASI INSERT
// =========================================================================
echo "\n--- 9. SIMULASI INSERT via notification_helper.php ---\n\n";

include_once 'notification_helper.php';

$testRef = 999999;
$testTitle = 'TEST AUDIT - Status Pengiriman Diperbarui';
$testMsg = 'TEST AUDIT - Pesanan Anda sekarang berstatus "Terkirim".';
$testLink = 'riwayat_pesanan.php?id=999999';

$insertedId = createNotification('patient', $testRef, $testTitle, $testMsg, $testLink);
if ($insertedId !== false) {
    echo "  createNotification BERHASIL! Insert ID: $insertedId\n";
    
    $v = $conn->query("SELECT * FROM notifications WHERE id = $insertedId LIMIT 1");
    if ($v && $v->num_rows > 0) {
        $vr = $v->fetch_assoc();
        echo "  Data tersimpan di database:\n";
        echo "    ID: {$vr['id']}\n";
        echo "    user_type: '{$vr['user_type']}' (expected: 'patient')\n";
        echo "    user_reference: {$vr['user_reference']} (expected: $testRef)\n";
        echo "    title: \"{$vr['title']}\" (expected: \"$testTitle\")\n";
        echo "    message: \"{$vr['message']}\" (expected: \"$testMsg\")\n";
        echo "    link: \"{$vr['link']}\" (expected: \"$testLink\")\n";
        
        $pass = true;
        if ($vr['user_type'] !== 'patient') { echo "    >> user_type MISMATCH!\n"; $pass = false; }
        if ((int)$vr['user_reference'] !== $testRef) { echo "    >> user_reference MISMATCH!\n"; $pass = false; }
        if ($vr['title'] !== $testTitle) { echo "    >> title MISMATCH! (GOT: \"{$vr['title']}\")\n"; $pass = false; }
        if ($vr['message'] !== $testMsg) { echo "    >> message MISMATCH!\n"; $pass = false; }
        if ($vr['link'] !== $testLink) { echo "    >> link MISMATCH!\n"; $pass = false; }
        if ($pass) echo "    >> SEMUA NILAI VALID! createNotification bekerja dengan benar.\n";
    }
    
    $conn->query("DELETE FROM notifications WHERE id = $insertedId");
    echo "  Test record deleted (ID: $insertedId)\n\n";
} else {
    echo "  createNotification GAGAL!\n";
    echo "  Error info:\n";
    if (isset($conn->error) && $conn->error) {
        echo "  MySQL error: " . $conn->error . "\n";
    } else {
        echo "  (no MySQL error - likely validation failed)\n";
    }
}

// =========================================================================
// SUMMARY
// =========================================================================
echo "\n=================================================================\n";
echo "  RINGKASAN AUDIT\n";
echo "=================================================================\n\n";

$c1 = $conn->query("SELECT COUNT(*) as c FROM notifications")->fetch_assoc()['c'];
$c2 = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_type='patient'")->fetch_assoc()['c'];
$c3 = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];

echo "1. Tabel notifications: $c1 total records\n";
echo "2. Patient notifications: $c2 records\n";
echo "3. Tabel orders: $c3 records\n";
echo "4. INSERT test: " . ($insertedId !== false ? "BERHASIL" : "GAGAL") . "\n";

echo "\n5. Rekomendasi:\n";
echo "   - Jalankan script ini via browser dengan session pasien aktif\n";
echo "   - Pastikan SESSION[patient_session_id] == notifications.user_reference\n";
echo "   - Pastikan tidak ada duplikasi akibat double-submit di deliveries.php\n";

echo "\n=================================================================\n";
echo "  AUDIT SELESAI\n";
echo "=================================================================\n\n";
?>


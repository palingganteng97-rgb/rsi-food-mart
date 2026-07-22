<?php
include 'db.php';
$r = mysqli_query($conn, 'DESCRIBE notifications');
if ($r) {
    echo "=== notifications table exists ===\n";
    while ($row = mysqli_fetch_assoc($r)) {
        echo $row['Field'] . ' | ' . $row['Type'] . ' | Null:' . $row['Null'] . ' | Default:' . ($row['Default'] ?? 'NULL') . ' | Extra:' . $row['Extra'] . "\n";
    }
} else {
    echo "notifications table does NOT exist: " . mysqli_error($conn) . "\n";
}

// Check patient_sessions
$r2 = mysqli_query($conn, 'DESCRIBE patient_sessions');
if ($r2) {
    echo "\n=== patient_sessions table ===\n";
    while ($row = mysqli_fetch_assoc($r2)) {
        echo $row['Field'] . ' | ' . $row['Type'] . ' | Null:' . $row['Null'] . ' | Default:' . ($row['Default'] ?? 'NULL') . ' | Extra:' . $row['Extra'] . "\n";
    }
}

// Check users table
$r3 = mysqli_query($conn, 'DESCRIBE users');
if ($r3) {
    echo "\n=== users table ===\n";
    while ($row = mysqli_fetch_assoc($r3)) {
        echo $row['Field'] . ' | ' . $row['Type'] . ' | Null:' . $row['Null'] . ' | Default:' . ($row['Default'] ?? 'NULL') . ' | Extra:' . $row['Extra'] . "\n";
    }
}
?>


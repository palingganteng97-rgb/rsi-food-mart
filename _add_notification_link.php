<?php
/**
 * _add_notification_link.php
 * One-time migration: Add `link` column to notifications table
 * Run this file once via browser or CLI
 */

include 'db.php';

$columnExists = false;
$check = mysqli_query($conn, "SHOW COLUMNS FROM notifications LIKE 'link'");
if ($check && mysqli_num_rows($check) > 0) {
    $columnExists = true;
    echo "✓ Column 'link' already exists in notifications table.\n";
} else {
    $alter = "ALTER TABLE notifications ADD COLUMN link VARCHAR(255) DEFAULT NULL AFTER message";
    if (mysqli_query($conn, $alter)) {
        echo "✓ Column 'link' added successfully to notifications table.\n";
    } else {
        echo "✗ Failed to add column: " . mysqli_error($conn) . "\n";
    }
}

echo "\nCurrent notifications table structure:\n";
$desc = mysqli_query($conn, "DESCRIBE notifications");
if ($desc) {
    while ($row = mysqli_fetch_assoc($desc)) {
        echo "  {$row['Field']} | {$row['Type']} | Null:{$row['Null']} | Default:" . ($row['Default'] ?? 'NULL') . " | Extra:{$row['Extra']}\n";
    }
}
?>


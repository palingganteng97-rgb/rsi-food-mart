<?php
include 'db.php';

$tables = ['orders', 'order_items', 'payments', 'carts', 'cart_items', 'patient_sessions', 'payment_methods'];
foreach ($tables as $t) {
    $r = mysqli_query($conn, 'DESCRIBE ' . $t);
    if ($r) {
        echo "\n=== $t ===\n";
        while ($row = mysqli_fetch_assoc($r)) {
            echo $row['Field'] . ' | ' . $row['Type'] . ' | Null:' . $row['Null'] . ' | Default:' . ($row['Default'] ?? 'NULL') . ' | Extra:' . $row['Extra'] . "\n";
        }
    } else {
        echo "$t: " . mysqli_error($conn) . "\n";
    }
}
?>


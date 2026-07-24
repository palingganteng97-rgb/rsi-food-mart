<?php
include 'db.php';
$r = mysqli_query($conn, 'DESCRIBE orders');
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        echo $row['Field'] . ' | ' . $row['Type'] . PHP_EOL;
    }
} else {
    echo 'Error: ' . mysqli_error($conn) . PHP_EOL;
}
?>


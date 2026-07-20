<?php
// get_variants.php
include 'db.php';

header('Content-Type: application/json');

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$variants = [];

if ($product_id > 0) {
$query = "SELECT pv.id, pv.name 
          FROM product_variants pv 
          JOIN products p ON pv.product_id = p.id 
          WHERE pv.product_id = $product_id AND p.deleted_at IS NULL 
          ORDER BY pv.name ASC";
    $result = mysqli_query($conn, $query);
    if ($result) {
        $variants = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
}

echo json_encode($variants);
exit;

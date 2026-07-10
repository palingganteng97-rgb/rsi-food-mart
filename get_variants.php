<?php
// get_variants.php
include 'db.php';

header('Content-Type: application/json');

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$variants = [];

if ($product_id > 0) {
    $query = "SELECT id, name FROM product_variants WHERE product_id = $product_id ORDER BY name ASC";
    $result = mysqli_query($conn, $query);
    if ($result) {
        $variants = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
}

echo json_encode($variants);
exit;

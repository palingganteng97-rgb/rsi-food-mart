<?php
// get_addons.php
include 'db.php';

header('Content-Type: application/json');

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$addons = [];

if ($product_id > 0) {
    // Mengambil data topping berdasarkan id produk dari database Anda
    $query = "SELECT id, addon_name, required FROM product_addons WHERE product_id = $product_id ORDER BY id ASC";
    $result = mysqli_query($conn, $query);
    if ($result) {
        $addons = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
}

echo json_encode($addons);
exit;

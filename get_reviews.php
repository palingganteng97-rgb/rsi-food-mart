<?php
// get_reviews.php
include 'db.php';

header('Content-Type: application/json');

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$reviews = [];

if ($product_id > 0) {
    // Mengambil data ulasan produk dari database Anda
    $query = "SELECT id, rating, review FROM product_reviews WHERE product_id = $product_id ORDER BY id DESC";
    $result = mysqli_query($conn, $query);
    if ($result) {
        $reviews = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
}

echo json_encode($reviews);
exit;

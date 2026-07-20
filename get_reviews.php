<?php
// get_reviews.php
include 'db.php';

header('Content-Type: application/json');

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$reviews = [];
$avg = 0.0;
$count = 0;

if ($product_id > 0) {
    // Agregasi rating untuk UI marketplace
    $stmtAgg = $conn->prepare(
        "SELECT COUNT(*) AS cnt, COALESCE(AVG(rating), 0) AS avg_rating
         FROM product_reviews
         WHERE product_id = ?"
    );
    if ($stmtAgg) {
        $stmtAgg->bind_param('i', $product_id);
        $stmtAgg->execute();
        $resAgg = $stmtAgg->get_result();
        if ($resAgg && $rowAgg = $resAgg->fetch_assoc()) {
            $count = (int)($rowAgg['cnt'] ?? 0);
            $avg = (float)($rowAgg['avg_rating'] ?? 0);
        }
        $stmtAgg->close();
    }

    // Ambil review terbaru (dibatasi)
    $limit = isset($_GET['limit']) ? max(1, min(20, intval($_GET['limit']))) : 5;
    $stmt = $conn->prepare(
        "SELECT id, rating, review
         FROM product_reviews
         WHERE product_id = ?
         ORDER BY id DESC
         LIMIT ?"
    );
    if ($stmt) {
        $stmt->bind_param('ii', $product_id, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $reviews = mysqli_fetch_all($res, MYSQLI_ASSOC);
        }
        $stmt->close();
    }
}

echo json_encode([
    'avg_rating' => $avg,
    'review_count' => $count,
    'reviews' => $reviews
]);
exit;


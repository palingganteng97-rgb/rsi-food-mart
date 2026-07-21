<?php
// get_tenant_reviews.php
// Mengikuti arsitektur yg sama persis seperti get_reviews.php
// untuk mengambil data rating & ulasan tenant.

include 'db.php';

header('Content-Type: application/json');

$tenant_id = isset($_GET['tenant_id']) ? intval($_GET['tenant_id']) : 0;
$reviews = [];
$avg = 0.0;
$count = 0;

if ($tenant_id > 0) {
    // Agregasi rating tenant
    $stmtAgg = $conn->prepare(
        "SELECT COUNT(*) AS cnt, COALESCE(AVG(rating), 0) AS avg_rating
         FROM tenant_reviews
         WHERE tenant_id = ?"
    );
    if ($stmtAgg) {
        $stmtAgg->bind_param('i', $tenant_id);
        $stmtAgg->execute();
        $resAgg = $stmtAgg->get_result();
        if ($resAgg && $rowAgg = $resAgg->fetch_assoc()) {
            $count = (int)($rowAgg['cnt'] ?? 0);
            $avg = (float)($rowAgg['avg_rating'] ?? 0);
        }
        $stmtAgg->close();
    }

    // Ambil 5 review terbaru tenant dengan nama pasien (fallback: 'Pasien')
    $limit = isset($_GET['limit']) ? max(1, min(20, intval($_GET['limit']))) : 5;
    $stmt = $conn->prepare(
        "SELECT tr.id, tr.rating, tr.review, COALESCE(ps.patient_name, 'Pasien') AS patient_name
         FROM tenant_reviews tr
         LEFT JOIN patient_sessions ps ON tr.patient_session_id = ps.id
         WHERE tr.tenant_id = ?
         ORDER BY tr.id DESC
         LIMIT ?"
    );
    if ($stmt) {
        $stmt->bind_param('ii', $tenant_id, $limit);
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


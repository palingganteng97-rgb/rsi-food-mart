<?php
include "db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan pembeli sudah login
if (!isset($_SESSION['patient_session_id']) || intval($_SESSION['patient_session_id']) <= 0) {
    header("Location: login.php");
    exit;
}

$patient_session_id = (int)$_SESSION['patient_session_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $rating     = (int)($_POST['rating'] ?? 5);
    $review     = mysqli_real_escape_string($conn, trim((string)($_POST['review'] ?? '')));

    if ($product_id > 0 && $rating >= 1 && $rating <= 5 && $review !== '') {
        // Validasi: review hanya boleh jika ada order milik patient ini yang statusnya Selesai
        // Kompatibilitas: status orders di project bisa 'completed' atau 'selesai'.
        $orderStatusCheckSql = "
            SELECT o.id
            FROM orders o
            WHERE o.patient_session_id = ?
              AND (
                LOWER(o.status) = 'completed'
                OR LOWER(o.status) = 'selesai'
                OR LOWER(o.status) = 'done'
                OR LOWER(o.status) LIKE '%selesai%'
                OR LOWER(o.status) LIKE '%completed%'
              )
            ORDER BY o.id DESC
            LIMIT 1
        ";
        $stmt = $conn->prepare($orderStatusCheckSql);
        $stmt->bind_param('i', $patient_session_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();

        $orderRow = $res ? $res->fetch_assoc() : null;
        if ($orderRow && (int)($orderRow['id'] ?? 0) > 0) {
            $order_id = (int)$orderRow['id'];

            // Pastikan produk memang ada di order tersebut
            $productCheckSql = "
                SELECT 1
                FROM order_items oi
                WHERE oi.order_id = ?
                  AND oi.product_id = ?
                LIMIT 1
            ";
            $stmt2 = $conn->prepare($productCheckSql);
            $stmt2->bind_param('ii', $order_id, $product_id);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            $stmt2->close();

            if ($res2 && $res2->num_rows > 0) {
                $insertSql = "
                    INSERT INTO product_reviews (product_id, patient_session_id, rating, review)
                    VALUES (?, ?, ?, ?)
                ";
                $stmt3 = $conn->prepare($insertSql);
                $stmt3->bind_param('iiss', $product_id, $patient_session_id, $rating, $review);

                if ($stmt3->execute()) {
                    $stmt3->close();
                    header("Location: riwayat_pesanan.php?status=review_success");
                    exit;
                }
                $stmt3->close();
            }
        }
    }
}

header("Location: riwayat_pesanan.php?status=review_failed");
exit;


<?php
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =========================================================================
// Validasi session pasien
// =========================================================================
$patient_session_id = isset($_SESSION['patient_session_id']) ? intval($_SESSION['patient_session_id']) : 0;
if ($patient_session_id <= 0) {
    header("Location: index.php");
    exit;
}

// =========================================================================
// Hanya menerima POST
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: tenant_review_patients.php");
    exit;
}

$tenant_id = intval($_POST['tenant_id'] ?? 0);
$order_id  = intval($_POST['order_id'] ?? 0);
$rating    = intval($_POST['rating'] ?? 0);
$review    = trim($_POST['review'] ?? '');

// =========================================================================
// Validasi input
// =========================================================================
if ($tenant_id <= 0 || $order_id <= 0) {
    header("Location: tenant_review_patients.php?status=error&msg=" . urlencode("Data tenant atau pesanan tidak valid."));
    exit;
}

if ($rating < 1 || $rating > 5) {
    header("Location: tenant_review_patients.php?status=error&msg=" . urlencode("Rating wajib dipilih (1-5 bintang)."));
    exit;
}

// =========================================================================
// Validasi: Pastikan order milik pasien ini, status SUCCESS/DELIVERED/SELESAI,
// dan tenant_id sesuai dengan tenant di order tersebut
// =========================================================================
$stmtCheck = $conn->prepare("
    SELECT o.id 
    FROM orders o
    WHERE o.id = ? 
      AND o.patient_session_id = ?
      AND o.tenant_id = ?
      AND (LOWER(o.status) = 'completed' OR LOWER(o.status) = 'delivered' OR LOWER(o.status) = 'selesai')
    LIMIT 1
");
$stmtCheck->bind_param('iii', $order_id, $patient_session_id, $tenant_id);
$stmtCheck->execute();
$resCheck = $stmtCheck->get_result();
$validOrder = $resCheck && $resCheck->num_rows > 0;
$stmtCheck->close();

if (!$validOrder) {
    header("Location: tenant_review_patients.php?status=error&msg=" . urlencode("Pesanan tidak valid atau belum memenuhi syarat."));
    exit;
}

// =========================================================================
// Cek duplikasi: Apakah tenant ini sudah pernah direview oleh pasien ini
// (karena tabel tenant_reviews tidak memiliki kolom order_id)
// =========================================================================
$stmtDup = $conn->prepare("
    SELECT id FROM tenant_reviews 
    WHERE tenant_id = ? AND patient_session_id = ?
    LIMIT 1
");
$stmtDup->bind_param('ii', $tenant_id, $patient_session_id);
$stmtDup->execute();
$resDup = $stmtDup->get_result();
$alreadyReviewed = $resDup && $resDup->num_rows > 0;
$stmtDup->close();

if ($alreadyReviewed) {
    header("Location: tenant_review_patients.php?status=error&msg=" . urlencode("Tenant ini sudah pernah direview."));
    exit;
}

// =========================================================================
// Simpan review ke tenant_reviews
// =========================================================================
$stmtInsert = $conn->prepare("
    INSERT INTO tenant_reviews (tenant_id, patient_session_id, rating, review)
    VALUES (?, ?, ?, ?)
");
$stmtInsert->bind_param('iiis', $tenant_id, $patient_session_id, $rating, $review);

if ($stmtInsert->execute()) {
    $stmtInsert->close();
    header("Location: tenant_review_patients.php?status=success&msg=" . urlencode("Review berhasil dikirim!"));
    exit;
} else {
    $stmtInsert->close();
    header("Location: tenant_review_patients.php?status=error&msg=" . urlencode("Gagal menyimpan review."));
    exit;
}


<?php
// banner_delete.php - Hapus Banner

include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: banners.php?status=error&msg=' . urlencode('ID tidak valid'));
    exit;
}

$uploadDir = 'uploads/banners/';

$stmtOld = $conn->prepare('SELECT image FROM banners WHERE id = ? LIMIT 1');
$stmtOld->bind_param('i', $id);
$stmtOld->execute();
$res = $stmtOld->get_result();
$row = $res ? $res->fetch_assoc() : null;
$oldImage = $row['image'] ?? '';

if (!$row) {
    header('Location: banners.php?status=error&msg=' . urlencode('Banner tidak ditemukan'));
    exit;
}

try {
    $stmtDel = $conn->prepare('DELETE FROM banners WHERE id = ?');
    $stmtDel->bind_param('i', $id);
    $ok = $stmtDel->execute();

    if ($ok) {
        if (!empty($oldImage)) {
            $path = $uploadDir . $oldImage;
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        header('Location: banners.php?status=success_delete');
        exit;
    }

    header('Location: banners.php?status=error&msg=' . urlencode('Gagal menghapus'));
    exit;
} catch (Throwable $e) {
    header('Location: banners.php?status=error&msg=' . urlencode($e->getMessage()));
    exit;
}


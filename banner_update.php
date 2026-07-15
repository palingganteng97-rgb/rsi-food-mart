<?php
// banner_update.php - Update Banner

include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$title = isset($_POST['title']) ? trim((string)$_POST['title']) : '';
$link = isset($_POST['link']) ? trim((string)$_POST['link']) : '';
$status = isset($_POST['status']) ? (int)$_POST['status'] : 1;

if ($id <= 0 || $title === '' || $link === '' || !in_array($status, [0,1], true)) {
    header('Location: banners.php?status=error&msg=' . urlencode('Input tidak valid'));
    exit;
}

$uploadDir = 'uploads/banners/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

// ambil image lama
$stmtOld = $conn->prepare('SELECT image FROM banners WHERE id = ? LIMIT 1');
$stmtOld->bind_param('i', $id);
$stmtOld->execute();
$resOld = $stmtOld->get_result();
$old = $resOld ? $resOld->fetch_assoc() : null;
$oldImage = $old['image'] ?? '';

if (!$old) {
    header('Location: banners.php?status=error&msg=' . urlencode('Banner tidak ditemukan'));
    exit;
}

$newImageFileName = $oldImage;

$hasNewUpload = isset($_FILES['image']) && !empty($_FILES['image']['name']);

if ($hasNewUpload) {
    $maxSizeBytes = 5 * 1024 * 1024;
    if ((int)($_FILES['image']['size'] ?? 0) > $maxSizeBytes) {
        header('Location: banners.php?status=error&msg=' . urlencode('Ukuran gambar maksimal 5 MB'));
        exit;
    }

    $origName = (string)($_FILES['image']['name'] ?? '');
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp'];
    if (!in_array($ext, $allowed, true)) {
        header('Location: banners.php?status=error&msg=' . urlencode('Format gambar wajib jpg, jpeg, png, atau webp'));
        exit;
    }

    $dt = date('Ymd_His');
    $random = bin2hex(random_bytes(4));
    $newImageFileName = 'banner_' . $dt . '_' . $random . '.' . $ext;

    $tmp = (string)($_FILES['image']['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        header('Location: banners.php?status=error&msg=' . urlencode('File upload tidak valid'));
        exit;
    }

    $dest = $uploadDir . $newImageFileName;
    if (!move_uploaded_file($tmp, $dest)) {
        header('Location: banners.php?status=error&msg=' . urlencode('Gagal memindahkan file upload'));
        exit;
    }

    // hapus file lama
    if (!empty($oldImage)) {
        $oldPath = $uploadDir . $oldImage;
        if (file_exists($oldPath)) {
            @unlink($oldPath);
        }
    }
}

$stmt = $conn->prepare('UPDATE banners SET title = ?, image = ?, link = ?, status = ? WHERE id = ?');
$stmt->bind_param('sssii', $title, $newImageFileName, $link, $status, $id);


if ($stmt->execute()) {
    header('Location: banners.php?status=success_update');
    exit;
}

header('Location: banners.php?status=error&msg=' . urlencode('Gagal update data'));
exit;


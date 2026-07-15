<?php
// banner_save.php - Simpan Banner Baru

include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$uploadDir = 'uploads/banners/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

$title = isset($_POST['title']) ? trim((string)$_POST['title']) : '';
$link = isset($_POST['link']) ? trim((string)$_POST['link']) : '';
$status = isset($_POST['status']) ? (int)$_POST['status'] : 1;

if ($title === '' || $link === '' || !in_array($status, [0,1], true)) {
    header('Location: banners.php?status=error&msg=' . urlencode('Input tidak valid'));
    exit;
}

if (!isset($_FILES['image']) || empty($_FILES['image']['name'])) {
    header('Location: banners.php?status=error&msg=' . urlencode('Gambar wajib diunggah'));
    exit;
}

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
$bannerFileName = 'banner_' . $dt . '_' . $random . '.' . $ext;

$tmp = (string)($_FILES['image']['tmp_name'] ?? '');
if ($tmp === '' || !is_uploaded_file($tmp)) {
    header('Location: banners.php?status=error&msg=' . urlencode('File upload tidak valid'));
    exit;
}

$dest = $uploadDir . $bannerFileName;
if (!move_uploaded_file($tmp, $dest)) {
    header('Location: banners.php?status=error&msg=' . urlencode('Gagal memindahkan file upload'));
    exit;
}

// Insert ke database
try {
    $stmt = $conn->prepare('INSERT INTO banners (title, image, link, status) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('sssi', $title, $bannerFileName, $link, $status);
    if ($stmt->execute()) {
        header('Location: banners.php?status=success_add');
        exit;
    }

    // rollback file if db insert fails
    if (file_exists($dest)) {
        @unlink($dest);
    }

    header('Location: banners.php?status=error&msg=' . urlencode('Gagal menyimpan data'));
    exit;
} catch (Throwable $e) {
    if (file_exists($dest)) {
        @unlink($dest);
    }
    header('Location: banners.php?status=error&msg=' . urlencode($e->getMessage()));
    exit;
}


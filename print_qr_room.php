<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/phpqrcode/qrlib.php';

$roomName = isset($_GET['room_name']) ? trim($_GET['room_name']) : '';
$rooms = [];

function sanitizeFileName($name) {
    $name = trim($name);
    if ($name === '') return 'room';
    $name = preg_replace('/[\\/:*?"<>|]+/', '_', $name);
    $name = preg_replace('/\s+/', '_', $name);
    return $name;
}

$uploadDir = __DIR__ . '/uploads/qrcode/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$patientPath = $scriptBase . '/patient_sessions.php';

$qrUrl = $patientPath . '?room=' . urlencode($roomName);

$qrFileName = sanitizeFileName($roomName) . '.png';
$qrFilePath = $uploadDir . $qrFileName;
$qrPublicPath = 'uploads/qrcode/' . $qrFileName;

if ($roomName !== '' && !file_exists($qrFilePath)) {
    QRcode::png($qrUrl, $qrFilePath, QR_ECLEVEL_M, 10, 2);
}

$hospitalLogo = 'uploads/logo.png';
$logoExists = file_exists(__DIR__ . '/' . $hospitalLogo);
$hospitalLogoTag = $logoExists ? $hospitalLogo : 'https://dummyimage.com/140x140/0b1220/ffffff.png&text=RSI';

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Print QR Ruangan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #0b1220; color: #fff; }
        .print-wrap { max-width: 720px; margin: 24px auto; }
        .qr-big { width: 420px; max-width: 100%; background:#fff; padding: 14px; border-radius: 18px; }
        @media print {
            body { background: #fff; color:#000; }
            .card { border: none; }
            .no-print { display:none !important; }
        }
    </style>
</head>
<body>
<div class="print-wrap">
    <div class="no-print d-flex justify-content-end mb-3 gap-2">
        <button class="btn btn-outline-light" onclick="window.close()">Tutup</button>
        <button class="btn btn-success" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
    </div>

    <div class="card bg-transparent border-0">
        <div class="text-center">
            <img src="<?= htmlspecialchars($hospitalLogoTag); ?>" alt="Logo" style="width:120px; height:120px; object-fit:contain;" />
            <h4 class="mt-3 mb-0 fw-bold">Ruangan: <?= htmlspecialchars($roomName); ?></h4>
            <p class="text-white-50 mb-4">Silakan scan QR ini untuk registrasi pasien.</p>
        </div>

        <div class="d-flex justify-content-center">
            <div class="qr-big d-flex align-items-center justify-content-center">
                <img src="<?= htmlspecialchars($qrPublicPath); ?>" alt="QR <?= htmlspecialchars($roomName); ?>" style="width:100%; height:auto;" />
            </div>
        </div>

        <div class="mt-3 text-center">
            <div class="small mono text-white-50" style="word-break: break-word;">URL: <?= htmlspecialchars($qrUrl); ?></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.js"></script>
</body>
</html>


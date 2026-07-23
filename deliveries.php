<?php
include 'db.php';
include 'notification_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

$uploadDir = 'uploads/deliveries/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0775, true)) {
        error_log("[DELIVERIES] Gagal membuat direktori upload: $uploadDir");
    }
}

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id      = intval($_POST['order_id'] ?? 0);
    $courier_id    = intval($_POST['courier_id'] ?? 0);
    $status        = trim($_POST['status'] ?? 'PENDING');
    $pickup_time   = !empty($_POST['pickup_time']) ? $_POST['pickup_time'] : null;
    $delivery_time = !empty($_POST['delivery_time']) ? $_POST['delivery_time'] : null;
    $proof_photo   = '';

    // === PROSES UPLOAD FOTO ===
    if (isset($_FILES['proof_photo']) && $_FILES['proof_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        error_log("[DELIVERIES CREATE] \$_FILES[proof_photo] = " . print_r($_FILES['proof_photo'], true));
        if ($_FILES['proof_photo']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrorCode = $_FILES['proof_photo']['error'];
            error_log("[DELIVERIES CREATE] Upload error code: $uploadErrorCode");
            $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Gagal mengunggah foto. Kode error: ' . $uploadErrorCode];
            header("Location: deliveries.php");
            exit();
        }

        $fileTmpPath  = $_FILES['proof_photo']['tmp_name'];
        $fileNameOrig = $_FILES['proof_photo']['name'];
        $fileSize     = $_FILES['proof_photo']['size'];
        $fileExtension = strtolower(pathinfo($fileNameOrig, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($fileExtension, $allowedExtensions)) {
            error_log("[DELIVERIES CREATE] Format file tidak valid: $fileExtension");
            $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Format foto harus: ' . implode(', ', $allowedExtensions)];
            header("Location: deliveries.php");
            exit();
        }

        $photoName = 'delivery_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $destPath = $uploadDir . $photoName;

        if (!move_uploaded_file($fileTmpPath, $destPath)) {
            error_log("[DELIVERIES CREATE] move_uploaded_file() gagal: tmp=$fileTmpPath, dest=$destPath");
            $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Gagal menyimpan file upload ke server.'];
            header("Location: deliveries.php");
            exit();
        }

        error_log("[DELIVERIES CREATE] Upload sukses: $photoName");
        $proof_photo = $photoName;
    } else {
        error_log("[DELIVERIES CREATE] Tidak ada file upload. \$_FILES[proof_photo] = " . print_r($_FILES['proof_photo'] ?? 'UNDEFINED', true));
    }

    if ($order_id <= 0 || $courier_id <= 0) {
        $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Order dan Kurir harus dipilih!'];
        header("Location: deliveries.php");
        exit();
    }

    if ($order_id > 0 && $courier_id > 0) {
        // CEK DUPLIKAT: Pastikan order_id belum memiliki data delivery
        $checkStmt = $conn->prepare("SELECT id FROM deliveries WHERE order_id = ? LIMIT 1");
        $checkStmt->bind_param("i", $order_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult && $checkResult->num_rows > 0) {
            error_log("[Deliveries] DUPLICATE BLOCKED: order_id $order_id already has a delivery record");
            $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Pengiriman untuk pesanan ini sudah tersedia.'];
            header("Location: deliveries.php");
            exit();
        }
        $checkStmt->close();

        $stmt = $conn->prepare("INSERT INTO deliveries (order_id, courier_id, status, pickup_time, delivery_time, proof_photo) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $order_id, $courier_id, $status, $pickup_time, $delivery_time, $proof_photo);
        
        if ($stmt->execute()) {
            error_log("[Deliveries] INSERT success: delivery created for order_id $order_id");
            // NOTIFIKASI PASIEN: Beri tahu pasien bahwa kurir telah ditugaskan
            $orderStmt = $conn->prepare("SELECT order_number, patient_session_id FROM orders WHERE id = ? LIMIT 1");
            if ($orderStmt) {
                $orderStmt->bind_param("i", $order_id);
                $orderStmt->execute();
                $orderRes = $orderStmt->get_result();
                if ($orderData = $orderRes->fetch_assoc()) {
                    $orderNumberFull = $orderData['order_number'] ?? ('ID ' . $order_id);
                    $patientSessionId = (int)($orderData['patient_session_id'] ?? 0);
                    if ($patientSessionId > 0) {
                        $notifTitle = 'Kurir Ditugaskan';
                        $notifMessage = "Kurir telah ditugaskan untuk pesanan {$orderNumberFull} dan pengiriman sedang diproses.";
                        $notifLink = 'riwayat_pesanan.php?id=' . $order_id;
                        createNotification('patient', $patientSessionId, $notifTitle, $notifMessage, $notifLink);
                        error_log("[DELIVERIES NOTIFICATION] CREATE: order_id=$order_id, patient_session_id=$patientSessionId");
                    }
                }
                $orderStmt->close();
            }

            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Data pengiriman berhasil ditambahkan!'];
            header("Location: deliveries.php");
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Operasi gagal: ' . $stmt->error];
            header("Location: deliveries.php");
        }
        exit();
    }
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id            = intval($_POST['id'] ?? 0);
    $order_id      = intval($_POST['order_id'] ?? 0);
    $courier_id    = intval($_POST['courier_id'] ?? 0);
    $status        = trim($_POST['status'] ?? 'Pending');
    $pickup_time   = !empty($_POST['pickup_time']) ? $_POST['pickup_time'] : null;
    $delivery_time = !empty($_POST['delivery_time']) ? $_POST['delivery_time'] : null;

    if ($id > 0 && $order_id > 0 && $courier_id > 0) {
        // === GET OLD DATA BEFORE UPDATE ===
        $oldStatus = '';
        $oldOrderId = 0;
        $oldProofPhoto = '';
        $oldStmt = $conn->prepare("SELECT status, order_id, proof_photo FROM deliveries WHERE id = ? LIMIT 1");
        if ($oldStmt) {
            $oldStmt->bind_param("i", $id);
            $oldStmt->execute();
            $oldRes = $oldStmt->get_result();
            if ($oldRow = $oldRes->fetch_assoc()) {
                $oldStatus = trim($oldRow['status'] ?? '');
                $oldOrderId = (int)($oldRow['order_id'] ?? 0);
                $oldProofPhoto = trim($oldRow['proof_photo'] ?? '');
            }
            $oldStmt->close();
        }

        // === PROSES UPLOAD FOTO (Edit) ===
        $proof_photo = $oldProofPhoto; // Default: pertahankan foto lama

        // Cek apakah user mengklik tombol hapus foto (tanpa upload file baru)
        $deletePhotoFlag = isset($_POST['delete_photo_flag']) && $_POST['delete_photo_flag'] === '1';

        if ($deletePhotoFlag && empty($_FILES['proof_photo']['name'])) {
            // User ingin menghapus foto tanpa mengganti dengan file baru
            if (!empty($oldProofPhoto)) {
                $oldFilePath = $uploadDir . $oldProofPhoto;
                if (file_exists($oldFilePath)) {
                    if (unlink($oldFilePath)) {
                        error_log("[DELIVERIES UPDATE] File foto dihapus (flag hapus): $oldFilePath");
                    } else {
                        error_log("[DELIVERIES UPDATE] Gagal menghapus file foto (flag hapus): $oldFilePath");
                    }
                }
            }
            $proof_photo = '';
        }

        if (isset($_FILES['proof_photo']) && $_FILES['proof_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            error_log("[DELIVERIES UPDATE] \$_FILES[proof_photo] = " . print_r($_FILES['proof_photo'], true));
            if ($_FILES['proof_photo']['error'] !== UPLOAD_ERR_OK) {
                $uploadErrorCode = $_FILES['proof_photo']['error'];
                error_log("[DELIVERIES UPDATE] Upload error code: $uploadErrorCode");
                $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Gagal mengunggah foto. Kode error: ' . $uploadErrorCode];
                header("Location: deliveries.php");
                exit();
            }

            $fileTmpPath  = $_FILES['proof_photo']['tmp_name'];
            $fileNameOrig = $_FILES['proof_photo']['name'];
            $fileExtension = strtolower(pathinfo($fileNameOrig, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($fileExtension, $allowedExtensions)) {
                error_log("[DELIVERIES UPDATE] Format file tidak valid: $fileExtension");
                $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Format foto harus: ' . implode(', ', $allowedExtensions)];
                header("Location: deliveries.php");
                exit();
            }

            $photoName = 'delivery_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $destPath = $uploadDir . $photoName;

            if (!move_uploaded_file($fileTmpPath, $destPath)) {
                error_log("[DELIVERIES UPDATE] move_uploaded_file() gagal: tmp=$fileTmpPath, dest=$destPath");
                $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Gagal menyimpan file upload ke server.'];
                header("Location: deliveries.php");
                exit();
            }

            error_log("[DELIVERIES UPDATE] Upload sukses: $photoName");
            $proof_photo = $photoName;

            // Hapus file foto lama jika ada
            if (!empty($oldProofPhoto)) {
                $oldFilePath = $uploadDir . $oldProofPhoto;
                if (file_exists($oldFilePath)) {
                    if (unlink($oldFilePath)) {
                        error_log("[DELIVERIES UPDATE] File lama dihapus: $oldFilePath");
                    } else {
                        error_log("[DELIVERIES UPDATE] Gagal menghapus file lama: $oldFilePath");
                    }
                }
            }
        } else {
            error_log("[DELIVERIES UPDATE] Tidak ada file upload baru. Menggunakan foto lama: $oldProofPhoto");
        }

        $stmt = $conn->prepare("UPDATE deliveries SET order_id = ?, courier_id = ?, status = ?, pickup_time = ?, delivery_time = ?, proof_photo = ? WHERE id = ?");
        $stmt->bind_param("iissssi", $order_id, $courier_id, $status, $pickup_time, $delivery_time, $proof_photo, $id);
        
        if ($stmt->execute()) {
            // === PATIENT NOTIFICATION (ONLY): Deliveries is source of truth for delivery status ===
            // Notify ONLY the patient who owns the order. No admin notification for status changes.
            $statusChanged = (strcasecmp($oldStatus, $status) !== 0);
            if ($statusChanged && $oldOrderId > 0) {
                // Fetch order data for patient
                $orderStmt = $conn->prepare("SELECT order_number, patient_session_id FROM orders WHERE id = ? LIMIT 1");
                $orderStmt->bind_param("i", $oldOrderId);
                $orderStmt->execute();
                $orderRes = $orderStmt->get_result();
                if ($orderData = $orderRes->fetch_assoc()) {
                    $orderNumberFull = $orderData['order_number'] ?? ('ID ' . $oldOrderId);
                    $patientSessionId = (int)($orderData['patient_session_id'] ?? 0);

                    if ($patientSessionId > 0) {
                        $notifTitle = 'Status Pengiriman Diperbarui';
                        $notifLink = 'riwayat_pesanan.php?id=' . $oldOrderId;
                        $notifMessage = "Pesanan Anda dengan nomor {$orderNumberFull} sekarang berstatus \"{$status}\".";

                        // Prevent duplicate: check if notification for this specific status message already exists
                        // This ensures each unique status transition (e.g., "Diambil", "Diproses", "Terkirim")
                        // generates exactly ONE notification, while preventing duplicates on re-save.
                        $dupCheck = $conn->prepare(
                            "SELECT id FROM notifications 
                             WHERE user_type = 'patient' AND user_reference = ? 
                             AND link = ? 
                             AND message = ? 
                             LIMIT 1"
                        );
                        $dupCheck->bind_param("iss", $patientSessionId, $notifLink, $notifMessage);
                        $dupCheck->execute();
                        $dupRes = $dupCheck->get_result();
                        $notificationExists = ($dupRes && $dupRes->num_rows > 0);
                        $dupCheck->close();

                        if (!$notificationExists) {
                            createNotification(
                                'patient',
                                $patientSessionId,
                                $notifTitle,
                                $notifMessage,
                                $notifLink
                            );
                        }
                    }
                }
                $orderStmt->close();
            }

            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Data pengiriman berhasil diperbarui!'];
            header("Location: deliveries.php");
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Operasi gagal: ' . $stmt->error];
            header("Location: deliveries.php");
        }
        exit();
    }
}

if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id'] ?? 0);

    if ($id > 0) {
        // Ambil data foto lama sebelum hapus
        $oldStmt = $conn->prepare("SELECT proof_photo FROM deliveries WHERE id = ? LIMIT 1");
        $oldStmt->bind_param("i", $id);
        $oldStmt->execute();
        $oldRes = $oldStmt->get_result();
        $oldPhoto = '';
        if ($oldRow = $oldRes->fetch_assoc()) {
            $oldPhoto = trim($oldRow['proof_photo'] ?? '');
        }
        $oldStmt->close();

        $stmt = $conn->prepare("DELETE FROM deliveries WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // Hapus file foto jika ada
            if (!empty($oldPhoto)) {
                $oldFilePath = $uploadDir . $oldPhoto;
                if (file_exists($oldFilePath)) {
                    if (unlink($oldFilePath)) {
                        error_log("[DELIVERIES DELETE] File foto dihapus: $oldFilePath");
                    } else {
                        error_log("[DELIVERIES DELETE] Gagal menghapus file foto: $oldFilePath");
                    }
                }
            }
            // No notification sent on delete - delivery status changes only notify patients on update.
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Data pengiriman berhasil dihapus!'];
            header("Location: deliveries.php");
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Operasi gagal: ' . $stmt->error];
            header("Location: deliveries.php");
        }
        exit();
    }
}

$deliveries = [];
$sql = "SELECT d.*, o.order_number, c.name AS courier_name 
        FROM deliveries d
        LEFT JOIN orders o ON d.order_id = o.id
        LEFT JOIN couriers c ON d.courier_id = c.id
        ORDER BY d.id ASC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $deliveries[] = $row;
    }
}

// ===== SESSION FLASH MESSAGE =====
$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

$orders = [];
$orderRes = $conn->query("SELECT id, order_number FROM orders ORDER BY id DESC");
if ($orderRes) {
    while ($r = $orderRes->fetch_assoc()) {
        $orders[] = $r;
    }
}

$couriers = [];
$courierRes = $conn->query("SELECT id, name FROM couriers ORDER BY id DESC");
if ($courierRes) {
    while ($r = $courierRes->fetch_assoc()) {
        $couriers[] = $r;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Deliveries - RSI Food &amp; Mart</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

  <style>
    :root { --bg:#0f172a; --text:#e5e7eb; --muted:#94a3b8; --green:#22c55e; }
    body { background:var(--bg) !important; color:var(--text); }
    .table-transparent thead th,.table-transparent tbody td{color:#fff !important;}
    .table-transparent{background:transparent !important;}
    .table-transparent thead{background:rgba(15,23,42,.65) !important; border-bottom:1px solid rgba(148,163,184,.25) !important;}
    .table-transparent td,.table-transparent th{background:transparent !important; border-color:rgba(148,163,184,.12) !important;}
    .table-transparent tbody tr{border-bottom:1px solid rgba(148,163,184,.12) !important;}
    .table-transparent *{color:#fff !important;}
    .text-white*{color:#fff !important;}
    bottom-nav { position: fixed; left:0; right:0; bottom:0; z-index: 1035; background: rgba(15,23,42,.88); backdrop-filter: blur(10px); border-top: 1px solid rgba(148,163,184,.25); display:block; }
    #dragScrollDeliveryContainer::-webkit-scrollbar { display: none !important; }
    #dragScrollDeliveryContainer { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow-x: auto !important; cursor: grab !important; box-shadow: none !important; border: none !important; -webkit-box-shadow: none !important; }
    #dragScrollDeliveryContainer:active { cursor: grabbing !important; }
    #dragScrollDeliveryContainer table { border-collapse: collapse !important; border: none !important; }
    #dragScrollDeliveryContainer table th, #dragScrollDeliveryContainer table td { border-left: none !important; border-right: none !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; }
    .text-white-element { user-select: none; }
    @media (min-width: 992px) { main.content-shift { margin-left: 280px; } }
  </style>
</head>
<body>
  <?php require __DIR__ . '/sidebar.php'; ?>

<main class="content-shift p-4">
    <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
        <div>
            <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Deliveries</h2>
        </div>
        <div>
            <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalDelivery" onclick="openTambahDelivery()">
            <i class="bi bi-plus-circle"></i> Tambah Pengiriman
            </button>
        </div>
        </div>

        <?php if ($flash_message !== null): ?>
        <div class="alert <?= $flash_message['type'] === 'success' ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4 rounded-3 border-0" role="alert" style="background:<?= $flash_message['type'] === 'success' ? 'rgba(34,197,94,.15)' : 'rgba(239,68,68,.15)'; ?>; color:#e5e7eb;">
            <i class="bi <?= $flash_message['type'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?> me-2"></i>
            <?= htmlspecialchars($flash_message['text']) ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div id="dragScrollDeliveryContainer" class="table-responsive rounded-3" style="border: none !important; background: transparent !important;">
        <table class="table table-transparent table-hover align-middle mb-0 text-white-element" style="min-width: 950px; user-select: none;">
            <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important;">
            <tr>
                <th class="py-3 px-3 text-center" style="width: 100px;">ID</th>
                <th class="py-3" style="width: 220px;">Order</th>
                <th class="py-3" style="width: 220px;">Courier</th>
                <th class="py-3 text-center" style="width: 140px;">Status</th>
                <th class="py-3" style="width: 200px;">Pickup Time</th>
                <th class="py-3" style="width: 200px;">Delivery Time</th>
                <th class="py-3" style="width: 220px;">Proof Photo</th>
                <th class="py-3 text-center" style="width: 150px;">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($deliveries)): ?>
                <?php foreach ($deliveries as $row): ?>
                <tr>
                    <td class="text-center fw-semibold" style="color: #94a3b8 !important;"><?= (int)$row['id'] ?></td>
                    <td class="fw-semibold text-white"><?= htmlspecialchars($row['order_number'] ?? '-') ?></td>
                    <td class="text-white-50"><?= htmlspecialchars($row['courier_name'] ?? '-') ?></td>
                    <td class="text-center">
                    <?php
                        $st = trim((string)($row['status'] ?? ''));
                        // Indonesian delivery statuses
                        $statusGreen = ['Terkirim', 'Diambil'];
                        $statusRed = ['Gagal Kirim', 'Dibatalkan'];
                        $statusYellow = ['Pending', 'Dalam Perjalanan', 'Sedang Diantar'];
                        $statusBlue = ['Diproses', 'Dikembalikan'];
                        
                        if (in_array($st, $statusGreen)) {
                            echo '<span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-3 py-1.5 rounded-pill">'.htmlspecialchars($st).'</span>';
                        } elseif (in_array($st, $statusRed)) {
                            echo '<span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-50 px-3 py-1.5 rounded-pill">'.htmlspecialchars($st).'</span>';
                        } elseif (in_array($st, $statusYellow)) {
                            echo '<span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-50 px-3 py-1.5 rounded-pill">'.htmlspecialchars($st).'</span>';
                        } elseif (in_array($st, $statusBlue)) {
                            echo '<span class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-50 px-3 py-1.5 rounded-pill">'.htmlspecialchars($st).'</span>';
                        } else {
                            echo '<span class="badge bg-secondary bg-opacity-25 text-white border border-secondary border-opacity-50 px-3 py-1.5 rounded-pill">'.htmlspecialchars($st ?: '-').'</span>';
                        }
                    ?>
                    </td>
                    <td class="text-white-50"><?= htmlspecialchars($row['pickup_time'] ?? '-') ?></td>
                    <td class="text-white-50"><?= htmlspecialchars($row['delivery_time'] ?? '-') ?></td>
                    <td class="text-white-50">
                        <?php if (!empty($row['proof_photo']) && file_exists($uploadDir . $row['proof_photo'])): ?>
                            <img src="<?= $uploadDir . $row['proof_photo'] ?>" class="rounded-2 border border-secondary" style="max-height: 45px; max-width:100px; object-fit: cover;" alt="Proof Photo">
                        <?php else: ?>
                            <span class="text-muted small"><?= !empty($row['proof_photo']) ? htmlspecialchars($row['proof_photo']) : '-' ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                    <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-sm btn-outline-warning rounded-2" data-bs-toggle="modal" data-bs-target="#modalDelivery"
                            data-id="<?= (int)$row['id'] ?>"
                            data-order-id="<?= (int)$row['order_id'] ?>"
                            data-courier-id="<?= (int)$row['courier_id'] ?>"
                            data-status="<?= htmlspecialchars($row['status'] ?? '') ?>"
                            data-pickup-time="<?= htmlspecialchars($row['pickup_time'] ?? '') ?>"
                            data-delivery-time="<?= htmlspecialchars($row['delivery_time'] ?? '') ?>"
                            data-proof-photo="<?= htmlspecialchars($row['proof_photo'] ?? '') ?>"
                            onclick="openEditDelivery(this)">
                        <i class="bi bi-pencil-square"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-2" data-bs-toggle="modal" data-bs-target="#modalHapusDelivery" onclick="openHapusDelivery(<?= $row['id'] ?>, '<?= htmlspecialchars($row['order_number'] ?? '-') ?>')">
                        <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                <td colspan="8" class="text-center text-white-50 py-5">Belum ada data pengiriman.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>

    </div>

</main>

<!-- Modal Delivery (Create/Update) -->
<div class="modal fade" id="modalDelivery" tabindex="-1" aria-labelledby="modalDeliveryLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.93) !important; backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.2); color: #e5e7eb; border-radius: 16px;">
        <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
            <h5 class="modal-title fw-bold text-white" id="modalDeliveryLabel">Form Data Pengiriman</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <form id="formDelivery" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" id="delivery-id" value="">
            <input type="hidden" name="delete_photo_flag" id="delete-photo-flag" value="0">

            <div class="modal-body" style="overflow: visible !important;">
            <div class="row g-3">
                <div class="col-md-6">
                <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Order <span class="text-danger">*</span></label>
                <select class="form-select" name="order_id" id="delivery-order-id" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                    <option value="" disabled selected>-- Pilih Order --</option>
                    <?php foreach ($orders as $o): ?>
                    <option value="<?= (int)$o['id'] ?>"><?= htmlspecialchars($o['order_number'] ?? ('Order #'.$o['id'])) ?></option>
                    <?php endforeach; ?>
                </select>
                </div>

                <div class="col-md-6">
                <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Courier <span class="text-danger">*</span></label>
                <select class="form-select" name="courier_id" id="delivery-courier-id" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                    <option value="" disabled selected>-- Pilih Kurir --</option>
                    <?php foreach ($couriers as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name'] ?? ('Courier #'.$c['id'])) ?></option>
                    <?php endforeach; ?>
                </select>
                </div>

                <div class="col-md-12">
                <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Status <span class="text-danger">*</span></label>
                <select class="form-select" name="status" id="delivery-status" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                    <option value="Pending">Pending</option>
                    <option value="Diambil">Diambil</option>
                    <option value="Diproses">Diproses</option>
                    <option value="Dalam Perjalanan">Dalam Perjalanan</option>
                    <option value="Sedang Diantar">Sedang Diantar</option>
                    <option value="Terkirim">Terkirim</option>
                    <option value="Gagal Kirim">Gagal Kirim</option>
                    <option value="Dikembalikan">Dikembalikan</option>
                    <option value="Dibatalkan">Dibatalkan</option>
                </select>
                </div>

                <div class="col-md-6">
                <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Pickup Time</label>
                <input type="datetime-local" class="form-control" name="pickup_time" id="delivery-pickup-time" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important; color-scheme: dark;">
                </div>

                <div class="col-md-6">
                <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Delivery Time</label>
                <input type="datetime-local" class="form-control" name="delivery_time" id="delivery-delivery-time" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important; color-scheme: dark;">
                </div>

                <div class="col-md-12">
                <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Proof Photo</label>
                <div class="input-group">
                    <input type="file" class="form-control" name="proof_photo" id="delivery-proof-photo" accept="image/*" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
                    <button class="btn btn-primary px-3" type="button" id="btn-open-camera" title="Ambil dari Kamera">
                        <i class="bi bi-camera-fill"></i>
                    </button>
                    <button class="btn btn-danger px-3" type="button" id="btn-remove-photo" title="Hapus Foto">
                        <i class="bi bi-trash-fill"></i>
                    </button>
                </div>
                <div id="edit-photo-preview-container" class="mt-2" style="display: none;">
                    <span class="text-white-50 small d-block mb-1">Foto saat ini:</span>
                    <img id="edit-photo-preview" src="" class="rounded border" style="width: 80px; height: 80px; object-fit: cover;">
                </div>
                </div>
            </div>
            </div>

            <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.15); background: rgba(15, 23, 42, 0.95); border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success" id="btnSubmitDelivery">Simpan Data</button>
            </div>
        </form>
        </div>
    </div>
</div>

<!-- CONTAINER MODAL KAMERA INTERAKTIF (POP-UP DI ATAS MODAL UTAMA) -->
<div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true" style="z-index: 1060; backdrop-filter: blur(8px);">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white" style="border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 16px;">
      <div class="modal-header border-bottom border-secondary">
        <h6 class="modal-title fw-bold text-white"><i class="bi bi-camera me-2"></i> Kamera Pengiriman</h6>
        <button type="button" class="btn-close btn-close-white" id="btn-close-camera-x"></button>
      </div>
      <div class="modal-body text-center p-0 overflow-hidden bg-black" style="max-height: 400px;">
        <video id="webcamVideo" autoplay playsinline style="width: 100%; height: auto; max-height: 380px; object-fit: cover; transform: scaleX(-1);"></video>
        <canvas id="webcamCanvas" style="display: none;"></canvas>
      </div>
      <div class="modal-footer border-top border-secondary d-flex justify-content-center gap-2">
        <button type="button" class="btn btn-outline-light btn-sm px-3" id="btn-cancel-camera">Batal</button>
        <button type="button" class="btn btn-success btn-sm px-4 fw-medium" id="btn-capture-photo"><i class="bi bi-camera me-1"></i> Ambil Gambar</button>
      </div>
    </div>
  </div>
</div>

<!--Modal Hapus -->
<div class="modal fade" id="modalHapusDelivery" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
    <div class="modal-content text-white rounded-4 border-0" style="background: #111827; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-triangle-fill"></i> Hapus Pengiriman
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
      </div>
      <div class="modal-body py-3">
        <p class="text-white-50 m-0" style="font-size: 0.95rem; line-height: 1.5;">Apakah Anda yakin ingin menghapus data pengiriman untuk order <strong id="txtDeleteOrderInfo" class="text-white">-</strong>?</p>
      </div>
      <div class="modal-footer border-0 pt-0 d-flex gap-2">
        <button type="button" class="btn btn-sm rounded-pill px-4 fw-medium text-white border-0" data-bs-dismiss="modal" style="background: #1f2937;">Batal</button>
        <a id="btnConfirmDeleteDelivery" href="#" class="btn btn-danger btn-sm rounded-pill px-4 fw-medium shadow-sm">Ya, Hapus</a>
      </div>
    </div>
  </div>
</div>

<script>
    let streamInstance = null;
    let cameraModalObj = null;
    let videoEl = null;
    let canvasEl = null;
    let fileInputEl = null;

    document.addEventListener('DOMContentLoaded', function() {
        const deliverySlider = document.getElementById('dragScrollDeliveryContainer');
        if (!deliverySlider) return;
        
        let isDown = false, startX, scrollLeft;
        
        deliverySlider.addEventListener('mousedown', (e) => {
            if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input')) return;
            isDown = true; 
            deliverySlider.style.cursor = 'grabbing';
            startX = e.pageX - deliverySlider.offsetLeft; 
            scrollLeft = deliverySlider.scrollLeft;
        });
        
        deliverySlider.addEventListener('mouseleave', () => { isDown = false; deliverySlider.style.cursor = 'grab'; });
        deliverySlider.addEventListener('mouseup', () => { isDown = false; deliverySlider.style.cursor = 'grab'; });
        
        deliverySlider.addEventListener('mousemove', (e) => {
            if (!isDown) return; 
            e.preventDefault();
            const x = e.pageX - deliverySlider.offsetLeft;
            deliverySlider.scrollLeft = scrollLeft - ((x - startX) * 1.5);
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        const cameraModalEl = document.getElementById('cameraModal');
        if (cameraModalEl) {
            cameraModalObj = new bootstrap.Modal(cameraModalEl);
            videoEl = document.getElementById('webcamVideo');
            canvasEl = document.getElementById('webcamCanvas');
            fileInputEl = document.getElementById('delivery-proof-photo');

            const btnOpenCamera = document.getElementById('btn-open-camera');
            if (btnOpenCamera) {
                btnOpenCamera.addEventListener('click', async () => {
                    try {
                        streamInstance = await navigator.mediaDevices.getUserMedia({ 
                            video: { facingMode: "environment" }, 
                            audio: false 
                        });
                        if (videoEl) videoEl.srcObject = streamInstance;
                        cameraModalObj.show();
                    } catch (err) {
                        alert("Gagal mengakses kamera: Pastikan izin kamera browser Anda telah diizinkan.");
                    }
                });
            }

            const btnCapturePhoto = document.getElementById('btn-capture-photo');
            if (btnCapturePhoto) {
                btnCapturePhoto.addEventListener('click', () => {
                    if (!streamInstance || !canvasEl || !videoEl || !fileInputEl) return;

                    canvasEl.width = videoEl.videoWidth;
                    canvasEl.height = videoEl.videoHeight;
                    
                    const ctx = canvasEl.getContext('2d');
                    ctx.translate(canvasEl.width, 0);
                    ctx.scale(-1, 1);
                    ctx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);

                    canvasEl.toBlob((blob) => {
                        if (blob) {
                            const capturedFile = new File([blob], `proof_${Date.now()}.jpg`, { type: "image/jpeg" });
                            
                            const dataTransferContainer = new DataTransfer();
                            dataTransferContainer.items.add(capturedFile);
                            fileInputEl.files = dataTransferContainer.files;

                            fileInputEl.dispatchEvent(new Event('change'));
                            document.getElementById('delete-photo-flag').value = "0";
                            
                            stopCameraStream();
                        }
                    }, 'image/jpeg', 0.9);
                });
            }

            const btnCancelCamera = document.getElementById('btn-cancel-camera');
            const btnCloseCameraX = document.getElementById('btn-close-camera-x');
            if (btnCancelCamera) btnCancelCamera.addEventListener('click', stopCameraStream);
            if (btnCloseCameraX) btnCloseCameraX.addEventListener('click', stopCameraStream);
        }

        const btnRemovePhoto = document.getElementById('btn-remove-photo');
        if (btnRemovePhoto) {
            btnRemovePhoto.addEventListener('click', function() {
                if (fileInputEl) fileInputEl.value = "";
                document.getElementById('delete-photo-flag').value = "1";
                const previewContainer = document.getElementById('edit-photo-preview-container');
                if (previewContainer) previewContainer.style.display = "none";
            });
        }

        // Live preview ketika user memilih file foto baru
        const proofPhotoInput = document.getElementById('delivery-proof-photo');
        if (proofPhotoInput) {
            proofPhotoInput.addEventListener('change', function(e) {
                const previewContainer = document.getElementById('edit-photo-preview-container');
                const previewImg = document.getElementById('edit-photo-preview');
                
                if (this.files && this.files.length > 0) {
                    const file = this.files[0];
                    const reader = new FileReader();
                    reader.onload = function(evt) {
                        if (previewImg) previewImg.src = evt.target.result;
                        if (previewContainer) previewContainer.style.display = "block";
                    };
                    reader.readAsDataURL(file);
                    document.getElementById('delete-photo-flag').value = "0";
                }
            });
        }
    });

    function stopCameraStream() {
        if (streamInstance) {
            streamInstance.getTracks().forEach(track => track.stop());
            streamInstance = null;
        }
        if (videoEl) videoEl.srcObject = null;
        if (cameraModalObj) cameraModalObj.hide();
    }

    function openTambahDelivery() {
        document.getElementById('formDelivery').reset();
        document.getElementById('formDelivery').action = 'deliveries.php?action=create';
        document.getElementById('modalDeliveryLabel').innerText = 'Tambah Data Pengiriman';
        document.getElementById('delivery-id').value = '';
        document.getElementById('delete-photo-flag').value = "0";
        document.getElementById('btnSubmitDelivery').className = 'btn btn-success';
        document.getElementById('btnSubmitDelivery').innerText = 'Simpan Data';
        document.getElementById('btnSubmitDelivery').disabled = false;
        
        const previewContainer = document.getElementById('edit-photo-preview-container');
        if (previewContainer) previewContainer.style.display = "none";
        
        if (streamInstance) stopCameraStream();
    }

    function openEditDelivery(btn) {
        const data = {
            id: btn.getAttribute('data-id'),
            order_id: btn.getAttribute('data-order-id'),
            courier_id: btn.getAttribute('data-courier-id'),
            status: btn.getAttribute('data-status'),
            pickup_time: btn.getAttribute('data-pickup-time'),
            delivery_time: btn.getAttribute('data-delivery-time'),
            proof_photo: btn.getAttribute('data-proof-photo')
        };

        document.getElementById('formDelivery').reset();
        document.getElementById('formDelivery').action = 'deliveries.php?action=update';
        document.getElementById('modalDeliveryLabel').innerText = 'Perbarui Data Pengiriman';
        document.getElementById('delivery-id').value = data.id;
        document.getElementById('delivery-order-id').value = data.order_id;
        document.getElementById('delivery-courier-id').value = data.courier_id;
        document.getElementById('delivery-status').value = data.status || '';
        document.getElementById('delete-photo-flag').value = "0";

        if (data.pickup_time) {
            document.getElementById('delivery-pickup-time').value = data.pickup_time.replace(' ', 'T').substring(0, 16);
        } else {
            document.getElementById('delivery-pickup-time').value = '';
        }
        if (data.delivery_time) {
            document.getElementById('delivery-delivery-time').value = data.delivery_time.replace(' ', 'T').substring(0, 16);
        } else {
            document.getElementById('delivery-delivery-time').value = '';
        }

        const previewContainer = document.getElementById('edit-photo-preview-container');
        const previewImg = document.getElementById('edit-photo-preview');
        const uploadDir = 'uploads/deliveries/';
        
        if (data.proof_photo && data.proof_photo.trim() !== "" && data.proof_photo !== "null") {
            previewImg.src = uploadDir + data.proof_photo;
            previewContainer.style.display = "block";
        } else {
            previewImg.src = "";
            previewContainer.style.display = "none";
        }

        document.getElementById('btnSubmitDelivery').className = 'btn btn-warning text-dark fw-medium';
        document.getElementById('btnSubmitDelivery').innerText = 'Perbarui Data';
        document.getElementById('btnSubmitDelivery').disabled = false;
    }

    function openHapusDelivery(id, orderNumber) {
        const btnConfirmDelete = document.getElementById('btnConfirmDeleteDelivery');
        const txtOrderInfo = document.getElementById('txtDeleteOrderInfo');
        
        if (btnConfirmDelete) {
            btnConfirmDelete.href = 'deliveries.php?action=delete&id=' + id;
        }
        if (txtOrderInfo) {
            txtOrderInfo.innerText = orderNumber;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('formDelivery');
        if (form) {
            form.addEventListener('submit', function(e) {
                const btn = document.getElementById('btnSubmitDelivery');
                if (btn.disabled) {
                    e.preventDefault();
                    return false;
                }
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Memproses...';
            });
        }
    });
</script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


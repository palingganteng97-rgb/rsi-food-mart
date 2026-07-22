<?php
include 'db.php';
include 'notification_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id      = intval($_POST['order_id'] ?? 0);
    $courier_id    = intval($_POST['courier_id'] ?? 0);
    $status        = trim($_POST['status'] ?? 'PENDING');
    $pickup_time   = !empty($_POST['pickup_time']) ? $_POST['pickup_time'] : null;
    $delivery_time = !empty($_POST['delivery_time']) ? $_POST['delivery_time'] : null;
    $proof_photo   = trim($_POST['proof_photo'] ?? '');

    if ($order_id > 0 && $courier_id > 0) {
        // CEK DUPLIKAT: Pastikan order_id belum memiliki data delivery
        $checkStmt = $conn->prepare("SELECT id FROM deliveries WHERE order_id = ? LIMIT 1");
        $checkStmt->bind_param("i", $order_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult && $checkResult->num_rows > 0) {
            error_log("[Deliveries] DUPLICATE BLOCKED: order_id $order_id already has a delivery record");
            header("Location: deliveries.php?status=error&msg=" . urlencode("Pengiriman untuk pesanan ini sudah tersedia."));
            exit();
        }
        $checkStmt->close();

        $stmt = $conn->prepare("INSERT INTO deliveries (order_id, courier_id, status, pickup_time, delivery_time, proof_photo) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $order_id, $courier_id, $status, $pickup_time, $delivery_time, $proof_photo);
        
        if ($stmt->execute()) {
            error_log("[Deliveries] INSERT success: delivery created for order_id $order_id");
            // Patient notification is NOT sent on initial creation.
            // Patient will see the initial 'Pending' status in their order history.
            // Notifications are triggered only when the delivery status changes (see action=update).

            header("Location: deliveries.php?status=success_create");
        } else {
            header("Location: deliveries.php?status=error&msg=" . urlencode($stmt->error));
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
    $proof_photo   = trim($_POST['proof_photo'] ?? '');

    if ($id > 0 && $order_id > 0 && $courier_id > 0) {
        // === GET OLD STATUS BEFORE UPDATE (to detect changes) ===
        $oldStatus = '';
        $oldOrderId = 0;
        $oldStmt = $conn->prepare("SELECT status, order_id FROM deliveries WHERE id = ? LIMIT 1");
        if ($oldStmt) {
            $oldStmt->bind_param("i", $id);
            $oldStmt->execute();
            $oldRes = $oldStmt->get_result();
            if ($oldRow = $oldRes->fetch_assoc()) {
                $oldStatus = trim($oldRow['status'] ?? '');
                $oldOrderId = (int)($oldRow['order_id'] ?? 0);
            }
            $oldStmt->close();
        }

        $stmt = $conn->prepare("UPDATE deliveries SET order_id = ?, courier_id = ?, status = ?, pickup_time = ?, delivery_time = ?, proof_photo = ? WHERE id = ?");
        $stmt->bind_param("iissssi", $order_id, $courier_id, $status, $pickup_time, $delivery_time, $proof_photo, $id);
        
        if ($stmt->execute()) {
            // --- DEBUG: Log delivery update ---
            error_log("[DELIVERY UPDATE] id=$id, order_id=$order_id, old_status=$oldStatus, new_status=$status, changed=" . (strcasecmp($oldStatus, $status) !== 0 ? 'yes' : 'no'));

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

                    // --- DEBUG: Log patient data ---
                    error_log("[DELIVERY NOTIFICATION DEBUG] order_id=$oldOrderId, order_number=$orderNumberFull, patient_session_id=$patientSessionId, user_type=patient, user_reference=$patientSessionId");

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
                            $insertedId = createNotification(
                                'patient',
                                $patientSessionId,
                                $notifTitle,
                                $notifMessage,
                                $notifLink
                            );

                            // --- DEBUG: Log notification insert result ---
                            error_log("[DELIVERY NOTIFICATION INSERT] result_id=" . ($insertedId !== false ? $insertedId : 'FAILED') . ", user_type=patient, user_reference=$patientSessionId, title=$notifTitle, link=$notifLink");
                        } else {
                            error_log("[DELIVERY NOTIFICATION SKIP] Duplicate notification already exists for order_id=$oldOrderId, status=$status");
                        }
                    } else {
                        error_log("[DELIVERY NOTIFICATION ERROR] patient_session_id is 0 or empty for order_id=$oldOrderId");
                    }
                } else {
                    error_log("[DELIVERY NOTIFICATION ERROR] No order found for id=$oldOrderId");
                }
                $orderStmt->close();
            } else {
                error_log("[DELIVERY NOTIFICATION SKIP] Status unchanged or no order_id. changed=" . (strcasecmp($oldStatus, $status) !== 0 ? 'yes' : 'no') . ", oldOrderId=$oldOrderId");
            }

            header("Location: deliveries.php?status=success_update");
        } else {
            header("Location: deliveries.php?status=error&msg=" . urlencode($stmt->error));
        }
        exit();
    }
}

if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id'] ?? 0);

    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM deliveries WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // No notification sent on delete - delivery status changes only notify patients on update.
            header("Location: deliveries.php?status=success_delete");
        } else {
            header("Location: deliveries.php?status=error&msg=" . urlencode($stmt->error));
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

$status = isset($_GET['status']) ? (string)$_GET['status'] : '';
$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';

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

        <?php if (!empty($status)): ?>
        <div class="alert <?= strpos($status, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4 rounded-3 border-0" role="alert" style="background:<?= strpos($status, 'success') !== false ? 'rgba(34,197,94,.15)' : 'rgba(239,68,68,.15)'; ?>; color:#e5e7eb;">
            <i class="bi <?= strpos($status, 'success') !== false ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?> me-2"></i>
            <?php 
                if ($status === 'success_create') echo "Data pengiriman berhasil ditambahkan!";
                elseif ($status === 'success_update') echo "Data pengiriman berhasil diperbarui!";
                elseif ($status === 'success_delete') echo "Data pengiriman berhasil dihapus!";
                elseif ($status === 'error') echo "Operasi gagal: " . htmlspecialchars($msg);
                else echo "Operasi: " . htmlspecialchars($status);
            ?>
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
                    <td class="text-white-50"><?= htmlspecialchars($row['proof_photo'] ?? '-') ?></td>
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
                <input type="file" class="form-control" name="proof_photo" id="delivery-proof-photo" accept="image/*" capture="environment" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
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
document.addEventListener('DOMContentLoaded', function() {
    // DISESUAIKAN: Mengganti ID menjadi dragScrollDeliveryContainer agar pas dengan HTML tabel pengiriman
    const deliverySlider = document.getElementById('dragScrollDeliveryContainer');
    if (!deliverySlider) return;
    
    let isDown = false, startX, scrollLeft;
    
    deliverySlider.addEventListener('mousedown', (e) => {
        // Mencegah gangguan geser jika admin mengklik tombol edit atau hapus di dalam tabel
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
        // Kalikan 1.5 atau 2 jika ingin pergeseran tabel terasa lebih cepat dan ringan
        deliverySlider.scrollLeft = scrollLeft - ((x - startX) * 1.5);
    });
});

function openTambahDelivery() {
    document.getElementById('formDelivery').reset();
    document.getElementById('formDelivery').action = 'deliveries.php?action=create';
    document.getElementById('modalDeliveryLabel').innerText = 'Tambah Data Pengiriman';
    document.getElementById('delivery-id').value = '';
    document.getElementById('btnSubmitDelivery').className = 'btn btn-success';
    document.getElementById('btnSubmitDelivery').innerText = 'Simpan Data';
    document.getElementById('btnSubmitDelivery').disabled = false;
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

    console.log('Delivery ID:', data.id);
    console.log('Data Delivery:', data);

    document.getElementById('formDelivery').reset();
    document.getElementById('formDelivery').action = 'deliveries.php?action=update';
    document.getElementById('modalDeliveryLabel').innerText = 'Perbarui Data Pengiriman';
    document.getElementById('delivery-id').value = data.id;
    document.getElementById('delivery-order-id').value = data.order_id;
    document.getElementById('delivery-courier-id').value = data.courier_id;
    document.getElementById('delivery-status').value = data.status || '';

    // Convert datetime from MySQL format (YYYY-MM-DD HH:MM:SS) to browser format (YYYY-MM-DDTHH:MM)
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

    document.getElementById('btnSubmitDelivery').className = 'btn btn-warning text-dark fw-medium';
    document.getElementById('btnSubmitDelivery').innerText = 'Perbarui Data';
    document.getElementById('btnSubmitDelivery').disabled = false;
}

// FUNGSI BARU: Untuk mengisi data ke Modal Konfirmasi Hapus secara dinamis
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

// CEK GANDA: Mencegah double submit pada form delivery
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formDelivery');
    if (form) {
        form.addEventListener('submit', function(e) {
            const btn = document.getElementById('btnSubmitDelivery');
            if (btn.disabled) {
                e.preventDefault();
                console.log('[Deliveries] Double submit prevented');
                return false;
            }
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Memproses...';
            console.log('[Deliveries] Form submitted, button disabled');
        });
    }
});
</script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


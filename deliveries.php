<?php
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

// 1. PROSES TAMBAH DATA (CREATE) — DENGAN VALIDASI FK & TRANSACTION
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id      = intval($_POST['order_id'] ?? 0);
    $courier_id    = intval($_POST['courier_id'] ?? 0);
    $status        = trim($_POST['status'] ?? 'PENDING');
    $pickup_time   = !empty($_POST['pickup_time']) ? $_POST['pickup_time'] : null;
    $delivery_time = !empty($_POST['delivery_time']) ? $_POST['delivery_time'] : null;
    $proof_photo   = trim($_POST['proof_photo'] ?? '');

    $errors = [];
    if ($order_id <= 0) {
        $errors[] = 'order_id tidak valid';
    }
    if ($courier_id <= 0) {
        $errors[] = 'courier_id tidak valid';
    }

    // Validasi Foreign Key: order_id harus ada di tabel orders
    if ($order_id > 0) {
        $chkOrder = $conn->prepare("SELECT id FROM orders WHERE id = ?");
        $chkOrder->bind_param("i", $order_id);
        $chkOrder->execute();
        if ($chkOrder->get_result()->num_rows === 0) {
            $errors[] = "order_id ($order_id) tidak ditemukan di tabel orders. Foreign Key violation.";
        }
        $chkOrder->close();
    }

    // Validasi Foreign Key: courier_id harus ada di tabel couriers
    if ($courier_id > 0) {
        $chkCourier = $conn->prepare("SELECT id FROM couriers WHERE id = ?");
        $chkCourier->bind_param("i", $courier_id);
        $chkCourier->execute();
        if ($chkCourier->get_result()->num_rows === 0) {
            $errors[] = "courier_id ($courier_id) tidak ditemukan di tabel couriers. Foreign Key violation.";
        }
        $chkCourier->close();
    }

    // Cegah duplikasi: cek apakah sudah ada delivery untuk order_id ini
    if ($order_id > 0) {
        $chkDup = $conn->prepare("SELECT id FROM deliveries WHERE order_id = ? LIMIT 1");
        $chkDup->bind_param("i", $order_id);
        $chkDup->execute();
        if ($chkDup->get_result()->num_rows > 0) {
            $errors[] = "Delivery untuk order_id ($order_id) sudah ada. Tidak boleh duplikat.";
        }
        $chkDup->close();
    }

    if (!empty($errors)) {
        $errMsg = implode(' | ', $errors);
        header("Location: deliveries.php?status=error&msg=" . urlencode($errMsg));
        exit();
    }

    // Gunakan TRANSACTION agar data konsisten
    mysqli_begin_transaction($conn);
    try {
        $stmt = $conn->prepare("INSERT INTO deliveries (order_id, courier_id, status, pickup_time, delivery_time, proof_photo) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $order_id, $courier_id, $status, $pickup_time, $delivery_time, $proof_photo);
        
        if (!$stmt->execute()) {
            throw new Exception("SQL INSERT error: " . $stmt->error);
        }
        
        mysqli_commit($conn);
        header("Location: deliveries.php?status=success_create");
    } catch (Exception $e) {
        mysqli_rollback($conn);
        header("Location: deliveries.php?status=error&msg=" . urlencode($e->getMessage()));
    }
    exit();
}

// 2. PROSES UBAH DATA (UPDATE) — DENGAN VALIDASI FK & TRANSACTION
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id            = intval($_POST['id'] ?? 0);
    $order_id      = intval($_POST['order_id'] ?? 0);
    $courier_id    = intval($_POST['courier_id'] ?? 0);
    $status        = trim($_POST['status'] ?? 'PENDING');
    $pickup_time   = !empty($_POST['pickup_time']) ? $_POST['pickup_time'] : null;
    $delivery_time = !empty($_POST['delivery_time']) ? $_POST['delivery_time'] : null;
    $proof_photo   = trim($_POST['proof_photo'] ?? '');

    $errors = [];
    if ($id <= 0) {
        $errors[] = 'id delivery tidak valid';
    }
    if ($order_id <= 0) {
        $errors[] = 'order_id tidak valid';
    }
    if ($courier_id <= 0) {
        $errors[] = 'courier_id tidak valid';
    }

    // Validasi Foreign Key
    if ($order_id > 0) {
        $chkOrder = $conn->prepare("SELECT id FROM orders WHERE id = ?");
        $chkOrder->bind_param("i", $order_id);
        $chkOrder->execute();
        if ($chkOrder->get_result()->num_rows === 0) {
            $errors[] = "order_id ($order_id) tidak ditemukan di tabel orders. Foreign Key violation.";
        }
        $chkOrder->close();
    }
    if ($courier_id > 0) {
        $chkCourier = $conn->prepare("SELECT id FROM couriers WHERE id = ?");
        $chkCourier->bind_param("i", $courier_id);
        $chkCourier->execute();
        if ($chkCourier->get_result()->num_rows === 0) {
            $errors[] = "courier_id ($courier_id) tidak ditemukan di tabel couriers. Foreign Key violation.";
        }
        $chkCourier->close();
    }
    // Cek apakah record delivery dengan id ini ada
    if ($id > 0) {
        $chkDel = $conn->prepare("SELECT id FROM deliveries WHERE id = ?");
        $chkDel->bind_param("i", $id);
        $chkDel->execute();
        if ($chkDel->get_result()->num_rows === 0) {
            $errors[] = "Delivery dengan id ($id) tidak ditemukan.";
        }
        $chkDel->close();
    }

    if (!empty($errors)) {
        $errMsg = implode(' | ', $errors);
        header("Location: deliveries.php?status=error&msg=" . urlencode($errMsg));
        exit();
    }

    // Gunakan TRANSACTION
    mysqli_begin_transaction($conn);
    try {
        $stmt = $conn->prepare("UPDATE deliveries SET order_id = ?, courier_id = ?, status = ?, pickup_time = ?, delivery_time = ?, proof_photo = ? WHERE id = ?");
        $stmt->bind_param("iissssi", $order_id, $courier_id, $status, $pickup_time, $delivery_time, $proof_photo, $id);
        
        if (!$stmt->execute()) {
            throw new Exception("SQL UPDATE error: " . $stmt->error);
        }
        
        mysqli_commit($conn);
        header("Location: deliveries.php?status=success_update");
    } catch (Exception $e) {
        mysqli_rollback($conn);
        header("Location: deliveries.php?status=error&msg=" . urlencode($e->getMessage()));
    }
    exit();
}

// 3. PROSES HAPUS DATA (DELETE) — DENGAN TRANSACTION
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id'] ?? 0);

    $errors = [];
    if ($id <= 0) {
        $errors[] = 'id delivery tidak valid';
    }

    // Cek apakah record delivery dengan id ini ada
    if ($id > 0) {
        $chkDel = $conn->prepare("SELECT id FROM deliveries WHERE id = ?");
        $chkDel->bind_param("i", $id);
        $chkDel->execute();
        $chkRes = $chkDel->get_result();
        if ($chkRes->num_rows === 0) {
            $errors[] = "Delivery dengan id ($id) tidak ditemukan.";
        }
        $chkDel->close();
    }

    if (!empty($errors)) {
        $errMsg = implode(' | ', $errors);
        header("Location: deliveries.php?status=error&msg=" . urlencode($errMsg));
        exit();
    }

    // Gunakan TRANSACTION
    mysqli_begin_transaction($conn);
    try {
        // Hapus data tracking terkait terlebih dahulu (FK constraint)
        $delTrack = $conn->prepare("DELETE FROM delivery_tracking WHERE delivery_id = ?");
        $delTrack->bind_param("i", $id);
        $delTrack->execute();
        $delTrack->close();

        // Hapus delivery
        $stmt = $conn->prepare("DELETE FROM deliveries WHERE id = ?");
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("SQL DELETE error: " . $stmt->error);
        }
        $stmt->close();

        mysqli_commit($conn);
        header("Location: deliveries.php?status=success_delete");
    } catch (Exception $e) {
        mysqli_rollback($conn);
        header("Location: deliveries.php?status=error&msg=" . urlencode($e->getMessage()));
    }
    exit();
}

// 4. PROSES READ DATA DENGAN JOIN (Untuk Relasi Nama Kurir & Invoice Pesanan)
$deliveries = [];
$sql = "SELECT d.*, o.order_number, c.name AS courier_name 
        FROM deliveries d
        LEFT JOIN orders o ON d.order_id = o.id
        LEFT JOIN couriers c ON d.courier_id = c.id
        ORDER BY d.id DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $deliveries[] = $row;
    }
}

$status = isset($_GET['status']) ? (string)$_GET['status'] : '';
$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';

// Data dropdown untuk form (dibaca untuk tampilan saja)
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
  <title>Etalase Menu - RSI Food &amp; Mart</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

  <style>
    :root { --bg:#0f172a; --text:#e5e7eb; --muted:#94a3b8; --green:#22c55e; }
    body { background:var(--bg) !important; color:var(--text); }
    bottom-nav { position: fixed; left:0; right:0; bottom:0; z-index: 1035; background: rgba(15,23,42,.88); backdrop-filter: blur(10px); border-top: 1px solid rgba(148,163,184,.25); display:block; }
    #dragScrollDeliveryContainer::-webkit-scrollbar { display: none !important; }
    #dragScrollDeliveryContainer { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow-x: auto !important; cursor: grab !important; box-shadow: none !important; border: none !important; -webkit-box-shadow: none !important; }
    #dragScrollDeliveryContainer:active { cursor: grabbing !important; }
    #dragScrollDeliveryContainer table { border-collapse: collapse !important; border: none !important; }
    #dragScrollDeliveryContainer table th, #dragScrollDeliveryContainer table td { border-left: none !important; border-right: none !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; }
    .text-white-element { user-select: none; }
    @media (min-width: 992px) { main.content-shift { margin-left: 280px; } }
    
    /* PERBAIKAN 1: Background baris tabel abu-abu gelap transparan */
    #dragScrollDeliveryContainer table tbody tr { background: rgba(31, 41, 55, 0.5) !important; transition: background 0.15s ease; }
    #dragScrollDeliveryContainer table tbody tr:hover { background: rgba(31, 41, 55, 0.8) !important; }
    #dragScrollDeliveryContainer table tbody tr td { background: transparent !important; color: #e5e7eb !important; }
    #dragScrollDeliveryContainer table tbody tr:nth-child(even) { background: rgba(31, 41, 55, 0.3) !important; }
    #dragScrollDeliveryContainer table tbody tr:nth-child(even):hover { background: rgba(31, 41, 55, 0.7) !important; }
    
    /* PERBAIKAN 2: Semua label form di dalam modal berwarna putih */
    #modalDelivery .form-label,
    #modalHapusDelivery .form-label {
      color: #ffffff !important;
    }
    
    /* PERBAIKAN 3: Input datetime-local — teks putih, ikon kalender kontras */
    input[type="datetime-local"] {
      color-scheme: dark !important;
    }
    input[type="datetime-local"]::-webkit-calendar-picker-indicator {
      filter: invert(1) brightness(200%) contrast(100%) !important;
      cursor: pointer !important;
      opacity: 1 !important;
    }
    input[type="datetime-local"]::-webkit-datetime-edit {
      color: #e5e7eb !important;
    }
    input[type="datetime-local"]::-webkit-datetime-edit-fields-wrapper {
      color: #e5e7eb !important;
    }
    input[type="datetime-local"]::-webkit-datetime-edit-text {
      color: #94a3b8 !important;
    }
    input[type="datetime-local"]::-webkit-datetime-edit-month-field,
    input[type="datetime-local"]::-webkit-datetime-edit-day-field,
    input[type="datetime-local"]::-webkit-datetime-edit-year-field,
    input[type="datetime-local"]::-webkit-datetime-edit-hour-field,
    input[type="datetime-local"]::-webkit-datetime-edit-minute-field {
      color: #e5e7eb !important;
      background: transparent !important;
    }
    input[type="datetime-local"]:focus {
      color: #e5e7eb !important;
    }
    /* Fallback untuk Firefox */
    input[type="datetime-local"] {
      color: #e5e7eb !important;
    }
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
        <div class="alert <?= strpos($status, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
            <strong>
            <?php 
                if ($status === 'success_create') echo "Data pengiriman berhasil ditambahkan!";
                elseif ($status === 'success_update') echo "Data pengiriman berhasil diperbarui!";
                elseif ($status === 'success_delete') echo "Data pengiriman berhasil dihapus!";
                elseif ($status === 'error') echo "Operasi gagal: " . htmlspecialchars($msg);
                else echo "Operasi: " . htmlspecialchars($status);
            ?>
            </strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div id="dragScrollDeliveryContainer" class="table-responsive rounded-3" style="border: none !important; background: transparent !important;">
        <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 950px; user-select: none; border-collapse: collapse !important;">
            <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
            <tr>
                <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 100px;">ID</th>
                <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 220px;">Order</th>
                <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 220px;">Courier</th>
                <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 140px;">Status</th>
                <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 200px;">Pickup Time</th>
                <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 200px;">Delivery Time</th>
                <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 220px;">Proof Photo</th>
                <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;">Aksi</th>
            </tr>
            </thead>
            <tbody style="background: transparent !important;">
            <?php if (!empty($deliveries)): ?>
                <?php foreach ($deliveries as $row): ?>
                <tr style="background: transparent !important; font-size: 0.88rem;">
                    <td class="text-center fw-semibold" style="color: #94a3b8 !important;"><?= (int)$row['id'] ?></td>
                    <td class="fw-semibold text-white"><?= htmlspecialchars($row['order_number'] ?? '-') ?></td>
                    <td class="text-white-50"><?= htmlspecialchars($row['courier_name'] ?? '-') ?></td>
                    <td class="text-center">
                    <?php
                        $st = strtoupper(trim((string)($row['status'] ?? '')));
                        if ($st === 'DELIVERED' || $st === 'DONE') {
                        echo '<span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-3 py-1.5 rounded-pill">Delivered</span>';
                        } elseif ($st === 'ON_PROGRESS' || $st === 'PICKUP' || $st === 'IN_PROGRESS') {
                        echo '<span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-50 px-3 py-1.5 rounded-pill">On Progress</span>';
                        } else {
                        echo '<span class="badge bg-secondary bg-opacity-25 text-white border border-secondary border-opacity-50 px-3 py-1.5 rounded-pill">'.htmlspecialchars($row['status'] ?? '-').'</span>';
                        }
                    ?>
                    </td>
                    <td class="text-white-50"><?= htmlspecialchars($row['pickup_time'] ?? '-') ?></td>
                    <td class="text-white-50"><?= htmlspecialchars($row['delivery_time'] ?? '-') ?></td>
                    <td class="text-white-50"><?= htmlspecialchars($row['proof_photo'] ?? '-') ?></td>
                    <td class="text-center">
                    <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-sm btn-outline-warning rounded-2" data-bs-toggle="modal" data-bs-target="#modalDelivery" onclick='openEditDelivery(<?= json_encode($row) ?>)'>
                        <i class="bi bi-pencil-square"></i>
                        </button>
                        <!-- Perbaikan Utama: Mengganti tag <a> menjadi tombol pemicu modal konfirmasi hapus -->
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-2" data-bs-toggle="modal" data-bs-target="#modalHapusDelivery" onclick="openHapusDelivery(<?= $row['id'] ?>, '<?= htmlspecialchars($row['order_number'] ?? '-') ?>')">
                        <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                <td colspan="8" class="text-center py-5 text-muted italic" style="background: transparent !important; border: none !important;">Belum ada data pengiriman.</td>
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

    <form id="formDelivery" action="proses_tambah_pengiriman.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" id="delivery-action" value="create">
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
                <option value="" disabled selected>-- Pilih Status --</option>
                <option value="PENDING">Pending</option>
                <option value="ON_PROGRESS">Dalam Perjalanan</option>
                <option value="DELIVERED">Sampai Tujuan</option>
            </select>
            </div>

            <div class="col-md-6">
            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Pickup Time</label>
            <input type="datetime-local" class="form-control" name="pickup_time" id="delivery-pickup-time" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
            </div>

            <div class="col-md-6">
            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Delivery Time</label>
            <input type="datetime-local" class="form-control" name="delivery_time" id="delivery-delivery-time" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
            </div>

            <div class="col-md-12">
            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Proof Photo</label>
            <input type="file" class="form-control" name="proof_photo" id="delivery-proof-photo" accept=".jpg,.jpeg,.png" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
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

<!-- KOMPONEN BARU: Struktur Modal Pop-Up Konfirmasi Hapus Data Pengiriman -->
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
    document.getElementById('modalDeliveryLabel').innerText = 'Tambah Data Pengiriman';
    document.getElementById('delivery-id').value = '';
    document.getElementById('delivery-action').value = 'create';
    document.getElementById('btnSubmitDelivery').className = 'btn btn-success';
    document.getElementById('btnSubmitDelivery').innerText = 'Simpan Data';
}

function openEditDelivery(data) {
    document.getElementById('formDelivery').reset();
    document.getElementById('modalDeliveryLabel').innerText = 'Perbarui Data Pengiriman';
    document.getElementById('delivery-id').value = data.id;
    document.getElementById('delivery-action').value = 'update';

    document.getElementById('delivery-order-id').value = data.order_id;
    document.getElementById('delivery-courier-id').value = data.courier_id;
    document.getElementById('delivery-status').value = data.status || '';
    document.getElementById('delivery-proof-photo').value = data.proof_photo || '';

    // datetime-local expects format YYYY-MM-DDTHH:MM; jika DB simpan berbeda, biarkan kosong.
    document.getElementById('delivery-pickup-time').value = (data.pickup_time || '').replace(' ', 'T');
    document.getElementById('delivery-delivery-time').value = (data.delivery_time || '').replace(' ', 'T');

    document.getElementById('btnSubmitDelivery').className = 'btn btn-warning text-dark fw-medium';
    document.getElementById('btnSubmitDelivery').innerText = 'Perbarui Data';
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

// Hapus query string ?status=...&msg=... dari URL bar tanpa refresh halaman
// Mencegah parameter status/error tertinggal saat user refresh (F5)
(function() {
    const currentUrl = new URL(window.location.href);
    if (currentUrl.searchParams.has('status') || currentUrl.searchParams.has('msg')) {
        // Buat URL bersih tanpa query string
        const cleanUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;
        // Ganti URL di address bar tanpa reload halaman
        window.history.replaceState({}, '', cleanUrl);
    }
})();
</script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


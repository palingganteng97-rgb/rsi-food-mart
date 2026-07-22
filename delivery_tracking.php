<?php
include 'db.php';
include 'notification_helper.php'; // INTEGRASI: Menyertakan fungsi pembuat notifikasi

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

// 1. PROSES TAMBAH LOG PELACAKAN (CREATE)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $delivery_id     = intval($_POST['delivery_id'] ?? 0);
    $latitude        = isset($_POST['latitude']) ? floatval($_POST['latitude']) : 0.0;
    $longitude       = isset($_POST['longitude']) ? floatval($_POST['longitude']) : 0.0;
    $tracking_status = trim($_POST['tracking_status'] ?? 'ON_PROGRESS');

    if ($delivery_id > 0) {
        $stmt = $conn->prepare("INSERT INTO delivery_tracking (delivery_id, latitude, longitude, tracking_status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("idds", $delivery_id, $latitude, $longitude, $tracking_status);
        
        if ($stmt->execute()) {
            // INTEGRASI: Ambil nomor pesanan terkait untuk detail pesan notifikasi
            $infoQuery = $conn->prepare("SELECT o.order_number FROM deliveries d LEFT JOIN orders o ON d.order_id = o.id WHERE d.id = ? LIMIT 1");
            $infoQuery->bind_param("i", $delivery_id);
            $infoQuery->execute();
            $infoRes = $infoQuery->get_result()->fetch_assoc();
            $orderNumber = $infoRes ? $infoRes['order_number'] : "Delivery ID " . $delivery_id;
            $infoQuery->close();

            // INTEGRASI: Membuat entri log notifikasi pelacakan baru
            createNotification(
                'admin', 
                (int)$_SESSION['user_id'], 
                'Log Tracking Ditambahkan', 
                "Titik koordinat baru ditambahkan untuk Pesanan #$orderNumber dengan status: $tracking_status", 
                'delivery_tracking.php'
            );

            header("Location: delivery_tracking.php?status=success_create");
        } else {
            header("Location: delivery_tracking.php?status=error&msg=" . urlencode($stmt->error));
        }
        $stmt->close();
        exit();
    }
}

// 2. PROSES UBAH DATA LOG PELACAKAN (UPDATE)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id              = intval($_POST['id'] ?? 0);
    $delivery_id     = intval($_POST['delivery_id'] ?? 0);
    $latitude        = isset($_POST['latitude']) ? floatval($_POST['latitude']) : 0.0;
    $longitude       = isset($_POST['longitude']) ? floatval($_POST['longitude']) : 0.0;
    $tracking_status = trim($_POST['tracking_status'] ?? 'ON_PROGRESS');

    if ($id > 0 && $delivery_id > 0) {
        $stmt = $conn->prepare("UPDATE delivery_tracking SET delivery_id = ?, latitude = ?, longitude = ?, tracking_status = ? WHERE id = ?");
        $stmt->bind_param("iddsi", $delivery_id, $latitude, $longitude, $tracking_status, $id);
        
        if ($stmt->execute()) {
            // INTEGRASI: Ambil nomor pesanan terkait untuk detail pesan notifikasi
            $infoQuery = $conn->prepare("SELECT o.order_number FROM deliveries d LEFT JOIN orders o ON d.order_id = o.id WHERE d.id = ? LIMIT 1");
            $infoQuery->bind_param("i", $delivery_id);
            $infoQuery->execute();
            $infoRes = $infoQuery->get_result()->fetch_assoc();
            $orderNumber = $infoRes ? $infoRes['order_number'] : "Delivery ID " . $delivery_id;
            $infoQuery->close();

            // INTEGRASI: Membuat entri log notifikasi perubahan data pelacakan
            createNotification(
                'admin', 
                (int)$_SESSION['user_id'], 
                'Log Tracking Diperbarui', 
                "Log pelacakan ID $id untuk Pesanan #$orderNumber berhasil diperbarui", 
                'delivery_tracking.php'
            );

            header("Location: delivery_tracking.php?status=success_update");
        } else {
            header("Location: delivery_tracking.php?status=error&msg=" . urlencode($stmt->error));
        }
        $stmt->close();
        exit();
    }
}

// 3. PROSES HAPUS DATA LOG PELACAKAN (DELETE)
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id'] ?? 0);

    if ($id > 0) {
        // INTEGRASI: Ambil nomor pesanan terkait sebelum log pelacakan dihapus dari database
        $infoQuery = $conn->prepare("SELECT o.order_number FROM delivery_tracking dt LEFT JOIN deliveries d ON dt.delivery_id = d.id LEFT JOIN orders o ON d.order_id = o.id WHERE dt.id = ? LIMIT 1");
        $infoQuery->bind_param("i", $id);
        $infoQuery->execute();
        $infoRes = $infoQuery->get_result()->fetch_assoc();
        $orderNumber = $infoRes ? $infoRes['order_number'] : "Unknown";
        $infoQuery->close();

        $stmt = $conn->prepare("DELETE FROM delivery_tracking WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // INTEGRASI: Membuat entri log notifikasi penghapusan pelacakan
            createNotification(
                'admin', 
                (int)$_SESSION['user_id'], 
                'Log Tracking Dihapus', 
                "Log pelacakan ID $id untuk Pesanan #$orderNumber berhasil dihapus dari sistem", 
                'delivery_tracking.php'
            );

            header("Location: delivery_tracking.php?status=success_delete");
        } else {
            header("Location: delivery_tracking.php?status=error&msg=" . urlencode($stmt->error));
        }
        $stmt->close();
        exit();
    }
}

// 4. PROSES READ DATA DENGAN JOIN (Menghubungkan ke Invoice Pesanan dan Nama Kurir)
$trackings = [];
$sql = "SELECT dt.*, o.order_number, c.name AS courier_name 
        FROM delivery_tracking dt
        LEFT JOIN deliveries d ON dt.delivery_id = d.id
        LEFT JOIN orders o ON d.order_id = o.id
        LEFT JOIN couriers c ON d.courier_id = c.id
        ORDER BY dt.id DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $trackings[] = $row;
    }
}

$status = isset($_GET['status']) ? (string)$_GET['status'] : '';
$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';

// Data dropdown untuk form (tampilan saja)
$deliveryOptions = [];
$deliveryRes = $conn->query("SELECT d.id, o.order_number, c.name AS courier_name FROM deliveries d LEFT JOIN orders o ON d.order_id = o.id LEFT JOIN couriers c ON d.courier_id = c.id ORDER BY d.id DESC");
if ($deliveryRes) {
    while ($r = $deliveryRes->fetch_assoc()) {
        $deliveryOptions[] = $r;
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
    #dragScrollTrackingContainer::-webkit-scrollbar { display: none !important; }
    #dragScrollTrackingContainer { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow-x: auto !important; cursor: grab !important; box-shadow: none !important; border: none !important; -webkit-box-shadow: none !important; }
    #dragScrollTrackingContainer:active { cursor: grabbing !important; }
    #dragScrollTrackingContainer table { border-collapse: collapse !important; border: none !important; }
    #dragScrollTrackingContainer table th, #dragScrollTrackingContainer table td { border-left: none !important; border-right: none !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; }
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
          <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Delivery Tracking</h2>
        </div>
        <div>
          <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalTracking" onclick="openTambahTracking()">
            <i class="bi bi-plus-circle"></i> Tambah Tracking
          </button>
        </div>
      </div>

      <?php if (!empty($status)): ?>
        <div class="alert <?= strpos($status, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
          <strong>
            <?php 
              if ($status === 'success_create') echo "Data tracking berhasil ditambahkan!";
              elseif ($status === 'success_update') echo "Data tracking berhasil diperbarui!";
              elseif ($status === 'success_delete') echo "Data tracking berhasil dihapus!";
              elseif ($status === 'error') echo "Operasi gagal: " . htmlspecialchars($msg);
              else echo "Operasi: " . htmlspecialchars($status);
            ?>
          </strong>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <div id="dragScrollTrackingContainer" class="table-responsive rounded-3" style="border: none !important; background: transparent !important;">
        <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 1050px; user-select: none; border-collapse: collapse !important;">
          <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
            <tr>
              <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 90px;">ID</th>
              <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 220px;">Order</th>
              <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 220px;">Courier</th>
              <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 140px;">Delivery ID</th>
              <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 160px;">Latitude</th>
              <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 160px;">Longitude</th>
              <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 180px;">Tracking</th>
              <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;">Aksi</th>
            </tr>
          </thead>
          <tbody style="background: transparent !important;">
            <?php if (!empty($trackings)): ?>
              <?php foreach ($trackings as $row): ?>
                <tr style="background: transparent !important; font-size: 0.88rem;">
                  <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= (int)($row['id'] ?? 0) ?></td>
                  <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['order_number'] ?? '-') ?></td>
                  <td class="text-white-50" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['courier_name'] ?? '-') ?></td>
                  <td class="text-white-50" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['delivery_id'] ?? '-') ?></td>
                  <td class="text-white-50" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['latitude'] ?? '-') ?></td>
                  <td class="text-white-50" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['longitude'] ?? '-') ?></td>
                  <td class="text-center" style="background: transparent !important; border: none !important;">
                    <?php
                      $ts = strtoupper(trim((string)($row['tracking_status'] ?? '')));
                      if ($ts === 'ON_PROGRESS') {
                        echo '<span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-50 px-3 py-1.5 rounded-pill">ON_PROGRESS</span>';
                      } elseif ($ts === 'PICKUP' || $ts === 'IN_PROGRESS') {
                        echo '<span class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-50 px-3 py-1.5 rounded-pill">'.htmlspecialchars($row['tracking_status'] ?? '').'</span>';
                      } elseif ($ts === 'DELIVERED') {
                        echo '<span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-3 py-1.5 rounded-pill">DELIVERED</span>';
                      } else {
                        echo '<span class="badge bg-secondary bg-opacity-25 text-white border border-secondary border-opacity-50 px-3 py-1.5 rounded-pill">'.htmlspecialchars($row['tracking_status'] ?? '-').'</span>';
                      }
                    ?>
                  </td>
                  <td class="text-center" style="background: transparent !important; border: none !important;">
                    <div class="d-flex justify-content-center gap-2">
                      <button class="btn btn-sm btn-outline-warning rounded-2" data-bs-toggle="modal" data-bs-target="#modalTracking" onclick='openEditTracking(<?= json_encode($row) ?>)'>
                        <i class="bi bi-pencil-square"></i>
                      </button>
                      <a href="delivery_tracking.php?action=delete&id=<?= (int)($row['id'] ?? 0) ?>" class="btn btn-sm btn-outline-danger rounded-2" onclick="return confirm('Apakah Anda yakin ingin menghapus tracking ini?')">
                        <i class="bi bi-trash"></i>
                      </a>
                    </div>
                  </td>
                </tr>

              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="8" class="text-center py-5 text-muted italic" style="background: transparent !important; border: none !important;">Belum ada data tracking.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </main>

  <!-- Modal Tracking (Create/Update) -->
  <div class="modal fade" id="modalTracking" tabindex="-1" aria-labelledby="modalTrackingLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content" style="background: rgba(15, 23, 42, 0.93) !important; backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.2); color: #e5e7eb; border-radius: 16px;">
        <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
          <h5 class="modal-title fw-bold text-white" id="modalTrackingLabel">Form Data Tracking</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <form id="formTracking" action="delivery_tracking.php" method="POST">
          <input type="hidden" name="action" id="tracking-action" value="create">
          <input type="hidden" name="id" id="tracking-id" value="">

          <div class="modal-body" style="overflow: visible !important;">
            <div class="row g-3">
              <div class="col-md-12">
                <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Delivery <span class="text-danger">*</span></label>
                <select class="form-select" name="delivery_id" id="tracking-delivery-id" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                  <option value="" disabled selected>-- Pilih Delivery --</option>
                  <?php foreach ($deliveryOptions as $dOpt): ?>
                    <option value="<?= (int)$dOpt['id'] ?>"><?= htmlspecialchars($dOpt['order_number'] ?? ('Order #'.$dOpt['id'])) ?> - <?= htmlspecialchars($dOpt['courier_name'] ?? '') ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Latitude</label>
                <input type="number" step="any" class="form-control" name="latitude" id="tracking-latitude" placeholder="-6.2" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" />
              </div>

              <div class="col-md-6">
                <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Longitude</label>
                <input type="number" step="any" class="form-control" name="longitude" id="tracking-longitude" placeholder="106.8" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" />
              </div>

              <div class="col-md-12">
                <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Tracking Status</label>
                <select class="form-select" name="tracking_status" id="tracking-status" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
                  <option value="ON_PROGRESS" selected>ON_PROGRESS</option>
                  <option value="PICKUP">PICKUP</option>
                  <option value="IN_PROGRESS">IN_PROGRESS</option>
                  <option value="DELIVERED">DELIVERED</option>
                </select>
              </div>
            </div>
          </div>

          <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.15); background: rgba(15, 23, 42, 0.95); border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success" id="btnSubmitTracking">Simpan Data</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    function openTambahTracking() {
      document.getElementById('formTracking').reset();
      document.getElementById('modalTrackingLabel').innerText = 'Tambah Data Tracking';
      document.getElementById('tracking-id').value = '';
      document.getElementById('tracking-action').value = 'create';
      document.getElementById('btnSubmitTracking').className = 'btn btn-success';
      document.getElementById('btnSubmitTracking').innerText = 'Simpan Data';
    }

    function openEditTracking(data) {
      document.getElementById('formTracking').reset();
      document.getElementById('modalTrackingLabel').innerText = 'Perbarui Data Tracking';
      document.getElementById('tracking-id').value = data.id;
      document.getElementById('tracking-action').value = 'update';

      document.getElementById('tracking-delivery-id').value = data.delivery_id;
      document.getElementById('tracking-latitude').value = data.latitude;
      document.getElementById('tracking-longitude').value = data.longitude;
      document.getElementById('tracking-status').value = data.tracking_status || 'ON_PROGRESS';

      document.getElementById('btnSubmitTracking').className = 'btn btn-warning text-dark fw-medium';
      document.getElementById('btnSubmitTracking').innerText = 'Perbarui Data';
    }
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


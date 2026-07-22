<?php
include "db.php"; 
include "notification_helper.php";

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$status = '';
$msg = '';
if (isset($_SESSION['success'])) {
    $status = $_SESSION['success']; 
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $status = 'error';
    $msg = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_POST['action']) && $_POST['action'] == 'insert') {
    $tenant_id   = $_POST['tenant_id'];
    $days        = isset($_POST['day_of_week']) ? $_POST['day_of_week'] : []; 
    $open_time   = $_POST['open_time'];
    $close_time  = $_POST['close_time'];
    $is_open     = $_POST['is_open']; 

    if (!empty($days)) {
        $tenantQuery = $conn->prepare("SELECT name FROM tenants WHERE id = ? LIMIT 1");
        $tenantQuery->bind_param("i", $tenant_id);
        $tenantQuery->execute();
        $tenantRes = $tenantQuery->get_result()->fetch_assoc();
        $tenantName = $tenantRes ? $tenantRes['name'] : "ID " . $tenant_id;
        $tenantQuery->close();

        $query = "INSERT INTO tenant_operating_hours (tenant_id, day_of_week, open_time, close_time, is_open) VALUES (?, ?, ?, ?, ?)";
        $stmt  = $conn->prepare($query);
        
        foreach ($days as $day_of_week) {
            $queryCheck = "SELECT id FROM tenant_operating_hours WHERE tenant_id = ? AND day_of_week = ?";
            $stmtCheck = $conn->prepare($queryCheck);
            $stmtCheck->bind_param("ii", $tenant_id, $day_of_week);
            $stmtCheck->execute();
            $resCheck = $stmtCheck->get_result();
            
            if ($resCheck->num_rows == 0) {
                $stmt->bind_param("iissi", $tenant_id, $day_of_week, $open_time, $close_time, $is_open);
                $stmt->execute();
            }
        }
        
        createNotification(
            'admin', 
            (int)$_SESSION['user_id'], 
            'Jam Operasional Tenant', 
            "Pengaturan jam operasional baru untuk tenant '$tenantName' berhasil disimpan", 
            'tenant_operating_hours.php'
        );

        $_SESSION['success'] = "success_insert";
    } else {
        $_SESSION['error'] = "Silakan pilih minimal satu hari operasional!";
    }
    header("Location: tenant_operating_hours.php");
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'update') {
    $id          = $_POST['id'];
    $tenant_id   = $_POST['tenant_id'];
    $days        = isset($_POST['day_of_week']) ? $_POST['day_of_week'] : []; 
    $open_time   = $_POST['open_time'];
    $close_time  = $_POST['close_time'];
    $is_open     = $_POST['is_open']; 

    if (!empty($days)) {
        $tenantQuery = $conn->prepare("SELECT name FROM tenants WHERE id = ? LIMIT 1");
        $tenantQuery->bind_param("i", $tenant_id);
        $tenantQuery->execute();
        $tenantRes = $tenantQuery->get_result()->fetch_assoc();
        $tenantName = $tenantRes ? $tenantRes['name'] : "ID " . $tenant_id;
        $tenantQuery->close();

        $queryFindOld = "SELECT open_time, close_time, is_open FROM tenant_operating_hours WHERE id = ?";
        $stmtFind = $conn->prepare($queryFindOld);
        $stmtFind->bind_param("i", $id);
        $stmtFind->execute();
        $resOld = $stmtFind->get_result()->fetch_assoc();

        if ($resOld) {
            $old_open  = $resOld['open_time'];
            $old_close = $resOld['close_time'];
            $old_is_open = $resOld['is_open'];

            $queryDeleteGroup = "DELETE FROM tenant_operating_hours WHERE tenant_id = ? AND open_time = ? AND close_time = ? AND is_open = ?";
            $stmtDel = $conn->prepare($queryDeleteGroup);
            $stmtDel->bind_param("issi", $tenant_id, $old_open, $old_close, $old_is_open);
            $stmtDel->execute();
        }

        $queryInsertNew = "INSERT INTO tenant_operating_hours (tenant_id, day_of_week, open_time, close_time, is_open) VALUES (?, ?, ?, ?, ?)";
        $stmtIns = $conn->prepare($queryInsertNew);
        
        foreach ($days as $day_of_week) {
            $queryCheck = "SELECT id FROM tenant_operating_hours WHERE tenant_id = ? AND day_of_week = ?";
            $stmtCheck = $conn->prepare($queryCheck);
            $stmtCheck->bind_param("ii", $tenant_id, $day_of_week);
            $stmtCheck->execute();
            if ($stmtCheck->get_result()->num_rows == 0) {
                $stmtIns->bind_param("iissi", $tenant_id, $day_of_week, $open_time, $close_time, $is_open);
                $stmtIns->execute();
            }
        }

        createNotification(
            'admin', 
            (int)$_SESSION['user_id'], 
            'Jam Operasional Diperbarui', 
            "Jam operasional kelompok untuk tenant '$tenantName' berhasil diperbarui", 
            'tenant_operating_hours.php'
        );

        $_SESSION['success'] = "success_update";
    } else {
        $_SESSION['error'] = "Silakan pilih minimal satu hari operasional!";
    }
    header("Location: tenant_operating_hours.php");
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];

    $infoQuery = $conn->prepare("SELECT toh.tenant_id, t.name AS tenant_name FROM tenant_operating_hours toh LEFT JOIN tenants t ON toh.tenant_id = t.id WHERE toh.id = ? LIMIT 1");
    $infoQuery->bind_param("i", $id);
    $infoQuery->execute();
    $infoRes = $infoQuery->get_result()->fetch_assoc();
    $tenantName = $infoRes ? $infoRes['tenant_name'] : "ID " . ($infoRes['tenant_id'] ?? 0);
    $infoQuery->close();

    $query = "DELETE FROM tenant_operating_hours WHERE id = ?";
    $stmt  = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        createNotification(
            'admin', 
            (int)$_SESSION['user_id'], 
            'Jam Operasional Dihapus', 
            "Konfigurasi jam operasional untuk tenant '$tenantName' berhasil dihapus dari sistem", 
            'tenant_operating_hours.php'
        );

        $_SESSION['success'] = "success_delete";
    } else {
        $_SESSION['error'] = $conn->error;
    }
    header("Location: tenant_operating_hours.php");
    exit;
}

$listActiveTenants = [];
$queryActiveTenants = "SELECT id, name FROM tenants WHERE deleted_at IS NULL ORDER BY name ASC";
$resultActiveTenants = $conn->query($queryActiveTenants);
if ($resultActiveTenants) {
    while ($row = $resultActiveTenants->fetch_assoc()) {
        $listActiveTenants[] = $row;
    }
}

$listOperatingHours = [];
$query  = "SELECT MAX(toh.id) as id, toh.tenant_id, toh.open_time, toh.close_time, toh.is_open, t.name as tenant_name, 
          GROUP_CONCAT(toh.day_of_week ORDER BY toh.day_of_week ASC) as array_days 
          FROM tenant_operating_hours toh 
          LEFT JOIN tenants t ON toh.tenant_id = t.id 
          GROUP BY toh.tenant_id, toh.open_time, toh.close_time, toh.is_open, t.name
          ORDER BY toh.tenant_id ASC";
          
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $listOperatingHours[] = $row;
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
    .content-bg { background: transparent; }
    .search-box { background: rgba(2,6,23,.35); border:1px solid rgba(148,163,184,.25); border-radius: 18px; }
    .diet-pill { border:1px solid rgba(34,197,94,.35); background: rgba(34,197,94,.08); color:#86efac; }
    .diet-pill[data-active="true"] { background: rgba(34,197,94,.92); color:#06210f; border-color: rgba(34,197,94,.65); }
    .card-food { background: rgba(2,6,23,.40); border:1px solid rgba(148,163,184,.22); border-radius: 18px; overflow:hidden; transition: transform .15s ease, border-color .15s ease; }
    .card-food:hover { transform: translateY(-2px); border-color: rgba(34,197,94,.35); }
    .food-img { height: 150px; background: linear-gradient(180deg, rgba(34,197,94,.10), rgba(2,6,23,.0)); display:flex; align-items:center; justify-content:center; color: rgba(148,163,184,.8); position: relative; }
    .food-img img { width:100%; height:100%; object-fit: cover; }
    .price-badge { display:inline-flex; align-items:center; gap:.35rem; padding:.35rem .7rem; background: rgba(15,23,42,.55); border:1px solid rgba(148,163,184,.25); border-radius: 999px; color: var(--text); }
    .bottom-nav { position: fixed; left:0; right:0; bottom:0; z-index: 1035; background: rgba(15,23,42,.88); backdrop-filter: blur(10px); border-top: 1px solid rgba(148,163,184,.25); display:block; }
    #dragScrollUserContainer::-webkit-scrollbar, #dragScrollContainer::-webkit-scrollbar, .drag-scroll-container::-webkit-scrollbar { display: none !important; }
    #dragScrollUserContainer, #dragScrollContainer, .drag-scroll-container { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow-x: auto !important; cursor: grab !important; border: none !important; box-shadow: none !important; -webkit-box-shadow: none !important; }
    #dragScrollUserContainer:active, #dragScrollContainer:active, .drag-scroll-container:active { cursor: grabbing !important; }
    #dragScrollUserContainer table, #dragScrollContainer table, .drag-scroll-container table { border-collapse: collapse !important; border: none !important; }
    #dragScrollUserContainer table th, #dragScrollUserContainer table td, #dragScrollContainer table th, #dragScrollContainer table td, .drag-scroll-container table th, .drag-scroll-container table td { border-left: none !important; border-right: none !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; }
    .text-white-element { -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }
    .text-white-element { -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }
    .modal-lg-custom { max-width: 800px !important; }
    .modal-body::-webkit-scrollbar { display: none !important; }
    .modal-body { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow: visible !important; }
    .bi-clock-history, .text-white-icon { color: #ffffff !important; opacity: 1 !important; filter: drop-shadow(0 0 1px rgba(255,255,255,0.2)); }
    input[type="time"]::-webkit-calendar-picker-indicator,
    input[type="date"]::-webkit-calendar-picker-indicator {filter: invert(1) brightness(100%) contrast(100%) !important;cursor: pointer;}
    @media (min-width: 992px) { main.content-shift { margin-left: 280px; } .bottom-nav { display:none; } }
</style>

</head>
<body>
  <?php require __DIR__ . '/sidebar.php'; ?>

<!-- MAIN KONTEN -->
<main class="content-shift p-4">
  <!-- Container tabel dengan tema gelap transparan yang selaras sempurna -->
  <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
    
    <!-- HEADER TABEL & TOMBOL TAMBAH JAM OPERASIONAL -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
      <div>
        <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;"> Tenant Operating Hours </h2>
      </div>
      <div>
        <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalOperatingHour" onclick="openTambahOperatingHour()">
          <i class="bi bi-clock-history"></i> Tambah Jam Operasional
        </button>
      </div>
    </div>

    <!-- NOTIFIKASI STATUS OPERASI CRUD -->
    <?php if (!empty($status)): ?>
        <div class="alert <?= strpos($status, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
            <strong>
                <?php 
                if ($status == 'success_insert') echo "Data jam operasional berhasil ditambahkan!";
                elseif ($status == 'success_update') echo "Data jam operasional berhasil diperbarui!";
                elseif ($status == 'success_delete') echo "Data jam operasional berhasil dihapus!";
                else echo "Operasi gagal: " . htmlspecialchars($msg);
                ?>
            </strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- STRUKTUR TABEL LIST DATA JAM OPERASIONAL (DRAG SCROLL & TRANSPARAN) -->
    <div id="dragScrollProductContainer" class="table-responsive rounded-3 drag-scroll-container" style="border: none !important; background: transparent !important; cursor: grab; box-shadow: none !important; -webkit-box-shadow: none !important;">
      <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 1000px; user-select: none; border-collapse: collapse !important;">
        <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
          <tr>
            <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 100px;"> ID</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 200px;"> Tenant ID</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 180px;"> Day of Week</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 180px;"> Open Time</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 180px;"> Close Time</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;"> Status</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;">Aksi</th>
          </tr>
        </thead>
        <tbody style="background: transparent !important;">
          <?php if (!empty($listOperatingHours)): 
              $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
              foreach ($listOperatingHours as $row): ?>
                  <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                    <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= $row['id'] ?></td>
                    <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['tenant_name'] ?? 'ID: '.$row['tenant_id']) ?></td>
                    <td class="text-center fw-medium" style="background: transparent !important; border: none !important; color: #cbd5e1 !important;">
                        <?php 
                        $hari_map = [
                            '1' => 'Senin', 
                            '2' => 'Selasa', 
                            '3' => 'Rabu', 
                            '4' => 'Kamis', 
                            '5' => 'Jumat', 
                            '6' => 'Sabtu', 
                            '0' => 'Minggu'
                        ];
                        
                        $current_days = !empty($row['array_days']) ? explode(',', $row['array_days']) : [];
                        $nama_hari = [];
                        
                        foreach ($current_days as $d) {
                            if (isset($hari_map[$d])) {
                                $nama_hari[] = $hari_map[$d];
                            }
                        }
                        
                        if (count($nama_hari) === 7) {
                            echo 'Setiap Hari';
                        } else {
                            echo implode(', ', $nama_hari);
                        }
                        ?>
                    </td>
                    <td class="text-center text-white-50" style="background: transparent !important; border: none !important;"><?= substr($row['open_time'], 0, 5) ?></td>
                    <td class="text-center text-white-50" style="background: transparent !important; border: none !important;"><?= substr($row['close_time'], 0, 5) ?></td>
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <?php 
                        // 1. Ambil waktu dan tanggal saat ini berdasarkan zona waktu lokal
                        date_default_timezone_set('Asia/Jakarta');
                        $currentDate = date('Y-m-d'); // Mendapatkan tanggal hari ini (Format: YYYY-MM-DD)
                        $currentTime = date('H:i:s'); // Mendapatkan jam menit detik sekarang

                        // 2. Langkah A: Periksa ke database apakah tenant ini sedang memiliki agenda libur pada TANGGAL HARI INI
                        $queryCheckHoliday = "SELECT id FROM tenant_holidays WHERE tenant_id = ? AND holiday_date = ?";
                        $stmtCheckHoliday = $conn->prepare($queryCheckHoliday);
                        $stmtCheckHoliday->bind_param("is", $row['tenant_id'], $currentDate);
                        $stmtCheckHoliday->execute();
                        $isHoliday = $stmtCheckHoliday->get_result()->num_rows > 0;
                        $stmtCheckHoliday->close();

                        // 3. Langkah B: Tentukan status real-time berdasarkan libur kalender dan rentang jam kerja
                        $isRealOpen = false;

                        // Jika hari ini TIDAK LIBUR dan status master database bernilai aktif (1)
                        if (!$isHoliday && $row['is_open'] == 1) {
                            if ($row['close_time'] < $row['open_time']) {
                                if ($currentTime >= $row['open_time'] || $currentTime <= $row['close_time']) {
                                    $isRealOpen = true;
                                }
                            } else {
                                if ($currentTime >= $row['open_time'] && $currentTime <= $row['close_time']) {
                                    $isRealOpen = true;
                                }
                            }
                        }
                        
                        // 4. Tampilkan badge status visual yang sesuai
                        if ($isRealOpen): ?>
                            <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem; background: rgba(25, 135, 84, 0.15);"><i class="bi bi-door-open-fill me-1"></i>Buka</span>
                        <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem; background: rgba(220, 53, 69, 0.15);"><i class="bi bi-door-closed-fill me-1"></i>Tutup</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                      <div class="d-flex justify-content-center gap-1">
                        <button class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Edit" onclick='openEditOperatingHour(<?= json_encode($row) ?>)'>
                          <i class="bi bi-pencil-square"></i>
                        </button>
                            <a href="javascript:void(0);" 
                            onclick="triggerDeleteOperatingHour('delete_operating_hours_handler.php?id=<?= $row['id'] ?>', '<?= htmlspecialchars($row['tenant_name'] ?? 'Tenant ini', ENT_QUOTES); ?>', '<?= htmlspecialchars($row['day_of_week'] ?? '', ENT_QUOTES); ?>')" 
                            class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" 
                            title="Delete">
                                <i class="bi bi-trash-fill"></i>
                            </a>
                        </a>
                      </div>
                    </td>
                  </tr>
              <?php endforeach; 
          else: ?>
              <tr>
                <td colspan="7" class="text-center py-5 text-muted shadow-none" style="background: transparent !important; border: none !important;">
                  <i class="bi bi-folder-x d-block mb-2" style="font-size: 2rem; color: rgba(148, 163, 184, 0.4);"></i>
                  Tidak ada data jam operasional tenant saat ini.
                </td>
              </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- MODAL FORM INPUT MELEBAR DI TENGAH (WIDE MODE & BEBAS SCROLLBAR) -->
<div class="modal fade" id="modalOperatingHour" tabindex="-1" aria-labelledby="modalOperatingHourLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.93) !important; backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.2); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
                <h5 class="modal-title fw-bold text-white" id="modalOperatingHourLabel">Form Jam Operasional</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formOperatingHour" action="tenant_operating_hours.php" method="POST">
                <input type="hidden" name="id" id="operating_id">
                <div id="operating_action_flag"></div>
                
                <div class="modal-body" style="overflow: visible !important;">
                    <div class="row g-3">
                        <!-- PILIH TENANT -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Pilih Tenant <span class="text-danger">*</span></label>
                            <select class="form-select" name="tenant_id" id="operating_tenant_id" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                                <option value="" disabled selected>-- Pilih Tenant --</option>
                                <?php foreach ($listActiveTenants as $tOption): ?>
                                    <option value="<?= $tOption['id'] ?>"><?= htmlspecialchars($tOption['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- PILIH HARI MULTIPLE (ALARM STYLE MULTI-CHECKBOX) -->
                        <div class="col-md-6">
                            <label class="form-label d-block" style="color: #94a3b8 !important; font-weight: 500;">Hari <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                <?php 
                                $days = [
                                    '1' => 'Sen', '2' => 'Sel', '3' => 'Rab', 
                                    '4' => 'Kam', '5' => 'Jum', '6' => 'Sab', '0' => 'Min'
                                ];
                                foreach ($days as $val => $label): 
                                ?>
                                    <input type="checkbox" class="btn-check operating-day-checkbox" name="day_of_week[]" value="<?= $val ?>" id="day_<?= $val ?>">
                                    <label class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center text-white p-0" for="day_<?= $val ?>" style="width: 38px; height: 38px; font-size: 0.78rem; border-color: rgba(148, 163, 184, 0.25); cursor: pointer;">
                                        <?= $label ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- JAM BUKA -->
                        <div class="col-md-4">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Jam Buka <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="open_time" id="operating_open_time" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>

                        <!-- JAM TUTUP -->
                        <div class="col-md-4">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Jam Tutup <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="close_time" id="operating_close_time" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>

                        <!-- STATUS (IS OPEN) -->
                        <div class="col-md-4">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Status Operasional <span class="text-danger">*</span></label>
                            <select class="form-select" name="is_open" id="operating_is_open" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                                <option value="1" selected>Buka</option>
                                <option value="0">Tutup / Libur Rutin</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.15); background: rgba(15, 23, 42, 0.95); border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="btnSubmitOperatingHour">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus Jam Operasional -->
<div class="modal fade" id="modalConfirmDeleteHours" tabindex="-1" aria-labelledby="modalConfirmDeleteHoursLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-bg-dark border-secondary" style="background-color: #111827 !important; border-color: #374151 !important;">
      
      <div class="modal-header border-bottom border-secondary">
        <h5 class="modal-title text-white fw-bold d-flex align-items-center" id="modalConfirmDeleteHoursLabel">
          <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Konfirmasi Hapus
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body text-center p-4">
        <div class="mb-3">
          <i class="bi bi-clock-history text-danger" style="font-size: 3.5rem;"></i>
        </div>
        <p class="text-light fs-6 mb-1">Apakah Anda yakin ingin menghapus jam operasional untuk tenant berikut?</p>
        <h6 id="delete_tenant_name" class="text-warning fw-bold mt-2 mb-0"></h6>
        <small id="delete_tenant_day" class="text-muted d-block mt-1"></small>
      </div>
      
      <div class="modal-footer border-top border-secondary justify-content-center">
        <button type="button" class="btn btn-secondary px-4 rounded-2" data-bs-dismiss="modal">Batal</button>
        <button type="button" id="btnConfirmDeleteHoursAction" class="btn btn-danger px-4 rounded-2 fw-bold">Oke, Hapus</button>
      </div>

    </div>
  </div>
</div>

<!-- JAVASCRIPT LOGIC -->
<script>
let deleteHoursUrlTarget = '';
let bootstrapDeleteHoursModalInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    const prodSlider = document.getElementById('dragScrollProductContainer');
    if (prodSlider) {
        let isDown = false;
        let startX, scrollLeft;
        
        prodSlider.addEventListener('mousedown', (e) => {
            if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input') || e.target.closest('select')) return;
            isDown = true; 
            prodSlider.style.cursor = 'grabbing';
            startX = e.pageX - prodSlider.offsetLeft; 
            scrollLeft = prodSlider.scrollLeft;
        });
        
        prodSlider.addEventListener('mouseleave', () => { 
            isDown = false; 
            prodSlider.style.cursor = 'grab'; 
        });
        
        prodSlider.addEventListener('mouseup', () => { 
            isDown = false; 
            prodSlider.style.cursor = 'grab'; 
        });
        
        prodSlider.addEventListener('mousemove', (e) => {
            if (!isDown) return; 
            e.preventDefault();
            const x = e.pageX - prodSlider.offsetLeft;
            prodSlider.scrollLeft = scrollLeft - ((x - startX) * 1.5);
        });
    }

    const operatingIsOpen = document.getElementById('operating_is_open');
    if (operatingIsOpen) {
        operatingIsOpen.addEventListener('change', function () {
            toggleTimeRequired(this.value);
        });
    }

    const formOperating = document.getElementById('formOperatingHour');
    if (formOperating) {
        formOperating.addEventListener('submit', function (e) {
            const openTimeInput = document.getElementById('operating_open_time').value;
            const closeTimeInput = document.getElementById('operating_close_time').value;
            const isOpen = document.getElementById('operating_is_open').value;

            const checkedDays = document.querySelectorAll('.operating-day-checkbox:checked');
            if (checkedDays.length === 0) {
                e.preventDefault();
                alert('⚠️ Silakan pilih minimal satu hari operasional!');
                return;
            }

            if (isOpen === "1" && openTimeInput && closeTimeInput) {
                if (closeTimeInput <= openTimeInput) {
                    e.preventDefault();
                    alert('⚠️ Logika Salah: Jam Tutup harus lebih lambat daripada Jam Buka!');
                }
            }
        });
    }

    // Hubungkan aksi ke tombol "Oke, Hapus" di dalam modal konfirmasi baru
    const btnActionDelete = document.getElementById('btnConfirmDeleteHoursAction');
    if (btnActionDelete) {
        btnActionDelete.addEventListener('click', function() {
            if (deleteHoursUrlTarget) {
                window.location.href = deleteHoursUrlTarget;
            }
        });
    }
});

function openTambahOperatingHour() {
    document.getElementById('formOperatingHour').reset();
    document.getElementById('modalOperatingHourLabel').innerText = 'Tambah Jam Operasional';
    document.getElementById('operating_id').value = '';
    document.getElementById('operating_action_flag').innerHTML = '<input type="hidden" name="action" value="insert">';
    document.getElementById('operating_tenant_id').disabled = false;
    document.getElementById('operating_open_time').required = true;
    document.getElementById('operating_close_time').required = true;

    document.querySelectorAll('.operating-day-checkbox').forEach(cb => {
        cb.checked = false;
        cb.disabled = false;
    });
}

function openEditOperatingHour(data) {
    document.getElementById('formOperatingHour').reset();
    document.getElementById('modalOperatingHourLabel').innerText = 'Edit Jam Operasional';
    document.getElementById('operating_id').value = data.id;
    document.getElementById('operating_action_flag').innerHTML = '<input type="hidden" name="action" value="update">';
    document.getElementById('operating_tenant_id').value = data.tenant_id;
    
    if (data.open_time) document.getElementById('operating_open_time').value = data.open_time.substring(0, 5);
    if (data.close_time) document.getElementById('operating_close_time').value = data.close_time.substring(0, 5);
    
    document.getElementById('operating_is_open').value = data.is_open;
    toggleTimeRequired(data.is_open.toString());

    document.querySelectorAll('.operating-day-checkbox').forEach(cb => {
        cb.checked = (cb.value == data.day_of_week);
        cb.disabled = false; 
    });
    
    const modalElement = document.getElementById('modalOperatingHour');
    const myModal = new bootstrap.Modal(modalElement);
    myModal.show();
}

// PERBAIKAN: Nama fungsi diselaraskan menjadi triggerDeleteOperatingHour sesuai tag <a> Anda
function triggerDeleteOperatingHour(url, tenantName, dayOfWeek) {
    // Menyimpan target URL handler (delete_operating_hours_handler.php?id=...)
    deleteHoursUrlTarget = url;
    
    // Memasukkan info teks nama tenant dan hari operasional ke dalam komponen modal
    const tenantPlaceholder = document.getElementById('delete_tenant_name');
    const dayPlaceholder = document.getElementById('delete_tenant_day');
    
    if (tenantPlaceholder) tenantPlaceholder.innerText = tenantName;
    if (dayPlaceholder) dayPlaceholder.innerText = "Hari: " + dayOfWeek;
    
    // Membuka jendela dialog konfirmasi modal Bootstrap 5
    if (!bootstrapDeleteHoursModalInstance) {
        bootstrapDeleteHoursModalInstance = new bootstrap.Modal(document.getElementById('modalConfirmDeleteHours'));
    }
    bootstrapDeleteHoursModalInstance.show();
}

function toggleTimeRequired(isOpenValue) {
    const openTime = document.getElementById('operating_open_time');
    const closeTime = document.getElementById('operating_close_time');
    
    if (isOpenValue === "0") {
        openTime.required = false;
        closeTime.required = false;
        if (!openTime.value) openTime.value = "00:00";
        if (!closeTime.value) closeTime.value = "00:00";
    } else {
        openTime.required = true;
        closeTime.required = true;
    }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

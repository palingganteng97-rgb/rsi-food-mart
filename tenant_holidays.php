<?php
// tenant_holidays.php
include "db.php"; // Memanggil koneksi database ($conn) & session_start()

// Proteksi Halaman: Jika sesi user_id kosong, tendang kembali ke login.php
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Menangkap notifikasi dari session untuk dicocokkan dengan alert komponen HTML Anda
$status = $_GET['status'] ?? '';
$msg = $_GET['msg'] ?? '';

// =========================================================================
// 1. CRUD: LOGIKA INSERT DATA LIBUR TENANT BARU (DARI INPUT DATE DUA KOLOM)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_holiday'])) {
    $tenantId  = (int)($_POST['tenant_id'] ?? 0);
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate   = trim($_POST['end_date'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($tenantId <= 0 || empty($startDate) || empty($endDate)) {
        header("Location: tenant_holidays.php?status=error_insert&msg=" . urlencode("Nama tenant, tanggal mulai, dan tanggal selesai wajib diisi!"));
        exit;
    }

    try {
        $insertStmt = $conn->prepare("INSERT INTO tenant_holidays (tenant_id, holiday_date, description) VALUES (?, ?, ?)");
        
        // Melakukan perulangan (looping) hari dari tanggal mulai sampai tanggal selesai
        $currentTimestamp = strtotime($startDate);
        $endTimestamp     = strtotime($endDate);

        while ($currentTimestamp <= $endTimestamp) {
            $date = date('Y-m-d', $currentTimestamp);

            // Validasi: Cegah duplikasi tanggal libur yang sama persis untuk tenant tersebut
            $queryCheck = "SELECT id FROM tenant_holidays WHERE tenant_id = ? AND holiday_date = ?";
            $stmtCheck = $conn->prepare($queryCheck);
            $stmtCheck->bind_param("is", $tenantId, $date);
            $stmtCheck->execute();
            $resCheck = $stmtCheck->get_result();

            if ($resCheck->num_rows == 0) {
                $insertStmt->bind_param("iss", $tenantId, $date, $description);
                $insertStmt->execute();
            }
            $stmtCheck->close();
            
            // Maju 1 hari berikutnya
            $currentTimestamp = strtotime("+1 day", $currentTimestamp);
        }
        
        header("Location: tenant_holidays.php?status=success_insert");
        exit;
    } catch (Throwable $e) {
        header("Location: tenant_holidays.php?status=error_insert&msg=" . urlencode($e->getMessage()));
        exit;
    }
}

// =========================================================================
// 2. CRUD: LOGIKA UPDATE DATA LIBUR TENANT (DARI INPUT DATE DUA KOLOM)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_holiday'])) {
    $targetId  = (int)($_POST['id'] ?? 0);
    $tenantId  = (int)($_POST['tenant_id'] ?? 0);
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate   = trim($_POST['end_date'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($targetId <= 0 || $tenantId <= 0 || empty($startDate) || empty($endDate)) {
        header("Location: tenant_holidays.php?status=error_update&msg=" . urlencode("Semua kolom tanggal wajib diisi dengan benar."));
        exit;
    }

    try {
        // Langkah A: Cari tahu konfigurasi deskripsi/keterangan lama kelompok data ini sebelum dihapus
        $queryFindOld = "SELECT tenant_id, description FROM tenant_holidays WHERE id = ?";
        $stmtFind = $conn->prepare($queryFindOld);
        $stmtFind->bind_param("i", $targetId);
        $stmtFind->execute();
        $resOld = $stmtFind->get_result()->fetch_assoc();
        $stmtFind->close();

        if ($resOld) {
            $oldTenantId = $resOld['tenant_id'];
            $oldDesc     = $resOld['description'];

            // Langkah B: Hapus kelompok data libur lama yang memiliki relasi tenant dan keterangan yang sama
            $queryDeleteGroup = "DELETE FROM tenant_holidays WHERE tenant_id = ? AND description = ?";
            $stmtDel = $conn->prepare($queryDeleteGroup);
            $stmtDel->bind_param("is", $oldTenantId, $oldDesc);
            $stmtDel->execute();
            $stmtDel->close();
        }

        // Langkah C: Masukkan kembali baris-baris tanggal baru hasil editan range input
        $insertStmt = $conn->prepare("INSERT INTO tenant_holidays (tenant_id, holiday_date, description) VALUES (?, ?, ?)");
        
        $currentTimestamp = strtotime($startDate);
        $endTimestamp     = strtotime($endDate);

        while ($currentTimestamp <= $endTimestamp) {
            $date = date('Y-m-d', $currentTimestamp);

            $queryCheck = "SELECT id FROM tenant_holidays WHERE tenant_id = ? AND holiday_date = ?";
            $stmtCheck = $conn->prepare($queryCheck);
            $stmtCheck->bind_param("is", $tenantId, $date);
            $stmtCheck->execute();
            
            if ($stmtCheck->get_result()->num_rows == 0) {
                $insertStmt->bind_param("iss", $tenantId, $date, $description);
                $insertStmt->execute();
            }
            $stmtCheck->close();
            
            $currentTimestamp = strtotime("+1 day", $currentTimestamp);
        }
        
        header("Location: tenant_holidays.php?status=success_update");
        exit;
    } catch (Throwable $e) {
        header("Location: tenant_holidays.php?status=error_update&msg=" . urlencode($e->getMessage()));
        exit;
    }
}

// =========================================================================
// 3. CRUD: LOGIKA DELETE DATA LIBUR TENANT (HAPUS PERMANEN KELOMPOK)
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $deleteId = (int)$_GET['id'];

    if ($deleteId > 0) {
        try {
            // Mengambil info relasi data terlebih dahulu agar bisa menghapus satu kelompok grup liburan sekaligus
            $queryFindOld = "SELECT tenant_id, description FROM tenant_holidays WHERE id = ?";
            $stmtFind = $conn->prepare($queryFindOld);
            $stmtFind->bind_param("i", $deleteId);
            $stmtFind->execute();
            $resOld = $stmtFind->get_result()->fetch_assoc();
            $stmtFind->close();

            if ($resOld) {
                $oldTenantId = $resOld['tenant_id'];
                $oldDesc     = $resOld['description'];

                $deleteStmt = $conn->prepare("DELETE FROM tenant_holidays WHERE tenant_id = ? AND description = ?");
                $deleteStmt->bind_param("is", $oldTenantId, $oldDesc);
                
                if ($deleteStmt->execute()) {
                    header("Location: tenant_holidays.php?status=success_delete");
                    exit;
                } else {
                    header("Location: tenant_holidays.php?status=error_delete&msg=" . urlencode($deleteStmt->error));
                    exit;
                }
                $deleteStmt->close();
            }
        } catch (Throwable $e) {
            header("Location: tenant_holidays.php?status=error_delete&msg=" . urlencode($e->getMessage()));
            exit;
        }
    }
}

// =========================================================================
// 4. READ DATA: AMBIL DATA LIBUR (JOIN NAMA TENANT) & LIST DATA TENANTS
// =========================================================================
$listHolidays = [];
$listActiveTenants = [];

try {
    // Meringkas deretan tanggal libur yang bermotif/berketerangan sama menjadi 1 baris menggunakan GROUP_CONCAT
    $queryRead = "SELECT MAX(th.id) AS id, th.tenant_id, th.description, t.name AS tenant_name,
                  GROUP_CONCAT(th.holiday_date ORDER BY th.holiday_date ASC) AS array_dates 
                  FROM tenant_holidays th 
                  LEFT JOIN tenants t ON th.tenant_id = t.id 
                  GROUP BY th.tenant_id, th.description, t.name
                  ORDER BY MAX(th.holiday_date) DESC";
                  
    $resultRead = $conn->query($queryRead);
    if ($resultRead) {
        while ($row = $resultRead->fetch_assoc()) {
            $listHolidays[] = $row;
        }
    }

    // Mengambil opsi data tenant untuk dimasukkan ke dalam elemen select option form modal input
    $queryTenants = "SELECT id, name FROM tenants WHERE deleted_at IS NULL ORDER BY name ASC";
    $resultTenants = $conn->query($queryTenants);
    if ($resultTenants) {
        while ($tRow = $resultTenants->fetch_assoc()) {
            $listActiveTenants[] = $tRow;
        }
    }
} catch (Throwable $e) {
    // Mencegah crash patah halaman jika kueri bermasalah
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
    .modal-dialog { max-width: 800px !important; }
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

<main class="content-shift p-4">
  <!-- Container tabel dengan tema gelap transparan yang selaras sempurna -->
  <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
    
    <!-- HEADER TABEL & TOMBOL TAMBAH HARI LIBUR -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
      <div>
        <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;"> Tenant Holidays </h2>
      </div>
      <div>
        <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalHoliday" onclick="openTambahHoliday()">
          <i class="bi bi-calendar-plus"></i> Tambah Hari Libur
        </button>
      </div>
    </div>

    <!-- NOTIFIKASI STATUS OPERASI CRUD -->
    <?php if (!empty($status) || isset($_GET['status'])): 
        $currentStatus = $_GET['status'] ?? $status;
        $msgError = $_GET['msg'] ?? '';
    ?>
        <div class="alert <?= strpos($currentStatus, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
            <strong>
                <?php 
                if ($currentStatus == 'success_insert') echo "Data hari libur berhasil ditambahkan!";
                elseif ($currentStatus == 'success_update') echo "Data hari libur berhasil diperbarui!";
                elseif ($currentStatus == 'success_delete') echo "Data hari libur berhasil dihapus!";
                else echo "Operasi gagal: " . htmlspecialchars($msgError ?: $msg);
                ?>
            </strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- STRUKTUR TABEL LIST DATA HARI LIBUR (DRAG SCROLL & TRANSPARAN) -->
    <div id="dragScrollHolidayContainer" class="table-responsive rounded-3 drag-scroll-container" style="border: none !important; background: transparent !important; cursor: grab; box-shadow: none !important; -webkit-box-shadow: none !important;">
      <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 1000px; user-select: none; border-collapse: collapse !important;">
        <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
          <tr>
            <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 100px;"> ID</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 250px;"> Tenant Name</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 300px;"> Holiday Date</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important;"> Description</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;">Aksi</th>
          </tr>
        </thead>
        <tbody style="background: transparent !important;">
          <?php if (!empty($listHolidays)): 
              foreach ($listHolidays as $row): ?>
                  <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                    <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= $row['id'] ?></td>
                    <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['tenant_name'] ?? 'NULL (ID: '.$row['tenant_id'].')') ?></td>
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <?php 
                        $explodedDates = !empty($row['array_dates']) ? explode(',', $row['array_dates']) : [];
                        if (!empty($explodedDates)): 
                            $firstDate = date('d M Y', strtotime(trim($explodedDates[0])));
                            $lastDate = date('d M Y', strtotime(trim($explodedDates[count($explodedDates) - 1])));
                        ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem; background: rgba(220, 53, 69, 0.15);">
                                <i class="bi bi-calendar3 me-1"></i><?= $firstDate ?> s/d <?= $lastDate ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-white-50" style="background: transparent !important; border: none !important; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($row['description'] ?: '-') ?></td>
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                      <div class="d-flex justify-content-center gap-1">
                        <button class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Edit" onclick='openEditHoliday(<?= json_encode($row) ?>)'>
                          <i class="bi bi-pencil-square"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Delete" data-bs-toggle="modal" data-bs-target="#modalDeleteHoliday" onclick="prepareDeleteHoliday(<?= $row['id'] ?>)">
                          <i class="bi bi-trash-fill"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
              <?php endforeach; 
          else: ?>
              <tr>
                <td colspan="5" class="text-center py-5 text-muted shadow-none" style="background: transparent !important; border: none !important;">
                  <i class="bi bi-folder-x d-block mb-2" style="font-size: 2rem; color: rgba(148, 163, 184, 0.4);"></i>
                  Tidak ada data hari libur tenant saat ini.
                </td>
              </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- MODAL FORM INPUT MELEBAR DI TENGAH (WIDE MODE & BEBAS SCROLLBAR) -->
<div class="modal fade" id="modalHoliday" tabindex="-1" aria-labelledby="modalHolidayLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.93) !important; backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.2); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
                <h5 class="modal-title fw-bold text-white" id="modalHolidayLabel">Form Hari Libur</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formHoliday" action="tenant_holidays.php" method="POST">
                <input type="hidden" name="id" id="holiday_id"><div id="holiday_action_flag"></div>
                <div class="modal-body" style="overflow: visible !important;">
                    <div class="row g-3">
                        <!-- PILIH TENANT -->
                        <div class="col-12">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Pilih Tenant <span class="text-danger">*</span></label>
                            <select class="form-select" name="tenant_id" id="holiday_tenant_id" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                                <option value="" disabled selected>-- Pilih Tenant --</option>
                                <?php foreach ($listActiveTenants as $tOption): ?>
                                    <option value="<?= $tOption['id'] ?>"><?= htmlspecialchars($tOption['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- TANGGAL MULAI LIBUR -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Tanggal Mulai <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="start_date" id="holiday_start_date" onclick="this.showPicker()" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>

                        <!-- TANGGAL SELESAI LIBUR -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Tanggal Selesai <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="end_date" id="holiday_end_date" onclick="this.showPicker()" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>

                        <!-- KETERANGAN -->
                        <div class="col-12">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Keterangan / Alasan Libur</label>
                            <textarea class="form-control" name="description" id="holiday_description" rows="3" maxlength="255" placeholder="Contoh: Libur Hari Raya Idul Fitri..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.15); background: rgba(15, 23, 42, 0.95); border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="btnSubmitHoliday">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL KONFIRMASI HAPUS HARI LIBUR (THEME GELAP) -->
<div class="modal fade" id="modalDeleteHoliday" tabindex="-1" aria-labelledby="modalDeleteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(12px); border: 1px solid rgba(239, 68, 68, 0.3); color: #e5e7eb; border-radius: 14px;">
            <div class="modal-body text-center p-4">
                <i class="bi bi-exclamation-triangle-fill text-danger d-block mb-3" style="font-size: 3rem;"></i>
                <h5 class="modal-title fw-bold text-white mb-2" id="modalDeleteLabel">Konfirmasi Hapus</h5>
                <p class="text-muted small mb-4">Apakah Anda yakin ingin menghapus kelompok data hari libur ini? Semua tanggal dalam grup ini akan dihapus permanen.</p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-sm btn-secondary px-3 rounded-2" data-bs-dismiss="modal">Batal</button>
                    <a id="btnConfirmDeleteHolidayUrl" href="#" class="btn btn-sm btn-danger px-3 rounded-2">Ya, Hapus</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT EVENT MOUSE DRAG TO SCROLL & HANDLER MODAL -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.history.replaceState && window.location.search) {
        const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
        window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
    }

    const holSlider = document.getElementById('dragScrollHolidayContainer');
    if (holSlider) {
        let isDown = false;
        let startX, scrollLeft;
        
        holSlider.addEventListener('mousedown', (e) => {
            if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input') || e.target.closest('select') || e.target.closest('textarea')) return;
            isDown = true; 
            holSlider.style.cursor = 'grabbing';
            startX = e.pageX - holSlider.offsetLeft; 
            scrollLeft = holSlider.scrollLeft;
        });
        
        holSlider.addEventListener('mouseleave', () => { isDown = false; holSlider.style.cursor = 'grab'; });
        holSlider.addEventListener('mouseup', () => { isDown = false; holSlider.style.cursor = 'grab'; });
        
        holSlider.addEventListener('mousemove', (e) => {
            if (!isDown) return; 
            e.preventDefault();
            const x = e.pageX - holSlider.offsetLeft;
            holSlider.scrollLeft = scrollLeft - ((x - startX) * 1.5);
        });
    }

    const formHoliday = document.getElementById('formHoliday');
    if (formHoliday) {
        formHoliday.addEventListener('submit', function (e) {
            const startDate = document.getElementById('holiday_start_date').value;
            const endDate = document.getElementById('holiday_end_date').value;

            if (startDate && endDate && endDate < startDate) {
                e.preventDefault();
                alert('⚠️ Logika Salah: Tanggal Selesai tidak boleh mendahului Tanggal Mulai!');
            }
        });
    }
});

function openTambahHoliday() {
    document.getElementById('formHoliday').reset();
    document.getElementById('modalHolidayLabel').innerText = 'Tambah Hari Libur Tenant';
    document.getElementById('holiday_id').value = '';
    document.getElementById('btnSubmitHoliday').className = "btn btn-success";
    document.getElementById('btnSubmitHoliday').innerText = "Simpan Data";
    document.getElementById('holiday_action_flag').innerHTML = '<input type="hidden" name="action_add_holiday" value="1">';
}

function openEditHoliday(data) {
    document.getElementById('formHoliday').reset();
    document.getElementById('modalHolidayLabel').innerText = 'Ubah Data Hari Libur';
    document.getElementById('holiday_id').value = data.id;
    document.getElementById('holiday_tenant_id').value = data.tenant_id;
    document.getElementById('holiday_description').value = data.description ?? '';
    document.getElementById('btnSubmitHoliday').className = "btn btn-warning text-dark fw-medium";
    document.getElementById('btnSubmitHoliday').innerText = "Simpan Perubahan";
    document.getElementById('holiday_action_flag').innerHTML = '<input type="hidden" name="action_update_holiday" value="1">';
    
    if (data.array_dates) {
        const dates = data.array_dates.split(',');
        document.getElementById('holiday_start_date').value = dates[0].trim();
        document.getElementById('holiday_end_date').value = dates[dates.length - 1].trim();
    } else if (data.holiday_date) {
        document.getElementById('holiday_start_date').value = data.holiday_date;
        document.getElementById('holiday_end_date').value = data.holiday_date;
    }
    
    var myModal = new bootstrap.Modal(document.getElementById('modalHoliday'));
    myModal.show();
}

function prepareDeleteHoliday(id) {
    document.getElementById('btnConfirmDeleteHolidayUrl').href = 'tenant_holidays.php?action=delete&id=' + id;
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// tenant_holidays.php
include "db.php"; // Memanggil koneksi database ($conn) & session_start()

// Proteksi Halaman: Jika sesi user_id kosong, tendang kembali ke login.php
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// =========================================================================
// 1. CRUD: LOGIKA INSERT DATA LIBUR TENANT BARU (DARI MODAL TAMBAH)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_holiday'])) {
    $tenantId    = (int)($_POST['tenant_id'] ?? 0);
    $holidayDate = trim($_POST['holiday_date'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($tenantId <= 0 || empty($holidayDate)) {
        header("Location: tenant_holidays.php?status=error_insert&msg=" . urlencode("Nama tenant dan tanggal libur wajib diisi!"));
        exit;
    }

    try {
        $insertStmt = $conn->prepare("INSERT INTO tenant_holidays (tenant_id, holiday_date, description) VALUES (?, ?, ?)");
        $insertStmt->bind_param("iss", $tenantId, $holidayDate, $description);
        
        if ($insertStmt->execute()) {
            header("Location: tenant_holidays.php?status=success_insert");
            exit;
        } else {
            header("Location: tenant_holidays.php?status=error_insert&msg=" . urlencode($insertStmt->error));
            exit;
        }
        $insertStmt->close();
    } catch (Throwable $e) {
        header("Location: tenant_holidays.php?status=error_insert&msg=" . urlencode($e->getMessage()));
        exit;
    }
}

// =========================================================================
// 2. CRUD: LOGIKA UPDATE DATA LIBUR TENANT (DARI MODAL EDIT)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_holiday'])) {
    $targetId    = (int)($_POST['id'] ?? 0);
    $tenantId    = (int)($_POST['tenant_id'] ?? 0);
    $holidayDate = trim($_POST['holiday_date'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($targetId <= 0 || $tenantId <= 0 || empty($holidayDate)) {
        header("Location: tenant_holidays.php?status=error_update&msg=" . urlencode("Semua data wajib diisi dengan benar."));
        exit;
    }

    try {
        $updateStmt = $conn->prepare("UPDATE tenant_holidays SET tenant_id = ?, holiday_date = ?, description = ? WHERE id = ?");
        $updateStmt->bind_param("issi", $tenantId, $holidayDate, $description, $targetId);
        
        if ($updateStmt->execute()) {
            header("Location: tenant_holidays.php?status=success_update");
            exit;
        } else {
            header("Location: tenant_holidays.php?status=error_update&msg=" . urlencode($updateStmt->error));
            exit;
        }
        $updateStmt->close();
    } catch (Throwable $e) {
        header("Location: tenant_holidays.php?status=error_update&msg=" . urlencode($e->getMessage()));
        exit;
    }
}

// =========================================================================
// 3. CRUD: LOGIKA DELETE DATA LIBUR TENANT (HAPUS PERMANEN)
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $deleteId = (int)$_GET['id'];

    if ($deleteId > 0) {
        try {
            $deleteStmt = $conn->prepare("DELETE FROM tenant_holidays WHERE id = ?");
            $deleteStmt->bind_param("i", $deleteId);
            
            if ($deleteStmt->execute()) {
                header("Location: tenant_holidays.php?status=success_delete");
                exit;
            } else {
                header("Location: tenant_holidays.php?status=error_delete&msg=" . urlencode($deleteStmt->error));
                exit;
            }
            $deleteStmt->close();
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
    // Mengambil data libur dengan LEFT JOIN ke tabel tenants untuk menarik nama tenant asli
    $queryRead = "SELECT th.id, th.tenant_id, th.holiday_date, th.description, t.name AS tenant_name 
                  FROM tenant_holidays th 
                  LEFT JOIN tenants t ON th.tenant_id = t.id 
                  ORDER BY th.holiday_date DESC";
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
    /* Penyelarasan Tabel & Sembunyikan Scrollbar Horizontal */
    #dragScrollUserContainer::-webkit-scrollbar, #dragScrollContainer::-webkit-scrollbar, .drag-scroll-container::-webkit-scrollbar { display: none !important; }
    #dragScrollUserContainer, #dragScrollContainer, .drag-scroll-container { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow-x: auto !important; cursor: grab !important; border: none !important; box-shadow: none !important; -webkit-box-shadow: none !important; }
    #dragScrollUserContainer:active, #dragScrollContainer:active, .drag-scroll-container:active { cursor: grabbing !important; }
    #dragScrollUserContainer table, #dragScrollContainer table, .drag-scroll-container table { border-collapse: collapse !important; border: none !important; }
    #dragScrollUserContainer table th, #dragScrollUserContainer table td, #dragScrollContainer table th, #dragScrollContainer table td, .drag-scroll-container table th, .drag-scroll-container table td { border-left: none !important; border-right: none !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; }
    .text-white-element { -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }
    /* Kustomisasi Modal Tengah Melebar & Izinkan Kalender Pop-up Muncul Keluar Melayang */
    .modal-dialog { max-width: 800px !important; }
    .modal-body::-webkit-scrollbar { display: none !important; }
    .modal-body { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow: visible !important; }
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
    <?php if (!empty($status)): ?>
        <div class="alert <?= strpos($status, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
            <strong>
                <?php 
                if ($status == 'success_insert') echo "Data hari libur berhasil ditambahkan!";
                elseif ($status == 'success_update') echo "Data hari libur berhasil diperbarui!";
                elseif ($status == 'success_delete') echo "Data hari libur berhasil dihapus!";
                else echo "Operasi gagal: " . htmlspecialchars($msg);
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
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 200px;"> Holiday Date</th>
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
                        <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem; background: rgba(220, 53, 69, 0.15);"><i class="bi bi-calendar3 me-1"></i><?= date('d M Y', strtotime($row['holiday_date'])) ?></span>
                    </td>
                    <td class="text-white-50" style="background: transparent !important; border: none !important; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($row['description'] ?: '-') ?></td>
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                      <div class="d-flex justify-content-center gap-1">
                        <button class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Edit" onclick='openEditHoliday(<?= json_encode($row) ?>)'>
                          <i class="bi bi-pencil-square"></i>
                        </button>
                        <a href="tenant_holidays.php?action=delete&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Delete" onclick="return confirm('Apakah Anda yakin ingin menghapus data hari libur ini?')">
                          <i class="bi bi-trash-fill"></i>
                        </a>
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
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Pilih Tenant <span class="text-danger">*</span></label>
                            <select class="form-select" name="tenant_id" id="holiday_tenant_id" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                                <option value="" disabled selected>-- Pilih Tenant --</option>
                                <?php foreach ($listActiveTenants as $tOption): ?>
                                    <option value="<?= $tOption['id'] ?>"><?= htmlspecialchars($tOption['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Tanggal Libur <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="holiday_date" id="holiday_date" onclick="this.showPicker()" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Keterangan / Alasan Libur</label>
                            <textarea class="form-control" name="description" id="holiday_description" rows="3" placeholder="Contoh: Libur Hari Raya Idul Fitri..." style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;"></textarea>
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

<!-- JAVASCRIPT EVENT MOUSE DRAG TO SCROLL & HANDLER MODAL -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const holSlider = document.getElementById('dragScrollHolidayContainer');
    if (!holSlider) return;
    let isDown = false, startX, scrollLeft;
    holSlider.addEventListener('mousedown', (e) => {
        if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input')) return;
        isDown = true; holSlider.style.cursor = 'grabbing';
        startX = e.pageX - holSlider.offsetLeft; scrollLeft = holSlider.scrollLeft;
    });
    holSlider.addEventListener('mouseleave', () => { isDown = false; holSlider.style.cursor = 'grab'; });
    holSlider.addEventListener('mouseup', () => { isDown = false; holSlider.style.cursor = 'grab'; });
    holSlider.addEventListener('mousemove', (e) => {
        if (!isDown) return; e.preventDefault();
        const x = e.pageX - holSlider.offsetLeft;
        holSlider.scrollLeft = scrollLeft - ((x - startX) * 1.5);
    });
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
    openTambahHoliday();
    document.getElementById('modalHolidayLabel').innerText = 'Ubah Data Hari Libur';
    document.getElementById('holiday_id').value = data.id;
    document.getElementById('holiday_tenant_id').value = data.tenant_id;
    document.getElementById('holiday_date').value = data.holiday_date;
    document.getElementById('holiday_description').value = data.description;
    document.getElementById('btnSubmitHoliday').className = "btn btn-warning text-dark fw-medium";
    document.getElementById('btnSubmitHoliday').innerText = "Simpan Perubahan";
    document.getElementById('holiday_action_flag').innerHTML = '<input type="hidden" name="action_update_holiday" value="1">';
    var myModal = new bootstrap.Modal(document.getElementById('modalHoliday'));
    myModal.show();
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

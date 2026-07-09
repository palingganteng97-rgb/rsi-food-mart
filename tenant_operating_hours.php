<?php
// tenant_operating_hours.php
include "db.php"; // Memanggil koneksi database ($conn) & session_start()

// Proteksi Halaman: Jika sesi user_id kosong, tendang kembali ke login.php
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ==========================================
// 1. PROSES SIMPAN / TAMBAH DATA (CREATE)
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'store') {
    $tenant_id   = $_POST['tenant_id'];
    $day_of_week = $_POST['day_of_week'];
    $open_time   = $_POST['open_time'];
    $close_time  = $_POST['close_time'];
    $is_open     = isset($_POST['is_open']) ? 1 : 0; // Default di DB adalah '1' jika tidak diisi

    $query = "INSERT INTO tenant_operating_hours (tenant_id, day_of_week, open_time, close_time, is_open) VALUES (?, ?, ?, ?, ?)";
    $stmt  = $conn->prepare($query);
    $stmt->bind_param("iissi", $tenant_id, $day_of_week, $open_time, $close_time, $is_open);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Data berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan data: " . $conn->error;
    }
    header("Location: tenant_operating_hours.php");
    exit;
}

// ==========================================
// 2. PROSES UPDATE DATA (UPDATE)
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'update') {
    $id          = $_POST['id'];
    $tenant_id   = $_POST['tenant_id'];
    $day_of_week = $_POST['day_of_week'];
    $open_time   = $_POST['open_time'];
    $close_time  = $_POST['close_time'];
    $is_open     = isset($_POST['is_open']) ? 1 : 0;

    $query = "UPDATE tenant_operating_hours SET tenant_id = ?, day_of_week = ?, open_time = ?, close_time = ?, is_open = ? WHERE id = ?";
    $stmt  = $conn->prepare($query);
    $stmt->bind_param("iissii", $tenant_id, $day_of_week, $open_time, $close_time, $is_open, $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Data berhasil diperbarui!";
    } else {
        $_SESSION['error'] = "Gagal memperbarui data: " . $conn->error;
    }
    header("Location: tenant_operating_hours.php");
    exit;
}

// ==========================================
// 3. PROSES HAPUS DATA (DELETE)
// ==========================================
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    $query = "DELETE FROM tenant_operating_hours WHERE id = ?";
    $stmt  = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Data berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus data: " . $conn->error;
    }
    header("Location: tenant_operating_hours.php");
    exit;
}

// ==========================================
// 4. AMBIL DATA UNTUK DITAMPILKAN (READ)
// ==========================================
$query  = "SELECT * FROM tenant_operating_hours ORDER BY tenant_id ASC, day_of_week ASC";
$result = $conn->query($query);
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
    <div id="dragScrollOperatingContainer" class="table-responsive rounded-3 drag-scroll-container" style="border: none !important; background: transparent !important; cursor: grab; box-shadow: none !important; -webkit-box-shadow: none !important;">
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
                        <?= isset($hari[$row['day_of_week']]) ? $hari[$row['day_of_week']] : $row['day_of_week'] ?>
                    </td>
                    <td class="text-center text-white-50" style="background: transparent !important; border: none !important;"><?= substr($row['open_time'], 0, 5) ?></td>
                    <td class="text-center text-white-50" style="background: transparent !important; border: none !important;"><?= substr($row['close_time'], 0, 5) ?></td>
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <?php if ($row['is_open'] == 1): ?>
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
                        <a href="tenant_operating_hours.php?action=delete&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Delete" onclick="return confirm('Apakah Anda yakin ingin menghapus data operasional ini?')">
                          <i class="bi bi-trash-fill"></i>
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

                        <!-- PILIH HARI -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Hari <span class="text-danger">*</span></label>
                            <select class="form-select" name="day_of_week" id="operating_day_of_week" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                                <option value="" disabled selected>-- Pilih Hari --</option>
                                <option value="1">Senin</option>
                                <option value="2">Selasa</option>
                                <option value="3">Rabu</option>
                                <option value="4">Kamis</option>
                                <option value="5">Jumat</option>
                                <option value="6">Sabtu</option>
                                <option value="0">Minggu</option>
                            </select>
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

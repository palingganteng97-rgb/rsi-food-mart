<?php
include 'db.php';
include 'notification_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$status = isset($_GET['status']) ? $_GET['status'] : "";
$msg = "";

if (isset($_POST['action_add_unit'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $symbol = mysqli_real_escape_string($conn, $_POST['symbol']);

    if (empty($name) || empty($symbol)) {
        $status = "error";
        $msg = "Semua bidang bertanda bintang wajib diisi.";
    } else {
        $check = mysqli_query($conn, "SELECT id FROM units WHERE symbol = '$symbol' LIMIT 1");
        if (mysqli_num_rows($check) > 0) {
            $status = "error";
            $msg = "Simbol satuan '$symbol' sudah terdaftar!";
        } else {
            $query = "INSERT INTO units (name, symbol) VALUES ('$name', '$symbol')";
            if (mysqli_query($conn, $query)) {
                $status = "success_insert";
            } else {
                $status = "error";
                $msg = "Gagal menyimpan data ke database: " . mysqli_error($conn);
            }
        }
    }
}

if (isset($_POST['action_update_unit'])) {
    $id = intval($_POST['id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $symbol = mysqli_real_escape_string($conn, $_POST['symbol']);

    if (empty($id) || empty($name) || empty($symbol)) {
        $status = "error";
        $msg = "Semua bidang bertanda bintang wajib diisi.";
    } else {
        $check = mysqli_query($conn, "SELECT id FROM units WHERE symbol = '$symbol' AND id != $id LIMIT 1");
        if (mysqli_num_rows($check) > 0) {
            $status = "error";
            $msg = "Simbol satuan '$symbol' sudah digunakan oleh data lain!";
        } else {
            $query = "UPDATE units SET name = '$name', symbol = '$symbol' WHERE id = $id";
            if (mysqli_query($conn, $query)) {
                $status = "success_update";
            } else {
                $status = "error";
                $msg = "Gagal memperbarui database: " . mysqli_error($conn);
            }
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $query = "DELETE FROM units WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        $status = "success_delete";
    } else {
        $status = "error";
        $msg = "Gagal menghapus data dari database: " . mysqli_error($conn);
    }
}

$listUnits = [];
$fetchQuery = mysqli_query($conn, "SELECT * FROM units ORDER BY id DESC");
if ($fetchQuery) {
    while ($row = mysqli_fetch_assoc($fetchQuery)) {
        $listUnits[] = $row;
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
    <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
            <div>
                <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Units</h2>
            </div>
            <div>
                <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" onclick="openTambahUnit()">
                    <i class="bi bi-plus-circle"></i> Tambah Satuan
                </button>
            </div>
        </div>

        <?php if (!empty($status)): ?>
            <div class="alert <?= strpos($status, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
                <strong>
                    <?php 
                    if ($status == 'success_insert') echo "Data satuan berhasil ditambahkan!";
                    elseif ($status == 'success_update') echo "Data satuan berhasil diperbarui!";
                    elseif ($status == 'success_delete') echo "Data satuan berhasil dihapus!";
                    else echo "Operasi gagal: " . htmlspecialchars($msg);
                    ?>
                </strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div id="dragScrollUnitContainer" class="table-responsive rounded-3 drag-scroll-container" style="border: none !important; background: transparent !important; cursor: grab; box-shadow: none !important; -webkit-box-shadow: none !important;">
            <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 800px; user-select: none; border-collapse: collapse !important;">
                <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
                    <tr>
                        <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 100px;">ID</th>
                        <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Unit Name</th>
                        <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 200px;">Symbol</th>
                        <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody style="background: transparent !important;">
                    <?php if (!empty($listUnits)): foreach ($listUnits as $row): ?>
                        <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                            <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= $row['id'] ?></td>
                            <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;">
                                <?= htmlspecialchars($row['name']) ?>
                            </td>
                            <td class="text-white" style="background: transparent !important; border: none !important;">
                                <span class="badge bg-info-subtle text-info border border-info border-opacity-25 rounded-2 px-2.5 py-1" style="font-size: 0.85rem; background: rgba(13, 202, 240, 0.15);">
                                    <?= htmlspecialchars($row['symbol']) ?>
                                </span>
                            </td>
                            <td class="text-center" style="background: transparent !important; border: none !important;">
                                <div class="d-flex justify-content-center gap-1">
                                    <button class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Edit" onclick='openEditUnit(<?= json_encode($row) ?>)'>
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Delete" onclick='confirmDeleteUnit(<?= json_encode($row) ?>)'>
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted shadow-none" style="background: transparent !important; border: none !important;">
                                <i class="bi bi-folder-x d-block mb-2" style="font-size: 2rem; color: rgba(148, 163, 184, 0.4);"></i>
                                Tidak ada data satuan saat ini.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- MODAL FORM INPUT MELEBAR DI TENGAH (WIDE MODE & BEBAS SCROLLBAR) -->
<div class="modal fade" id="modalUnit" tabindex="-1" aria-labelledby="modalUnitLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.93) !important; backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.2); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
                <h5 class="modal-title fw-bold text-white" id="modalUnitLabel">Form Satuan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formUnit" action="units.php" method="POST">
                <input type="hidden" name="id" id="unit_id">
                <div id="unit_action_flag"></div>
                <div class="modal-body" style="overflow: visible !important;">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Nama Satuan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="unit_name" placeholder="Contoh: Kilogram, Liter, Porsi, Bungkus..." style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Simbol Satuan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="symbol" id="unit_symbol" placeholder="Contoh: kg, l, pcs, bks..." style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.15); background: rgba(15, 23, 42, 0.95); border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="btnSubmitUnit">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL HAPUS -->
<div class="modal fade" id="modalDeleteUnit" tabindex="-1" aria-labelledby="modalDeleteUnitLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(12px); border: 1px solid rgba(239, 68, 68, 0.2); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-body text-center p-4">
                <div class="text-danger mb-3">
                    <i class="bi bi-exclamation-triangle-fill" style="font-size: 3rem;"></i>
                </div>
                <h5 class="modal-title fw-bold text-white mb-2" id="modalDeleteUnitLabel">Hapus Satuan?</h5>
                <p class="text-white-50 small mb-4">
                    Apakah Anda yakin ingin menghapus satuan <span id="delete_unit_name" class="fw-bold text-white"></span>? Tindakan ini tidak dapat dibatalkan.
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal" style="border-radius: 8px;">Batal</button>
                    <a id="btn_confirm_delete_unit" href="#" class="btn btn-danger px-4" style="border-radius: 8px;">Ya, Hapus</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT EVENT MOUSE DRAG TO SCROLL & HANDLER MODAL -->
<script>
let deleteUnitModalInstance = null;
let unitModalInstance = null;

function getUnitModal() {
    if (!unitModalInstance) {
        unitModalInstance = new bootstrap.Modal(document.getElementById('modalUnit'));
    }
    return unitModalInstance;
}

function openTambahUnit() {
    document.getElementById('formUnit').reset();
    document.getElementById('modalUnitLabel').innerText = 'Tambah Satuan Baru';
    document.getElementById('unit_id').value = '';
    document.getElementById('btnSubmitUnit').className = "btn btn-success";
    document.getElementById('btnSubmitUnit').innerText = "Simpan Data";
    document.getElementById('unit_action_flag').innerHTML = '<input type="hidden" name="action_add_unit" value="1">';
    getUnitModal().show();
}

function openEditUnit(data) {
    document.getElementById('formUnit').reset();
    document.getElementById('modalUnitLabel').innerText = 'Ubah Data Satuan';
    document.getElementById('unit_id').value = data.id;
    document.getElementById('unit_name').value = data.name;
    document.getElementById('unit_symbol').value = data.symbol;
    document.getElementById('btnSubmitUnit').className = "btn btn-warning text-dark fw-medium";
    document.getElementById('btnSubmitUnit').innerText = "Simpan Perubahan";
    document.getElementById('unit_action_flag').innerHTML = '<input type="hidden" name="action_update_unit" value="1">';
    getUnitModal().show();
}

function confirmDeleteUnit(data) {
    document.getElementById('delete_unit_name').innerText = '"' + data.name + '"';
    document.getElementById('btn_confirm_delete_unit').href = 'units.php?action=delete&id=' + data.id;
    
    if (!deleteUnitModalInstance) {
        deleteUnitModalInstance = new bootstrap.Modal(document.getElementById('modalDeleteUnit'));
    }
    deleteUnitModalInstance.show();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

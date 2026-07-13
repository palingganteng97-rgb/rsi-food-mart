<?php
// tenant_settings.php
include "db.php"; // Memanggil koneksi database ($conn) & session_start()

// Proteksi Halaman: Jika sesi user_id kosong, tendang kembali ke login.php
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Inisialisasi variabel status notifikasi
$status = "";
$msg = "";
if (isset($_SESSION['status'])) {
    $status = $_SESSION['status'];
    $msg = $_SESSION['msg'] ?? "";
    unset($_SESSION['status'], $_SESSION['msg']);
}

// ==========================================
// 1. PROSES SIMPAN / TAMBAH DATA (CREATE)
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'insert') {
    $tenant_id     = $_POST['tenant_id'];
    $auto_accept   = isset($_POST['auto_accept']) ? (int)$_POST['auto_accept'] : 0;
    $accept_order  = isset($_POST['accept_order']) ? (int)$_POST['accept_order'] : 1;
    $minimum_order = $_POST['minimum_order'] ?: '0.00';
    $maximum_order = $_POST['maximum_order'] ?: '0.00';

    $query = "INSERT INTO tenant_settings (tenant_id, auto_accept, accept_order, minimum_order, maximum_order) VALUES (?, ?, ?, ?, ?)";
    $stmt  = $conn->prepare($query);
    $stmt->bind_param("iiidd", $tenant_id, $auto_accept, $accept_order, $minimum_order, $maximum_order);
    
    if ($stmt->execute()) {
        $_SESSION['status'] = 'success_insert';
    } else {
        $_SESSION['status'] = 'failed';
        $_SESSION['msg'] = $conn->error;
    }
    header("Location: tenant_settings.php");
    exit;
}

// ==========================================
// 2. PROSES UPDATE DATA (UPDATE)
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'update') {
    $id            = $_POST['id'];
    $tenant_id     = $_POST['tenant_id'];
    $auto_accept   = isset($_POST['auto_accept']) ? (int)$_POST['auto_accept'] : 0;
    $accept_order  = isset($_POST['accept_order']) ? (int)$_POST['accept_order'] : 0;
    $minimum_order = $_POST['minimum_order'] ?: '0.00';
    $maximum_order = $_POST['maximum_order'] ?: '0.00';

    $query = "UPDATE tenant_settings SET tenant_id = ?, auto_accept = ?, accept_order = ?, minimum_order = ?, maximum_order = ? WHERE id = ?";
    $stmt  = $conn->prepare($query);
    $stmt->bind_param("iiiddi", $tenant_id, $auto_accept, $accept_order, $minimum_order, $maximum_order, $id);
    
    if ($stmt->execute()) {
        $_SESSION['status'] = 'success_update';
    } else {
        $_SESSION['status'] = 'failed';
        $_SESSION['msg'] = $conn->error;
    }
    header("Location: tenant_settings.php");
    exit;
}

// ==========================================
// 3. PROSES HAPUS DATA (DELETE)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $id = $_GET['id'];
    
    $query = "DELETE FROM tenant_settings WHERE id = ?";
    $stmt  = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['status'] = 'success_delete';
    } else {
        $_SESSION['status'] = 'failed';
        $_SESSION['msg'] = $conn->error;
    }
    header("Location: tenant_settings.php");
    exit;
}

// ==========================================
// 4. AMBIL DATA UNTUK DITAMPILKAN (READ)
// ==========================================
$query  = "SELECT ts.*, t.name AS tenant_name 
           FROM tenant_settings ts 
           LEFT JOIN tenants t ON ts.tenant_id = t.id 
           ORDER BY ts.id DESC";
$result = $conn->query($query);
$listSettings = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $listSettings[] = $row;
    }
}

$listActiveTenants = [];
$tResult = $conn->query("SELECT id, name FROM tenants ORDER BY name ASC");
if ($tResult && $tResult->num_rows > 0) {
    while ($tRow = $tResult->fetch_assoc()) {
        $listActiveTenants[] = $tRow;
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

<main class="content-shift p-4">
  <!-- Container tabel dengan tema gelap transparan yang selaras sempurna -->
  <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
    
    <!-- HEADER TABEL & TOMBOL TAMBAH PENGATURAN -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
      <div>
        <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;"> Tenant Settings </h2>
      </div>
      <div>
        <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalTenantSetting" onclick="openTambahTenantSetting()">
          <i class="bi bi-sliders"></i> Konfigurasi Tenant
        </button>
      </div>
    </div>

    <!-- NOTIFIKASI STATUS OPERASI CRUD -->
    <?php if (!empty($status)): ?>
        <div class="alert <?= strpos($status, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
            <strong>
                <?php 
                if ($status == 'success_insert') echo "Konfigurasi tenant berhasil ditambahkan!";
                elseif ($status == 'success_update') echo "Konfigurasi tenant berhasil diperbarui!";
                elseif ($status == 'success_delete') echo "Konfigurasi tenant berhasil dihapus!";
                else echo "Operasi gagal: " . htmlspecialchars($msg);
                ?>
            </strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- STRUKTUR TABEL LIST DATA CONFIG TENANT (DRAG SCROLL & TRANSPARAN) -->
    <div id="dragScrollProductContainer" class="table-responsive rounded-3 drag-scroll-container" style="border: none !important; background: transparent !important; cursor: grab; box-shadow: none !important; -webkit-box-shadow: none !important;">
      <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 1000px; user-select: none; border-collapse: collapse !important;">
        <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
          <tr>
            <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 80px;"> ID</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 220px;"> Tenant Name</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;"> Auto Accept</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;"> Accept Order</th>
            <th class="py-3 text-end text-white" style="background: transparent !important; border: none !important; width: 180px;"> Min Order</th>
            <th class="py-3 text-end text-white" style="background: transparent !important; border: none !important; width: 180px;"> Max Order</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 120px;">Aksi</th>
          </tr>
        </thead>
        <tbody style="background: transparent !important;">
          <?php if (!empty($listSettings)): 
              foreach ($listSettings as $row): ?>
                  <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                    <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= $row['id'] ?></td>
                    <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['tenant_name'] ?? 'ID: '.$row['tenant_id']) ?></td>
                    
                    <!-- Status Auto Accept -->
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <?php if ($row['auto_accept'] == 1): ?>
                            <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem; background: rgba(25, 135, 84, 0.15);"><i class="bi bi-lightning-charge-fill me-1"></i>Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-muted border border-secondary border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem; background: rgba(148, 163, 184, 0.1);"><i class="bi bi-dash-circle me-1"></i>Nonaktif</span>
                        <?php endif; ?>
                    </td>

                    <!-- Status Accept Order -->
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <?php if ($row['accept_order'] == 1): ?>
                            <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem; background: rgba(25, 135, 84, 0.15);"><i class="bi bi-check-circle-fill me-1"></i>Bisa Order</span>
                        <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem; background: rgba(220, 53, 69, 0.15);"><i class="bi bi-x-circle-fill me-1"></i>Tutup Order</span>
                        <?php endif; ?>
                    </td>

                    <!-- Nilai Minimum & Maximum Order dengan Format Rupiah/Mata Uang -->
                    <td class="text-end fw-medium text-white-50" style="background: transparent !important; border: none !important;">
                        Rp <?= number_format($row['minimum_order'], 2, ',', '.') ?>
                    </td>
                    <td class="text-end fw-medium text-white-50" style="background: transparent !important; border: none !important;">
                        Rp <?= number_format($row['maximum_order'], 2, ',', '.') ?>
                    </td>

                    <!-- Tombol Aksi -->
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                      <div class="d-flex justify-content-center gap-1">
                        <button class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Edit" onclick='openEditTenantSetting(<?= json_encode($row) ?>)'>
                          <i class="bi bi-pencil-square"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" 
                                onclick="triggerDeleteTenantSetting('tenant_settings.php?action=delete&id=<?php echo $row['id']; ?>', '<?php echo addslashes($row['tenant_name']); ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                        </a>
                      </div>
                    </td>
                  </tr>
              <?php endforeach; 
          else: ?>
              <tr>
                <td colspan="7" class="text-center py-5 text-muted shadow-none" style="background: transparent !important; border: none !important;">
                  <i class="bi bi-folder-x d-block mb-2" style="font-size: 2rem; color: rgba(148, 163, 184, 0.4);"></i>
                  Tidak ada data konfigurasi tenant saat ini.
                </td>
              </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- MODAL FORM INPUT MELEBAR DI TENGAH (WIDE MODE & BEBAS SCROLLBAR) -->
<div class="modal fade" id="modalTenantSetting" tabindex="-1" aria-labelledby="modalTenantSettingLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.93) !important; backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.2); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
                <h5 class="modal-title fw-bold text-white" id="modalTenantSettingLabel">Form Konfigurasi Tenant</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formTenantSetting" action="tenant_settings.php" method="POST">
                <input type="hidden" name="id" id="setting_id">
                <div id="setting_action_flag"></div>
                
                <div class="modal-body" style="overflow: visible !important;">
                    <div class="row g-3">
                        <!-- PILIH TENANT -->
                        <div class="col-12">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Pilih Tenant <span class="text-danger">*</span></label>
                            <!-- Kontainer Tambahan Untuk Menjaga Dropdown Kunci Tetap Terbaca -->
                            <div id="tenant_select_wrapper">
                                <select class="form-select" name="tenant_id" id="setting_tenant_id" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                                    <option value="" disabled selected>-- Pilih Tenant --</option>
                                    <?php foreach ($listActiveTenants as $tOption): ?>
                                        <option value="<?= $tOption['id'] ?>"><?= htmlspecialchars($tOption['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- AUTO ACCEPT -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Auto Accept Order <span class="text-danger">*</span></label>
                            <select class="form-select" name="auto_accept" id="setting_auto_accept" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                                <option value="0" selected>Nonaktif (Manual)</option>
                                <option value="1">Aktif (Otomatis Terima)</option>
                            </select>
                        </div>

                        <!-- ACCEPT ORDER -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Status Terima Pesanan <span class="text-danger">*</span></label>
                            <select class="form-select" name="accept_order" id="setting_accept_order" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                                <option value="1" selected>Buka (Bisa Menerima Order)</option>
                                <option value="0">Tutup (Blokir Order Masuk)</option>
                            </select>
                        </div>

                        <!-- MINIMUM ORDER -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Minimum Order (Rp) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" name="minimum_order" id="setting_minimum_order" placeholder="0.00" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>

                        <!-- MAXIMUM ORDER -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Maximum Order (Rp) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" name="maximum_order" id="setting_maximum_order" placeholder="0.00" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.15); background: rgba(15, 23, 42, 0.95); border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="btnSubmitTenantSetting">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus Tenant -->
<div class="modal fade" id="modalDeleteTenant" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(10px); border: 1px solid rgba(239, 68, 68, 0.25); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-body text-center p-4">
                <div class="text-danger mb-3">
                    <i class="bi bi-exclamation-triangle-fill" style="font-size: 3rem; filter: drop-shadow(0 0 10px rgba(239, 68, 68, 0.3));"></i>
                </div>
                <h5 class="fw-bold text-white mb-2">Hapus Tenant Settings?</h5>
                <p class="text-muted small mb-4">Tindakan ini akan menghapus data tenant settings <span id="delete_tenant_name" class="text-white fw-semibold"></span>. Data yang dihapus tidak dapat dikembalikan.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-sm btn-secondary rounded-3 px-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
                    <a id="btn_confirm_delete" href="#" class="btn btn-sm btn-danger rounded-3 px-3 py-2 fw-medium">Ya, Hapus</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let deleteTenantUrlTarget = '';
let bootstrapDeleteTenantModalInstance = null;

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
        
        prodSlider.addEventListener('mouseleave', () => { isDown = false; prodSlider.style.cursor = 'grab'; });
        prodSlider.addEventListener('mouseup', () => { isDown = false; prodSlider.style.cursor = 'grab'; });
        
        prodSlider.addEventListener('mousemove', (e) => {
            if (!isDown) return; 
            e.preventDefault();
            const x = e.pageX - prodSlider.offsetLeft;
            prodSlider.scrollLeft = scrollLeft - ((x - startX) * 1.5);
        });
    }

    const formSetting = document.getElementById('formTenantSetting');
    if (formSetting) {
        formSetting.addEventListener('submit', function (e) {
            const minOrder = parseFloat(document.getElementById('setting_minimum_order').value) || 0;
            const maxOrder = parseFloat(document.getElementById('setting_maximum_order').value) || 0;

            if (maxOrder > 0 && maxOrder < minOrder) {
                e.preventDefault();
                alert('⚠️ Logika Salah: Maximum Order tidak boleh lebih kecil dari Minimum Order!');
            }
        });
    }

    // Handler klik konfirmasi hapus tenant settings
    const btnConfirmDelete = document.getElementById('btn_confirm_delete');
    if (btnConfirmDelete) {
        btnConfirmDelete.addEventListener('click', function(e) {
            if (deleteTenantUrlTarget) {
                e.preventDefault();
                window.location.href = deleteTenantUrlTarget;
            }
        });
    }
});

function openTambahTenantSetting() {
    document.getElementById('formTenantSetting').reset();
    document.getElementById('modalTenantSettingLabel').innerText = 'Tambah Konfigurasi Tenant';
    document.getElementById('setting_id').value = '';
    
    const tenantSelect = document.getElementById('setting_tenant_id');
    if (tenantSelect) {
        tenantSelect.removeAttribute('disabled');
        tenantSelect.disabled = false;
        tenantSelect.style.pointerEvents = 'auto';
        tenantSelect.style.backgroundColor = 'rgba(2, 6, 23, 0.4)';
    }
    
    document.getElementById('setting_auto_accept').value = '0';
    document.getElementById('setting_accept_order').value = '1';
    document.getElementById('setting_minimum_order').value = '0.00';
    document.getElementById('setting_maximum_order').value = '0.00';
    
    document.getElementById('btnSubmitTenantSetting').className = "btn btn-success";
    document.getElementById('btnSubmitTenantSetting').innerText = "Simpan Data";
    document.getElementById('setting_action_flag').innerHTML = '<input type="hidden" name="action" value="insert">';
}

function openEditTenantSetting(data) {
    document.getElementById('formTenantSetting').reset();
    document.getElementById('modalTenantSettingLabel').innerText = 'Ubah Konfigurasi Tenant';
    document.getElementById('setting_id').value = data.id;
    
    const tenantSelect = document.getElementById('setting_tenant_id');
    if (tenantSelect) {
        tenantSelect.removeAttribute('disabled');
        tenantSelect.disabled = false;
        tenantSelect.value = data.tenant_id;
        tenantSelect.style.pointerEvents = 'none';
        tenantSelect.style.backgroundColor = 'rgba(15, 23, 42, 0.6)';
    }
    
    document.getElementById('setting_auto_accept').value = data.auto_accept;
    document.getElementById('setting_accept_order').value = data.accept_order;
    document.getElementById('setting_minimum_order').value = data.minimum_order;
    document.getElementById('setting_maximum_order').value = data.maximum_order;
    
    document.getElementById('btnSubmitTenantSetting').className = "btn btn-warning text-dark fw-medium";
    document.getElementById('btnSubmitTenantSetting').innerText = "Simpan Perubahan";
    document.getElementById('setting_action_flag').innerHTML = '<input type="hidden" name="action" value="update">';
    
    var myModal = new bootstrap.Modal(document.getElementById('modalTenantSetting'));
    myModal.show();
}

// Fungsi pemicu untuk membuka modal konfirmasi hapus tenant settings
function triggerDeleteTenantSetting(url, tenantName) {
    deleteTenantUrlTarget = url;
    
    const namePlaceholder = document.getElementById('delete_tenant_name');
    if (namePlaceholder) {
        namePlaceholder.innerText = tenantName;
    }
    
    const modalElement = document.getElementById('modalDeleteTenant');
    if (modalElement) {
        if (!bootstrapDeleteTenantModalInstance) {
            bootstrapDeleteTenantModalInstance = new bootstrap.Modal(modalElement);
        }
        bootstrapDeleteTenantModalInstance.show();
    }
}

</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


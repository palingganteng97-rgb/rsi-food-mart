<?php
// role_permissions.php (Hanya Logika Atas Backend)
include "db.php"; // Di dalam db.php harus sudah dipanggil session_start()

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$crudError = '';
$crudSuccess = '';

// =========================================================================
// 1. PROSES MASS-UPSERT: SIMPAN PERUBAHAN PERMISSIONS KELOMPOK ROLE
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_matrix'])) {
    $matrixData = $_POST['matrix'] ?? []; // Struktur data array: [role_id][permission_id] = 1

    try {
        $conn->begin_transaction();

        // Kosongkan seluruh isi tabel role_permissions agar tidak bentrok
        $conn->query("DELETE FROM role_permissions");

        // Masukkan relasi Many-to-Many baru secara massal jika ada centang yang dikirim
        if (!empty($matrixData) && is_array($matrixData)) {
            $insStmt = $conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            
            foreach ($matrixData as $roleId => $permList) {
                $roleId = (int)$roleId;
                if (!is_array($permList)) continue;

                foreach ($permList as $permId => $value) {
                    $permId = (int)$permId;
                    $insStmt->bind_param("ii", $roleId, $permId);
                    $insStmt->execute();
                }
            }
            $insStmt->close();
        }

        $conn->commit();
        $crudSuccess = "Matriks hak akses grup berhasil diperbarui!";
    } catch (Throwable $e) {
        $conn->rollback();
        $crudError = "Kesalahan sistem saat menyimpan matriks: " . $e->getMessage();
    }
}

// =========================================================================
// 1B. PROSES SAVE PER BARIS (VIA AJAX - action_save_row)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_row'])) {
    header('Content-Type: application/json');
    $permissionId = (int)($_POST['permission_id'] ?? 0);
    $roleIds = [];
    
    if (isset($_POST['role_ids'])) {
        $decoded = json_decode($_POST['role_ids'], true);
        if (is_array($decoded)) {
            $roleIds = array_map('intval', $decoded);
        }
    }

    if ($permissionId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID permission tidak valid.']);
        exit;
    }

    try {
        $conn->begin_transaction();
        
        // Hapus semua relasi untuk permission ini
        $delStmt = $conn->prepare("DELETE FROM role_permissions WHERE permission_id = ?");
        $delStmt->bind_param("i", $permissionId);
        $delStmt->execute();
        $delStmt->close();
        
        // Insert relasi baru
        if (!empty($roleIds)) {
            $insStmt = $conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($roleIds as $rid) {
                $insStmt->bind_param("ii", $rid, $permissionId);
                $insStmt->execute();
            }
            $insStmt->close();
        }
        
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Izin baris berhasil disimpan!']);
    } catch (Throwable $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Kesalahan: ' . $e->getMessage()]);
    }
    exit;
}

// =========================================================================
// 2. QUERY FETCH DATA: AMBIL DAFTAR ROLES, PERMISSIONS, & RELASI AKTIF
// =========================================================================
$roles = [];
$rQuery = $conn->query("SELECT id, name FROM roles ORDER BY id ASC");
if ($rQuery) {
    while ($r = $rQuery->fetch_assoc()) {
        $roles[] = $r;
    }
}

$permissions = [];
// PERBAIKAN: Menggunakan kolom 'permission_name' sesuai struktur riil database Anda
$pQuery = $conn->query("SELECT id, permission_name, module_name FROM permissions ORDER BY module_name ASC, permission_name ASC");
if ($pQuery) {
    while ($p = $pQuery->fetch_assoc()) {
        $permissions[] = $p;
    }
}

// Mengambil relasi perantara aktif untuk dicocokkan ke status 'checked' checkbox
$activeMap = [];
$mQuery = $conn->query("SELECT role_id, permission_id FROM role_permissions");
if ($mQuery) {
    while ($m = $mQuery->fetch_assoc()) {
        $activeMap[$m['role_id']][$m['permission_id']] = true;
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
  <div class="container-fluid rounded-4 p-4 text-white" style="background: transparent !important; border: none !important; box-shadow: none !important;">
    
    <!-- HEADER TABEL -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
      <div>
        <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Matrix Role Permissions</h2>
      </div>
    </div>

    <!-- AREA NOTIFIKASI RESPONS JAVASCRIPT AJAX -->
    <div id="ajaxAlertContainer"></div>

    <!-- KONTEN TABEL DRAG-SCROLL SEJAJAR -->
    <div class="table-responsive" id="dragScrollStockContainer" style="overflow-x: auto; cursor: grab; scrollbar-width: none; -ms-overflow-style: none;">
      <style>
          #dragScrollStockContainer::-webkit-scrollbar { display: none; }
          .table-custom-hover tbody tr:hover { background: rgba(148, 163, 184, 0.15) !important; transition: background 0.15s ease-in-out; }
          .table-force-white th, .table-force-white td { color: #ffffff !important; }
      </style>
      
      <table class="table align-middle mb-0 table-force-white table-custom-hover" style="--bs-table-bg: transparent; border-collapse: separate; border-spacing: 0 8px; min-width: 1100px; user-select: none;">
        <thead style="font-size: 0.85rem; background: rgba(30, 41, 59, 0.65) !important;">
          <tr>
            <th class="ps-4 py-3" style="border-radius: 10px 0 0 10px; border-bottom: 1px solid rgba(148, 163, 184, 0.2); width: 220px;">Modul / Nama Izin</th>
            <th class="py-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.2); width: 180px;">Key Akses</th>
            <?php foreach ($roles as $r): ?>
                <th class="text-center py-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.2); width: 140px;">
                    <?= htmlspecialchars($r['name']) ?>
                </th>
            <?php endforeach; ?>
            <!-- PERBAIKAN: Mengubah text-end dan pe-4 menjadi text-center agar header lurus di tengah -->
            <th class="text-center py-3" style="border-radius: 0 10px 10px 0; border-bottom: 1px solid rgba(148, 163, 184, 0.2); width: 120px;">Aksi</th>
          </tr>
        </thead>
        <tbody style="font-size: 0.9rem;">
          <?php if (!empty($permissions)): ?>
            <?php foreach ($permissions as $p): ?>
              <tr class="row-permission" data-permission-id="<?= $p['id'] ?>" style="background: rgba(30, 41, 59, 0.55); border: 1px solid rgba(148, 163, 184, 0.15);">
                
                <!-- KOLOM 1: Modul -->
                <td class="ps-4 py-3" style="border-radius: 10px 0 0 10px;">
                  <span class="badge bg-secondary rounded-2 px-2 py-1 small text-uppercase" style="font-size: 0.7rem; color: #ffffff !important;"><?= htmlspecialchars($p['module_name'] ?: 'System') ?></span>
                </td>
                
                <!-- KOLOM 2: Key Akses -->
                <td class="fw-bold" style="font-family: monospace; font-size: 0.95rem; color: #ffffff !important;"><?= htmlspecialchars($p['permission_name']) ?></td>
                
                <!-- KOLOM DINAMIS: Checkbox Roles -->
                <?php foreach ($roles as $r): 
                    $isChecked = isset($activeMap[$r['id']][$p['id']]); ?>
                    <td class="text-center">
                        <input type="checkbox" data-role-id="<?= $r['id'] ?>" class="form-check-input bg-dark border-secondary checking-role" style="width: 1.25rem; height: 1.25rem; cursor: pointer;" <?= $isChecked ? 'checked' : ''; ?>>
                    </td>
                <?php endforeach; ?>
                
                <!-- KOLOM AKSI: Tombol Simpan Per Baris -->
                <!-- PERBAIKAN: Mengubah text-end dan pe-4 menjadi text-center agar tombol lurus simetris di tengah -->
                <td class="text-center" style="border-radius: 0 10px 10px 0;">
                  <button type="button" class="btn btn-sm btn-success rounded-3 px-3 py-1.5 fw-medium d-inline-flex align-items-center justify-content-center gap-1" onclick="saveRowPermission(this, <?= $p['id'] ?>)">
                    <i class="bi bi-cloud-arrow-up-fill"></i> Simpan
                  </button>
                </td>
                
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="<?= count($roles) + 3 ?>" class="text-center py-5 fw-bold" style="background: rgba(30, 41, 59, 0.2); border-radius: 10px;">
                  <i class="bi bi-shield-slash fs-2 d-block mb-2 text-muted"></i>
                  Belum ada data izin akses (permissions) terdaftar di sistem.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</main>

<script>
    let deleteRolePermissionUrlTarget = '';

    document.addEventListener('DOMContentLoaded', function() {
        if (window.history.replaceState && window.location.search.includes('status=')) {
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
        }

        const matrixSlider = document.getElementById('dragScrollStockContainer');
        if (matrixSlider) {
            let isDown = false;
            let startX, scrollLeft;
            
            matrixSlider.addEventListener('mousedown', (e) => {
                if (e.target.closest('button') || e.target.closest('a') || e.target.closest('select')) return;
                isDown = true; 
                matrixSlider.style.cursor = 'grabbing';
                startX = e.pageX - matrixSlider.offsetLeft; 
                scrollLeft = matrixSlider.scrollLeft;
            });
            
            matrixSlider.addEventListener('mouseleave', () => { isDown = false; matrixSlider.style.cursor = 'grab'; });
            matrixSlider.addEventListener('mouseup', () => { isDown = false; matrixSlider.style.cursor = 'grab'; });
            
            matrixSlider.addEventListener('mousemove', (e) => {
                if (!isDown) return; 
                const x = e.pageX - matrixSlider.offsetLeft;
                const walk = (x - startX) * 2;
                if (Math.abs(walk) > 5) {
                    e.preventDefault();
                    matrixSlider.scrollLeft = scrollLeft - walk;
                }
            });
        }

        const btnConfirmDeleteRolePermission = document.getElementById('btn_confirm_delete_role_permission');
        if (btnConfirmDeleteRolePermission) {
            btnConfirmDeleteRolePermission.addEventListener('click', function(e) {
                if (deleteRolePermissionUrlTarget) {
                    e.preventDefault();
                    window.location.href = deleteRolePermissionUrlTarget;
                }
            });
        }
    });

    function saveRowPermission(btn, permissionId) {
        const row = btn.closest('.row-permission');
        const checkboxes = row.querySelectorAll('.checking-role');
        let selectedRoles = [];
        checkboxes.forEach(cb => {
            if (cb.checked) {
                selectedRoles.push(cb.getAttribute('data-role-id'));
            }
        });
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        fetch('role_permissions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action_save_row=1&permission_id=' + permissionId + '&role_ids=' + JSON.stringify(selectedRoles)
        })
        .then(response => response.text())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            const alertContainer = document.getElementById('ajaxAlertContainer');
            if (alertContainer) {
                alertContainer.innerHTML = `
                    <div class="alert alert-success border-0 rounded-3 mb-4 alert-dismissible fade show" role="alert" style="background: rgba(34, 197, 94, 0.12) !important; color: #86efac !important;">
                        <i class="bi bi-check-circle-fill me-2"></i> Izin baris berhasil disimpan secara instan!
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>`;
                setTimeout(() => {
                    const alertEl = alertContainer.querySelector('.alert');
                    if (alertEl) {
                        const bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
                        bsAlert.close();
                    }
                }, 3000);
            }
        })
        .catch(error => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            alert('Gagal memproses penyimpanan data: ' + error);
        });
    }

    function triggerDeleteRolePermission(url, roleName) {
        const namePlaceholder = document.getElementById('delete_role_permission_name');
        if (namePlaceholder) {
            namePlaceholder.innerText = roleName;
        }
        deleteRolePermissionUrlTarget = url;
        const btnConfirm = document.getElementById('btn_confirm_delete_role_permission');
        if (btnConfirm) {
            btnConfirm.setAttribute('href', url);
        }
        const modalElement = document.getElementById('modalDeleteRolePermission');
        if (modalElement) {
            const existingInstance = bootstrap.Modal.getInstance(modalElement);
            if (existingInstance) {
                existingInstance.dispose();
            }
            const newModalInstance = new bootstrap.Modal(modalElement);
            newModalInstance.show();
        }
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

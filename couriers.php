<?php
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Menangkap parameter aksi dari URL
$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

// 1. PROSES TAMBAH DATA (CREATE)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $status = isset($_POST['status']) ? intval($_POST['status']) : 1;

    if ($name !== '') {
        $stmt = $conn->prepare("INSERT INTO couriers (name, phone, status) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $name, $phone, $status);
        
        if ($stmt->execute()) {
            header("Location: couriers.php?status=success_create");
        } else {
            header("Location: couriers.php?status=error&msg=" . urlencode($stmt->error));
        }
        exit();
    }
}

// 2. PROSES UBAH DATA (UPDATE)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = intval($_POST['id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $status = isset($_POST['status']) ? intval($_POST['status']) : 1;

    if ($id > 0 && $name !== '') {
        $stmt = $conn->prepare("UPDATE couriers SET name = ?, phone = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssii", $name, $phone, $status, $id);
        
        if ($stmt->execute()) {
            header("Location: couriers.php?status=success_update");
        } else {
            header("Location: couriers.php?status=error&msg=" . urlencode($stmt->error));
        }
        exit();
    }
}

// 3. PROSES HAPUS DATA (DELETE)
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id'] ?? 0);

    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM couriers WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            header("Location: couriers.php?status=success_delete");
        } else {
            header("Location: couriers.php?status=error&msg=" . urlencode($stmt->error));
        }
        exit();
    }
}

// 4. PROSES AMBIL DATA UNTUK MENAMPILKAN LIST (READ)
$couriers = [];
$result = $conn->query("SELECT * FROM couriers ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $couriers[] = $row;
    }
}

// Menangkap status alert notifikasi dari redirect parameter URL
$status = isset($_GET['status']) ? (string)$_GET['status'] : '';
$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';
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
    .search-box { background: rgba(2,6,23,.35); border:1px solid rgba(148,163,184,.25); border-radius: 18px; }
    .bottom-nav { position: fixed; left:0; right:0; bottom:0; z-index: 1035; background: rgba(15,23,42,.88); backdrop-filter: blur(10px); border-top: 1px solid rgba(148,163,184,.25); display:block; }
    #dragScrollCourierContainer::-webkit-scrollbar { display: none !important; }
    #dragScrollCourierContainer { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow-x: auto !important; cursor: grab !important; box-shadow: none !important; border: none !important; -webkit-box-shadow: none !important; }
    #dragScrollCourierContainer:active { cursor: grabbing !important; }
    #dragScrollCourierContainer table { border-collapse: collapse !important; border: none !important; }
    #dragScrollCourierContainer table th, #dragScrollCourierContainer table td { border-left: none !important; border-right: none !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; }
    .text-white-element { user-select: none; }
    @media (min-width: 992px) { main.content-shift { margin-left: 280px; } .bottom-nav { display:none; } }
  </style>
</head>
<body>
  <?php require __DIR__ . '/sidebar.php'; ?>

<main class="content-shift p-4">
    <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">

      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
        <div>
          <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Couriers</h2>
        </div>
        <div>
          <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalCourier" onclick="openTambahCourier()">
            <i class="bi bi-plus-circle"></i> Tambah Kurir
          </button>
        </div>
      </div>

      <?php if (!empty($status)): ?>
        <div class="alert <?= strpos($status, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
          <strong>
            <?php 
              if ($status === 'success_create') echo "Data kurir berhasil ditambahkan!";
              elseif ($status === 'success_update') echo "Data kurir berhasil diperbarui!";
              elseif ($status === 'success_delete') echo "Data kurir berhasil dihapus!";
              elseif ($status === 'error') echo "Operasi gagal: " . htmlspecialchars($msg);
              else echo "Operasi: " . htmlspecialchars($status);
            ?>
          </strong>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <div id="dragScrollCourierContainer" class="table-responsive rounded-3" style="border: none !important; background: transparent !important;">
        <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 700px; user-select: none; border-collapse: collapse !important;">
          <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
            <tr>
              <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 100px;">ID</th>
              <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 280px;">Name</th>
              <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 220px;">Phone</th>
              <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 130px;">Status</th>
              <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;">Aksi</th>
            </tr>
          </thead>
          <tbody style="background: transparent !important;">
            <?php if (!empty($couriers)): ?>
              <?php foreach ($couriers as $row): ?>
                <tr style="background: transparent !important; font-size: 0.88rem;">
                  <!-- PERBAIKAN: Mengubah kelas ID menjadi text-white-50 agar abu-abu redup serasi -->
                  <td class="text-center fw-medium text-white-50" style="background: transparent !important; border: none !important;"><?= (int)$row['id'] ?></td>
                  <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['name'] ?? '') ?></td>
                  <td class="text-white-50" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['phone'] ?? '') ?></td>
                  <td class="text-center" style="background: transparent !important; border: none !important;">
                    <?php if ((int)($row['status'] ?? 0) === 1): ?>
                      <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-3 py-1.5 rounded-pill">Aktif</span>
                    <?php else: ?>
                      <span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-50 px-3 py-1.5 rounded-pill">Non-Aktif</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center" style="background: transparent !important; border: none !important;">
                    <div class="d-flex justify-content-center gap-2">
                      <button class="btn btn-sm btn-outline-warning rounded-2" data-bs-toggle="modal" data-bs-target="#modalCourier" onclick='openEditCourier(<?= json_encode($row) ?>)'>
                        <i class="bi bi-pencil-square"></i>
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-danger rounded-2" data-bs-toggle="modal" data-bs-target="#modalHapusCourier" onclick="openHapusCourier(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name'] ?? '') ?>')">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" class="text-center py-5 text-muted italic" style="background: transparent !important; border: none !important;">Belum ada data kurir.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>

    <!-- MODAL POP-UP: FORM TAMBAH / EDIT KURIR -->
    <div class="modal fade" id="modalCourier" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" style="max-width: 450px;">
        <form id="formCourier" method="POST" action="couriers.php?action=create" class="modal-content text-white rounded-4 border-0" style="background: #1e293b;">
          <div class="modal-header border-bottom border-secondary border-opacity-20">
            <h5 class="modal-title fw-bold text-success" id="modalCourierLabel">Tambah Data Kurir</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
          </div>
          <div class="modal-body py-3">
            <input type="hidden" id="courier-id" name="id">
            <input type="hidden" id="courier-action" value="create">
            
            <div class="mb-3">
              <label for="courier-name" class="form-label small text-white-50">Nama Lengkap</label>
              <input type="text" class="form-content form-control bg-dark text-white border-secondary border-opacity-50" id="courier-name" name="name" required style="box-shadow: none;">
            </div>
            <div class="mb-3">
              <label for="courier-phone" class="form-label small text-white-50">Nomor Telepon / WA</label>
              <input type="text" class="form-content form-control bg-dark text-white border-secondary border-opacity-50" id="courier-phone" name="phone" required style="box-shadow: none;">
            </div>
            <div class="mb-2">
              <label for="courier-status" class="form-label small text-white-50">Status Aktif</label>
              <select class="form-select bg-dark text-white border-secondary border-opacity-50" id="courier-status" name="status" style="box-shadow: none;">
                <option value="1">Aktif</option>
                <option value="0">Non-Aktif</option>
              </select>
            </div>
          </div>
          <div class="modal-footer border-top border-secondary border-opacity-20 d-flex gap-2">
            <button type="button" class="btn btn-sm rounded-pill px-4 fw-medium text-white border-0" data-bs-dismiss="modal" style="background: #334155;">Batal</button>
            <button type="submit" id="btnSubmitCourier" class="btn btn-sm btn-success rounded-pill px-4 fw-medium shadow">Simpan Data</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL POP-UP: KONFIRMASI HAPUS DATA KURIR -->
    <div class="modal fade" id="modalHapusCourier" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content text-white rounded-4 border-0" style="background: #111827; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2">
              <i class="bi bi-exclamation-triangle-fill"></i> Hapus Kurir
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
          </div>
          <div class="modal-body py-3">
            <p class="text-white-50 m-0" style="font-size: 0.95rem; line-height: 1.5;">Apakah Anda yakin ingin menghapus data kurir bernama <strong id="txtDeleteCourierInfo" class="text-white">-</strong>?</p>
          </div>
          <div class="modal-footer border-0 pt-0 d-flex gap-2">
            <button type="button" class="btn btn-sm rounded-pill px-4 fw-medium text-white border-0" data-bs-dismiss="modal" style="background: #1f2937;">Batal</button>
            <a id="btnConfirmDeleteCourier" href="#" class="btn btn-danger btn-sm rounded-pill px-4 fw-medium shadow-sm">Ya, Hapus</a>
          </div>
        </div>
      </div>
    </div>

</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Bersihkan parameter status dari URL browser agar saat di-refresh tidak muncul lagi
    if (window.history.replaceState) {
        const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
        window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
    }

    // 2. Efek otomatis menutup notifikasi alert setelah 3 detik
    const alertElement = document.querySelector('.alert');
    if (alertElement) {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alertElement);
            bsAlert.close();
        }, 3000);
    }
});

// 2. FUNGSI UNTUK MODAL TAMBAH DATA KURIR
function openTambahCourier() {
    const form = document.getElementById('formCourier');
    form.reset();
    form.action = 'couriers.php?action=create'; // Mengarahkan aksi form ke proses insert

    document.getElementById('modalCourierLabel').innerText = 'Tambah Kurir Baru';
    document.getElementById('courier-id').value = '';
    document.getElementById('courier-action').value = 'create';
    
    const btnSubmit = document.getElementById('btnSubmitCourier');
    btnSubmit.className = 'btn btn-sm btn-success rounded-pill px-4 fw-medium shadow';
    btnSubmit.innerText = 'Simpan Data';
}

// 3. FUNGSI UNTUK MODAL EDIT DATA KURIR
function openEditCourier(data) {
    const form = document.getElementById('formCourier');
    form.reset();
    form.action = 'couriers.php?action=update'; // Mengarahkan aksi form ke proses update

    document.getElementById('modalCourierLabel').innerText = 'Perbarui Data Kurir';
    document.getElementById('courier-id').value = data.id;
    document.getElementById('courier-action').value = 'update';
    document.getElementById('courier-name').value = data.name || '';
    document.getElementById('courier-phone').value = data.phone || '';
    document.getElementById('courier-status').value = (data.status !== undefined && data.status !== null) ? String(data.status) : '1';
    
    const btnSubmit = document.getElementById('btnSubmitCourier');
    btnSubmit.className = 'btn btn-sm btn-warning text-dark rounded-pill px-4 fw-medium shadow';
    btnSubmit.innerText = 'Perbarui Data';
}

// 4. FUNGSI BARU: Mengisi Data ke Modal Konfirmasi Hapus Secara Dinamis
function openHapusCourier(id, courierName) {
    const btnConfirmDelete = document.getElementById('btnConfirmDeleteCourier');
    const txtCourierInfo = document.getElementById('txtDeleteCourierInfo');
    
    if (btnConfirmDelete) {
        btnConfirmDelete.href = 'couriers.php?action=delete&id=' + id;
    }
    if (txtCourierInfo) {
        txtCourierInfo.innerText = courierName;
    }
}
</script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


<?php
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Menangkap parameter aksi aksi dari URL
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

// Status notif dari URL
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
                  <td class="text-center fw-semibold" style="color: #94a3b8 !important;"><?= (int)$row['id'] ?></td>
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
                      <a href="couriers.php?action=delete&id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-danger rounded-2" onclick="return confirm('Apakah Anda yakin ingin menghapus kurir ini?')">
                        <i class="bi bi-trash"></i>
                      </a>
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
  </main>

  <!-- Modal Courier (Create/Update) -->
  <div class="modal fade" id="modalCourier" tabindex="-1" aria-labelledby="modalCourierLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content" style="background: rgba(15, 23, 42, 0.93) !important; backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.2); color: #e5e7eb; border-radius: 16px;">
        <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
          <h5 class="modal-title fw-bold text-white" id="modalCourierLabel">Form Data Kurir</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <form id="formCourier" action="couriers.php" method="POST">
          <input type="hidden" name="action" id="courier-action" value="create">
          <input type="hidden" name="id" id="courier-id" value="">

          <div class="modal-body" style="overflow: visible !important;">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Nama Kurir <span class="text-danger">*</span></label>
                <input type="text" name="name" id="courier-name" class="form-control" maxlength="150" placeholder="Masukkan nama kurir" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
              </div>

              <div class="col-md-6">
                <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Nomor Telepon</label>
                <input type="text" name="phone" id="courier-phone" class="form-control" maxlength="30" placeholder="Contoh: 0812xxxx" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
              </div>

              <div class="col-md-12">
                <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Status Publikasi</label>
                <select name="status" id="courier-status" class="form-select" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
                  <option value="1" selected>Aktif</option>
                  <option value="0">Non-Aktif</option>
                </select>
              </div>
            </div>
          </div>

          <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.15); background: rgba(15, 23, 42, 0.95); border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success" id="btnSubmitCourier">Simpan Data</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    function openTambahCourier() {
      document.getElementById('formCourier').reset();
      document.getElementById('modalCourierLabel').innerText = 'Tambah Kurir Baru';
      document.getElementById('courier-id').value = '';
      document.getElementById('courier-action').value = 'create';
      document.getElementById('btnSubmitCourier').className = 'btn btn-success';
      document.getElementById('btnSubmitCourier').innerText = 'Simpan Data';
    }

    function openEditCourier(data) {
      document.getElementById('formCourier').reset();
      document.getElementById('modalCourierLabel').innerText = 'Perbarui Data Kurir';
      document.getElementById('courier-id').value = data.id;
      document.getElementById('courier-action').value = 'update';
      document.getElementById('courier-name').value = data.name || '';
      document.getElementById('courier-phone').value = data.phone || '';
      document.getElementById('courier-status').value = (data.status !== undefined && data.status !== null) ? String(data.status) : '1';
      document.getElementById('btnSubmitCourier').className = 'btn btn-warning text-dark fw-medium';
      document.getElementById('btnSubmitCourier').innerText = 'Perbarui Data';
    }
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


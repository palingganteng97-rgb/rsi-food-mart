<?php
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

// 1. PROSES TAMBAH METODE PEMBAYARAN (CREATE)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $provider  = trim($_POST['provider'] ?? '');
    $is_online = isset($_POST['is_online']) ? intval($_POST['is_online']) : 0;

    if ($name !== '') {
        $stmt = $conn->prepare("INSERT INTO payment_methods (name, provider, is_online) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $name, $provider, $is_online);
        
        if ($stmt->execute()) {
            header("Location: payment_methods.php?status=success_create");
        } else {
            header("Location: payment_methods.php?status=error&msg=" . urlencode($stmt->error));
        }
        exit();
    }
}

// 2. PROSES UBAH METODE PEMBAYARAN (UPDATE)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = intval($_POST['id'] ?? 0);
    $name      = trim($_POST['name'] ?? '');
    $provider  = trim($_POST['provider'] ?? '');
    $is_online = isset($_POST['is_online']) ? intval($_POST['is_online']) : 0;

    if ($id > 0 && $name !== '') {
        $stmt = $conn->prepare("UPDATE payment_methods SET name = ?, provider = ?, is_online = ? WHERE id = ?");
        $stmt->bind_param("ssii", $name, $provider, $is_online, $id);
        
        if ($stmt->execute()) {
            header("Location: payment_methods.php?status=success_update");
        } else {
            header("Location: payment_methods.php?status=error&msg=" . urlencode($stmt->error));
        }
        exit();
    }
}

// 3. PROSES HAPUS METODE PEMBAYARAN (DELETE)
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id'] ?? 0);

    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM payment_methods WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            header("Location: payment_methods.php?status=success_delete");
        } else {
            header("Location: payment_methods.php?status=error&msg=" . urlencode($stmt->error));
        }
        exit();
    }
}

// 4. PROSES AMBIL DATA LIST (READ)
$payment_methods = [];
$result = $conn->query("SELECT * FROM payment_methods ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $payment_methods[] = $row;
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
    #dragScrollPaymentMethodsContainer::-webkit-scrollbar { display: none !important; }
    #dragScrollPaymentMethodsContainer { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow-x: auto !important; cursor: grab !important; box-shadow: none !important; border: none !important; -webkit-box-shadow: none !important; }
    #dragScrollPaymentMethodsContainer:active { cursor: grabbing !important; }
    #dragScrollPaymentMethodsContainer table { border-collapse: collapse !important; border: none !important; }
    #dragScrollPaymentMethodsContainer table th, #dragScrollPaymentMethodsContainer table td { border-left: none !important; border-right: none !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; }
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
          <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Payment Methods</h2>
          <div class="text-white-50" style="font-size:.9rem;">Kelola metode pembayaran</div>
        </div>
        <div>
          <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalPaymentMethod" onclick="openTambahPaymentMethod()">
            <i class="bi bi-plus-circle"></i> Tambah Metode
          </button>
        </div>
      </div>

      <?php if (!empty($status)): ?>
        <div class="alert <?= strpos($status, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
          <strong>
            <?php 
              if ($status === 'success_create') echo "Data metode berhasil ditambahkan!";
              elseif ($status === 'success_update') echo "Data metode berhasil diperbarui!";
              elseif ($status === 'success_delete') echo "Data metode berhasil dihapus!";
              elseif ($status === 'error') echo "Operasi gagal: " . htmlspecialchars($msg);
              else echo "Operasi: " . htmlspecialchars($status);
            ?>
          </strong>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <div id="dragScrollPaymentMethodsContainer" class="table-responsive rounded-3" style="border: none !important; background: transparent !important;">
        <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 820px; user-select: none; border-collapse: collapse !important;">
          <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
            <tr>
              <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 100px;">ID</th>
              <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 260px;">Name</th>
              <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 260px;">Provider</th>
              <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 140px;">Online</th>
              <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 180px;">Aksi</th>
            </tr>
          </thead>
          <tbody style="background: transparent !important;">
            <?php if (!empty($payment_methods)): ?>
              <?php foreach ($payment_methods as $row): ?>
                <tr style="background: transparent !important; font-size: 0.88rem;">
                  <td class="text-center fw-semibold" style="color: #94a3b8 !important;"><?= (int)($row['id'] ?? 0) ?></td>
                  <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['name'] ?? '') ?></td>
                  <td class="text-white-50" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['provider'] ?? '') ?></td>
                  <td class="text-center" style="background: transparent !important; border: none !important;">
                    <?php if ((int)($row['is_online'] ?? 0) === 1): ?>
                      <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-3 py-1.5 rounded-pill">Online</span>
                    <?php else: ?>
                      <span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-50 px-3 py-1.5 rounded-pill">Offline</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center" style="background: transparent !important; border: none !important;">
                    <div class="d-flex justify-content-center gap-2">
                      <button class="btn btn-sm btn-outline-warning rounded-2" data-bs-toggle="modal" data-bs-target="#modalPaymentMethod" onclick='openEditPaymentMethod(<?= json_encode($row) ?>)'>
                        <i class="bi bi-pencil-square"></i>
                      </button>
                      <a href="payment_methods.php?action=delete&id=<?= (int)($row['id'] ?? 0) ?>" class="btn btn-sm btn-outline-danger rounded-2" onclick="return confirm('Apakah Anda yakin ingin menghapus metode ini?')">
                        <i class="bi bi-trash"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" class="text-center py-5 text-muted italic" style="background: transparent !important; border: none !important;">Belum ada data metode pembayaran.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </main>

  <!-- Modal Payment Method (Create/Update) -->
  <div class="modal fade" id="modalPaymentMethod" tabindex="-1" aria-labelledby="modalPaymentMethodLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content" style="background: rgba(15, 23, 42, 0.93) !important; backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.2); color: #e5e7eb; border-radius: 16px;">
        <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
          <h5 class="modal-title fw-bold text-white" id="modalPaymentMethodLabel">Form Metode Pembayaran</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <form id="formPaymentMethod" action="payment_methods.php" method="POST">
          <input type="hidden" name="action" id="paymentmethod-action" value="create">
          <input type="hidden" name="id" id="paymentmethod-id" value="">

          <div class="modal-body" style="overflow: visible !important;">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Nama Metode <span class="text-danger">*</span></label>
                <input type="text" name="name" id="paymentmethod-name" class="form-control" maxlength="150" placeholder="Masukkan nama metode" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
              </div>

              <div class="col-md-6">
                <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Provider</label>
                <input type="text" name="provider" id="paymentmethod-provider" class="form-control" maxlength="150" placeholder="Contoh: BCA / Midtrans / dll" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
              </div>

              <div class="col-md-12">
                <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Status Online</label>
                <select name="is_online" id="paymentmethod-is_online" class="form-select" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
                  <option value="1" selected>Online</option>
                  <option value="0">Offline</option>
                </select>
              </div>
            </div>
          </div>

          <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.15); background: rgba(15, 23, 42, 0.95); border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success" id="btnSubmitPaymentMethod">Simpan Data</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    function openTambahPaymentMethod() {
      document.getElementById('formPaymentMethod').reset();
      document.getElementById('modalPaymentMethodLabel').innerText = 'Tambah Metode Pembayaran Baru';
      document.getElementById('paymentmethod-id').value = '';
      document.getElementById('paymentmethod-action').value = 'create';
      document.getElementById('btnSubmitPaymentMethod').className = 'btn btn-success';
      document.getElementById('btnSubmitPaymentMethod').innerText = 'Simpan Data';
    }

    function openEditPaymentMethod(data) {
      document.getElementById('formPaymentMethod').reset();
      document.getElementById('modalPaymentMethodLabel').innerText = 'Perbarui Data Metode Pembayaran';
      document.getElementById('paymentmethod-id').value = data.id;
      document.getElementById('paymentmethod-action').value = 'update';
      document.getElementById('paymentmethod-name').value = data.name || '';
      document.getElementById('paymentmethod-provider').value = data.provider || '';
      document.getElementById('paymentmethod-is_online').value = (data.is_online !== undefined && data.is_online !== null) ? String(data.is_online) : '1';
      document.getElementById('btnSubmitPaymentMethod').className = 'btn btn-warning text-dark fw-medium';
      document.getElementById('btnSubmitPaymentMethod').innerText = 'Perbarui Data';
    }
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


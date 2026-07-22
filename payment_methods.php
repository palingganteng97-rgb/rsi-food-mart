<?php
include 'db.php';
include 'notification_helper.php'; // INTEGRASI: Menyertakan fungsi pembuat notifikasi

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
            // INTEGRASI: Membuat notifikasi setelah berhasil menambahkan metode pembayaran baru
            createNotification(
                'admin', 
                (int)$_SESSION['user_id'], 
                'Metode Pembayaran Baru', 
                "Metode pembayaran '$name' ($provider) berhasil ditambahkan", 
                'payment_methods.php'
            );

            header("Location: payment_methods.php?status=success_create");
        } else {
            header("Location: payment_methods.php?status=error&msg=" . urlencode($stmt->error));
        }
        $stmt->close();
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
            // INTEGRASI: Membuat notifikasi setelah berhasil memperbarui metode pembayaran
            createNotification(
                'admin', 
                (int)$_SESSION['user_id'], 
                'Metode Pembayaran Diperbarui', 
                "Metode pembayaran '$name' (ID: $id) berhasil diperbarui", 
                'payment_methods.php'
            );

            header("Location: payment_methods.php?status=success_update");
        } else {
            header("Location: payment_methods.php?status=error&msg=" . urlencode($stmt->error));
        }
        $stmt->close();
        exit();
    }
}

// 3. PROSES HAPUS METODE PEMBAYARAN (DELETE)
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id'] ?? 0);

    if ($id > 0) {
        // INTEGRASI: Ambil nama metode pembayaran sebelum datanya dihapus permanen dari database
        $nameQuery = $conn->prepare("SELECT name FROM payment_methods WHERE id = ? LIMIT 1");
        $nameQuery->bind_param("i", $id);
        $nameQuery->execute();
        $nameResult = $nameQuery->get_result()->fetch_assoc();
        $savedName = $nameResult ? $nameResult['name'] : "ID " . $id;
        $nameQuery->close();

        $stmt = $conn->prepare("DELETE FROM payment_methods WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // INTEGRASI: Membuat notifikasi setelah berhasil menghapus metode pembayaran
            createNotification(
                'admin', 
                (int)$_SESSION['user_id'], 
                'Metode Pembayaran Dihapus', 
                "Metode pembayaran '$savedName' berhasil dihapus dari sistem", 
                'payment_methods.php'
            );

            header("Location: payment_methods.php?status=success_delete");
        } else {
            header("Location: payment_methods.php?status=error&msg=" . urlencode($stmt->error));
        }
        $stmt->close();
        exit();
    }
}

// 4. PROSES AMBIL DATA (READ)
$payment_methods = [];
$result = $conn->query("SELECT * FROM payment_methods ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $payment_methods[] = $row;
    }
}

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
                <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Metode Pembayaran</h2>
                <div class="text-white-50 small mt-1">Kelola daftar opsi metode pembayaran pasien</div>
            </div>
            <div>
                <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalPaymentMethod" onclick="openTambahMethod()">
                    <i class="bi bi-plus-circle"></i> Tambah Metode
                </button>
            </div>
        </div>

        <?php if (!empty($status)): ?>
            <div class="alert <?= strpos($status, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
                <strong>
                <?php 
                    if ($status === 'success_create') echo "Metode pembayaran berhasil ditambahkan!";
                    elseif ($status === 'success_update') echo "Metode pembayaran berhasil diperbarui!";
                    elseif ($status === 'success_delete') echo "Metode pembayaran berhasil dihapus!";
                    elseif ($status === 'error') echo "Operasi gagal: " . htmlspecialchars($msg);
                ?>
                </strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div id="dragScrollPaymentMethodsContainer" class="table-responsive rounded-3 drag-scroll-container" style="border: none !important; background: transparent !important; cursor: grab;">
            <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 750px; user-select: none; border-collapse: collapse !important;">
                <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
                    <tr>
                        <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 100px;">ID</th>
                        <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 250px;">Nama Metode</th>
                        <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 220px;">Provider</th>
                        <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;">Jenis</th>
                        <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody style="background: transparent !important;">
                    <?php if (!empty($payment_methods)): foreach ($payment_methods as $row): ?>
                        <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                            <td class="text-center fw-medium text-white-50" style="background: transparent !important; border: none !important;"><?= (int)$row['id'] ?></td>
                            <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['name'] ?? '') ?></td>
                            <td class="text-white-50" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['provider'] ?? '-') ?></td>
                            <td class="text-center" style="background: transparent !important; border: none !important;">
                                <?php if ((int)($row['is_online'] ?? 0) === 1): ?>
                                    <span class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-50 px-3 py-1.5 rounded-pill"><i class="bi bi-globe me-1"></i> Online</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-25 text-white border border-secondary border-opacity-50 px-3 py-1.5 rounded-pill"><i class="bi bi-cash me-1"></i> Offline / Cash</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center" style="background: transparent !important; border: none !important;">
                                <div class="d-flex justify-content-center gap-2">
                                    <button class="btn btn-sm btn-outline-warning rounded-2" data-bs-toggle="modal" data-bs-target="#modalPaymentMethod" onclick='openEditMethod(<?= json_encode($row) ?>)'>
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-2" data-bs-toggle="modal" data-bs-target="#modalHapusMethod" onclick="openHapusMethod(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name'] ?? '') ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted shadow-none" style="background: transparent !important; border: none !important;">Belum ada data metode pembayaran.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- MODAL POP-UP: FORM TAMBAH / EDIT METODE PEMBAYARAN -->
<div class="modal fade" id="modalPaymentMethod" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 450px;">
        <form id="formPaymentMethod" method="POST" action="payment_methods.php?action=create" class="modal-content text-white rounded-4 border-0" style="background: #1e293b;">
            <div class="modal-header border-bottom border-secondary border-opacity-20">
                <h5 class="modal-title fw-bold text-success" id="modalMethodLabel">Tambah Metode Pembayaran</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
            </div>
            <div class="modal-body py-3">
                <input type="hidden" id="method-id" name="id">
                
                <div class="mb-3">
                    <label for="method-name" class="form-label small text-white-50">Nama Metode <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-dark text-white border-secondary border-opacity-50" id="method-name" name="name" placeholder="Contoh: QRIS, Transfer Mandiri, Tunai..." required style="box-shadow: none;">
                </div>
                <div class="mb-3">
                    <label for="method-provider" class="form-label small text-white-50">Provider / Bank <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-dark text-white border-secondary border-opacity-50" id="method-provider" name="provider" placeholder="Contoh: Bank Mandiri, Midtrans, Cash..." required style="box-shadow: none;">
                </div>
                <div class="mb-2">
                    <label for="method-isonline" class="form-label small text-white-50">Jenis Konektivitas</label>
                    <select class="form-select bg-dark text-white border-secondary border-opacity-50" id="method-isonline" name="is_online" style="box-shadow: none;">
                        <option value="1">Online (Payment Gateway/E-Wallet)</option>
                        <option value="0">Offline (Tunai / Manual Transfer)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-top border-secondary border-opacity-20 d-flex gap-2">
                <button type="button" class="btn btn-sm rounded-pill px-4 fw-medium text-white border-0" data-bs-dismiss="modal" style="background: #334155;">Batal</button>
                <button type="submit" id="btnSubmitMethod" class="btn btn-sm btn-success rounded-pill px-4 fw-medium shadow">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL POP-UP: KONFIRMASI HAPUS METODE PEMBAYARAN -->
<div class="modal fade" id="modalHapusMethod" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content text-white rounded-4 border-0" style="background: #111827; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i> Hapus Metode
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
            </div>
            <div class="modal-body py-3">
                <p class="text-white-50 m-0" style="font-size: 0.95rem; line-height: 1.5;">Apakah Anda yakin ingin menghapus metode pembayaran <strong id="txtDeleteMethodInfo" class="text-white">-</strong>?</p>
            </div>
            <div class="modal-footer border-0 pt-0 d-flex gap-2">
                <button type="button" class="btn btn-sm rounded-pill px-4 fw-medium text-white border-0" data-bs-dismiss="modal" style="background: #1f2937;">Batal</button>
                <a id="btnConfirmDeleteMethod" href="#" class="btn btn-danger btn-sm rounded-pill px-4 fw-medium shadow-sm">Ya, Hapus</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. FITUR DRAG SCROLL (Geser Tabel Menggunakan Mouse)
    const methodSlider = document.getElementById('dragScrollPaymentMethodsContainer');
    if (methodSlider) {
        let isDown = false, startX, scrollLeft;
        methodSlider.addEventListener('mousedown', (e) => {
            if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input')) return;
            isDown = true; 
            methodSlider.style.cursor = 'grabbing';
            startX = e.pageX - methodSlider.offsetLeft; 
            scrollLeft = methodSlider.scrollLeft;
        });
        methodSlider.addEventListener('mouseleave', () => { isDown = false; methodSlider.style.cursor = 'grab'; });
        methodSlider.addEventListener('mouseup', () => { isDown = false; methodSlider.style.cursor = 'grab'; });
        methodSlider.addEventListener('mousemove', (e) => {
            if (!isDown) return; 
            e.preventDefault();
            const x = e.pageX - methodSlider.offsetLeft;
            methodSlider.scrollLeft = scrollLeft - ((x - startX) * 1.5);
        });
    }

    // 2. OTOMATIS BERSIHKAN URL PARAMETER & TUTUP ALERT DALAM 3 DETIK
    if (window.history.replaceState && window.location.search.includes('status')) {
        const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
        window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
    }
    const alertElement = document.querySelector('.alert');
    if (alertElement) {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alertElement);
            bsAlert.close();
        }, 3000);
    }
});

// 3. FUNGSI UNTUK MODAL TAMBAH DATA METODE PEMBAYARAN
function openTambahPaymentMethod() {
    const form = document.getElementById('formPaymentMethod');
    form.reset();
    form.action = 'payment_methods.php?action=create';

    document.getElementById('modalPaymentMethodLabel').innerText = 'Tambah Metode Pembayaran Baru';
    document.getElementById('paymentmethod-id').value = '';
    document.getElementById('paymentmethod-action').value = 'create';
    
    const btnSubmit = document.getElementById('btnSubmitPaymentMethod');
    btnSubmit.className = 'btn btn-sm btn-success rounded-pill px-4 fw-medium shadow';
    btnSubmit.innerText = 'Simpan Data';
}

// 4. FUNGSI UNTUK MODAL EDIT DATA METODE PEMBAYARAN
function openEditPaymentMethod(data) {
    const form = document.getElementById('formPaymentMethod');
    form.reset();
    form.action = 'payment_methods.php?action=update';

    document.getElementById('modalPaymentMethodLabel').innerText = 'Perbarui Data Metode Pembayaran';
    document.getElementById('paymentmethod-id').value = data.id;
    document.getElementById('paymentmethod-action').value = 'update';
    document.getElementById('paymentmethod-name').value = data.name || '';
    document.getElementById('paymentmethod-provider').value = data.provider || '';
    document.getElementById('paymentmethod-is_online').value = (data.is_online !== undefined && data.is_online !== null) ? String(data.is_online) : '1';
    
    const btnSubmit = document.getElementById('btnSubmitPaymentMethod');
    btnSubmit.className = 'btn btn-sm btn-warning text-dark rounded-pill px-4 fw-medium shadow';
    btnSubmit.innerText = 'Perbarui Data';
}

// 5. FUNGSI UNTUK MENGISI DATA KE MODAL KONFIRMASI HAPUS DINAMIS
function openHapusPaymentMethod(id, methodName) {
    const btnConfirmDelete = document.getElementById('btnConfirmDeleteMethod');
    const txtMethodInfo = document.getElementById('txtDeleteMethodInfo');
    
    if (btnConfirmDelete) {
        btnConfirmDelete.href = 'payment_methods.php?action=delete&id=' + id;
    }
    if (txtMethodInfo) {
        txtMethodInfo.innerText = methodName;
    }
}
</script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


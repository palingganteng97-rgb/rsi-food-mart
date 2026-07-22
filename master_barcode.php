<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notification_helper.php'; // INTEGRASI: Menyertakan fungsi pembuat notifikasi

// db.php: diharapkan menyediakan $conn (mysqli)
if (!($conn instanceof mysqli)) {
    // fallback: jika db.php memakai variabel lain
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $conn = $mysqli;
    }
}

// Helper: escape html
function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function sanitizeFileName($name) {
    $name = trim((string)$name);
    if ($name === '') return 'room';
    $name = preg_replace('/[\\/:*?"<>|]+/', '_', $name);
    $name = preg_replace('/\s+/', '_', $name);
    return $name;
}

function getPatientUrl($roomName) {
    $base_url = 'http://192.168.110.83:8000';
    return $base_url . '/patient_sessions.php?room=' . urlencode($roomName);
}


function ensureQrFile($qrUrl, $filePath) {
    // QR via layanan online (tanpa library phpqrcode)
    if (file_exists($filePath)) return;

    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $size = 500; // generate kualitas tinggi
    $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($qrUrl);
    $pngData = @file_get_contents($apiUrl);
    if ($pngData !== false) {
        @file_put_contents($filePath, $pngData);
    }
}

$uploadDir = __DIR__ . '/uploads/qrcode/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

$qrBaseDirPublic = 'uploads/qrcode/';

// Actions
$action = $_GET['action'] ?? '';

function json_redirect($url) {
    header('Location: ' . $url);
    exit;
}

function set_flash($key, $value) {
    $_SESSION[$key] = $value;
}

// session_start sudah ada di db.php

// Tambah / Edit / Hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $op = $_POST['op'] ?? '';

    if ($op === 'add') {
        $room_name = trim((string)($_POST['room_name'] ?? ''));
        if ($room_name !== '') {
            $sql = "INSERT INTO master_barcode (room_name, created_at, updated_at) VALUES (?, NOW(), NOW())";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('s', $room_name);
                if ($stmt->execute()) {
                    // QR otomatis tersedia
                    $id = $stmt->insert_id;
                    $qrUrl = getPatientUrl($room_name);
                    $fileName = sanitizeFileName($room_name) . '_' . $id . '.png';
                    $filePath = $uploadDir . $fileName;
                    ensureQrFile($qrUrl, $filePath);
                    
                    // INTEGRASI: Membuat notifikasi setelah berhasil tambah master barcode/ruangan
                    createNotification(
                        'admin', 
                        (int)$_SESSION['user_id'], 
                        'Barcode Barcode Ditambahkan', 
                        "Barcode untuk ruangan '$room_name' (ID: $id) telah berhasil digenerate", 
                        'master_barcode.php'
                    );

                    set_flash('success', 'Barcode berhasil ditambahkan.');
                } else {
                    set_flash('error', 'Gagal menambahkan barcode.');
                }
                $stmt->close();
            } else {
                set_flash('error', 'Gagal menyiapkan query (add).');
            }
        } else {
            set_flash('error', 'Nama ruangan tidak boleh kosong.');
        }
        json_redirect('master_barcode.php');
    }

    if ($op === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $room_name = trim((string)($_POST['room_name'] ?? ''));
        if ($id > 0 && $room_name !== '') {
            $sql = "UPDATE master_barcode SET room_name = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('si', $room_name, $id);
                if ($stmt->execute()) {
                    $qrUrl = getPatientUrl($room_name);
                    $fileName = sanitizeFileName($room_name) . '_' . $id . '.png';
                    $filePath = $uploadDir . $fileName;
                    ensureQrFile($qrUrl, $filePath);
                    
                    // INTEGRASI: Membuat notifikasi setelah berhasil mengubah master barcode/ruangan
                    createNotification(
                        'admin', 
                        (int)$_SESSION['user_id'], 
                        'Barcode Diperbarui', 
                        "Data barcode ruangan telah disesuaikan menjadi '$room_name' (ID: $id)", 
                        'master_barcode.php'
                    );

                    set_flash('success', 'Barcode berhasil diperbarui.');
                } else {
                    set_flash('error', 'Gagal memperbarui barcode.');
                }
                $stmt->close();
            } else {
                set_flash('error', 'Gagal menyiapkan query (edit).');
            }
        } else {
            set_flash('error', 'Data tidak valid untuk edit.');
        }
        json_redirect('master_barcode.php');
    }

    if ($op === 'edit_id' || $op === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Ambil nama ruangan untuk penghapusan file bila ada
            $room_name = '';
            $sel = $conn->prepare("SELECT room_name FROM master_barcode WHERE id = ?");
            if ($sel) {
                $sel->bind_param('i', $id);
                $sel->execute();
                $res = $sel->get_result();
                if ($res && $row = $res->fetch_assoc()) {
                    $room_name = $row['room_name'];
                }
                $sel->close();
            }

            $del = $conn->prepare("DELETE FROM master_barcode WHERE id = ?");
            if ($del) {
                $del->bind_param('i', $id);
                if ($del->execute()) {
                    // Hapus file QR terkait (best-effort)
                    if ($room_name !== '') {
                        $fileName = sanitizeFileName($room_name) . '_' . $id . '.png';
                        $filePath = $uploadDir . $fileName;
                        if (file_exists($filePath)) {
                            @unlink($filePath);
                        }
                    }
                    
                    // INTEGRASI: Membuat notifikasi setelah berhasil menghapus master barcode/ruangan
                    createNotification(
                        'admin', 
                        (int)$_SESSION['user_id'], 
                        'Barcode Dihapus', 
                        "Barcode untuk ruangan '$room_name' (ID: $id) telah dihapus dari sistem", 
                        'master_barcode.php'
                    );

                    set_flash('success', 'Barcode berhasil dihapus.');
                } else {
                    set_flash('error', 'Gagal menghapus barcode.');
                }
                $del->close();
            }
        }
        json_redirect('master_barcode.php');
    }
}

// Read all
// Jika tabel belum ada, buat otomatis via SQL migration
$createTableSql = "CREATE TABLE IF NOT EXISTS master_barcode (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    room_name VARCHAR(100) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($createTableSql);

$rows = [];
$sql = "SELECT id, room_name, created_at, updated_at FROM master_barcode ORDER BY id DESC";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    $stmt->close();
}

// Flash
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Fetch current data for edit modal (optional)
$editId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$editRoomName = '';
if ($editId > 0) {
    $sel = $conn->prepare("SELECT id, room_name FROM master_barcode WHERE id = ?");
    if ($sel) {
        $sel->bind_param('i', $editId);
        $sel->execute();
        $res = $sel->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $editRoomName = $row['room_name'];
        }
        $sel->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Master Barcode Ruangan</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    body { background: radial-gradient(1200px 600px at 20% 10%, rgba(34,197,94,.18), transparent 60%), radial-gradient(900px 500px at 85% 20%, rgba(16,185,129,.10), transparent 50%), #0b1220; }
    .glass { background: rgba(2,6,23,.55); border: 1px solid rgba(148,163,184,.12); border-radius: 18px; }
    .qr-preview { width: 120px; height: 120px; object-fit: contain; background:#fff; border-radius: 14px; padding: 6px; border: 1px solid rgba(148,163,184,.15); }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    .table-dark { --bs-table-bg: rgba(13,19,34,.55); }
    .table-responsive { scrollbar-width: none; -ms-overflow-style: none; cursor: grab; user-select: none; }
    .table-responsive::-webkit-scrollbar { display: none; }
    .table-responsive:active { cursor: grabbing; }
</style>

</head>
<body class="text-light">
  <?php require __DIR__ . '/sidebar.php'; ?>

<main class="content-shift p-4">
  <div class="container-fluid glass p-4 p-md-5 text-light">

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
      <div>
        <h4 class="fw-bold mb-1">Master Barcode Ruangan</h4>
        <div class="text-white-50 small">Kelola barcode ruangan (QR mengarah ke patient_sessions.php).</div>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAdd">
          <i class="bi bi-plus-circle me-1"></i>Tambah Barcode
        </button>
      </div>
    </div>

    <hr class="border border-secondary-subtle opacity-50 my-4" />

    <?php if ($success): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i> <?= h($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i> <?= h($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <div id="tableDragContainer" class="table-drag-container table-responsive" aria-label="Daftar Barcode" >
<table class="table table-hover align-middle mb-0" id="barcodeTable" style="--bs-table-bg: transparent; --bs-table-hover-bg: rgba(255,255,255,0.03); border-collapse: collapse;">
  <thead>
    <tr class="text-white-50" style="border-bottom: 2px solid rgba(148,163,184,0.2);">
      <th style="width: 60px; background: transparent; color: rgba(255,255,255,0.5); white-space: nowrap; border-right: 1px solid rgba(148,163,184,0.15); padding: 12px;">No</th>
      <th style="width: 180px; background: transparent; color: rgba(255,255,255,0.5); white-space: nowrap; border-right: 1px solid rgba(148,163,184,0.15); padding: 12px;">Nama Ruangan</th>
      <th style="width: 150px; background: transparent; color: rgba(255,255,255,0.5); white-space: nowrap; border-right: 1px solid rgba(148,163,184,0.15); padding: 12px;">Preview QR</th>
      <th style="background: transparent; color: rgba(255,255,255,0.5); white-space: nowrap; border-right: 1px solid rgba(148,163,184,0.15); padding: 12px;">URL</th>
      <th style="width: 120px; background: transparent; color: rgba(255,255,255,0.5); white-space: nowrap; border-right: 1px solid rgba(148,163,184,0.15); padding: 12px;">Print</th>
      <th style="width: 140px; background: transparent; color: rgba(255,255,255,0.5); white-space: nowrap; border-right: 1px solid rgba(148,163,184,0.15); padding: 12px;">Download</th>
      <th style="width: 150px; background: transparent; color: rgba(255,255,255,0.5); white-space: nowrap; padding: 12px;">Aksi</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($rows)): ?>
      <tr>
        <td colspan="7" class="text-center text-white-50 py-5" style="background: transparent;">Belum ada data.</td>
      </tr>
    <?php else: ?>
      <?php foreach ($rows as $i => $row):
        $id = (int)$row['id'];
        $room_name = $row['room_name'];
        $qrUrl = getPatientUrl($room_name);
        $fileName = sanitizeFileName($room_name) . '_' . $id . '.png';
        $filePath = $uploadDir . $fileName;
        if (!file_exists($filePath)) {
            ensureQrFile($qrUrl, $filePath);
        }
        $publicQr = $qrBaseDirPublic . $fileName;
      ?>
        <tr style="border-bottom: 1px solid rgba(148,163,184,0.12);">
          <td class="fw-semibold text-white" style="background: transparent; border-right: 1px solid rgba(148,163,184,0.12); padding: 12px;">#<?= $i + 1 ?></td>
          <td class="fw-semibold text-white" style="background: transparent; border-right: 1px solid rgba(148,163,184,0.12); padding: 12px; max-width: 180px; word-break: break-word; white-space: normal;"><?= h($room_name) ?></td>
          <td style="background: transparent; border-right: 1px solid rgba(148,163,184,0.12); padding: 12px;">
            <img class="qr-preview" src="<?= h($publicQr) ?>" alt="QR <?= h($room_name) ?>">
          </td>
          <td style="background: transparent; border-right: 1px solid rgba(148,163,184,0.12); padding: 12px;">
            <div class="mono small text-white-50" style="max-width: 220px; overflow:hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= h($qrUrl) ?>">
              <?= h($qrUrl) ?>
            </div>
          </td>
          <td style="background: transparent; border-right: 1px solid rgba(148,163,184,0.12); padding: 12px;">
            <button type="button" class="btn btn-sm btn-outline-light" onclick="window.open('print_qr_room.php?room_name=<?= urlencode($room_name) ?>','_blank','noopener,noreferrer');">
              <i class="bi bi-printer me-1"></i>Print
            </button>
          </td>
          <td style="background: transparent; border-right: 1px solid rgba(148,163,184,0.12); padding: 12px;">
            <a class="btn btn-sm btn-success" download href="<?= h($publicQr) ?>">
              <i class="bi bi-download me-1"></i>Download
            </a>
          </td>
          <td style="background: transparent; padding: 12px;">
            <div class="d-flex flex-column flex-sm-row gap-2">
              <button type="button" class="btn btn-sm btn-primary" 
                data-bs-toggle="modal" data-bs-target="#modalEdit"
                onclick="fillEdit(<?= (int)$id ?>, <?= json_encode($room_name) ?>)">
                <i class="bi bi-pencil-square me-1"></i>Edit
              </button>
              <form method="post" action="master_barcode.php" onsubmit="return confirm('Hapus barcode ini?');">
                <input type="hidden" name="op" value="delete">
                <input type="hidden" name="id" value="<?= (int)$id ?>">
                <button class="btn btn-sm btn-danger" type="submit">
                  <i class="bi bi-trash text-white me-1"></i>Hapus
                </button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

    </div>

  </div>
</main>

<style>
  /* Drag-to-scroll horizontal (tanpa scrollbar) */
  #barcodeTable {
    min-width: 1100px; /* sesuaikan agar scroll horizontal relevan */
  }
  .drag-scroll {
    overflow-x: hidden;
    cursor: grab;
    user-select: none;
  }
  .drag-scroll.dragging {
    cursor: grabbing;
  }
</style>

<!-- Modal Add -->
<div class="modal fade" id="modalAdd" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-light">
      <form method="post" action="master_barcode.php">
        <input type="hidden" name="op" value="add">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-plus-circle me-1"></i>Tambah Barcode</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Nama Ruangan</label>
          <input type="text" name="room_name" class="form-control" required maxlength="200" placeholder="Contoh: ICU">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success"><i class="bi bi-save me-1"></i>Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-light">
      <form method="post" action="master_barcode.php">
        <input type="hidden" name="op" value="edit">
        <input type="hidden" name="id" id="edit_id" value="">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-pencil-square me-1"></i>Edit Barcode</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Nama Ruangan</label>
          <input type="text" name="room_name" id="edit_room_name" class="form-control" required maxlength="200">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  function fillEdit(id, roomName) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_room_name').value = roomName;
  }
</script>
<script>
  (function () {
    const container = document.getElementById('tableDragContainer');
    if (!container) return;
    let isDown = false, startX = 0, scrollLeft = 0;
    container.addEventListener('mousedown', function (e) {
      if (e.target && e.target.closest && e.target.closest('button, a, input, textarea, select, label, form')) return;
      isDown = true;
      startX = e.pageX - container.offsetLeft;
      scrollLeft = container.scrollLeft;
    });
    container.addEventListener('mousemove', function (e) {
      if (!isDown) return;
      e.preventDefault();
      const walk = (e.pageX - container.offsetLeft) - startX;
      container.scrollLeft = scrollLeft - walk;
    });
    container.addEventListener('mouseup', function () { isDown = false; });
    container.addEventListener('mouseleave', function () { isDown = false; });
  })();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


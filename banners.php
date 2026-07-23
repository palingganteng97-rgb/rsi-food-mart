<?php
// banners.php - Modul CRUD Banners (Admin) LENGKAP
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$uploadDir = 'uploads/banners/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

// Ambil notifikasi status dari query parameter URL
$status = isset($_GET['status']) ? (string)$_GET['status'] : '';
$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';

// ==========================================
// 1. ACTION HANDLER (PROSES POST/GET ACTIONS)
// ==========================================
$action = isset($_POST['action']) ? (string)$_POST['action'] : (isset($_GET['action']) ? (string)$_GET['action'] : '');

// Aksi Tambah Banner (Create)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string)($_POST['title'] ?? ''));
    $link = trim((string)($_POST['link'] ?? ''));
    $statusParam = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    $imageName = '';

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['image']['tmp_name'];
        $fileNameOrig = $_FILES['image']['name'];
        $fileExtension = strtolower(pathinfo($fileNameOrig, PATHINFO_EXTENSION));
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $imageName = time() . '_' . uniqid() . '.' . $fileExtension;
            $destPath = $uploadDir . $imageName;
            
            if (!move_uploaded_file($fileTmpPath, $destPath)) {
                header('Location: banners.php?status=error&msg=Gagal mengunggah gambar');
                exit;
            }
        } else {
            header('Location: banners.php?status=error&msg=Format gambar tidak valid');
            exit;
        }
    }

    $sqlInsert = 'INSERT INTO banners (title, image, link, status) VALUES (?, ?, ?, ?)';
    $stmtIns = $conn->prepare($sqlInsert);
    $stmtIns->bind_param('sssi', $title, $imageName, $link, $statusParam);
    
    if ($stmtIns->execute()) {
        header('Location: banners.php?status=success&msg=Banner berhasil ditambahkan');
    } else {
        header('Location: banners.php?status=error&msg=Gagal menyimpan data');
    }
    exit;
}

// Aksi Ubah Banner (Update)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $link = trim((string)($_POST['link'] ?? ''));
    $statusParam = isset($_POST['status']) ? (int)$_POST['status'] : 1;

    $sqlOld = 'SELECT image FROM banners WHERE id = ?';
    $stmtOld = $conn->prepare($sqlOld);
    $stmtOld->bind_param('i', $id);
    $stmtOld->execute();
    $resOld = $stmtOld->get_result()->fetch_assoc();
    $imageName = $resOld['image'] ?? '';

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['image']['tmp_name'];
        $fileNameOrig = $_FILES['image']['name'];
        $fileExtension = strtolower(pathinfo($fileNameOrig, PATHINFO_EXTENSION));
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $newImageName = time() . '_' . uniqid() . '.' . $fileExtension;
            $destPath = $uploadDir . $newImageName;
            
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                if ($imageName !== '' && file_exists($uploadDir . $imageName)) {
                    unlink($uploadDir . $imageName);
                }
                $imageName = $newImageName;
            }
        }
    }

    $sqlUpdate = 'UPDATE banners SET title = ?, image = ?, link = ?, status = ? WHERE id = ?';
    $stmtUpd = $conn->prepare($sqlUpdate);
    $stmtUpd->bind_param('sssii', $title, $imageName, $link, $statusParam, $id);
    
    if ($stmtUpd->execute()) {
        header('Location: banners.php?status=success&msg=Banner berhasil diperbarui');
    } else {
        header('Location: banners.php?status=error&msg=Gagal memperbarui data');
    }
    exit;
}

// Aksi Hapus Banner (Delete)
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    $sqlImg = 'SELECT image FROM banners WHERE id = ?';
    $stmtImg = $conn->prepare($sqlImg);
    $stmtImg->bind_param('i', $id);
    $stmtImg->execute();
    $resImg = $stmtImg->get_result()->fetch_assoc();
    
    if ($resImg) {
        $imageName = $resImg['image'];
        if ($imageName !== '' && file_exists($uploadDir . $imageName)) {
            unlink($uploadDir . $imageName);
        }

        $sqlDel = 'DELETE FROM banners WHERE id = ?';
        $stmtDel = $conn->prepare($sqlDel);
        $stmtDel->bind_param('i', $id);
        
        if ($stmtDel->execute()) {
            header('Location: banners.php?status=success&msg=Banner berhasil dihapus');
        } else {
            header('Location: banners.php?status=error&msg=Gagal menghapus data dari database');
        }
    } else {
        header('Location: banners.php?status=error&msg=Data tidak ditemukan');
    }
    exit;
}

// ==========================================
// 2. LOGIKA READ & PAGINATION (DATA RETRIEVAL)
// ==========================================
$search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$where = '';
$params = [];
$types = '';

if ($search !== '') {
    $where = 'WHERE title LIKE ?';
    $types = 's';
    $params[] = '%' . $search . '%';
}

$sqlCount = 'SELECT COUNT(*) AS total FROM banners ' . $where;
$stmtCount = $conn->prepare($sqlCount);
if ($where !== '') {
    $stmtCount->bind_param($types, $params[0]);
}
$stmtCount->execute();
$resCount = $stmtCount->get_result();
$totalRows = 0;
if ($resCount) {
    $row = $resCount->fetch_assoc();
    $totalRows = (int)($row['total'] ?? 0);
}
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$sql = 'SELECT id, title, image, link, status FROM banners ' . $where . ' ORDER BY id DESC LIMIT ? OFFSET ?';
$stmt = $conn->prepare($sql);

if ($where !== '') {
    $stmt->bind_param($types . 'ii', $params[0], $perPage, $offset);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$list = $stmt->get_result();
$banners = [];
if ($list) {
    while ($r = $list->fetch_assoc()) {
        $banners[] = $r;
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
  <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
    
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
      <div>
        <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;"> Banners </h2>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <form method="GET" action="banners.php" class="d-flex gap-2">
            <input type="text" name="q" class="form-control rounded-3 bg-dark text-white border-secondary" placeholder="Cari judul..." value="<?= htmlspecialchars($search) ?>" style="font-size: 0.9rem; min-width: 200px;">
            <button type="submit" class="btn btn-secondary rounded-3 px-3"><i class="bi bi-search"></i></button>
            <?php if ($search !== ''): ?>
                <a href="banners.php" class="btn btn-outline-light rounded-3">Reset</a>
            <?php endif; ?>
        </form>
        <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" onclick="openTambahBanner()">
          <i class="bi bi-image"></i> Tambah Banner
        </button>
      </div>
    </div>

    <?php if (!empty($status)): ?>
        <div class="alert <?= ($status === 'success' || strpos($status, 'success') !== false) ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
            <strong>
                <?php 
                if ($status === 'success' && !empty($msg)) echo htmlspecialchars(urldecode($msg));
                elseif ($status == 'success_insert') echo "Data banner baru berhasil ditambahkan!";
                elseif ($status == 'success_update') echo "Data banner berhasil diperbarui!";
                elseif ($status == 'success_delete') echo "Data banner berhasil dihapus!";
                else echo "Operasi gagal: " . htmlspecialchars($msg);
                ?>
            </strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div id="dragScrollProductContainer" class="table-responsive rounded-3 drag-scroll-container" style="border: none !important; background: transparent !important; cursor: grab; box-shadow: none !important; -webkit-box-shadow: none !important;">
      <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 1000px; user-select: none; border-collapse: collapse !important;">
        <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
          <tr>
            <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 80px;"> ID</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 160px;"> Gambar</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 250px;"> Judul Banner</th>
            <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 250px;"> Tautan / Link URL</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 130px;"> Status</th>
            <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;"> Aksi</th>
          </tr>
        </thead>
        <tbody style="background: transparent !important;">
          <?php if (!empty($banners)): 
              foreach ($banners as $row): ?>
                  <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                    <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= $row['id'] ?></td>
                    <td style="background: transparent !important; border: none !important;">
                        <?php if (!empty($row['image']) && file_exists($uploadDir . $row['image'])): ?>
                            <img src="<?= $uploadDir . $row['image'] ?>" class="rounded-2 shadow-sm border border-secondary" style="max-height: 55px; max-width: 140px; object-fit: cover;">
                        <?php else: ?>
                            <span class="text-muted italic opacity-50" style="font-size: 0.8rem;">No Image</span>
                        <?php endif; ?>
                    </td>
                    <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;"><?= htmlspecialchars($row['title'] !== '' ? $row['title'] : '(Tanpa Judul)') ?></td>
                    <td class="text-white-50" style="background: transparent !important; border: none !important;">
                        <?php if (!empty($row['link'])): ?>
                            <a href="<?= htmlspecialchars($row['link']) ?>" target="_blank" class="text-info text-decoration-none text-truncate d-inline-block" style="max-width: 240px;"><?= htmlspecialchars($row['link']) ?></a>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <?php if ($row['status'] == 1): ?>
                            <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-3 py-1.5 rounded-pill">Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-50 px-3 py-1.5 rounded-pill">Non-Aktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                      <div class="d-flex justify-content-center gap-2">
                        <button class="btn btn-sm btn-outline-warning rounded-2" onclick='openEditBanner(<?= json_encode($row) ?>)'>
                          <i class="bi bi-pencil-square"></i>
                        </button>
                        <a href="banners.php?action=delete&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger rounded-2" onclick="return confirm('Apakah Anda yakin ingin menghapus aset banner ini?')">
                          <i class="bi bi-trash"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
              <?php endforeach; ?>
          <?php else: ?>
              <tr>
                <td colspan="6" class="text-center py-5 text-muted italic" style="background: transparent !important; border: none !important;">Belum ada data banner yang terdaftar.</td>
              </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-center mt-4">
            <nav>
                <ul class="pagination mb-0 gap-1 shadow-sm">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link rounded-2 bg-dark text-white border-secondary" href="banners.php?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>">Prev</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                            <a class="page-link rounded-2 <?= $page == $i ? 'bg-primary border-primary text-white' : 'bg-dark text-white border-secondary' ?>" href="banners.php?page=<?= $i ?>&q=<?= urlencode($search) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link rounded-2 bg-dark text-white border-secondary" href="banners.php?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
  </div>
</main>

<!-- MODAL FORM INPUT MELEBAR DI TENGAH (WIDE MODE & BEBAS SCROLLBAR) -->
<div class="modal fade" id="modalBanner" tabindex="-1" aria-labelledby="modalBannerLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.93) !important; backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.2); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
                <h5 class="modal-title fw-bold text-white" id="modalBannerLabel">Form Data Banner</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formBanner" action="banners.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="form-action" value="create">
                <input type="hidden" name="id" id="banner-id">
                
                <div class="modal-body" style="overflow: visible !important;">
                    <div class="row g-3">
                        <!-- JUDUL BANNER -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Judul Banner</label>
                            <input type="text" name="title" id="banner-title" class="form-control" maxlength="150" placeholder="Masukkan judul promosi/banner" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
                        </div>

                        <!-- STATUS PUBLIKASI -->
                        <div class="col-md-6">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Status Publikasi <span class="text-danger">*</span></label>
                            <select name="status" id="banner-status" class="form-select" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;" required>
                                <option value="1" selected>Aktif (Tampilkan)</option>
                                <option value="0">Non-Aktif (Sembunyikan)</option>
                            </select>
                        </div>

                        <!-- TAUTAN REDIRECT (LINK / URL) -->
                        <div class="col-md-12">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Tautan Redirect (Link / URL)</label>
                            <input type="url" name="link" id="banner-link" class="form-control" maxlength="255" placeholder="https://example.com" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
                        </div>

                        <!-- ASET GAMBAR BANNER -->
                        <div class="col-md-12">
                            <label class="form-label" style="color: #94a3b8 !important; font-weight: 500;">Aset Gambar Banner <span class="text-danger">*</span></label>
                            <input type="file" name="image" id="banner-image" class="form-control" accept="image/*" style="background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.25) !important; color: #e5e7eb !important;">
                            <div id="edit-img-hint" class="form-text text-muted small mt-1 d-none" style="color: #64748b !important;">Kosongkan kolom gambar jika tidak ingin mengganti aset lama.</div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer" style="border-top: 1px solid rgba(148, 163, 184, 0.15); background: rgba(15, 23, 42, 0.95); border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="btn-submit-modal">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus Banner -->
<div class="modal fade" id="modalConfirmDeleteBanner" tabindex="-1" aria-labelledby="modalConfirmDeleteBannerLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-bg-dark border-secondary" style="background-color: #111827 !important; border-color: #374151 !important;">
      
      <div class="modal-header border-bottom border-secondary">
        <h5 class="modal-title text-white fw-bold d-flex align-items-center" id="modalConfirmDeleteBannerLabel">
          <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Konfirmasi Hapus
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body text-center p-4">
        <div class="mb-3">
          <i class="bi bi-image text-danger" style="font-size: 3.5rem;"></i>
        </div>
        <p class="text-light fs-6 mb-1">Apakah Anda yakin ingin menghapus aset promo banner berikut?</p>
        <h6 id="delete_banner_title" class="text-warning fw-bold mt-2 mb-0"></h6>
      </div>
      
      <div class="modal-footer border-top border-secondary justify-content-center">
        <button type="button" class="btn btn-secondary px-4 rounded-2" data-bs-dismiss="modal">Batal</button>
        <a id="btnConfirmDeleteBannerAction" href="#" class="btn btn-danger px-4 rounded-2 fw-bold">Oke, Hapus</a>
      </div>

    </div>
  </div>
</div>

<!-- JAVASCRIPT LOGIC -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
    // Menghapus parameter status dan msg dari URL tanpa mereset halaman
    if (window.history.replaceState && window.location.search) {
        const url = new URL(window.location.href);
        url.searchParams.delete('status');
        url.searchParams.delete('msg');
        window.history.replaceState({}, document.title, url.pathname + url.search);
    }
});

// Singleton: hanya SATU instance Bootstrap Modal untuk modalBanner
let modalBannerInstance = null;
function getModalBanner() {
    if (!modalBannerInstance) {
        modalBannerInstance = new bootstrap.Modal(document.getElementById('modalBanner'));
    }
    return modalBannerInstance;
}

let deleteBannerUrlTarget = '';
let bootstrapDeleteBannerModalInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    // Safety cleanup: pastikan backdrop & class modal-open bersih saat modal ditutup
    const modalBannerEl = document.getElementById('modalBanner');
    if (modalBannerEl) {
        modalBannerEl.addEventListener('hidden.bs.modal', function () {
            // Hapus sisa backdrop yang mungkin tertinggal
            document.querySelectorAll('.modal-backdrop').forEach(function(el) {
                el.remove();
            });
            // Hapus class modal-open dari body
            document.body.classList.remove('modal-open');
            // Kembalikan style body ke normal
            document.body.style.removeProperty('padding-right');
            document.body.style.removeProperty('overflow');
        });
    }

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
        
        prodSlider.addEventListener('mouseleave', () => { 
            isDown = false; 
            prodSlider.style.cursor = 'grab'; 
        });
        
        prodSlider.addEventListener('mouseup', () => { 
            isDown = false; 
            prodSlider.style.cursor = 'grab'; 
        });
        
        prodSlider.addEventListener('mousemove', (e) => {
            if (!isDown) return; 
            e.preventDefault();
            const x = e.pageX - prodSlider.offsetLeft;
            prodSlider.scrollLeft = scrollLeft - ((x - startX) * 1.5);
        });
    }

    const formBanner = document.getElementById('formBanner');
    if (formBanner) {
        formBanner.addEventListener('submit', function (e) {
            const bannerImage = document.getElementById('banner-image');
            const formAction = document.getElementById('form-action').value;

            if (formAction === 'create' && bannerImage && bannerImage.files.length === 0) {
                e.preventDefault();
                alert('⚠️ Silakan pilih berkas gambar banner terlebih dahulu!');
                return;
            }
        });
    }

    const btnActionDelete = document.getElementById('btnConfirmDeleteBannerAction');
    if (btnActionDelete) {
        btnActionDelete.addEventListener('click', function(e) {
            if (deleteBannerUrlTarget) {
                window.location.href = deleteBannerUrlTarget;
            }
        });
    }
});

function openTambahBanner() {
    document.getElementById('formBanner').reset();
    document.getElementById('modalBannerLabel').innerText = 'Tambah Banner Baru';
    document.getElementById('banner-id').value = '';
    document.getElementById('form-action').value = 'create';
    document.getElementById('banner-image').required = true;
    document.getElementById('edit-img-hint').classList.add('d-none');
    
    const submitBtn = document.getElementById('btn-submit-modal');
    if (submitBtn) {
        submitBtn.className = "btn btn-success";
        submitBtn.innerText = "Simpan Data";
    }

    getModalBanner().show();
}

function openEditBanner(data) {
    document.getElementById('formBanner').reset();
    document.getElementById('modalBannerLabel').innerText = 'Perbarui Data Banner';
    document.getElementById('banner-id').value = data.id;
    document.getElementById('form-action').value = 'update';
    document.getElementById('banner-title').value = data.title || '';
    document.getElementById('banner-link').value = data.link || '';
    document.getElementById('banner-status').value = data.status;
    document.getElementById('banner-image').required = false;
    document.getElementById('edit-img-hint').classList.remove('d-none');
    
    const submitBtn = document.getElementById('btn-submit-modal');
    if (submitBtn) {
        submitBtn.className = "btn btn-warning text-dark fw-medium";
        submitBtn.innerText = "Perbarui Data";
    }

    getModalBanner().show();
}

function triggerDeleteBanner(url, bannerTitle) {
    deleteBannerUrlTarget = url;
    
    const titlePlaceholder = document.getElementById('delete_banner_title');
    if (titlePlaceholder) {
        titlePlaceholder.innerText = bannerTitle ? bannerTitle : '(Tanpa Judul)';
    }
    
    if (!bootstrapDeleteBannerModalInstance) {
        bootstrapDeleteBannerModalInstance = new bootstrap.Modal(document.getElementById('modalConfirmDeleteBanner'));
    }
    bootstrapDeleteBannerModalInstance.show();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

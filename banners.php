<?php
// banners.php - Modul CRUD Banners (Admin)
// PHP Native + MySQLi

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

$status = isset($_GET['status']) ? (string)$_GET['status'] : '';
$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';

$search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Hitung total data
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

// Ambil list data
$sql = 'SELECT id, title, image, link, status FROM banners ' . $where . ' ORDER BY id DESC LIMIT ? OFFSET ?';
$stmt = $conn->prepare($sql);

if ($where !== '') {
    // bind: title, limit, offset
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
  <title>Admin - Banners</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet" />

  <style>
    :root { --bg:#0f172a; --text:#e5e7eb; --muted:#94a3b8; --green:#22c55e; }
    body { background:var(--bg) !important; color:var(--text); }
    .content-bg { background: transparent; }
    .badge-active { background: rgba(34,197,94,.12) !important; color:#4ade80 !important; border: 1px solid rgba(34,197,94,.35) !important; }
    .badge-inactive { background: rgba(239,68,68,.10) !important; color:#f87171 !important; border: 1px solid rgba(239,68,68,.30) !important; }
    .table-wrap { background: rgba(15,23,42,.35) !important; border: 1px solid rgba(148,163,184,.18) !important; border-radius: 16px; }
    .table thead th { border-bottom: 1px solid rgba(148,163,184,.25) !important; color: #94a3b8 !important; font-size: .8rem; font-weight: 700; }
    .table td { color: #e5e7eb !important; vertical-align: middle; }
    .table td, .table th { white-space: nowrap; }

    .img-preview {
      width: 64px; height: 42px; object-fit: cover; border-radius: 10px;
      border: 1px solid rgba(148,163,184,.2);
      background: rgba(2,6,23,.35);
    }

    .search-input {
      background: rgba(2,6,23,.35);
      border: 1px solid rgba(148,163,184,.25);
      color: #e5e7eb;
      border-radius: 14px;
    }
    .search-input::placeholder { color: rgba(229,231,235,.45); }

    .modal-content { background: rgba(15,23,42,.96) !important; border: 1px solid rgba(148,163,184,.25) !important; color:#e5e7eb; border-radius: 20px; }

    @media (min-width: 992px) {
      .content-shift { margin-left: 280px; }
      .bottom-nav { display:none; }
    }
  </style>
</head>
<body>
  <?php require __DIR__ . '/sidebar.php'; ?>

  <main class="content-shift p-4">
    <div class="container-fluid p-4 table-wrap">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148,163,184,.15) !important;">
        <div>
          <h2 class="fw-bold m-0" style="font-size: 2rem;">Banners</h2>
          <div class="text-white-50" style="font-size:.85rem;">Kelola banner pada halaman utama</div>
        </div>
        <div class="d-flex gap-2 align-items-center">
          <div>
            <form method="GET" class="d-flex gap-2" role="search" aria-label="Search banners">
              <input class="form-control search-input" type="text" name="q" value="<?php echo h($search); ?>" placeholder="Cari berdasarkan title" />
              <input type="hidden" name="page" value="1" />
              <button class="btn btn-outline-light" type="submit" style="border-radius: 14px;">Cari</button>
            </form>
          </div>
          <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalBanner" onclick="openAddBanner()">
            <i class="bi bi-plus-circle"></i> Tambah Banner
          </button>
        </div>
      </div>

      <?php if (!empty($status)): ?>
        <div class="alert alert-dismissible fade show mb-4 <?php echo (strpos($status,'success')!==false)?'alert-success':'alert-danger'; ?>" role="alert" style="background-color:#1e1e24; border-color:#2d2d34; color:#fff;">
          <strong>
            <?php
              if ($status === 'success_add') echo 'Banner berhasil ditambahkan!';
              elseif ($status === 'success_update') echo 'Banner berhasil diperbarui!';
              elseif ($status === 'success_delete') echo 'Banner berhasil dihapus!';
              else echo 'Operasi gagal: ' . h($msg);
            ?>
          </strong>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-hover align-middle" style="min-width: 980px; background: transparent;">
          <thead>
            <tr>
              <th class="text-center" style="width:80px;">No</th>
              <th>Judul</th>
              <th class="text-center" style="width:140px;">Preview Gambar</th>
              <th style="width:260px;">Link</th>
              <th class="text-center" style="width:140px;">Status</th>
              <th class="text-center" style="width:180px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($banners) > 0): ?>
              <?php foreach ($banners as $idx => $b): ?>
                <tr>
                  <td class="text-center text-white-50"><?php echo (int)($offset + $idx + 1); ?></td>
                  <td class="fw-semibold"><?php echo h($b['title']); ?></td>
                  <td class="text-center">
                    <?php $imgPath = $b['image'] ? ($uploadDir . $b['image']) : ''; ?>
                    <?php if ($imgPath && file_exists($imgPath)): ?>
                      <img class="img-preview" src="<?php echo h($imgPath); ?>" alt="Preview" />
                    <?php else: ?>
                      <span class="text-muted" style="font-size:.8rem;">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="text-truncate" style="max-width: 260px;" title="<?php echo h($b['link']); ?>"><?php echo h($b['link']); ?></div>
                  </td>
                  <td class="text-center">
                    <?php if ((int)$b['status'] === 1): ?>
                      <span class="badge badge-active rounded-pill px-3 py-2 fw-semibold" style="font-size:.75rem;">Aktif</span>
                    <?php else: ?>
                      <span class="badge badge-inactive rounded-pill px-3 py-2 fw-semibold" style="font-size:.75rem;">Nonaktif</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <div class="d-flex justify-content-center gap-2">
                      <button type="button" class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Edit" onclick='openEditBanner(<?php echo json_encode($b, JSON_UNESCAPED_UNICODE); ?>)'>
                        <i class="bi bi-pencil-square"></i>
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Hapus" data-bs-toggle="modal" data-bs-target="#modalConfirmDelete" onclick='prepareDeleteBanner(<?php echo (int)$b['id']; ?>, <?php echo json_encode($b['title'], JSON_UNESCAPED_UNICODE); ?>)'>
                        <i class="bi bi-trash-fill"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="text-center py-5 text-muted">
                  <i class="bi bi-megaphone" style="font-size:2rem; opacity:.5; display:block; margin-bottom:10px;"></i>
                  Belum ada banner
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalRows > 0): ?>
        <nav aria-label="Pagination">
          <ul class="pagination justify-content-center mb-0" style="gap:8px;">
            <?php
              $qs = [];
              if ($search !== '') $qs['q'] = $search;
              $buildUrl = function($p) use ($qs) {
                $q = $qs;
                $q['page'] = $p;
                return 'banners.php?' . http_build_query($q);
              };
            ?>
            <li class="page-item <?php echo ($page<=1)?'disabled':''; ?>">
              <a class="page-link" href="<?php echo $page<=1 ? '#' : h($buildUrl(1)); ?>" aria-label="First">&laquo;</a>
            </li>
            <li class="page-item <?php echo ($page<=1)?'disabled':''; ?>">
              <a class="page-link" href="<?php echo $page<=1 ? '#' : h($buildUrl($page-1)); ?>" aria-label="Previous">&lsaquo;</a>
            </li>
            <li class="page-item disabled">
              <span class="page-link" style="background: transparent; border-color: rgba(148,163,184,.25); color:#e5e7eb;">Halaman <?php echo (int)$page; ?> / <?php echo (int)$totalPages; ?></span>
            </li>
            <li class="page-item <?php echo ($page>=$totalPages)?'disabled':''; ?>">
              <a class="page-link" href="<?php echo $page>=$totalPages ? '#' : h($buildUrl($page+1)); ?>" aria-label="Next">&rsaquo;</a>
            </li>
            <li class="page-item <?php echo ($page>=$totalPages)?'disabled':''; ?>">
              <a class="page-link" href="<?php echo $page>=$totalPages ? '#' : h($buildUrl($totalPages)); ?>" aria-label="Last">&raquo;</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>

    </div>
  </main>

  <!-- Modal Banner Add/Edit -->
  <div class="modal fade" id="modalBanner" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <form id="formBanner" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" id="banner_id" value="">

        <div class="modal-content">
          <div class="modal-header border-0 pb-0" style="padding: 1.5rem 1.5rem 0 1.5rem;">
            <h5 class="fw-bold text-white m-0" id="modalBannerLabel">Tambah Banner</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label small text-white-50 fw-medium">Judul</label>
                <input type="text" name="title" id="banner_title" class="form-control text-white border-secondary" style="background: rgba(2,6,23,.4); box-shadow:none;" maxlength="150" required>
              </div>

              <div class="col-12">
                <label class="form-label small text-white-50 fw-medium">Link</label>
                <input type="text" name="link" id="banner_link" class="form-control text-white border-secondary" style="background: rgba(2,6,23,.4); box-shadow:none;" maxlength="255" required>
              </div>

              <div class="col-12">
                <label class="form-label small text-white-50 fw-medium">Status</label>
                <select name="status" id="banner_status" class="form-select bg-dark text-white border-secondary rounded-3" style="background-color: rgba(2,6,23,.4) !important;">
                  <option value="1">Aktif</option>
                  <option value="0">Nonaktif</option>
                </select>
              </div>

              <div class="col-12">
                <label class="form-label small text-white-50 fw-medium">Gambar Banner (jpg/jpeg/png/webp, max 5MB)</label>
                <input type="file" name="image" id="banner_image" class="form-control text-white border-secondary rounded-3" style="background: rgba(2,6,23,.4); box-shadow:none;" accept="image/*" />
                <div class="mt-2" id="currentImageWrap" style="display:none;">
                  <div class="text-white-50 small mb-2">Gambar saat ini:</div>
                  <img id="currentImage" src="#" class="img-preview" style="width:120px; height:70px;" alt="Current" />
                  <div class="text-white-50 small mt-2">Jika tidak upload gambar, gunakan gambar lama.</div>
                </div>
              </div>

            </div>
          </div>

          <div class="modal-footer border-0 pt-0" style="padding: 0 1.5rem 1.5rem 1.5rem;">
            <button type="button" class="btn btn-secondary rounded-3 px-4" data-bs-dismiss="modal" style="background: rgba(148,163,184,0.1); border: 1px solid rgba(148,163,184,0.2); color:#94a3b8;">Batal</button>
            <button type="submit" id="banner_submit_btn" class="btn btn-success rounded-3 px-4 fw-medium">Simpan</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Confirm Delete -->
  <div class="modal fade" id="modalConfirmDelete" tabindex="-1" aria-hidden="true" aria-labelledby="modalConfirmDeleteLabel">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content text-bg-dark border-secondary" style="background-color:#111827 !important; border-color:#374151 !important; border-radius: 16px;">
        <div class="modal-header border-bottom border-secondary">
          <h5 class="modal-title text-white fw-bold d-flex align-items-center" id="modalConfirmDeleteLabel">
            <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Konfirmasi Hapus
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center p-4">
          <div class="mb-3"><i class="bi bi-trash3-fill text-danger" style="font-size:3.5rem;"></i></div>
          <p class="text-white-50 fs-6 mb-1">Apakah Anda yakin ingin menghapus banner?</p>
          <h6 id="delete_banner_title" class="text-warning fw-bold mt-2"></h6>
        </div>
        <div class="modal-footer border-top border-secondary justify-content-center">
          <button type="button" class="btn btn-sm btn-secondary px-4 rounded-3 py-2" data-bs-dismiss="modal" style="background: rgba(148,163,184,0.1); border: 1px solid rgba(148,163,184,0.2); color:#94a3b8;">Batal</button>
          <a id="btnConfirmDeleteAction" href="#" class="btn btn-sm btn-danger px-4 rounded-3 py-2 fw-bold">Oke, Hapus</a>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

  <script>
    function openAddBanner() {
      const form = document.getElementById('formBanner');
      document.getElementById('modalBannerLabel').innerText = 'Tambah Banner';
      document.getElementById('banner_id').value = '';
      form.action = 'banner_save.php';
      form.method = 'POST';

      document.getElementById('banner_title').value = '';
      document.getElementById('banner_link').value = '';
      document.getElementById('banner_status').value = '1';

      const input = document.getElementById('banner_image');
      if (input) {
        input.value = '';
        input.required = true;
      }

      document.getElementById('currentImageWrap').style.display = 'none';
    }

    function openEditBanner(data) {
      const form = document.getElementById('formBanner');
      document.getElementById('modalBannerLabel').innerText = 'Edit Banner';
      document.getElementById('banner_id').value = data.id;
      form.action = 'banner_update.php';
      form.method = 'POST';

      document.getElementById('banner_title').value = data.title ?? '';
      document.getElementById('banner_link').value = data.link ?? '';
      document.getElementById('banner_status').value = (String(data.status) === '1') ? '1' : '0';

      const input = document.getElementById('banner_image');
      if (input) {
        input.value = '';
        input.required = false;
      }

      const wrap = document.getElementById('currentImageWrap');
      const img = document.getElementById('currentImage');
      if (wrap && img) {
        if (data.image) {
          img.src = 'uploads/banners/' + data.image;
          wrap.style.display = 'block';
        } else {
          wrap.style.display = 'none';
        }
      }

      const modalEl = document.getElementById('modalBanner');
      const instance = bootstrap.Modal.getOrCreateInstance(modalEl);
      instance.show();
    }

    function prepareDeleteBanner(id, title) {
      const a = document.getElementById('btnConfirmDeleteAction');
      a.href = 'banner_delete.php?id=' + id;
      const t = document.getElementById('delete_banner_title');
      if (t) t.innerText = title;
    }

    <?php if (!empty($status)): ?>
      (function(){
        let icon = 'success';
        let title = '';
        <?php if (strpos($status,'success') !== false): ?>
          title = 'Berhasil';
        <?php else: ?>
          icon = 'error';
          title = 'Gagal';
        <?php endif; ?>
        const status = <?php echo json_encode($status); ?>;
        const msg = <?php echo json_encode($msg); ?>;
        let text = '';
        <?php
          if ($status === 'success_add') echo "text='Banner berhasil ditambahkan!';";
          elseif ($status === 'success_update') echo "text='Banner berhasil diperbarui!';";
          elseif ($status === 'success_delete') echo "text='Banner berhasil dihapus!';";
          else echo "text='Operasi gagal: ' + msg;";
        ?>
        Swal.fire({
          icon: icon,
          title: title,
          text: text,
          toast: false,
          position: 'center',
          confirmButtonText: 'OK',
          timer: 3500
        });
      })();
    <?php endif; ?>
  </script>

</body>
</html>


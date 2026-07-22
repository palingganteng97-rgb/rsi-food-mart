<?php
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// READ-ONLY: Tidak ada handler create/update/delete.

// Ambil list tenants untuk filter
$tenants = [];
$tenantRes = $conn->query("SELECT id, name FROM tenants ORDER BY name ASC");
if ($tenantRes) {
    while ($trow = $tenantRes->fetch_assoc()) {
        $tenants[] = $trow;
    }
}

// Filter & Pagination
$search     = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$tenant_id  = isset($_GET['tenant_id']) ? intval($_GET['tenant_id']) : 0;
$rating     = isset($_GET['rating']) ? intval($_GET['rating']) : 0;
$date_from  = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
$date_to    = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';
$page       = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page   = isset($_GET['per_page']) ? max(5, min(100, intval($_GET['per_page']))) : 10;
$offset     = ($page - 1) * $per_page;

// Tergantung skema kolom tanggal di tenant_reviews, kita coba gunakan created_at.
// Jika ternyata nama kolom berbeda, ubah $dateColumn agar sesuai.
$dateColumn = 'created_at';

$where = [];
$params = [];
$types = '';

if ($tenant_id > 0) {
    $where[] = 'tr.tenant_id = ?';
    $params[] = $tenant_id;
    $types .= 'i';
}

if ($rating >= 1 && $rating <= 5) {
    $where[] = 'tr.rating = ?';
    $params[] = $rating;
    $types .= 'i';
}

if ($date_from !== '') {
    $where[] = "tr.$dateColumn >= ?";
    $params[] = $date_from . ' 00:00:00';
    $types .= 's';
}

if ($date_to !== '') {
    $where[] = "tr.$dateColumn <= ?";
    $params[] = $date_to . ' 23:59:59';
    $types .= 's';
}

// Search (tenant_name, username/patient, review)
if ($search !== '') {
    $where[] = '(t.name LIKE ? OR tr.review LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $types .= 'ss';
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// Data READ
$tenant_reviews = [];
$sqlBase = "
    SELECT tr.*, 
           t.name AS tenant_name
    FROM tenant_reviews tr
    LEFT JOIN tenants t ON tr.tenant_id = t.id
    $whereSql
";

// Total
$total = 0;
$countSql = "SELECT COUNT(*) AS cnt FROM tenant_reviews tr LEFT JOIN tenants t ON tr.tenant_id = t.id $whereSql";
if ($stmtCount = $conn->prepare($countSql)) {
    if (!empty($params)) {
        $stmtCount->bind_param($types, ...$params);
    }
    $stmtCount->execute();
    $resCount = $stmtCount->get_result();
    if ($resCount) {
        $rowCnt = $resCount->fetch_assoc();
        $total = intval($rowCnt['cnt'] ?? 0);
    }
    $stmtCount->close();
}

// Pagination fetch
$sql = $sqlBase . " ORDER BY tr.id DESC LIMIT ? OFFSET ?";
$paramsFetch = $params;
$typesFetch = $types;
$paramsFetch[] = $per_page;
$typesFetch .= 'ii';
$paramsFetch[] = $offset;

if ($stmt = $conn->prepare($sql)) {
    if (!empty($paramsFetch)) {
        $stmt->bind_param($typesFetch, ...$paramsFetch);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $tenant_reviews[] = $row;
    }
    $stmt->close();
}

$total_pages = $per_page > 0 ? (int)ceil($total / $per_page) : 1;

$status = '';
$msg = '';

function renderAlert(string $status, string $msg): void {
    if ($status === '') return;
    $type = 'info';
    $title = 'Info';
    switch ($status) {
        case 'success_create':
        case 'success_update':
        case 'success_delete':
            $type = 'success';
            $title = 'Berhasil';
            break;
        case 'error':
            $type = 'danger';
            $title = 'Gagal';
            break;
    }
    $body = $msg !== '' ? $msg : $status;
    echo "<div class=\"alert alert-{$type} alert-dismissible fade show rounded-4\" role=\"alert\">";
    echo "<div class=\"fw-bold\">" . htmlspecialchars($title) . "</div>";
    echo "<div>" . htmlspecialchars($body) . "</div>";
    echo "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button>";
    echo "</div>";
}
?>

<!DOCTYPE html>

<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Ulasan Tenant - RSI Food &amp; Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

    <style>
        :root { --bg:#0f172a; --text:#e5e7eb; --muted:#94a3b8; --green:#22c55e; }
        body { background: var(--bg) !important; color: var(--text); }
        @media (min-width: 992px) {
            main.content-shift { margin-left: 280px; }
            .bottom-nav { display:none; }
        }
        .table-dark { --bs-table-bg: rgba(2,6,23,.25); --bs-table-striped-bg: rgba(2,6,23,.16); }
        .star-rating i { color: #fbbf24; }
    </style>
</head>
<body>
    <?php require __DIR__ . '/sidebar.php'; ?>

<main class="content-shift p-4">
    <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
            <div>
                <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Ulasan Tenant</h2>
                <div class="text-white-50 small mt-1">Kelola ulasan pelanggan untuk setiap tenant</div>
            </div>
            <div class="text-end text-white-50 small">
                <i class="bi bi-info-circle me-1 text-warning"></i> Rating ditampilkan sebagai bintang
            </div>
        </div>

        <?php if (!empty($status)): ?>
            <div class="alert <?= strpos($status, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
                <strong>
                    <?php 
                    if ($status === 'success_delete') echo "Ulasan berhasil dihapus secara permanen!";
                    elseif ($status === 'success_update') echo "Ulasan berhasil diperbarui!";
                    elseif ($status === 'error') echo "Operasi gagal: " . htmlspecialchars($msg);
                    ?>
                </strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div id="dragScrollReviewsContainer" class="table-responsive rounded-3 drag-scroll-container" style="border: none !important; background: transparent !important; cursor: grab;">
            <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 850px; user-select: none; border-collapse: collapse !important;">
                <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
                    <tr>
                        <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 80px;">ID</th>
                        <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 150px;">Pasien Sesi</th>
                        <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 220px;">Tenant</th>
                        <th class="py-3 text-white" style="background: transparent !important; border: none !important; width: 160px;">Rating</th>
                        <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Isi Ulasan</th>
                        <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 130px;">Aksi</th>
                    </tr>
                </thead>
                <tbody style="background: transparent !important;">
                    <?php if (!empty($tenant_reviews)): foreach ($tenant_reviews as $row): ?>
                        <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                            <td class="text-center fw-medium text-white-50" style="background: transparent !important; border: none !important;"><?= (int)$row['id'] ?></td>
                            <td class="fw-semibold text-white-50" style="background: transparent !important; border: none !important;">
                                <span class="badge bg-secondary bg-opacity-25 text-white-50 border border-secondary border-opacity-25 rounded-2">Sesi #<?= (int)$row['patient_session_id'] ?></span>
                            </td>
                            <td class="fw-bold text-white" style="background: transparent !important; border: none !important;">
                                <?= htmlspecialchars($row['tenant_name'] ?? 'Tenant ID: '.$row['tenant_id']) ?>
                            </td>
                            <td class="text-white" style="background: transparent !important; border: none !important;">
                                <div class="text-warning d-flex gap-0.5">
                                    <?php 
                                    $stars = (int)($row['rating'] ?? 5);
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $stars ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star text-white-50" style="opacity:0.3;"></i>';
                                    }
                                    ?>
                                </div>
                            </td>
                            <td class="text-white-50" style="background: transparent !important; border: none !important; max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= htmlspecialchars($row['review'] ?? '-') ?>
                            </td>
                            <td class="text-center" style="background: transparent !important; border: none !important;">
                                <div class="d-flex justify-content-center gap-1">
                                    <!-- Tombol Detail Info Ulasan Lengkap -->
                                    <button type="button" class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Lihat Detail" data-bs-toggle="modal" data-bs-target="#modalDetailReview" onclick='openDetailReview(<?= json_encode($row) ?>)'>
                                        <i class="bi bi-eye-fill"></i>
                                    </button>
                                    <!-- Tombol Hapus Ulasan -->
                                    <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Hapus Ulasan" data-bs-toggle="modal" data-bs-target="#modalHapusReview" onclick="openHapusReview(<?= $row['id'] ?>, '<?= htmlspecialchars($row['tenant_name'] ?? 'Tenant') ?>')">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted shadow-none" style="background: transparent !important; border: none !important;">
                                <div class="py-4">
                                    <i class="bi bi-star d-block mb-3" style="font-size: 3rem; color: rgba(148, 163, 184, 0.3);"></i>
                                    <h5 class="fw-medium text-white-50 mb-0">Belum ada ulasan</h5>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- MODAL POP-UP: LIHAT DETAIL ULASAN LENGKAP -->
<div class="modal fade" id="modalDetailReview" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-white rounded-4 border-0" style="background: #1e293b;">
      <div class="modal-header border-bottom border-secondary border-opacity-20">
        <h5 class="modal-title fw-bold text-success"><i class="bi bi-chat-left-heart-fill me-2"></i> Rincian Ulasan Pelanggan</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow:none;"></button>
      </div>
      <div class="modal-body py-3">
        <div class="mb-3">
            <label class="small text-white-50 d-block mb-1">Nama Tenant / Toko</label>
            <div id="detail-tenant-name" class="fw-bold fs-5 text-white">-</div>
        </div>
        <div class="row mb-3">
            <div class="col-6">
                <label class="small text-white-50 d-block mb-1">Sesi Pasien</label>
                <div id="detail-patient-session" class="fw-semibold text-white">-</div>
            </div>
            <div class="col-6">
                <label class="small text-white-50 d-block mb-1">Rating Bintang</label>
                <div id="detail-rating-stars" class="text-warning d-flex gap-0.5">-</div>
            </div>
        </div>
        <div class="mb-2">
            <label class="small text-white-50 d-block mb-1">Isi Pesan Ulasan</label>
            <div id="detail-review-text" class="p-3 bg-dark bg-opacity-50 border border-secondary border-opacity-30 rounded-3 text-white-50" style="white-space: pre-wrap; word-break: break-word; min-height: 80px;">-</div>
        </div>
      </div>
      <div class="modal-footer border-top border-secondary border-opacity-20">
        <button type="button" class="btn btn-sm rounded-pill px-4 fw-medium btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL POP-UP: KONFIRMASI HAPUS ULASAN -->
<div class="modal fade" id="modalHapusReview" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
    <div class="modal-content text-white rounded-4 border-0" style="background: #111827; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-triangle-fill"></i> Hapus Ulasan
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="box-shadow: none;"></button>
      </div>
      <div class="modal-body py-3">
        <p class="text-white-50 m-0" style="font-size: 0.95rem; line-height: 1.5;">Apakah Anda yakin ingin menghapus data ulasan dari tenant <strong id="txtDeleteReviewInfo" class="text-white">-</strong> secara permanen?</p>
      </div>
      <div class="modal-footer border-0 pt-0 d-flex gap-2">
        <button type="button" class="btn btn-sm rounded-pill px-4 fw-medium text-white border-0" data-bs-dismiss="modal" style="background: #1f2937;">Batal</button>
        <a id="btnConfirmDeleteReview" href="#" class="btn btn-danger btn-sm rounded-pill px-4 fw-medium shadow-sm">Ya, Hapus</a>
      </div>
    </div>
  </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


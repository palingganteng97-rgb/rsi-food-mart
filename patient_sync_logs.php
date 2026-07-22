<?php
// patient_sync_logs.php (Hanya Logika Atas Backend)
include "db.php"; // Di dalam db.php harus sudah dipanggil session_start()

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 1. Logika Pemrosesan Pencarian (Filter Berdasarkan Nomor Rekam Medis atau Status)
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$whereClauses = [];
$params = [];
$types = "";

if ($search !== '') {
    $whereClauses[] = "medical_record_number LIKE ?";
    $searchParam = "%" . $search . "%";
    $params[] = &$searchParam;
    $types .= "s";
}

if ($statusFilter !== '') {
    $whereClauses[] = "sync_status = ?";
    $params[] = &$statusFilter;
    $types .= "s";
}

$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

// 2. Query Ambil Daftar Log Sinkronisasi Berdasarkan Filter
$sql = "SELECT id, medical_record_number, sync_status, created_at, request_payload, response_payload 
        FROM patient_sync_logs 
        $whereSql 
        ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$resultLogs = $stmt->get_result();

$logs = [];
if ($resultLogs) {
    while ($row = $resultLogs->fetch_assoc()) {
        $logs[] = $row;
    }
}
$stmt->close();
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
        
        <!-- HEADER TABEL & FILTER PENCARIAN -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
            <div>
                <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Patient Sync Logs</h2>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <!-- Form Filter Pencarian -->
                <form method="GET" action="patient_sync_logs.php" class="d-flex gap-2">
                    <select name="status" class="form-select rounded-3 bg-dark text-white border-secondary" style="font-size: 0.9rem; min-width: 140px;">
                        <option value="">-- Semua Status --</option>
                        <option value="SUCCESS" <?= ($statusFilter === 'SUCCESS') ? 'selected' : '' ?>>SUCCESS</option>
                        <option value="FAILED" <?= ($statusFilter === 'FAILED') ? 'selected' : '' ?>>FAILED</option>
                    </select>
                    <input type="text" name="q" class="form-control rounded-3 bg-dark text-white border-secondary" placeholder="Cari No. RM Pasien..." value="<?= htmlspecialchars($search) ?>" style="font-size: 0.9rem; min-width: 220px;">
                    <button type="submit" class="btn btn-secondary rounded-3 px-3"><i class="bi bi-search"></i></button>
                    <?php if ($search !== '' || $statusFilter !== ''): ?>
                        <a href="patient_sync_logs.php" class="btn btn-outline-light rounded-3">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- TABEL DATA MONITORING SINKRONISASI -->
        <div class="table-responsive" id="dragScrollStockContainer" style="overflow-x: auto; cursor: grab; scrollbar-width: none; -ms-overflow-style: none;">
            <style>
                #dragScrollStockContainer::-webkit-scrollbar { display: none; }
                /* Menerapkan efek hover kustom transparan tipis agar warna abu-abu gelap bawaan terhapus */
                .table-custom-hover tbody tr:hover { background: rgba(148, 163, 184, 0.15) !important; transition: background 0.15s ease-in-out; }
                .table-force-white th, .table-force-white td { color: #ffffff !important; }
            </style>
            <!-- PERBAIKAN: Menghapus class .table bawaan Bootstrap agar tidak ditimpa background hover abu-abu pekat -->
            <table class="table-force-white table-custom-hover align-middle mb-0" style="width: 100%; border-collapse: separate; border-spacing: 0 8px; min-width: 1100px; user-select: none;">
                <thead style="font-size: 0.85rem; background: rgba(30, 41, 59, 0.45);">
                    <tr class="text-white">
                        <th class="ps-4 py-3" style="border-radius: 10px 0 0 10px; border-bottom: 1px solid rgba(148, 163, 184, 0.12); width: 80px; text-align: center;">ID</th>
                        <th class="py-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.12); width: 200px;">Waktu Sinkronisasi</th>
                        <th class="py-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.12); width: 220px;">No. Rekam Medis</th>
                        <th class="py-3 text-center" style="border-bottom: 1px solid rgba(148, 163, 184, 0.12); width: 150px;">Status API</th>
                        <th class="text-center py-3" style="border-radius: 0 10px 10px 0; border-bottom: 1px solid rgba(148, 163, 184, 0.12); width: 180px;">Inspeksi Payload</th>
                    </tr>
                </thead>
                <tbody style="font-size: 0.9rem;">
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $row): 
                            $isSuccess = ($row['sync_status'] === 'SUCCESS');
                            $badgeClass = $isSuccess ? 'bg-success' : 'bg-danger';
                        ?>
                        <tr class="text-white" style="background: rgba(30, 41, 59, 0.55); border: 1px solid rgba(148, 163, 184, 0.15);">
                            <td class="ps-4 py-3 text-center fw-semibold" style="border-radius: 10px 0 0 10px;"><?= $row['id'] ?></td>
                            <td class="fw-medium"><?= date('d/m/Y H:i:s', strtotime($row['created_at'])); ?></td>
                            <td class="fw-bold" style="font-family: monospace; font-size: 0.95rem;"><?= htmlspecialchars($row['medical_record_number']) ?></td>
                            <td class="text-center">
                                <span class="badge <?= $badgeClass; ?> rounded-2 px-2.5 py-1.5 fw-bold" style="font-size: 0.75rem; color: #fff !important;"><?= $row['sync_status']; ?></span>
                            </td>
                            <!-- Tombol Aksi Inspeksi Payload JSON (Memicu Modal Detail) -->
                            <td class="text-center" style="border-radius: 0 10px 10px 0;">
                                <button type="button" class="btn btn-sm btn-outline-info rounded-3 px-3 py-1.5 fw-medium d-inline-flex align-items-center gap-1" 
                                        onclick="viewPayloadDetail(<?= htmlspecialchars(json_encode([
                                            'rm' => $row['medical_record_number'],
                                            'time' => date('d/m/Y H:i:s', strtotime($row['created_at'])),
                                            'status' => $row['sync_status'],
                                            'request' => $row['request_payload'],
                                            'response' => $row['response_payload']
                                        ])) ?>)">
                                    <i class="bi bi-braces-asterisk"></i> Detail JSON
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-white fw-bold" style="background: rgba(30, 41, 59, 0.2); border-radius: 10px;">
                            <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                            Tidak ada riwayat integrasi atau log sinkronisasi ditemukan.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</main>

<!-- MODAL VIEW DETAIL INSPEKSI PAYLOAD JSON -->
<div class="modal fade" id="modalPayloadDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(10px); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-header border-0 pb-0" style="padding: 1.5rem 1.5rem 0 1.5rem;">
                <h5 class="fw-bold text-white m-0"><i class="bi bi-terminal text-info me-2"></i> Inspeksi Data Integrasi API</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3 text-white-50 small">
                    <div class="col-sm-4"><strong>No. Rekam Medis:</strong> <span id="detail_rm" class="text-white fw-bold"></span></div>
                    <div class="col-sm-4"><strong>Waktu Kirim:</strong> <span id="detail_time" class="text-white"></span></div>
                    <div class="col-sm-4"><strong>Status Respon:</strong> <span id="detail_status"></span></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-info fw-bold mb-1"><i class="bi bi-box-arrow-up"></i> Request Payload (JSON Kiriman)</label>
                        <pre id="detail_request" class="p-3 bg-dark rounded-3 border border-secondary text-success overflow-auto small mb-0" style="max-height: 350px; font-family: monospace;"></pre>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-warning fw-bold mb-1"><i class="bi bi-box-arrow-in-down"></i> Response Payload (Balikan Server)</label>
                        <pre id="detail_response" class="p-3 bg-dark rounded-3 border border-secondary text-warning overflow-auto small mb-0" style="max-height: 350px; font-family: monospace;"></pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0" style="padding: 0 1.5rem 1.5rem 1.5rem;">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 px-4 py-2" data-bs-dismiss="modal">Tutup Inspeksi</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fungsionalitas Geser Menggunakan Kursor Tikus (Drag-to-Scroll)
        const logSlider = document.getElementById('dragScrollStockContainer');
        if (logSlider) {
            let isDown = false;
            let startX, scrollLeft;
            
            logSlider.addEventListener('mousedown', (e) => {
                if (e.target.closest('button') || e.target.closest('a') || e.target.closest('select') || e.target.closest('input')) return;
                isDown = true; 
                logSlider.style.cursor = 'grabbing';
                startX = e.pageX - logSlider.offsetLeft; 
                scrollLeft = logSlider.scrollLeft;
            });
            
            logSlider.addEventListener('mouseleave', () => { isDown = false; logSlider.style.cursor = 'grab'; });
            logSlider.addEventListener('mouseup', () => { isDown = false; logSlider.style.cursor = 'grab'; });
            
            logSlider.addEventListener('mousemove', (e) => {
                if (!isDown) return; 
                e.preventDefault();
                const x = e.pageX - logSlider.offsetLeft;
                logSlider.scrollLeft = scrollLeft - ((x - startX) * 2);
            });
        }
    });

    // Fungsi menampilkan detail data payload JSON terformat rapi (Pretty Print) di dalam modal
    function viewPayloadDetail(data) {
        if (data) {
            document.getElementById('detail_rm').innerText = data.rm;
            document.getElementById('detail_time').innerText = data.time;
            
            const statusBadge = document.getElementById('detail_status');
            statusBadge.innerText = data.status;
            statusBadge.className = data.status === 'SUCCESS' ? 'badge bg-success' : 'badge bg-danger';

            try {
                const reqJson = JSON.parse(data.request);
                document.getElementById('detail_request').innerText = JSON.stringify(reqJson, null, 2);
            } catch(e) {
                document.getElementById('detail_request').innerText = data.request || '{}';
            }

            try {
                const resJson = JSON.parse(data.response);
                document.getElementById('detail_response').innerText = JSON.stringify(resJson, null, 2);
            } catch(e) {
                document.getElementById('detail_response').innerText = data.response || '{}';
            }

            const modalDetail = document.getElementById('modalPayloadDetail');
            if (modalDetail) {
                const instance = bootstrap.Modal.getOrCreateInstance(modalDetail);
                instance.show();
            }
        }
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

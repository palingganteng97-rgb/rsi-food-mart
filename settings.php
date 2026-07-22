<?php
// settings.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$status = $_GET['status'] ?? '';
$msg = $_GET['msg'] ?? '';

// =========================================================================
// PROSES UPDATE / SIMPAN KONFIGURASI GLOBAL (MASS UPDATE)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_settings'])) {
    // Mengambil semua input form berbentuk array key-value pair
    $configs = $_POST['config'] ?? [];

    if (empty($configs) || !is_array($configs)) {
        header("Location: settings.php?status=error&msg=" . urlencode("Tidak ada data pengaturan yang dikirim!"));
        exit;
    }

    try {
        $conn->begin_transaction();

        // Siapkan SQL Upsert (Insert jika belum ada key-nya, Update jika sudah ada key-nya)
        $sql = "INSERT INTO settings (`key`, `value`, created_at, updated_at) 
                VALUES (?, ?, NOW(), NOW()) 
                ON DUPLICATE KEY UPDATE `value` = ?, updated_at = NOW()";
        
        $stmt = $conn->prepare($sql);

        foreach ($configs as $keyName => $valueContent) {
            $keyName = trim($keyName);
            $valueContent = trim($valueContent);

            // Bind parameter: key, value (insert), value (update)
            $stmt->bind_param("sss", $keyName, $valueContent, $valueContent);
            $stmt->execute();
        }

        $stmt->close();
        $conn->commit();
        
        header("Location: settings.php?status=success");
        exit;

    } catch (Throwable $e) {
        $conn->rollback();
        header("Location: settings.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}

// =========================================================================
// PROSES MEMBACA DATA SETTINGS DARI DATABASE UNTUK DI-RENDER DI FORM
// =========================================================================
$currentSettings = [];
$query = $conn->query("SELECT `key`, `value` FROM settings");
if ($query) {
    while ($row = $query->fetch_assoc()) {
        $currentSettings[$row['key']] = $row['value'];
    }
}

// Fungsi helper untuk mengambil nilai setting secara aman di elemen HTML input value
function getSettingValue(string $key, array $currentSettings, string $default = ''): string {
    return htmlspecialchars($currentSettings[$key] ?? $default);
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
        
        <!-- HEADER HALAMAN SETTINGS -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
            <div>
                <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Global Settings</h2>
            </div>
            <div>
                <!-- Tombol Submit Form Utama -->
                <button type="submit" form="formGlobalSettings" class="btn btn-success rounded-3 px-4 py-2 fw-medium d-flex align-items-center gap-2">
                    <i class="bi bi-cloud-check-fill"></i> Simpan Perubahan
                </button>
            </div>
        </div>

        <!-- NOTIFIKASI STATUS OPERASI -->
        <?php if (isset($_GET['status'])): ?>
            <div class="alert <?= $_GET['status'] === 'success' ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
                <strong>
                    <?php 
                    if ($_GET['status'] === 'success') echo "Pengaturan global berhasil diperbarui!";
                    else echo "Operasi gagal: " . htmlspecialchars($_GET['msg'] ?? 'Terjadi kesalahan sistem.');
                    ?>
                </strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- FORM UTAMA CONFIGURASI -->
        <form action="settings.php" method="POST" id="formGlobalSettings">
            <input type="hidden" name="action_save_settings" value="1">
            
            <div class="row g-4">
                <!-- SEKSI 1: IDENTITAS APLIKASI -->
                <div class="col-12 col-lg-6">
                    <div class="p-4 rounded-4" style="background: rgba(30, 41, 59, 0.4); border: 1px solid rgba(148, 163, 184, 0.15);">
                        <h5 class="fw-bold text-white mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-display text-info"></i> Identitas Aplikasi
                        </h5>
                        
                        <div class="mb-3">
                            <label class="form-label small text-white-50 fw-medium">Nama Aplikasi / Sistem</label>
                            <input type="text" name="config[app_name]" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none; color: #ffffff !important;" value="<?= getSettingValue('app_name', $currentSettings, 'RSI FOOD & MART') ?>" required>
                        </div>

                        <div class="mb-0">
                            <label class="form-label small text-white-50 fw-medium">Slogan / Deskripsi Sistem</label>
                            <input type="text" name="config[app_slogan]" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none; color: #ffffff !important;" value="<?= getSettingValue('app_slogan', $currentSettings, 'Pemesanan Makanan Sehat') ?>">
                        </div>
                    </div>
                </div>

                <!-- SEKSI 2: PENGATURAN TRANSAKSI & BIAYA -->
                <div class="col-12 col-lg-6">
                    <div class="p-4 rounded-4" style="background: rgba(30, 41, 59, 0.4); border: 1px solid rgba(148, 163, 184, 0.15);">
                        <h5 class="fw-bold text-white mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-cash-coin text-warning"></i> Keuangan &amp; Pajak
                        </h5>
                        
                        <div class="mb-3">
                            <label class="form-label small text-white-50 fw-medium">Pajak Pertambahan Nilai / PPN (%)</label>
                            <input type="number" name="config[tax_percentage]" min="0" max="100" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none; color: #ffffff !important;" value="<?= getSettingValue('tax_percentage', $currentSettings, '11') ?>" required>
                        </div>

                        <div class="mb-0">
                            <label class="form-label small text-white-50 fw-medium">Biaya Layanan / Penanganan (Rp)</label>
                            <input type="number" name="config[service_fee]" min="0" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none; color: #ffffff !important;" value="<?= getSettingValue('service_fee', $currentSettings, '2000') ?>" required>
                        </div>
                    </div>
                </div>

                <!-- SEKSI 3: OPERASIONAL & KETERANGAN TOKO -->
                <div class="col-12">
                    <div class="p-4 rounded-4" style="background: rgba(30, 41, 59, 0.4); border: 1px solid rgba(148, 163, 184, 0.15);">
                        <h5 class="fw-bold text-white mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-geo-alt-fill text-danger"></i> Alamat &amp; Kontak Operasional
                        </h5>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small text-white-50 fw-medium">Nomor WhatsApp CS</label>
                                <input type="text" name="config[contact_whatsapp]" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none; color: #ffffff !important;" value="<?= getSettingValue('contact_whatsapp', $currentSettings, '081234567890') ?>" placeholder="Contoh: 0812...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-white-50 fw-medium">Email Informasi</label>
                                <input type="email" name="config[contact_email]" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none; color: #ffffff !important;" value="<?= getSettingValue('contact_email', $currentSettings, 'info@rsifoodmart.com') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-white-50 fw-medium">Alamat Lengkap Pusat</label>
                                <textarea name="config[office_address]" rows="3" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none; color: #ffffff !important;" placeholder="Tulis alamat rumah sakit atau kantor pusat..."><?= getSettingValue('office_address', $currentSettings) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

    </div>
</main>

<script>
    // Otomatis membersihkan parameter ?status=... dari alamat URL bar tanpa refresh ulang halaman
    if (window.history.replaceState && (window.location.search.includes('status=') || window.location.search.includes('msg='))) {
        const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
        window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
include 'db.php'; 
include 'notification_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : 'read';

if ($action == 'read') {
    $query = "SELECT pv.*, p.name AS product_name 
              FROM product_variants pv 
              JOIN products p ON pv.product_id = p.id 
              ORDER BY pv.id DESC";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Gagal mengambil data: " . mysqli_error($conn));
    }
    $variants = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

if ($action == 'create' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = intval($_POST['product_id']);
    $name       = mysqli_real_escape_string($conn, trim($_POST['name']));

    if (!empty($product_id) && !empty($name)) {
        $query = "INSERT INTO product_variants (product_id, name) VALUES ($product_id, '$name')";
        if (mysqli_query($conn, $query)) {
            createNotification('admin', (int)$_SESSION['user_id'], 'Varian Produk Ditambahkan', "Varian '$name' berhasil ditambahkan", 'product_variants.php');
            header("Location: product_variants.php?status=success_add");
            exit;
        } else {
            header("Location: product_variants.php?status=error&msg=" . urlencode(mysqli_error($conn)));
            exit;
        }
    }
}

if ($action == 'update' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id         = intval($_POST['id']);
    $product_id = intval($_POST['product_id']);
    $name       = mysqli_real_escape_string($conn, trim($_POST['name']));

    if (!empty($id) && !empty($product_id) && !empty($name)) {
        $query = "UPDATE product_variants SET product_id = $product_id, name = '$name' WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            createNotification('admin', (int)$_SESSION['user_id'], 'Varian Produk Diperbarui', "Varian '$name' (ID: $id) berhasil diperbarui", 'product_variants.php');
            header("Location: product_variants.php?status=success_edit");
            exit;
        } else {
            header("Location: product_variants.php?status=error&msg=" . urlencode(mysqli_error($conn)));
            exit;
        }
    }
}

if ($action == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $infoQuery = mysqli_query($conn, "SELECT name FROM product_variants WHERE id = $id");
    $variant_data = mysqli_fetch_assoc($infoQuery);
    $saved_name = $variant_data ? $variant_data['name'] : "ID " . $id;

    $query = "DELETE FROM product_variants WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        createNotification('admin', (int)$_SESSION['user_id'], 'Varian Produk Dihapus', "Varian '$saved_name' berhasil dihapus", 'product_variants.php');
        header("Location: product_variants.php?status=success_delete");
        exit;
    } else {
        header("Location: product_variants.php?status=error&msg=" . urlencode(mysqli_error($conn)));
        exit;
    }
}

$product_query = "SELECT id, name FROM products WHERE deleted_at IS NULL ORDER BY name ASC";
error_log('[product_variants.php][DEBUG] Executing product dropdown query. action=' . $action . ' uri=' . ($_SERVER['REQUEST_URI'] ?? '')); 

static $debugOnce = false;
if ($debugOnce) {
    error_log('[product_variants.php][DEBUG][WARN] product_variants.php logic executed again in same request (unexpected).');
} else {
    $debugOnce = true;
}
$product_result = mysqli_query($conn, $product_query);
$products = $product_result ? mysqli_fetch_all($product_result, MYSQLI_ASSOC) : [];
error_log('[product_variants.php][DEBUG] product dropdown rows=' . count($products));
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
    <!-- Container tabel dengan tema gelap transparan -->
    <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
        
        <!-- HEADER TABEL & TOMBOL TAMBAH VARIAN -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
            <div>
                <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Varian Produk</h2>
            </div>
            <div>
                <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalVariant" onclick="openTambahVariant()">
                    <i class="bi bi-plus-circle"></i> Tambah Varian
                </button>
            </div>
        </div>

        <!-- NOTIFIKASI STATUS OPERASI CRUD -->
        <?php if (!empty($status)): ?>
            <div class="alert <?= strpos($status, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
                <strong>
                    <?php 
                    if ($status == 'success_add') echo "Varian produk berhasil ditambahkan!";
                    elseif ($status == 'success_edit') echo "Data varian berhasil diperbarui!";
                    elseif ($status == 'success_delete') echo "Varian produk berhasil dihapus!";
                    else echo "Operasi gagal: " . htmlspecialchars($msg);
                    ?>
                </strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- STRUKTUR TABEL LIST DATA VARIAN (PAS SATU LAYAR FULL - NO SCROLL) -->
        <div id="variantTableContainer" class="table-responsive rounded-3" style="border: none !important; background: transparent !important; box-shadow: none !important; -webkit-box-shadow: none !important; overflow-x: hidden !important;">
            <table class="table table-hover align-middle mb-0 text-white" style="background: transparent !important; color: #e5e7eb !important; width: 100% !important; table-layout: auto !important; border-collapse: collapse !important;">
                <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
                    <tr>
                        <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 80px;">ID</th>
                        <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Nama Produk</th>
                        <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Nama Varian</th>
                        <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 130px;">Aksi</th>
                    </tr>
                </thead>
                <tbody style="background: transparent !important;">
                    <?php if (!empty($variants)): foreach ($variants as $row): ?>
                        <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                            <!-- Kolom ID -->
                            <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= $row['id'] ?></td>
                            
                            <!-- Kolom Nama Produk -->
                            <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;">
                                <div class="text-truncate" title="<?= htmlspecialchars($row['product_name']) ?>"><?= htmlspecialchars($row['product_name'] ?: 'Produk Telah Dihapus') ?></div>
                            </td>

                            <!-- Kolom Nama Varian (PERBAIKAN: Menggunakan bg-dark text-white & border tipis agar teks putih terlihat sangat jelas) -->
                            <td class="text-white" style="background: transparent !important; border: none !important;">
                                <span class="badge bg-dark text-white border border-secondary border-opacity-50 px-3 py-1.5" style="font-size: 0.85rem; letter-spacing: 0.5px;">
                                    <?= htmlspecialchars($row['name']) ?>
                                </span>
                            </td>
                            
                            <!-- Kolom Aksi Menu CRUD -->
                            <td class="text-center" style="background: transparent !important; border: none !important;">
                                <div class="d-flex justify-content-center gap-1">
                                    <!-- Tombol Edit Baru: Mengirimkan data sebagai argumen teks biasa, anti-error JSON -->
                                    <button type="button" class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Edit Data" 
                                            onclick="openEditVariant(<?= $row['id'] ?>, <?= $row['product_id'] ?>, '<?= addslashes(htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8')) ?>')">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    <!-- Tombol Hapus Terhubung ke Modal Konfirmasi -->
                                    <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Hapus Varian"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalConfirmDeleteVariant" 
                                            onclick="document.getElementById('delete_variant_display_name').innerText = '<?php echo addslashes($row['name']); ?>'; document.getElementById('btnConfirmDeleteVariantAction').setAttribute('href', 'product_variants.php?action=delete&id=<?= $row['id'] ?>')">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <!-- State Tampilan jika data kosong -->
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted shadow-none" style="background: transparent !important; border: none !important;">Belum ada data varian produk.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- =========================================================================
     MODAL INPUT DATA (TAMBAH / EDIT VARIAN)
     ========================================================================= -->
<div class="modal fade" id="modalVariant" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="product_variants.php" method="POST" id="formVariant" class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(10px); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-header border-0 pb-0" style="padding: 1.5rem 1.5rem 0 1.5rem;">
                <h5 class="fw-bold text-white m-0" id="modalVariantLabel">Tambah Varian</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Input Hidden ID untuk Operasi Update -->
                <input type="hidden" name="id" id="variant_id">
                
                <!-- Pilih Produk Relasi -->
                <div class="mb-3">
                    <label for="variant_product_id" class="form-label small text-white-50 fw-medium">Pilih Produk</label>
                    <select name="product_id" id="variant_product_id" class="form-select bg-dark text-white border-secondary rounded-3" style="background-color: rgba(2, 6, 23, 0.4) !important;" required>
                        <option value="">-- Pilih Produk --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Input Nama Varian -->
                <div class="mb-2">
                    <label for="variant_name" class="form-label small text-white-50 fw-medium">Nama Varian</label>
                    <input type="text" name="name" id="variant_name" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none;" placeholder="Contoh: Pedas, Jumbo, Ekstra Keju" required>
                </div>
            </div>
            
            <div class="modal-footer border-0 pt-0 d-flex gap-2 justify-content-end" style="padding: 0 1.5rem 1.5rem 1.5rem;">
                <button type="button" class="btn btn-sm btn-secondary rounded-3 px-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
                <!-- PERBAIKAN UTAMA: Memastikan id="btnSubmitVariant" ada agar name dan value bisa dimanipulasi dinamis oleh JS -->
                <button type="submit" name="action" value="create" id="btnSubmitVariant" class="btn btn-sm btn-success rounded-3 px-3 py-2 fw-medium">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL KONFIRMASI HAPUS DATA
     ========================================================================= -->
<div class="modal fade" id="modalConfirmDeleteVariant" tabindex="-1" aria-labelledby="modalConfirmDeleteVariantLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-bg-dark border-secondary" style="background-color: #111827 !important; border-color: #374151 !important; border-radius: 16px;">
      <div class="modal-header border-bottom border-secondary">
        <h5 class="modal-title text-white fw-bold d-flex align-items-center" id="modalConfirmDeleteVariantLabel">
          <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Konfirmasi Hapus
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4">
        <div class="mb-3">
          <i class="bi bi-trash3-fill text-danger" style="font-size: 3.5rem;"></i>
        </div>
        <p class="text-white-50 fs-6 mb-1">Apakah Anda yakin ingin menghapus varian dari produk ini?</p>
        <h6 id="delete_variant_display_name" class="text-warning fw-bold mt-2"></h6>
      </div>
      <div class="modal-footer border-top border-secondary justify-content-center">
        <button type="button" class="btn btn-sm btn-secondary px-4 rounded-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
        <a id="btnConfirmDeleteVariantAction" href="#" class="btn btn-sm btn-danger px-4 rounded-3 py-2 fw-bold d-inline-flex align-items-center justify-content-center">Oke, Hapus</a>
      </div>
    </div>
  </div>
</div>

<script>
    // DEBUG counters
    window.__debugVariant = window.__debugVariant || {
        openTambahVariant: 0,
        openEditVariant: 0,
        shownModal: 0,
        renderOptionCountAtShow: []
    };

    function countVariantOptions() {
        const sel = document.getElementById('variant_product_id');
        if (!sel) return 0;
        return sel.querySelectorAll('option').length;
    }

    document.addEventListener('DOMContentLoaded', () => {
        const modalEl = document.getElementById('modalVariant');
        if (!modalEl) return;

        modalEl.addEventListener('shown.bs.modal', () => {
            window.__debugVariant.shownModal++;
            const countNow = countVariantOptions();
            window.__debugVariant.renderOptionCountAtShow.push(countNow);
            console.log('[DEBUG][modalVariant shown] countOption=', countNow, ' shownModal=', window.__debugVariant.shownModal);
        });

        console.log('[DEBUG][DOMContentLoaded] initial option count in #variant_product_id =', countVariantOptions());
    });

    function openTambahVariant() {
        window.__debugVariant.openTambahVariant++;
        const form = document.getElementById('formVariant');
        const submitBtn = document.getElementById('btnSubmitVariant');

        console.log('[DEBUG][openTambahVariant] called=', window.__debugVariant.openTambahVariant, 'optionCountBefore=', countVariantOptions());
        
        if(form) form.action = "product_variants.php?action=create";
        if(submitBtn) {
            submitBtn.name = "action";
            submitBtn.value = "create";
        }
        
        document.getElementById('variant_id').value = "";
        document.getElementById('variant_product_id').value = "";
        document.getElementById('variant_name').value = "";
        document.getElementById('modalVariantLabel').innerText = "Tambah Varian";

        console.log('[DEBUG][openTambahVariant] optionCountAfterReset=', countVariantOptions());
    }

    // PERBAIKAN MUTLAK: Menginisialisasi modal secara langsung saat tombol diklik
    function openEditVariant(id, productId, name) {
        window.__debugVariant.openEditVariant++;
        const form = document.getElementById('formVariant');
        const submitBtn = document.getElementById('btnSubmitVariant');
        
        console.log('[DEBUG][openEditVariant] called=', window.__debugVariant.openEditVariant, 'optionCountBefore=', countVariantOptions());
        
        if(form) form.action = "product_variants.php?action=update";
        if(submitBtn) {
            submitBtn.name = "action";
            submitBtn.value = "update";
        }
        
        // Isi data ke form input
        document.getElementById('variant_id').value = id;
        document.getElementById('variant_product_id').value = productId;
        document.getElementById('variant_name').value = name;
        document.getElementById('modalVariantLabel').innerText = "Ubah Detail Varian";
        
        // Panggil langsung instansiasi modal Bootstrap di sini (Anti-Crash)
        const modalElement = document.getElementById('modalVariant');
        const instance = bootstrap.Modal.getOrCreateInstance(modalElement);
        instance.show();

        console.log('[DEBUG][openEditVariant] after show call optionCountNow=', countVariantOptions());
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

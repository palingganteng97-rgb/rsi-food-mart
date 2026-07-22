<?php
// addon_items.php
include 'db.php'; 
include 'notification_helper.php'; // INTEGRASI: Menyertakan fungsi pembuat notifikasi

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : 'read';
$status = isset($_GET['status']) ? $_GET['status'] : "";
$msg    = isset($_GET['msg']) ? $_GET['msg'] : "";

// 1. Ambil Semua Data Item Topping (Read) beserta nama Grup Addon-nya
if ($action == 'read') {
    // SINKRONISASI HEIDISQL: Mengambil dari tabel addon_items berelasi ke product_addons
    $query = "SELECT ai.*, pa.addon_name AS group_name 
              FROM addon_items ai 
              LEFT JOIN product_addons pa ON ai.addon_id = pa.id 
              ORDER BY ai.id DESC";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Gagal mengambil data: " . mysqli_error($conn));
    }
    $addon_items = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// 2. Tambah Data Item Topping (Create)
if ($action == 'create' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    // SINKRONISASI HEIDISQL: Menangkap input sesuai kolom database Anda
    $addon_id  = intval($_POST['addon_id']);
    $item_name = mysqli_real_escape_string($conn, trim($_POST['item_name']));
    $price     = floatval($_POST['price']);

    if (!empty($addon_id) && !empty($item_name)) {
        $query = "INSERT INTO addon_items (addon_id, item_name, price) VALUES ($addon_id, '$item_name', $price)";
        if (mysqli_query($conn, $query)) {
            // INTEGRASI: Membuat notifikasi setelah berhasil tambah data
            createNotification(
                'admin', 
                (int)$_SESSION['user_id'], 
                'Addon Baru Ditambahkan', 
                "Item addon '$item_name' dengan harga Rp " . number_format($price) . " berhasil ditambahkan", 
                'addon_items.php'
            );

            header("Location: addon_items.php?status=success_add");
            exit;
        } else {
            header("Location: addon_items.php?status=error&msg=" . urlencode(mysqli_error($conn)));
            exit;
        }
    }
}

// 3. Ubah Data Item Topping (Update)
if ($action == 'update' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    // SINKRONISASI HEIDISQL: Mengubah data berdasarkan ID item_name & price
    $id        = intval($_POST['id']);
    $addon_id  = intval($_POST['addon_id']);
    $item_name = mysqli_real_escape_string($conn, trim($_POST['item_name']));
    $price     = floatval($_POST['price']);

    if (!empty($id) && !empty($addon_id) && !empty($item_name)) {
        $query = "UPDATE addon_items SET addon_id = $addon_id, item_name = '$item_name', price = $price WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            // INTEGRASI: Membuat notifikasi setelah berhasil ubah data
            createNotification(
                'admin', 
                (int)$_SESSION['user_id'], 
                'Addon Diperbarui', 
                "Item addon '$item_name' (ID: $id) berhasil diperbarui", 
                'addon_items.php'
            );

            header("Location: addon_items.php?status=success_edit");
            exit;
        } else {
            header("Location: addon_items.php?status=error&msg=" . urlencode(mysqli_error($conn)));
            exit;
        }
    }
}

// 4. Hapus Data Item Topping (Delete)
if ($action == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Opsional: Ambil nama item terlebih dahulu untuk isi pesan notifikasi yang informatif sebelum dihapus
    $name_query = mysqli_query($conn, "SELECT item_name FROM addon_items WHERE id = $id");
    $item_data  = mysqli_fetch_assoc($name_query);
    $saved_name = $item_data ? $item_data['item_name'] : "ID " . $id;

    $query = "DELETE FROM addon_items WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        // INTEGRASI: Membuat notifikasi setelah berhasil hapus data
        createNotification(
            'admin', 
            (int)$_SESSION['user_id'], 
            'Addon Dihapus', 
            "Item addon '$saved_name' berhasil dihapus dari sistem", 
            'addon_items.php'
        );

        header("Location: addon_items.php?status=success_delete");
        exit;
    } else {
        header("Location: addon_items.php?status=error&msg=" . urlencode(mysqli_error($conn)));
        exit;
    }
}

// Ambil opsi pilihan kelompok Addon untuk komponen dropdown select form pembungkus
$addon_group_query = "SELECT id, addon_name FROM product_addons ORDER BY addon_name ASC";
$addon_group_result = mysqli_query($conn, $addon_group_query);
$addon_groups = $addon_group_result ? mysqli_fetch_all($addon_group_result, MYSQLI_ASSOC) : [];
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

<!--- MAIN KONTEN --->
<main class="content-shift p-4">
    <!-- Container tabel dengan tema gelap transparan -->
    <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
        
        <!-- HEADER TABEL & TOMBOL TAMBAH TOPPING -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
            <div>
                <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Daftar Harga Topping</h2>
            </div>
            <div>
                <!-- SINKRONISASI MODAL: Menuju modalItemTopping -->
                <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalItemTopping" onclick="openTambahItem()">
                    <i class="bi bi-plus-circle"></i> Tambah Topping
                </button>
            </div>
        </div>

        <!-- NOTIFIKASI STATUS OPERASI CRUD -->
        <?php if (!empty($status)): ?>
            <div class="alert <?= strpos($status, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
                <strong>
                    <?php 
                    if ($status == 'success_add') echo "Item topping berhasil ditambahkan!";
                    elseif ($status == 'success_edit') echo "Data harga topping berhasil diperbarui!";
                    elseif ($status == 'success_delete') echo "Item topping berhasil dihapus!";
                    else echo "Operasi gagal: " . htmlspecialchars($msg);
                    ?>
                </strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- STRUKTUR TABEL LIST DATA TOPPING (PAS SATU LAYAR FULL - NO SCROLL) -->
        <div id="addonTableContainer" class="table-responsive rounded-3" style="border: none !important; background: transparent !important; box-shadow: none !important; -webkit-box-shadow: none !important; overflow-x: hidden !important;">
            <table class="table table-hover align-middle mb-0 text-white" style="background: transparent !important; color: #e5e7eb !important; width: 100% !important; table-layout: auto !important; border-collapse: collapse !important;">
                <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
                    <tr>
                        <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 80px;">ID</th>
                        <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Kelompok Topping</th>
                        <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Nama Topping</th>
                        <th class="py-3 text-end text-white" style="background: transparent !important; border: none !important; width: 150px; padding-right: 2rem;">Harga</th>
                        <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 130px;">Aksi</th>
                    </tr>
                </thead>
                <tbody style="background: transparent !important;">
                    <?php if (!empty($addon_items)): foreach ($addon_items as $row): ?>
                        <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                            <!-- Kolom ID -->
                            <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= $row['id'] ?></td>
                            
                            <!-- Kolom Kelompok / Grup Topping -->
                            <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;">
                                <div class="text-truncate" title="<?= htmlspecialchars($row['group_name'] ?? '') ?>"><?= htmlspecialchars($row['group_name'] ?: 'Umum / Tanpa Kelompok') ?></div>
                            </td>

                            <!-- Kolom Nama Topping -->
                            <td class="text-white" style="background: transparent !important; border: none !important;">
                                <span class="badge bg-dark text-white border border-secondary border-opacity-50 px-3 py-1.5" style="font-size: 0.85rem; letter-spacing: 0.5px;">
                                    <?= htmlspecialchars($row['item_name']) ?>
                                </span>
                            </td>

                            <!-- Kolom Harga Format Rupiah -->
                            <td class="text-end fw-bold text-success" style="background: transparent !important; border: none !important; padding-right: 2rem;">
                                Rp <?= number_format((float)$row['price'], 0, ',', '.') ?>
                            </td>
                            
                            <!-- Kolom Aksi Menu CRUD -->
                            <td class="text-center" style="background: transparent !important; border: none !important;">
                                <div class="d-flex justify-content-center gap-1">
                                    <!-- Tombol Edit: Mengirimkan parameter terpisah (id, addon_id, item_name, price) -->
                                    <button type="button" class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Edit Data" 
                                            onclick="openEditItem(<?= $row['id'] ?>, <?= $row['addon_id'] ?>, '<?= addslashes(htmlspecialchars($row['item_name'], ENT_QUOTES, 'UTF-8')) ?>', <?= $row['price'] ?>)">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    <!-- Tombol Hapus Terhubung ke Modal Konfirmasi -->
                                    <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Hapus Topping"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalConfirmDeleteItem" 
                                            onclick="document.getElementById('delete_item_display_name').innerText = '<?php echo addslashes($row['item_name']); ?>'; document.getElementById('btnConfirmDeleteItemAction').setAttribute('href', 'addon_items.php?action=delete&id=<?= $row['id'] ?>')">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <!-- State Tampilan jika data kosong -->
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted shadow-none" style="background: transparent !important; border: none !important;">Belum ada data rincian item harga topping.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- =========================================================================
     MODAL INPUT DATA (TAMBAH / EDIT ITEM TOPPING)
     ========================================================================= -->
<div class="modal fade" id="modalItemTopping" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <!-- Target Form diarahkan ke addon_items.php -->
        <form action="addon_items.php" method="POST" id="formItemTopping" class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(10px); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-header border-0 pb-0" style="padding: 1.5rem 1.5rem 0 1.5rem;">
                <h5 class="fw-bold text-white m-0" id="modalItemToppingLabel">Tambah Topping</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- SINKRONISASI HEIDISQL: Menyimpan Primary Key ID item -->
                <input type="hidden" name="id" id="item_primary_id">
                
                <!-- SINKRONISASI HEIDISQL: Pilihan Kelompok Topping (addon_id) -->
                <div class="mb-3">
                    <label for="item_addon_id" class="form-label small text-white-50 fw-medium">Kelompok Topping</label>
                    <select name="addon_id" id="item_addon_id" class="form-select bg-dark text-white border-secondary rounded-3" style="background-color: rgba(2, 6, 23, 0.4) !important;" required>
                        <option value="">-- Pilih Kelompok Topping --</option>
                        <?php foreach ($addon_groups as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['addon_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- SINKRONISASI HEIDISQL: Input Nama Topping (item_name) -->
                <div class="mb-3">
                    <label for="item_name" class="form-label small text-white-50 fw-medium">Nama Item Topping</label>
                    <input type="text" name="item_name" id="item_name" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none;" placeholder="Contoh: Telur Mata Sapi, Keju Cheddar, Sambal" required>
                </div>

                <!-- SINKRONISASI HEIDISQL: Input Harga Topping (price) -->
                <div class="mb-2">
                    <label for="item_price" class="form-label small text-white-50 fw-medium">Harga Satuan (Rupiah)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-white-50" style="background-color: rgba(2, 6, 23, 0.4) !important;">Rp</span>
                        <input type="number" step="0.01" min="0" name="price" id="item_price" class="form-control text-white border-secondary rounded-end-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none;" placeholder="Contoh: 3000" required>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer border-0 pt-0 d-flex gap-2 justify-content-end" style="padding: 0 1.5rem 1.5rem 1.5rem;">
                <button type="button" class="btn btn-sm btn-secondary rounded-3 px-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
                <button type="submit" id="btnSubmitItem" class="btn btn-sm btn-success rounded-3 px-3 py-2 fw-medium">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL KONFIRMASI HAPUS DATA
     ========================================================================= -->
<div class="modal fade" id="modalConfirmDeleteItem" tabindex="-1" aria-labelledby="modalConfirmDeleteItemLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-bg-dark border-secondary" style="background-color: #111827 !important; border-color: #374151 !important; border-radius: 16px;">
      <div class="modal-header border-bottom border-secondary">
        <h5 class="modal-title text-white fw-bold d-flex align-items-center" id="modalConfirmDeleteItemLabel">
          <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Konfirmasi Hapus
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4">
        <div class="mb-3">
          <i class="bi bi-trash3-fill text-danger" style="font-size: 3.5rem;"></i>
        </div>
        <p class="text-white-50 fs-6 mb-1">Apakah Anda yakin ingin menghapus item harga topping ini?</p>
        <!-- Penamaan ID diselaraskan dengan fungsi onclick di tabel utama -->
        <h6 id="delete_item_display_name" class="text-warning fw-bold mt-2"></h6>
      </div>
      <div class="modal-footer border-top border-secondary justify-content-center">
        <button type="button" class="btn btn-sm btn-secondary px-4 rounded-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
        <a id="btnConfirmDeleteItemAction" href="#" class="btn btn-sm btn-danger px-4 rounded-3 py-2 fw-bold d-inline-flex align-items-center justify-content-center">Oke, Hapus</a>
      </div>
    </div>
  </div>
</div>

<script>
    // Fungsi Reset Form saat Tombol "Tambah Topping" diklik
    function openTambahItem() {
        const form = document.getElementById('formItemTopping');
        const submitBtn = document.getElementById('btnSubmitItem');
        
        if(form) form.action = "addon_items.php?action=create";
        if(submitBtn) {
            submitBtn.name = "action";
            submitBtn.value = "create";
        }
        
        // Reset input data item topping baru
        document.getElementById('item_primary_id').value = "";
        document.getElementById('item_addon_id').value = "";
        document.getElementById('item_name').value = "";
        document.getElementById('item_price').value = ""; 
        document.getElementById('modalItemToppingLabel').innerText = "Tambah Topping";
    }

    // Fungsi Pengisian Form & Tampil Jendela saat Tombol "Edit" diklik
    function openEditItem(id, addonId, itemName, price) {
        const form = document.getElementById('formItemTopping');
        const submitBtn = document.getElementById('btnSubmitItem');
        
        if(form) form.action = "addon_items.php?action=update";
        if(submitBtn) {
            submitBtn.name = "action";
            submitBtn.value = "update";
        }
        
        // Memasukkan data record ke dalam form modal
        document.getElementById('item_primary_id').value = id;
        document.getElementById('item_addon_id').value = addonId;
        document.getElementById('item_name').value = itemName;
        document.getElementById('item_price').value = price;
        document.getElementById('modalItemToppingLabel').innerText = "Ubah Detail Harga Topping";
        
        // Panggil instansiasi objek modal Bootstrap 5
        const modalElement = document.getElementById('modalItemTopping');
        const instance = bootstrap.Modal.getOrCreateInstance(modalElement);
        instance.show();
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

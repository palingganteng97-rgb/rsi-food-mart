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

$status = isset($_GET['status']) ? $_GET['status'] : "";
$msg = isset($_GET['msg']) ? $_GET['msg'] : "";
$uploadDir = "uploads/products/gallery/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

if (isset($_POST['action_add_image'])) {
    $product_id = intval($_POST['product_id']);
    $is_primary = isset($_POST['is_primary']) ? intval($_POST['is_primary']) : 0;
    $imageName  = "";

    if (isset($_FILES['image']) && !empty($_FILES['image']['name'])) {
        $fileExt   = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $imageName = "gal_" . time() . "_" . uniqid() . "." . $fileExt;
        
        if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp'])) {
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName)) {
                header("Location: product_images.php?status=error&msg=" . urlencode("Gagal mengunggah file gambar."));
                exit();
            }
        } else {
            header("Location: product_images.php?status=error&msg=" . urlencode("Format gambar wajib JPG, PNG, atau WEBP."));
            exit();
        }
    } else {
        header("Location: product_images.php?status=error&msg=" . urlencode("Berkas foto wajib dipilih!"));
        exit();
    }

    if ($is_primary === 1) {
        mysqli_query($conn, "UPDATE product_images SET is_primary = 0 WHERE product_id = $product_id");
    }

    $query = "INSERT INTO product_images (product_id, image, is_primary) VALUES ($product_id, '$imageName', $is_primary)";
    if (mysqli_query($conn, $query)) {
        $productQuery = mysqli_query($conn, "SELECT name FROM products WHERE id = $product_id");
        $productData  = mysqli_fetch_assoc($productQuery);
        $productName  = $productData ? $productData['name'] : "ID " . $product_id;

        createNotification('admin', (int)$_SESSION['user_id'], 'Gambar Produk Ditambahkan', "Gambar baru berhasil diunggah untuk produk '$productName'", 'product_images.php');
        header("Location: product_images.php?status=success_insert");
        exit();
    } else {
        header("Location: product_images.php?status=error&msg=" . urlencode(mysqli_error($conn)));
        exit();
    }
}

if (isset($_POST['action_update_image'])) {
    $id         = intval($_POST['id']);
    $product_id = intval($_POST['product_id']);
    $is_primary = isset($_POST['is_primary']) ? intval($_POST['is_primary']) : 0;

    $checkQuery  = mysqli_query($conn, "SELECT image FROM product_images WHERE id = $id");
    $currentData = mysqli_fetch_assoc($checkQuery);
    $oldImage    = $currentData['image'];
    $imageName   = $oldImage;

    if (isset($_FILES['image']) && !empty($_FILES['image']['name'])) {
        $fileExt      = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $newImageName = "gal_" . time() . "_" . uniqid() . "." . $fileExt;
        
        if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp'])) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $newImageName)) {
                if (!empty($oldImage) && file_exists($uploadDir . $oldImage)) {
                    unlink($uploadDir . $oldImage);
                }
                $imageName = $newImageName;
            }
        }
    }

    if ($is_primary === 1) {
        mysqli_query($conn, "UPDATE product_images SET is_primary = 0 WHERE product_id = $product_id");
    }

    $query = "UPDATE product_images SET product_id = $product_id, image = '$imageName', is_primary = $is_primary WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        $productQuery = mysqli_query($conn, "SELECT name FROM products WHERE id = $product_id");
        $productData  = mysqli_fetch_assoc($productQuery);
        $productName  = $productData ? $productData['name'] : "ID " . $product_id;

        createNotification('admin', (int)$_SESSION['user_id'], 'Gambar Produk Diperbarui', "Pengaturan gambar untuk produk '$productName' (ID: $id) berhasil diperbarui", 'product_images.php');
        header("Location: product_images.php?status=success_update");
        exit();
    } else {
        header("Location: product_images.php?status=error&msg=" . urlencode(mysqli_error($conn)));
        exit();
    }
}

if (isset($_GET['action_delete'])) {
    $id = intval($_GET['action_delete']);

    $checkQuery  = mysqli_query($conn, "SELECT product_id, image FROM product_images WHERE id = $id");
    $currentData = mysqli_fetch_assoc($checkQuery);
    
    $savedProductName = "ID " . $id;
    if ($currentData) {
        $product_id = intval($currentData['product_id']);
        $productQuery = mysqli_query($conn, "SELECT name FROM products WHERE id = $product_id");
        $productData  = mysqli_fetch_assoc($productQuery);
        if ($productData) {
            $savedProductName = $productData['name'];
        }

        $oldImage = $currentData['image'];
        if (!empty($oldImage) && file_exists($uploadDir . $oldImage)) {
            unlink($uploadDir . $oldImage);
        }
    }

    $query = "DELETE FROM product_images WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        createNotification('admin', (int)$_SESSION['user_id'], 'Gambar Produk Dihapus', "Gambar galeri untuk produk '$savedProductName' berhasil dihapus dari sistem", 'product_images.php');
        header("Location: product_images.php?status=success_delete");
        exit();
    } else {
        header("Location: product_images.php?status=error&msg=" . urlencode(mysqli_error($conn)));
        exit();
    }
}

$listProducts = [];
$qProd = mysqli_query($conn, "SELECT id, name FROM products WHERE deleted_at IS NULL ORDER BY name ASC");
while ($r = mysqli_fetch_assoc($qProd)) { $listProducts[] = $r; }

$listGallery = [];
$sql = "SELECT pi.*, p.name AS product_name 
        FROM product_images pi
        LEFT JOIN products p ON pi.product_id = p.id
        ORDER BY pi.id DESC";

$fetchQuery = mysqli_query($conn, $sql);
if ($fetchQuery) {
    while ($row = mysqli_fetch_assoc($fetchQuery)) {
        $listGallery[] = $row;
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
    <!-- Container tabel dengan tema gelap transparan -->
    <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
        
        <!-- HEADER TABEL & TOMBOL TAMBAH GAMBAR -->
    <!-- CLEAN URL PARAM AFTER PAGE LOAD agar notifikasi tidak muncul saat refresh -->
    <script>
    if (window.location.search.includes('status=')) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    </script>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
            <div>
                <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Galeri Gambar Produk</h2>
            </div>
            <div>
                <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalGallery" onclick="openTambahGallery()">
                    <i class="bi bi-plus-circle"></i> Tambah Gambar
                </button>
            </div>
        </div>

        <!-- NOTIFIKASI STATUS OPERASI CRUD -->
        <?php if (!empty($status)): ?>
            <div class="alert <?= strpos($status, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
                <strong>
                    <?php 
                    if ($status == 'success_insert') echo "Gambar galeri berhasil ditambahkan!";
                    elseif ($status == 'success_update') echo "Data galeri berhasil diperbarui!";
                    elseif ($status == 'success_delete') echo "Gambar galeri berhasil dihapus!";
                    else echo "Operasi gagal: " . htmlspecialchars($msg);
                    ?>
                </strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- STRUKTUR TABEL LIST DATA GALLERY (PAS SATU LAYAR FULL - NO SCROLL) -->
        <div id="galleryTableContainer" class="table-responsive rounded-3" style="border: none !important; background: transparent !important; box-shadow: none !important; -webkit-box-shadow: none !important; overflow-x: hidden !important;">
            <table class="table table-hover align-middle mb-0 text-white" style="background: transparent !important; color: #e5e7eb !important; width: 100% !important; table-layout: auto !important; border-collapse: collapse !important;">
                <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
                    <tr>
                        <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 80px;">ID</th>
                        <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 120px;">Gambar</th>
                        <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Nama Produk</th>
                        <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 180px;">Status Utama</th>
                        <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 130px;">Aksi</th>
                    </tr>
                </thead>
                <tbody style="background: transparent !important;">
                    <?php if (!empty($listGallery)): foreach ($listGallery as $row): ?>
                        <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                            <!-- Kolom ID -->
                            <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= $row['id'] ?></td>
                            
                            <!-- Kolom Gambar -->
                            <td class="text-center" style="background: transparent !important; border: none !important;">
                                <?php if (!empty($row['image'])): ?>
                                    <img src="uploads/products/gallery/<?= htmlspecialchars($row['image']) ?>" alt="Gambar" class="rounded-2" style="max-height: 50px; max-width: 50px; object-fit: cover; border: 1px solid rgba(148, 163, 184, 0.2);">
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 0.75rem;">No Image</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Kolom Nama Produk -->
                            <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;">
                                <div class="text-truncate" title="<?= htmlspecialchars($row['product_name']) ?>"><?= htmlspecialchars($row['product_name'] ?: 'Produk Telah Dihapus') ?></div>
                            </td>
                            
                            <!-- Kolom Status Utama (is_primary) -->
                            <td class="text-center" style="background: transparent !important; border: none !important;">
                                <?php if ((int)$row['is_primary'] === 1): ?>
                                    <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem;"><i class="bi bi-star-fill me-1"></i>Utama (1)</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-muted border border-secondary border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.75rem;">Biasa (0)</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Kolom Aksi Menu CRUD -->
                            <td class="text-center" style="background: transparent !important; border: none !important;">
                                <div class="d-flex justify-content-center gap-1">
                                    <!-- Tombol Edit -->
                                    <button type="button" class="btn btn-sm btn-outline-success border-0 rounded-2 text-success" title="Edit Data" 
                                            onclick='openEditGallery(<?= json_encode($row) ?>)'>
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    
                                    <!-- Tombol Hapus Terhubung ke Modal Konfirmasi -->
                                    <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Hapus Gambar"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalConfirmDeleteGallery" 
                                            onclick="document.getElementById('delete_gallery_product_name').innerText = '<?php echo addslashes($row['product_name']); ?>'; document.getElementById('btnConfirmDeleteGalleryAction').setAttribute('href', 'product_images.php?action_delete=<?= $row['id'] ?>')">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <!-- State Tampilan jika data kosong -->
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted shadow-none" style="background: transparent !important; border: none !important;">
                                <i class="bi bi-images d-block mb-2" style="font-size: 2rem; color: rgba(148, 163, 184, 0.4);"></i>
                                Belum ada foto galeri produk yang diunggah.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</main>

<div class="modal fade" id="modalGallery" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="product_images.php" method="POST" id="formGallery" enctype="multipart/form-data" class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(10px); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-header border-0 pb-0" style="padding: 1.5rem 1.5rem 0 1.5rem;">
                <h5 class="fw-bold text-white m-0" id="modalGalleryLabel">Tambah Gambar</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Input Hidden ID untuk Operasi Update -->
                <input type="hidden" name="id" id="gallery_id">
                
                <!-- Pilih Produk Relasi -->
                <div class="mb-3">
                    <label for="gallery_product_id" class="form-label small text-white-50 fw-medium">Pilih Produk</label>
                    <select name="product_id" id="gallery_product_id" class="form-select bg-dark text-white border-secondary rounded-3" style="background-color: rgba(2, 6, 23, 0.4) !important;" required>
                        <option value="">-- Pilih Produk --</option>
                        <?php foreach ($listProducts as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Input Berkas Gambar -->
                <div class="mb-3">
                    <label for="gallery_image_file" class="form-label small text-white-50 fw-medium">Berkas Gambar (JPG, PNG, WEBP)</label>
                    <input type="file" name="image" id="gallery_image_file" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none;">
                </div>
                
                <!-- Set Status Gambar Utama -->
                <div class="mb-2">
                    <label for="gallery_is_primary" class="form-label small text-white-50 fw-medium">Jadikan Gambar Utama?</label>
                    <select name="is_primary" id="gallery_is_primary" class="form-select bg-dark text-white border-secondary rounded-3" style="background-color: rgba(2, 6, 23, 0.4) !important;" required>
                        <option value="0">Biasa (0)</option>
                        <option value="1">Utama (1)</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer border-0 pt-0 d-flex gap-2 justify-content-end" style="padding: 0 1.5rem 1.5rem 1.5rem;">
                <button type="button" class="btn btn-sm btn-secondary rounded-3 px-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
                <button type="submit" name="action_add_image" id="btnSubmitGallery" class="btn btn-sm btn-success rounded-3 px-3 py-2 fw-medium">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalConfirmDeleteGallery" tabindex="-1" aria-labelledby="modalConfirmDeleteGalleryLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-bg-dark border-secondary" style="background-color: #111827 !important; border-color: #374151 !important; border-radius: 16px;">
      <div class="modal-header border-bottom border-secondary">
        <h5 class="modal-title text-white fw-bold d-flex align-items-center" id="modalConfirmDeleteGalleryLabel">
          <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Konfirmasi Hapus
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4">
        <div class="mb-3">
          <i class="bi bi-trash3-fill text-danger" style="font-size: 3.5rem;"></i>
        </div>
        <p class="text-white-50 fs-6 mb-1">Apakah Anda yakin ingin menghapus foto dari galeri produk ini?</p>
        <h6 id="delete_gallery_product_name" class="text-warning fw-bold mt-2"></h6>
      </div>
      <div class="modal-footer border-top border-secondary justify-content-center">
        <button type="button" class="btn btn-sm btn-secondary px-4 rounded-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
        <a id="btnConfirmDeleteGalleryAction" href="#" class="btn btn-sm btn-danger px-4 rounded-3 py-2 fw-bold d-inline-flex align-items-center justify-content-center">Oke, Hapus</a>
      </div>
    </div>
  </div>
</div>

<script>
    function openTambahGallery() {
        document.getElementById('modalGalleryLabel').innerText = 'Tambah Gambar Baru';
        document.getElementById('gallery_id').value = '';
        document.getElementById('gallery_product_id').value = '';
        document.getElementById('gallery_image_file').value = '';
        document.getElementById('gallery_image_file').setAttribute('required', 'required');
        document.getElementById('gallery_is_primary').value = '0';
        const btnSubmit = document.getElementById('btnSubmitGallery');
        if (btnSubmit) {
            btnSubmit.setAttribute('name', 'action_add_image');
            btnSubmit.className = "btn btn-sm btn-success rounded-3 px-3 py-2 fw-medium";
            btnSubmit.innerText = "Simpan Data";
        }
    }
    function openEditGallery(data) {
        if (data) {
            document.getElementById('modalGalleryLabel').innerText = 'Ubah Gambar Galeri';
            document.getElementById('gallery_id').value = data.id;
            document.getElementById('gallery_product_id').value = data.product_id;
            document.getElementById('gallery_image_file').value = '';
            document.getElementById('gallery_image_file').removeAttribute('required');
            document.getElementById('gallery_is_primary').value = data.is_primary;
            const btnSubmit = document.getElementById('btnSubmitGallery');
            if (btnSubmit) {
                btnSubmit.setAttribute('name', 'action_update_image');
                btnSubmit.className = "btn btn-sm btn-warning text-dark rounded-3 px-3 py-2 fw-semibold";
                btnSubmit.innerText = "Simpan Perubahan";
            }
            const modalEl = document.getElementById('modalGallery');
            if (modalEl) {
                const instance = bootstrap.Modal.getOrCreateInstance(modalEl);
                instance.show();
            }
        }
    }
    function bersihkanMacet() {
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }
    document.addEventListener('hidden.bs.modal', bersihkanMacet);
    setInterval(() => {
        let adaModalAktif = false;
        document.querySelectorAll('.modal').forEach(m => {
            if (m.classList.contains('show')) adaModalAktif = true;
        });
        if (!adaModalAktif && document.querySelector('.modal-backdrop')) {
            bersihkanMacet();
        }
    }, 300);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

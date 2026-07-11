<?php
// product_reviews.php
include "db.php"; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteksi Halaman: Jika sesi user_id kosong, tendang kembali ke login.php
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$crudError = '';
$crudSuccess = '';

// ==========================================
// 1. PROSES CRUD: MENAMPILKAN DATA (READ)
// ==========================================
$reviews = [];
try {
    $query = "SELECT pr.*, p.name AS product_name 
              FROM product_reviews pr 
              JOIN products p ON pr.product_id = p.id 
              ORDER BY pr.id DESC";
    $result = mysqli_query($conn, $query);
    if ($result) {
        $reviews = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
} catch (Throwable $e) {
    $crudError = "Gagal memuat ulasan: " . $e->getMessage();
}

// ==========================================
// 2. PROSES CRUD: TAMBAH ULASAN BARU (CREATE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create'])) {
    $product_id         = (int)($_POST['product_id'] ?? 0);
    $patient_session_id = (int)($_POST['patient_session_id'] ?? 0);
    $rating             = (int)($_POST['rating'] ?? 5);
    $review             = trim($_POST['review'] ?? '');

    if ($product_id > 0 && $patient_session_id > 0 && !empty($review)) {
        try {
            $stmt = $conn->prepare("INSERT INTO product_reviews (product_id, patient_session_id, rating, review) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $product_id, $patient_session_id, $rating, $review);
            
            if ($stmt->execute()) {
                $crudSuccess = "Ulasan baru berhasil ditambahkan!";
                header("Location: product_reviews.php?status=success_add");
                exit;
            } else {
                $crudError = "Gagal menyimpan ulasan baru ke database.";
            }
            $stmt->close();
        } catch (Throwable $e) {
            $crudError = "Kesalahan sistem: " . $e->getMessage();
        }
    } else {
        $crudError = "Semua bidang formulir ulasan wajib diisi!";
    }
}

// ==========================================
// 3. PROSES CRUD: SIMPAN PERUBAHAN (UPDATE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update'])) {
    $targetId           = (int)($_POST['edit_id'] ?? 0);
    $product_id         = (int)($_POST['product_id'] ?? 0);
    $patient_session_id = (int)($_POST['patient_session_id'] ?? 0);
    $rating             = (int)($_POST['rating'] ?? 5);
    $review             = trim($_POST['review'] ?? '');

    if ($targetId > 0 && $product_id > 0 && $patient_session_id > 0 && !empty($review)) {
        try {
            $updateStmt = $conn->prepare("UPDATE product_reviews SET product_id = ?, patient_session_id = ?, rating = ?, review = ? WHERE id = ?");
            $updateStmt->bind_param("iiisi", $product_id, $patient_session_id, $rating, $review, $targetId);
            
            if ($updateStmt->execute()) {
                $crudSuccess = "Data ulasan berhasil diperbarui!";
                header("Location: product_reviews.php?status=success_edit");
                exit;
            } else {
                $crudError = "Gagal memperbarui data ulasan di database.";
            }
            $updateStmt->close();
        } catch (Throwable $e) {
            $crudError = "Kesalahan sistem saat mengubah data: " . $e->getMessage();
        }
    } else {
        $crudError = "Input data perubahan ulasan tidak valid.";
    }
}

// ==========================================
// 4. PROSES CRUD: HAPUS DATA (DELETE)
// ==========================================
if (isset($_GET['action_delete'])) {
    $deleteId = (int)$_GET['action_delete'];

    if ($deleteId > 0) {
        try {
            $deleteStmt = $conn->prepare("DELETE FROM product_reviews WHERE id = ?");
            $deleteStmt->bind_param("i", $deleteId);
            
            if ($deleteStmt->execute()) {
                $crudSuccess = "Ulasan berhasil dihapus dari sistem!";
                header("Location: product_reviews.php?status=success_delete");
                exit;
            } else {
                $crudError = "Gagal menghapus data dari database.";
            }
            $deleteStmt->close();
        } catch (Throwable $e) {
            $crudError = "Kesalahan sistem saat menghapus data: " . $e->getMessage();
        }
    }
}

// Ambil list produk untuk dropdown form input ulasan
$products = [];
try {
    $product_result = mysqli_query($conn, "SELECT id, name FROM products ORDER BY name ASC");
    if ($product_result) {
        $products = mysqli_fetch_all($product_result, MYSQLI_ASSOC);
    }
} catch (Throwable $e) {
    $products = [];
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
    .modal-dialog { max-width: 800px !important; }
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
  <div class="container-fluid rounded-4 p-4 text-white" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
    
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
      <div>
        <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Ulasan & Testimoni Produk</h2>
      </div>
      <div>
        <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalAddReview" onclick="openTambahReview()">
          <i class="bi bi-star-fill"></i> Tambah Ulasan
        </button>
      </div>
    </div>

    <?php if (!empty($crudSuccess)): ?>
      <div class="alert alert-success border-0 rounded-3 mb-4" role="alert" style="background: rgba(34, 197, 94, 0.12) !important; color: #86efac !important;">
        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($crudSuccess) ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($crudError)): ?>
      <div class="alert alert-danger border-0 rounded-3 mb-4" role="alert" style="background: rgba(239, 68, 68, 0.12) !important; color: #fecaca !important;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($crudError) ?>
      </div>
    <?php endif; ?>

    <div class="table-responsive border rounded-3" style="border-color: rgba(148, 163, 184, 0.15) !important; background: transparent !important;">
      <table class="table table-hover align-middle mb-0 text-white" style="background: transparent !important; color: #e5e7eb !important;">
        <thead class="text-uppercase" style="font-size: 0.85rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
            <tr>
            <th class="py-3 px-3 text-center text-white" style="width: 80px; background: transparent !important;">ID</th>
            <th class="py-3 text-white" style="background: transparent !important;">Nama Produk</th>
            <th class="py-3 text-center text-white" style="width: 150px; background: transparent !important;">Rating</th>
            <th class="py-3 text-white" style="background: transparent !important;">Isi Ulasan</th>
            <th class="py-3 text-center text-white" style="width: 120px; background: transparent !important;">Aksi</th>
            </tr>
        </thead>
        <tbody style="background: transparent !important;">
          <?php if (!empty($reviews)): foreach ($reviews as $row): ?>
              <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.9rem;">
                <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important;"><?= $row['id'] ?></td>
                <td class="fw-semibold text-white" style="background: transparent !important;"><?= htmlspecialchars($row['product_name'] ?? 'Produk Dihapus') ?></td>
                <td class="text-center" style="background: transparent !important;">
                    <span class="text-warning fw-bold">
                        <?php 
                        $stars = (int)$row['rating'];
                        for ($i = 1; $i <= 5; $i++) {
                            echo $i <= $stars ? '★' : '☆';
                        }
                        ?>
                    </span>
                </td>
                <td class="text-white-50" style="background: transparent !important; max-width: 300px;"><div class="text-truncate" title="<?= htmlspecialchars($row['review']) ?>"><?= htmlspecialchars($row['review'] ?? '-') ?></div></td>
                <td class="text-center" style="background: transparent !important;">
                  <div class="d-flex justify-content-center gap-1">
                    <button type="button" class="btn btn-sm btn-outline-success border-0 rounded-2" title="Edit Ulasan" onclick="openEditReview(<?= $row['id'] ?>, <?= $row['product_id'] ?>, <?= $row['patient_session_id'] ?>, <?= $row['rating'] ?>, '<?= addslashes(htmlspecialchars($row['review'], ENT_QUOTES, 'UTF-8')) ?>')">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Hapus Ulasan" data-bs-toggle="modal" data-bs-target="#modalDeleteReview" onclick="document.getElementById('delete_review_display_id').innerText = '<?= $row['id'] ?>'; document.getElementById('btn_confirm_delete_review').setAttribute('href', 'product_reviews.php?action_delete=<?= $row['id'] ?>')">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
          <?php endforeach; else: ?>
              <tr><td colspan="5" class="text-center py-5 border-0" style="color: #94a3b8 !important; background: transparent !important;">Belum ada data ulasan terdaftar di database.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</main>

<!-- =========================================================================
     MODAL INPUT DATA (TAMBAH / EDIT ULASAN)
     ========================================================================= -->
<div class="modal fade" id="modalReview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="product_reviews.php" method="POST" id="formReview" class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(10px); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-header border-0 pb-0" style="padding: 1.5rem 1.5rem 0 1.5rem;">
                <h5 class="fw-bold text-white m-0" id="modalReviewLabel">Tambah Ulasan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Input Hidden ID untuk Operasi Update -->
                <input type="hidden" name="edit_id" id="review_edit_id">
                
                <!-- Pilih Produk -->
                <div class="mb-3">
                    <label for="review_product_id" class="form-label small text-white-50 fw-medium">Pilih Produk</label>
                    <select name="product_id" id="review_product_id" class="form-select bg-dark text-white border-secondary rounded-3" style="background-color: rgba(2, 6, 23, 0.4) !important;" required>
                        <option value="">-- Pilih Produk --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Input ID Sesi Pasien (Sementara disamakan dengan user_id pelaksana demi kestabilan kueri) -->
                <div class="mb-3">
                    <label for="review_patient_session_id" class="form-label small text-white-50 fw-medium">ID Sesi Pasien</label>
                    <input type="number" name="patient_session_id" id="review_patient_session_id" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none;" placeholder="Contoh: 1" required>
                </div>
                
                <!-- Pilihan Rating Bintang (1 - 5) -->
                <div class="mb-3">
                    <label for="review_rating" class="form-label small text-white-50 fw-medium">Rating Skor Bintang</label>
                    <select name="rating" id="review_rating" class="form-select bg-dark text-white border-secondary rounded-3" style="background-color: rgba(2, 6, 23, 0.4) !important;" required>
                        <option value="5">★ ★ ★ ★ ★ (5 - Sangat Puas)</option>
                        <option value="4">★ ★ ★ ★ ☆ (4 - Puas)</option>
                        <option value="3">★ ★ ★ ☆ ☆ (3 - Cukup Baik)</option>
                        <option value="2">★ ★ ☆ ☆ ☆ (2 - Kurang Puas)</option>
                        <option value="1">★ ☆ ☆ ☆ ☆ (1 - Buruk)</option>
                    </select>
                </div>

                <!-- Input Isi Teks Ulasan -->
                <div class="mb-2">
                    <label for="review_text" class="form-label small text-white-50 fw-medium">Isi Teks Review / Ulasan</label>
                    <textarea name="review" id="review_text" rows="3" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none; resize: none;" placeholder="Ketik ulasan rasa makanan atau pelayanan di sini..." required></textarea>
                </div>
            </div>
            
            <div class="modal-footer border-0 pt-0 d-flex gap-2 justify-content-end" style="padding: 0 1.5rem 1.5rem 1.5rem;">
                <button type="button" class="btn btn-sm btn-secondary rounded-3 px-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
                <button type="submit" name="action_create" id="btnSubmitReview" class="btn btn-sm btn-success rounded-3 px-3 py-2 fw-medium">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL KONFIRMASI HAPUS DATA
     ========================================================================= -->
<div class="modal fade" id="modalDeleteReview" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-bg-dark border-secondary" style="background-color: #111827 !important; border-color: #374151 !important; border-radius: 16px;">
      <div class="modal-header border-bottom border-secondary">
        <h5 class="modal-title text-white fw-bold d-flex align-items-center">
          <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Konfirmasi Hapus
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4">
        <div class="mb-3">
          <i class="bi bi-trash3-fill text-danger" style="font-size: 3.5rem;"></i>
        </div>
        <p class="text-white-50 fs-6 mb-1">Apakah Anda yakin ingin menghapus data rekaman testimoni ulasan produk ini?</p>
        <h6 class="text-warning fw-bold mt-2">Ulasan ID: <span id="delete_review_display_id"></span></h6>
      </div>
      <div class="modal-footer border-top border-secondary justify-content-center">
        <button type="button" class="btn btn-sm btn-secondary px-4 rounded-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
        <a id="btn_confirm_delete_review" href="#" class="btn btn-sm btn-danger px-4 rounded-3 py-2 fw-bold d-inline-flex align-items-center justify-content-center">Oke, Hapus</a>
      </div>
    </div>
  </div>
</div>

<script>
    function openTambahReview() {
        const form = document.getElementById('formReview');
        const submitBtn = document.getElementById('btnSubmitReview');
        
        if (form) form.removeAttribute('action'); 
        if (submitBtn) {
            submitBtn.name = "action_create";
            submitBtn.value = "1";
        }
        
        document.getElementById('review_edit_id').value = "";
        document.getElementById('review_product_id').value = "";
        document.getElementById('review_patient_session_id').value = "";
        document.getElementById('review_rating').value = "5";
        document.getElementById('review_text').value = "";
        document.getElementById('modalReviewLabel').innerText = "Tambah Ulasan";
        
        const modalElement = document.getElementById('modalReview');
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }

    function openEditReview(id, productId, patientSessionId, rating, reviewText) {
        const form = document.getElementById('formReview');
        const submitBtn = document.getElementById('btnSubmitReview');
        
        if (form) form.removeAttribute('action'); 
        if (submitBtn) {
            submitBtn.name = "action_update";
            submitBtn.value = "1";
        }
        
        document.getElementById('review_edit_id').value = id;
        document.getElementById('review_product_id').value = productId;
        document.getElementById('review_patient_session_id').value = patientSessionId;
        document.getElementById('review_rating').value = rating;
        document.getElementById('review_text').value = reviewText;
        document.getElementById('modalReviewLabel').innerText = "Ubah Detail Ulasan";
        
        const modalElement = document.getElementById('modalReview');
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

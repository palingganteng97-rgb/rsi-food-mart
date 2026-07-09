<?php
// ====================================================================
// FILE: profile.php (STRUKTUR PHP + BASE URL DINAMIS UNTUK LAYAR HP)
// ====================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Cek session login aktif
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php'; 
$session_user_id = mysqli_real_escape_string($conn, $_SESSION['user_id']);

// ====================================================================
// PROSES SIMPAN EDIT PROFIL MANDIRI DARI HP
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_profile'])) {
    $upName  = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
    $upEmail = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $upPhone = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    
    // Ambil data foto lama untuk cadangan
    $check_old = mysqli_query($conn, "SELECT photo FROM users WHERE id = '$session_user_id'");
    $old_data  = mysqli_fetch_assoc($check_old);
    $finalPhotoName = $old_data['photo'] ?? '';

    // Proses upload foto baru jika ada file yang dipilih
    if (!empty($_FILES['photo']['name'])) {
        $targetDir = "uploads/";
        if (!file_exists($targetDir)) { mkdir($targetDir, 0777, true); }

        $fileExtension = strtolower(pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION));
        $newFileName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
        $targetFilePath = $targetDir . $newFileName;

        if (in_array($fileExtension, ['jpg', 'jpeg', 'png'])) {
            if ($_FILES["photo"]["size"] <= 2 * 1024 * 1024) {
                if (move_uploaded_file($_FILES["photo"]["tmp_name"], $targetFilePath)) {
                    // Hapus fisik foto lama dari folder uploads agar tidak jadi sampah berkas
                    $clean_old = str_replace('uploads/', '', $finalPhotoName);
                    if (!empty($clean_old) && file_exists("uploads/" . $clean_old)) {
                        @unlink("uploads/" . $clean_old);
                    }
                    // Simpan nama filenya saja tanpa embel-embel folder ke database
                    $finalPhotoName = $newFileName;
                }
            }
        }
    }

    // Eksekusi Update ke tabel users berdasarkan ID diri sendiri yang sedang login
    $updateQuery = "UPDATE users SET name = '$upName', email = '$upEmail', phone = '$upPhone', photo = '$finalPhotoName', updated_at = NOW() WHERE id = '$session_user_id'";
    
    if (mysqli_query($conn, $updateQuery)) {
        echo "<script>alert('Profil Anda berhasil diperbarui!'); window.location='profile.php';</script>";
        exit();
    }
}

// Ambil data profil terbaru untuk dirender ke halaman
$query_pribadi = "SELECT users.*, roles.name AS role_name FROM users LEFT JOIN roles ON users.role_id = roles.id WHERE users.id = '$session_user_id' AND users.deleted_at IS NULL LIMIT 1";
$result_pribadi = mysqli_query($conn, $query_pribadi);
$data_pribadi   = mysqli_fetch_assoc($result_pribadi);

if (!$data_pribadi) {
    echo "<script>alert('Data akun tidak ditemukan!'); window.location='login.php';</script>";
    exit();
}

$id         = $data_pribadi['id'];
$role_id    = $data_pribadi['role_id'];
$tenant_id  = $data_pribadi['tenant_id'] ?? 'NULL';
$name       = $data_pribadi['name'] ?? 'Pengguna';
$username   = $data_pribadi['username'] ?? 'username';
$email      = $data_pribadi['email'] ?? '-';
$phone      = $data_pribadi['phone'] ?? '-';
$photo      = trim($data_pribadi['photo'] ?? '');
$status     = $data_pribadi['status']; 
$last_login = $data_pribadi['last_login'] ?? 'NULL';
$created_at = $data_pribadi['created_at'] ?? 'NULL';
$updated_at = $data_pribadi['updated_at'] ?? 'NULL';
$role_name  = $data_pribadi['role_name'] ?? 'Tidak Diketahui';

// PERBAIKAN UTAMA: Membuat Base URL membaca IP peladen secara otomatis dan dinamis
$httpProtocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
$currentHost  = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$baseUrl      = $httpProtocol . $currentHost . "/";

// ====================================================================
// SOLUSI ABSOLUT SINKRONISASI PATH DIREKTORI XAMPP WINDOWS
// ====================================================================
if (!empty($photo)) {
    // 1. Bersihkan string dari folder 'uploads/' jika tidak sengaja tersimpan ganda
    $cleanPhoto = str_replace('uploads/', '', $photo);
    
    // 2. Tentukan default avatar cadangan dengan /api/?name= yang utuh
    $finalPath  = 'https://ui-avatars.com' . urlencode($name) . '&background=0d6efd&color=fff&size=150&bold=true';
    
    // 3. Tambahkan titik dan garis miring (./) agar PHP mendeteksi folder uploads secara absolut dari root proyek
    if (file_exists('./uploads/' . $cleanPhoto)) {
        $finalPath = $baseUrl . 'uploads/' . $cleanPhoto;
    }
} else {
    // Jalur jika data foto profil di database kosong
    $finalPath  = 'https://ui-avatars.com' . urlencode($name) . '&background=0d6efd&color=fff&size=150&bold=true';
}
?>

<!Doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Etalase Menu - RSI Food &amp; Mart</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

<style>
    :root { --bg:#0f172a; --text:#e5e7eb; --muted:#94a3b8; --green:#22c55e; }
    
    /* PERBAIKAN: Mengunci scrollbar halaman utama KECUALI jika file yang aktif adalah profile.php */
    body { background:var(--bg) !important; color:var(--text); overflow-y: hidden !important; } 
    
    /* Mengembalikan hak scroll vertikal murni khusus untuk halaman profile.php */
    body.profile-page, 
    body[class*="profile"] { 
        overflow-y: auto !important; 
    }

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
    
    /* Penyelarasan Tabel & Sembunyikan Scrollbar Horizontal */
    #dragScrollUserContainer::-webkit-scrollbar, #dragScrollContainer::-webkit-scrollbar, .drag-scroll-container::-webkit-scrollbar { display: none !important; }
    #dragScrollUserContainer, #dragScrollContainer, .drag-scroll-container { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow-x: auto !important; cursor: grab !important; border: none !important; box-shadow: none !important; -webkit-box-shadow: none !important; }
    #dragScrollUserContainer:active, #dragScrollContainer:active, .drag-scroll-container:active { cursor: grabbing !important; }
    #dragScrollUserContainer table, #dragScrollContainer table, .drag-scroll-container table { border-collapse: collapse !important; border: none !important; }
    #dragScrollUserContainer table th, #dragScrollUserContainer table td, #dragScrollContainer table th, #dragScrollContainer table td, .drag-scroll-container table th, .drag-scroll-container table td { border-left: none !important; border-right: none !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; }
    .text-white-element { -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }
    
    /* PERBAIKAN: MODAL TENGAH MELEBAR BERSIH TANPA SCROLLBAR FISIK BROWSER */
    .modal, .modal-open { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow-y: hidden !important; }
    .modal::-webkit-scrollbar { display: none !important; }
    
    /* MENGUBAH overflow-y JADI auto AGAR KONTEKS SCROLLABLE BOOTSTRAP BEKERJA DI DALAM MODAL-BODY */
    .modal-body { -ms-overflow-style: none !important; scrollbar-width: none !important; overflow-y: auto !important; }
    .modal-body::-webkit-scrollbar { display: none !important; }

    @media (min-width: 992px) { main.content-shift { margin-left: 280px; } .bottom-nav { display:none; } }
</style>

</head>
<body>
  <?php require __DIR__ . '/sidebar.php'; ?>


<!-- ==================================================================== -->
<!-- TAHAP 1: PEMBUNGKUS LUAR UTAMA (MAIN CONTAINER TRANSPARAN PENUH)     -->
<!-- ==================================================================== -->
<main class="p-3">
    <!-- KARTU ATAS: FOTO PROFILE, NAMA LENGKAP & TOMBOL INTERAKSI -->
    <div class="card-profile-top p-4 text-center mb-3 text-white rounded-4" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
        <!-- PERBAIKAN: Menambahkan /api/?name= pada onerror agar avatar inisial nama muncul sukses -->
        <img src="<?= $finalPath ?>" 
             onerror="this.onerror=null; this.src='https://ui-avatars.com<?= urlencode($name) ?>&background=0d6efd&color=fff&size=150&bold=true';" 
             class="rounded-circle mb-3 border border-2 border-success" 
             style="width: 110px; height: 110px; object-fit: cover;" draggable="false">
        
        <h4 class="fw-bold mb-1 text-white" style="font-size: 1.4rem;"><?= htmlspecialchars($name) ?></h4>
        <p class="text-white-50 small mb-1" style="font-size: 0.85rem;"><?= htmlspecialchars($role_name) ?></p>
        <p class="small mb-3" style="font-size: 0.82rem; color: #94a3b8 !important;">@<?= htmlspecialchars($username) ?></p>
        
<!-- GANTI DIV TOMBOL FOLLOW & MESSAGE ANDA DENGAN STRUKTUR INI -->
<div class="d-flex justify-content-center gap-2">
    <button type="button" class="btn btn-success px-4 py-1.5 rounded-3 fw-medium" data-bs-toggle="modal" data-bs-target="#modalEditProfilSaya">
        <i class="bi bi-pencil-square me-1"></i> Edit Profil
    </button>
    <button type="button" class="btn btn-outline-secondary px-3 py-1.5 rounded-3 fw-medium text-white border-secondary border-opacity-50" style="background: rgba(255,255,255,0.05);" onclick="window.location='mailto:<?= $email ?>'">
        <i class="bi bi-envelope me-1"></i> Message
    </button>
</div>

    </div>
    
    <!-- KARTU BAWAH: RINCIAN LIST GRUP DATA HEIDISQL -->
    <div class="card-profile-bottom rounded-4 overflow-hidden" style="background: rgba(15, 23, 42, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.25);">
        <div class="list-group list-group-flush" style="background: transparent !important;">
            
            <div class="list-group-item d-flex align-items-center justify-content-between px-4 py-3" style="background: transparent !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; font-size: 0.9rem;">
                <div class="item-label d-flex align-items-center gap-2 fw-medium text-white"><i class="bi bi-hash fs-5 text-success"></i> Website / ID</div>
                <div class="item-value fw-mono text-end" style="color: #94a3b8 !important; font-size: 0.88rem;">ID: <?= $id ?></div>
            </div>
            
            <div class="list-group-item d-flex align-items-center justify-content-between px-4 py-3" style="background: transparent !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; font-size: 0.9rem;">
                <div class="item-label d-flex align-items-center gap-2 fw-medium text-white"><i class="bi bi-shield-check fs-5 text-success"></i> Role ID</div>
                <div class="item-value text-end" style="color: #94a3b8 !important; font-size: 0.88rem;"><?= $role_id ?> (<span class="text-success fw-medium"><?= htmlspecialchars($role_name) ?></span>)</div>
            </div>
            
            <div class="list-group-item d-flex align-items-center justify-content-between px-4 py-3" style="background: transparent !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; font-size: 0.9rem;">
                <div class="item-label d-flex align-items-center gap-2 fw-medium text-white"><i class="bi bi-building fs-5 text-success"></i> Tenant ID</div>
                <div class="item-value fw-mono text-end" style="color: #94a3b8 !important; font-size: 0.88rem;"><?= htmlspecialchars($tenant_id) ?></div>
            </div>
            
            <div class="list-group-item d-flex align-items-center justify-content-between px-4 py-3" style="background: transparent !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; font-size: 0.9rem;">
                <div class="item-label d-flex align-items-center gap-2 fw-medium text-white"><i class="bi bi-envelope fs-5 text-success"></i> Email</div>
                <div class="item-value text-white-50 text-truncate text-end" style="max-width: 60%; font-size: 0.88rem;"><?= htmlspecialchars($email) ?></div>
            </div>
            
            <div class="list-group-item d-flex align-items-center justify-content-between px-4 py-3" style="background: transparent !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; font-size: 0.9rem;">
                <div class="item-label d-flex align-items-center gap-2 fw-medium text-white"><i class="bi bi-telephone fs-5 text-success"></i> Phone</div>
                <div class="item-value text-white-50 text-end" style="font-size: 0.88rem;"><?= htmlspecialchars($phone) ?></div>
            </div>
            
            <div class="list-group-item d-flex align-items-center justify-content-between px-4 py-3" style="background: transparent !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; font-size: 0.9rem;">
                <div class="item-label d-flex align-items-center gap-2 fw-medium text-white"><i class="bi bi-toggle-on fs-5 text-success"></i> Status</div>
                <div class="item-value text-end">
                    <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.72rem;"><?= ((int)$status === 1) ? '1 (Aktif)' : '0 (Nonaktif)' ?></span>
                </div>
            </div>
            
            <div class="list-group-item d-flex align-items-center justify-content-between px-4 py-3" style="background: transparent !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; font-size: 0.9rem;">
                <div class="item-label d-flex align-items-center gap-2 fw-medium text-white"><i class="bi bi-box-arrow-in-right fs-5 text-success"></i> Last Login</div>
                <div class="item-value fw-mono small" style="color: #94a3b8 !important; font-size: 0.85rem;"><?= htmlspecialchars($last_login) ?></div>
            </div>
            
            <div class="list-group-item d-flex align-items-center justify-content-between px-4 py-3" style="background: transparent !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; font-size: 0.9rem;">
                <div class="item-label d-flex align-items-center gap-2 fw-medium text-white"><i class="bi bi-calendar-check fs-5 text-success"></i> Created At</div>
                <div class="item-value fw-mono small" style="color: #94a3b8 !important; font-size: 0.85rem;"><?= htmlspecialchars($created_at) ?></div>
            </div>
            
            <div class="list-group-item d-flex align-items-center justify-content-between px-4 py-3" style="background: transparent !important; border: none !important; font-size: 0.9rem;">
                <div class="item-label d-flex align-items-center gap-2 fw-medium text-white"><i class="bi bi-pencil-square fs-5 text-success"></i> Updated At</div>
                <div class="item-value fw-mono small" style="color: #94a3b8 !important; font-size: 0.85rem;"><?= htmlspecialchars($updated_at) ?></div>
            </div>
            
        </div>
    </div>
</main>

<!-- ==================================================================== -->
<!-- MODAL DIALOG: EDIT PROFIL MANDIRI KHUSUS PERANGKAT MOBILE            -->
<!-- ==================================================================== -->
<div class="modal fade" id="modalEditProfilSaya" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable px-3">
        <div class="modal-content text-white rounded-4 border" style="background: #1e293b; border-color: rgba(148, 163, 184, 0.2) !important; box-shadow: 0 10px 30px rgba(0,0,0,.5);">
            <div class="modal-header border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-white"><i class="bi bi-person-gear me-2 text-success"></i> Ubah Biodata Saya</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action_update_profile" value="1">
                <div class="modal-body" style="overflow-y: auto; max-height: calc(100vh - 220px);">
                    
                    <div class="text-center mb-3">
                        <img id="mobile_preview_photo" src="<?= $finalPath ?>" class="rounded-circle border border-secondary shadow-sm mb-2" style="width: 80px; height: 80px; object-fit: cover;">
                        <small class="d-block text-muted">Pratinjau Foto Profil Baru</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-white-50 small fw-medium mb-1">Nama Lengkap</label>
                        <input type="text" name="name" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" value="<?= htmlspecialchars($name) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-white-50 small fw-medium mb-1">Alamat Email</label>
                        <input type="email" name="email" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" value="<?= htmlspecialchars($email) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-white-50 small fw-medium mb-1">Nomor Telepon / HP</label>
                        <input type="text" name="phone" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" value="<?= htmlspecialchars($phone) ?>">
                    </div>

                    <div class="mb-2">
                        <label class="form-label text-white-50 small fw-medium mb-1">Ganti Foto Profil (Opsional)</label>
                        <input type="file" name="photo" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3 py-2" accept="image/*" onchange="document.getElementById('mobile_preview_photo').src = window.URL.createObjectURL(this.files[0])">
                    </div>

                </div>
                <div class="modal-footer border-secondary border-opacity-25 d-flex justify-content-end gap-2 p-3">
                    <button type="button" class="btn btn-secondary rounded-3 text-white-50 border-0" data-bs-dismiss="modal" style="background: rgba(148,163,184, 0.1);">Batal</button>
                    <button type="submit" class="btn btn-success rounded-3 px-4 fw-medium">Simpan Profil</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================================================================== -->
<!-- TAHAP 5: LOGIKA JAVASCRIPT HALAMAN PROFIL TRANSPARAN SELULER         -->
<!-- ==================================================================== -->
<!-- ==================================================================== -->
<!-- TAHAP 5: PERBAIKAN LOGIKA JAVASCRIPT IMAGE FAILSAFE & SCROLL PROFILE -->
<!-- ==================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Memaksa halaman profile melepas penguncian scroll bar dari file dashboard utama
    if (document.body) {
        document.body.classList.add('profile-page');
    }

    // 2. Logika interaktif fungsionalitas Tombol Follow Pasien
    const btnFollow = document.querySelector('.btn-follow');
    if (btnFollow) {
        let isFollowing = false;
        btnFollow.addEventListener('click', function(e) {
            e.preventDefault();
            isFollowing = !isFollowing;
            if (isFollowing) {
                this.innerText = "Following";
                this.classList.remove('btn-success');
                this.classList.add('btn-secondary');
                this.style.setProperty('background-color', 'rgba(148, 163, 184, 0.25)', 'important');
            } else {
                this.innerText = "Follow";
                this.classList.remove('btn-secondary');
                this.classList.add('btn-success');
                this.style.removeProperty('background-color');
            }
        });
    }

    // 3. PERBAIKAN UTAMA: Mengamankan fungsi error agar tidak memotong paksa proses pemuatan foto asli
    const profileImg = document.querySelector('.card-profile-top img');
    if (profileImg) {
        profileImg.addEventListener('error', function() {
            // Skrip hanya akan memicu generator avatar tulisan jika file fisik di uploads benar-benar rusak/hilang
            this.onerror = null;
            const userName = "<?= urlencode($name ?? 'User') ?>";
            this.src = 'https://ui-avatars.com' + userName + '&background=0d6efd&color=fff&size=150&bold=true';
        });
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

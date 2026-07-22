<?php
// tenants.php (Full Kode Logika Atas Baris Nomor 1)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';
include 'notification_helper.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$crudError = '';
$crudSuccess = '';

// Menangkap notifikasi status dari session agar aman saat di-refresh (F5)
if (isset($_SESSION['tenant_success'])) {
    $crudSuccess = $_SESSION['tenant_success'];
    unset($_SESSION['tenant_success']);
}
if (isset($_SESSION['tenant_error'])) {
    $crudError = $_SESSION['tenant_error'];
    unset($_SESSION['tenant_error']);
}

// =========================================================================
// 1. PROSES CRUD: TAMBAH DATA TENANT BARU (CREATE)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create'])) {
    $name             = trim($_POST['name'] ?? '');
    $type             = trim($_POST['type'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $preparation_time = isset($_POST['preparation_time']) ? (int)$_POST['preparation_time'] : 15;
    $status           = isset($_POST['status']) ? (int)$_POST['status'] : 1;

    if (!empty($name) && !empty($type)) {
        try {
            // Validasi duplikasi: Cek nama tenant agar tidak kembar
            $check = $conn->prepare("SELECT id FROM tenants WHERE name = ? LIMIT 1");
            $check->bind_param("s", $name);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $_SESSION['tenant_error'] = "Nama Tenant sudah terdaftar!";
            } else {
                // Menyematkan kolom phone, email, dan preparation_time ke dalam query insert
                $stmt = $conn->prepare("INSERT INTO tenants (name, type, description, phone, email, preparation_time, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("sssssii", $name, $type, $description, $phone, $email, $preparation_time, $status);
                
                if ($stmt->execute()) {
                    $_SESSION['tenant_success'] = "Data tenant baru berhasil ditambahkan!";
                    createNotification('admin', $_SESSION['user_id'], 'Tenant Baru', "Tenant $name ($type) berhasil ditambahkan");
                } else {
                    $_SESSION['tenant_error'] = "Gagal menyimpan data tenant ke database.";
                }
                $stmt->close();
            }
            $check->close();
        } catch (Throwable $e) {
            $_SESSION['tenant_error'] = "Kesalahan sistem: " . $e->getMessage();
        }
    } else {
        $_SESSION['tenant_error'] = "Kolom Nama dan Tipe Tenant wajib diisi!";
    }
    header("Location: tenants.php");
    exit;
}

// =========================================================================
// 2. PROSES CRUD: SIMPAN PERUBAHAN DATA TENANT (UPDATE)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update'])) {
    $targetId         = (int)($_POST['edit_id'] ?? 0);
    $name             = trim($_POST['name'] ?? '');
    $type             = trim($_POST['type'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $preparation_time = isset($_POST['preparation_time']) ? (int)$_POST['preparation_time'] : 15;
    $status           = isset($_POST['status']) ? (int)$_POST['status'] : 1;

    if ($targetId > 0 && !empty($name) && !empty($type)) {
        try {
            // Validasi duplikasi untuk update: Nama tidak boleh kembar dengan ID lain
            $check = $conn->prepare("SELECT id FROM tenants WHERE name = ? AND id != ? LIMIT 1");
            $check->bind_param("si", $name, $targetId);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $_SESSION['tenant_error'] = "Nama Tenant tersebut sudah digunakan oleh data lain!";
            } else {
                // Memperbarui record database lengkap termasuk phone, email, dan preparation_time
                $updateStmt = $conn->prepare("UPDATE tenants SET name = ?, type = ?, description = ?, phone = ?, email = ?, preparation_time = ?, status = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->bind_param("sssssiii", $name, $type, $description, $phone, $email, $preparation_time, $status, $targetId);
                
                if ($updateStmt->execute()) {
                    $_SESSION['tenant_success'] = "Data tenant berhasil diperbarui!";
                    createNotification('admin', $_SESSION['user_id'], 'Tenant Diperbarui', "Tenant $name ($type) berhasil diperbarui");
                } else {
                    $_SESSION['tenant_error'] = "Gagal memperbarui data tenant di database.";
                }
                $updateStmt->close();
            }
            $check->close();
        } catch (Throwable $e) {
            $_SESSION['tenant_error'] = "Kesalahan sistem saat mengubah data: " . $e->getMessage();
        }
    } else {
        $_SESSION['tenant_error'] = "Input data perubahan tenant tidak valid.";
    }
    header("Location: tenants.php");
    exit;
}

// =========================================================================
// 3. PROSES CRUD: SOFT DELETE DATA TENANT (DELETE LOGIC)
// =========================================================================
if (isset($_GET['action_delete'])) {
    $deleteId = (int)$_GET['action_delete'];

    if ($deleteId > 0) {
        try {
            // Menggunakan Soft Delete (mengisi deleted_at) sesuai ketersediaan kolom pada struktur HeidiSQL Anda
            $deleteStmt = $conn->prepare("UPDATE tenants SET deleted_at = NOW() WHERE id = ?");
            $deleteStmt->bind_param("i", $deleteId);
            if ($deleteStmt->execute()) {
                $_SESSION['tenant_success'] = "Data tenant berhasil dihapus dari sistem!";
                createNotification('admin', $_SESSION['user_id'], 'Tenant Dihapus', "Tenant (ID: $deleteId) berhasil dihapus");
            } else {
                $_SESSION['tenant_error'] = "Gagal menghapus data dari database.";
            }
            $deleteStmt->close();
        } catch (Throwable $e) {
            $_SESSION['tenant_error'] = "Kesalahan sistem saat menghapus data: " . $e->getMessage();
        }
    }
    header("Location: tenants.php");
    exit;
}

// =========================================================================
// 4. READ DATA UNTUK LOOPING VIEW (Mengecualikan data yang sudah dihapus)
// =========================================================================
$tenantsData = [];
$sql = "SELECT id, name, type, description, logo, phone, email, preparation_time, status, created_at, updated_at 
        FROM tenants 
        WHERE deleted_at IS NULL 
        ORDER BY id ASC";
$fetchQuery = $conn->query($sql);
if ($fetchQuery) {
    while ($row = $fetchQuery->fetch_assoc()) {
        $tenantsData[] = $row;
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
    body, body.modal-open { background:var(--bg) !important; color:var(--text); overflow:auto !important; padding-right:0px !important; pointer-events:auto !important; }
    .modal-backdrop, .modal-backdrop.show { display:none !important; opacity:0 !important; visibility:hidden !important; pointer-events:none !important; }
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
    
    <!-- HEADER TABEL & TOMBOL TAMBAH TENANT -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
      <div>
        <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Manajemen Data Tenants</h2>
      </div>
      <div>
        <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalTenant" onclick="openTambahTenant()">
          <i class="bi bi-shop"></i> Tambah Tenant
        </button>
      </div>
    </div>

    <!-- NOTIFIKASI INFORMASI ALERT STATUS OPERASI CRUD -->
    <?php if (!empty($crudSuccess)): ?>
      <div class="alert alert-success border-0 rounded-3 mb-4 alert-dismissible fade show" role="alert" style="background: rgba(34, 197, 94, 0.12) !important; color: #86efac !important;">
        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($crudSuccess) ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close" style="font-size: 0.75rem; box-shadow: none;"></button>
      </div>
    <?php endif; ?>
    <?php if (!empty($crudError)): ?>
      <div class="alert alert-danger border-0 rounded-3 mb-4 alert-dismissible fade show" role="alert" style="background: rgba(239, 68, 68, 0.12) !important; color: #fecaca !important;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($crudError) ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close" style="font-size: 0.75rem; box-shadow: none;"></button>
      </div>
    <?php endif; ?>

    <!-- STRUKTUR TABEL LIST DATA TENANTS (DRAG SCROLL & TRANSPARAN) -->
    <div id="dragScrollStockContainer" class="table-responsive rounded-3 drag-scroll-container" style="border: none !important; background: transparent !important; cursor: grab; box-shadow: none !important; -webkit-box-shadow: none !important;">
      <table class="table table-hover align-middle mb-0 text-white-element" style="background: transparent !important; color: #e5e7eb !important; min-width: 1100px; user-select: none; border-collapse: collapse !important;">
        <thead class="text-uppercase" style="font-size: 0.8rem; font-weight: 700; color: #94a3b8 !important; background-color: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important;">
            <tr>
              <th class="py-3 px-3 text-center text-white" style="background: transparent !important; border: none !important; width: 100px;">ID</th>
              <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Nama Tenant</th>
              <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important;">Tipe</th>
              <th class="py-3 text-white" style="background: transparent !important; border: none !important;">Kontak &amp; Email</th>
              <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important;">Persiapan</th>
              <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important;">Status</th>
              <th class="py-3 text-center text-white" style="background: transparent !important; border: none !important; width: 150px;">Aksi</th>
            </tr>
        </thead>
        <tbody style="background: transparent !important;">
          <?php
          if (!empty($tenantsData)) {
              foreach ($tenantsData as $tenantRow) {
                  $statusBadge = ($tenantRow['status'] == 1) ? 'bg-success' : 'bg-secondary';
                  $statusText = ($tenantRow['status'] == 1) ? 'Aktif' : 'Non-Aktif';
                  ?>
                  <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; background: transparent !important; font-size: 0.88rem;">
                    <td class="text-center fw-semibold" style="color: #94a3b8 !important; background: transparent !important; border: none !important;"><?= $tenantRow['id'] ?></td>
                    <td class="fw-semibold text-white" style="background: transparent !important; border: none !important;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-shop text-white-50"></i>
                            <div>
                                <span><?= htmlspecialchars($tenantRow['name']) ?></span>
                                <span class="d-block small text-white-50 fw-normal"><?= htmlspecialchars($tenantRow['description'] ?: '-') ?></span>
                            </div>
                        </div>
                    </td>
                    <td class="text-center fw-medium" style="background: transparent !important; border: none !important; color: #cbd5e1 !important;"><span class="badge bg-dark border border-secondary text-light"><?= htmlspecialchars($tenantRow['type']) ?></span></td>
                    <td style="background: transparent !important; border: none !important;">
                        <span class="d-block fw-medium"><i class="bi bi-telephone me-1 text-white-50"></i><?= htmlspecialchars($tenantRow['phone'] ?: '-') ?></span>
                        <span class="d-block small text-white-50"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($tenantRow['email'] ?: '-') ?></span>
                    </td>
                    <td class="text-center fw-bold text-warning" style="background: transparent !important; border: none !important;"><?= $tenantRow['preparation_time'] ?> Menit</td>
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                        <span class="badge <?= $statusBadge ?> rounded-2 px-2.5 py-1.5 fw-bold" style="font-size: 0.75rem; color: #fff !important;"><?= $statusText ?></span>
                    </td>
                    <td class="text-center" style="background: transparent !important; border: none !important;">
                      <div class="d-inline-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-warning border-0 rounded-2 text-warning" title="Edit Tenant" onclick="openEditTenant(<?= htmlspecialchars(json_encode($tenantRow)) ?>)">
                          <i class="bi bi-pencil-square"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-2 text-danger" title="Hapus Tenant" onclick="triggerDeleteTenant('tenants.php?action_delete=<?= $tenantRow['id']; ?>', '<?= addslashes($tenantRow['name']); ?>')">
                          <i class="bi bi-trash3-fill"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                  <?php
              }
          } else {
              echo '<tr><td colspan="7" class="text-center py-5 text-muted" style="background: transparent !important; border: none !important;">
                      <i class="bi bi-folder-x d-block mb-2" style="font-size: 2rem; color: rgba(148, 163, 184, 0.4);"></i>
                      Belum ada data tenant terdaftar di database.
                    </td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>

  </div>
</main>

<!-- Modal CRUD (Memanjang Ke Kanan & Transparan Gelap Selaras) -->
<div class="modal fade" id="modalTenant" tabindex="-1" aria-hidden="true">
    <!-- Menggunakan class modal-lg agar ukuran kotak form memanjang lebar ke kanan -->
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form action="tenants.php" method="POST" id="formTenant" class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(10px); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb; border-radius: 16px;">
            
            <div class="modal-header border-0 pb-0" style="padding: 1.5rem 1.5rem 0 1.5rem;">
                <h5 class="fw-bold text-white m-0" id="modalTenantLabel"><i class="bi bi-shop text-success me-2"></i> Tambah Tenant Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Input Hidden ID Kunci Transaksi -->
                <input type="hidden" name="edit_id" id="tenant_id">
                
                <!-- ROW STRUKTUR MEMANJANG KE KANAN (2 KOLOM JAJAR SIDE-BY-SIDE) -->
                <div class="row g-3">
                    
                    <!-- SISI KIRI: IDENTITAS UTAMA TOKO -->
                    <div class="col-12 col-md-6 border-end-md" style="border-color: rgba(148, 163, 184, 0.15) !important;">
                        <div class="pe-md-2">
                            <div class="mb-3">
                                <label class="form-label small text-white-50 fw-medium">Nama Tenant / Toko</label>
                                <input type="text" name="name" id="tenant_name" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); color: #fff !important;" required placeholder="Contoh: Dapur Gizi Sehat">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-white-50 fw-medium">Tipe Kategori Tenant</label>
                                <select name="type" id="tenant_type" class="form-select text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); color: #fff !important;" required>
                                    <option value="kantin">Kantin</option>
                                    <option value="cafe">Cafe</option>
                                    <option value="koperasi">Koperasi</option>
                                    <option value="laundry">Laundry</option>
                                </select>
                            </div>

                            <div class="mb-0">
                                <label class="form-label small text-white-50 fw-medium">Status Operasional Global</label>
                                <select name="status" id="tenant_status" class="form-select text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); color: #fff !important;">
                                    <option value="1">Aktif / Buka</option>
                                    <option value="0">Non-Aktif / Tutup</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SISI KANAN: DESKRIPSI & TENTANG TENANT -->
                    <div class="col-12 col-md-6">
                        <div class="ps-md-2">
                            <div class="mb-0">
                                <label class="form-label small text-white-50 fw-medium">Deskripsi Singkat / Keterangan Toko</label>
                                <textarea name="description" id="tenant_description" rows="6" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); color: #fff !important;" placeholder="Tulis rincian atau catatan menu spesifik tenant disini..."></textarea>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- TOMBOL ACTION SUBMIT FORM UTAMA -->
                <div class="mt-4 pb-1">
                    <button type="submit" id="btnSubmitTenant" class="btn btn-success rounded-3 w-100 py-2 fw-medium">Simpan Data Tenant</button>
                </div>

                <!-- KOMPONEN DI BAWAH BUTTON: KONTAK, EMAIL & WAKTU PREPARASI -->
                <div class="p-3 rounded-4 mt-3" style="background: rgba(30, 41, 59, 0.3); border: 1px dashed rgba(148, 163, 184, 0.25);">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-white-50 fw-medium"><i class="bi bi-telephone me-1"></i> No. Telepon (Phone)</label>
                            <input type="text" name="phone" id="tenant_phone" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); color: #fff !important;" placeholder="Contoh: 0812345678">
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label small text-white-50 fw-medium"><i class="bi bi-envelope me-1"></i> Alamat Email Toko</label>
                            <input type="email" name="email" id="tenant_email" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); color: #fff !important;" placeholder="nama@tenant.com">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-white-50 fw-medium"><i class="bi bi-hourglass-split me-1"></i> Persiapan (Menit)</label>
                            <input type="number" name="preparation_time" id="tenant_preparation_time" min="1" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); color: #fff !important;" value="15">
                        </div>
                    </div>
                </div>

            </div>
            
            <div class="modal-footer border-0 pt-0 justify-content-center" style="padding: 0 1.5rem 1.5rem 1.5rem;">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 px-4 py-1.5" data-bs-dismiss="modal">Tutup Jendela</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Konfirmasi Hapus Tenant -->
<div class="modal fade" id="modalDeleteTenant" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(10px); border: 1px solid rgba(239, 68, 68, 0.25); color: #e5e7eb; border-radius: 16px;">
            <div class="modal-body text-center p-4">
                <!-- Visual Anchor berupa icon Exclamation dengan efek drop-shadow merah transparan -->
                <div class="text-danger mb-3">
                    <i class="bi bi-exclamation-triangle-fill" style="font-size: 3rem; filter: drop-shadow(0 0 10px rgba(239, 68, 68, 0.3));"></i>
                </div>
                <h5 class="fw-bold text-white mb-2">Hapus Data Tenant?</h5>
                <p class="text-white-50 small mb-4">Tindakan ini akan menonaktifkan tenant <span id="delete_tenant_name" class="text-white fw-semibold"></span> secara permanen dari sistem.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-sm btn-secondary rounded-3 px-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
                    <a id="btn_confirm_delete_tenant" href="#" class="btn btn-sm btn-danger rounded-3 px-3 py-2 fw-medium">Ya, Hapus</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Integrasi Modal, Isian Form, dan Drag to Scroll -->
<script>
let deleteModalInstance = null;
let tenantModalInstance = null;

function triggerDeleteTenant(url, tenantName) {
    document.getElementById('delete_tenant_name').innerText = '"' + tenantName + '"';
    document.getElementById('btn_confirm_delete_tenant').setAttribute('href', url);
    
    if (!deleteModalInstance) {
        deleteModalInstance = new bootstrap.Modal(document.getElementById('modalDeleteTenant'));
    }
    deleteModalInstance.show();
}

function getTenantModal() {
    if (!tenantModalInstance) {
        tenantModalInstance = new bootstrap.Modal(document.getElementById('modalTenant'));
    }
    return tenantModalInstance;
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

document.addEventListener('DOMContentLoaded', function() {
    if (window.history.replaceState && window.location.search !== '') {
        const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
        window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
    }

    const slider = document.getElementById('dragScrollStockContainer');
    if (!slider) return;
    let isDown = false, startX, scrollLeft;
    
    slider.addEventListener('mousedown', (e) => {
        if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input')) return;
        isDown = true; 
        slider.style.cursor = 'grabbing';
        startX = e.pageX - slider.offsetLeft; 
        scrollLeft = slider.scrollLeft;
    });
    slider.addEventListener('mouseleave', () => { isDown = false; slider.style.cursor = 'grab'; });
    slider.addEventListener('mouseup', () => { isDown = false; slider.style.cursor = 'grab'; });
    slider.addEventListener('mousemove', (e) => {
        if (!isDown) return; 
        e.preventDefault();
        const x = e.pageX - slider.offsetLeft; 
        const walk = (x - startX) * 2;
        slider.scrollLeft = scrollLeft - walk;
    });
});

function openTambahTenant() {
    resetForm();
    document.getElementById('modalTenantLabel').innerHTML = '<i class="bi bi-shop text-success me-2"></i> Tambah Tenant Baru';
    
    const btnSubmit = document.getElementById('btnSubmitTenant');
    if (btnSubmit) {
        btnSubmit.setAttribute('name', 'action_create');
        btnSubmit.className = "btn btn-success rounded-3 w-100 py-2 fw-medium";
        btnSubmit.innerText = "Simpan Data Tenant";
    }
    getTenantModal().show();
}

function resetForm() {
    const form = document.getElementById('formTenant');
    if (form) form.reset();
    document.getElementById('tenant_id').value = '';
    document.getElementById('tenant_preparation_time').value = '15';
}

function openEditTenant(tenantRow) {
    if (!tenantRow) return;
    resetForm();
    
    document.getElementById('modalTenantLabel').innerHTML = '<i class="bi bi-pencil-square text-warning me-2"></i> Ubah Data Tenant';
    
    document.getElementById('tenant_id').value = tenantRow.id;
    document.getElementById('tenant_name').value = tenantRow.name || '';
    document.getElementById('tenant_type').value = tenantRow.type || 'kantin';
    document.getElementById('tenant_description').value = tenantRow.description || '';
    document.getElementById('tenant_status').value = tenantRow.status;
    document.getElementById('tenant_phone').value = tenantRow.phone || '';
    document.getElementById('tenant_email').value = tenantRow.email || '';
    document.getElementById('tenant_preparation_time').value = tenantRow.preparation_time || '15';

    const btnSubmit = document.getElementById('btnSubmitTenant');
    if (btnSubmit) {
        btnSubmit.setAttribute('name', 'action_update');
        btnSubmit.className = "btn btn-warning text-dark rounded-3 w-100 py-2 fw-bold";
        btnSubmit.innerText = "Simpan Perubahan Tenant";
    }
    getTenantModal().show();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
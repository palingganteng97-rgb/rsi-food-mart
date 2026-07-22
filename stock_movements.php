<?php
// stock_movements.php (Hanya Logika Atas Backend)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_move_stock'])) {
    $productId     = (int)($_POST['product_id'] ?? 0);
    $movementType  = $_POST['movement_type'] ?? ''; // 'IN', 'OUT', 'ADJUSTMENT'
    $qty           = (int)($_POST['qty'] ?? 0);
    $referenceType = !empty($_POST['reference_type']) ? trim($_POST['reference_type']) : null;
    $referenceId   = !empty($_POST['reference_id']) ? (int)$_POST['reference_id'] : null;
    $userId        = (int)$_SESSION['user_id'];

    if ($productId <= 0 || $qty <= 0 || !in_array($movementType, ['IN', 'OUT', 'ADJUSTMENT'])) {
        header("Location: stock_movements.php?status=error&msg=" . urlencode("Data input mutasi tidak valid!"));
        exit;
    }

    try {
        $conn->begin_transaction();

        // 1. Ambil stok saat ini untuk mengamankan data (FOR UPDATE)
        $pStmt = $conn->prepare("SELECT stock FROM products WHERE id = ? FOR UPDATE");
        $pStmt->bind_param("i", $productId);
        $pStmt->execute();
        $pRes = $pStmt->get_result();
        
        if ($pRes->num_rows === 0) {
            throw new Exception("Produk tidak ditemukan.");
        }
        
        $pRow = $pRes->fetch_assoc();
        $beforeStock = (int)$pRow['stock'];
        $pStmt->close();

        // 2. Kalkulasi nominal after_stock berdasarkan movement_type
        if ($movementType === 'IN') {
            $afterStock = $beforeStock + $qty;
        } elseif ($movementType === 'OUT' || $movementType === 'ADJUSTMENT') {
            $afterStock = $beforeStock - $qty;
            if ($afterStock < 0) {
                throw new Exception("Stok fisik tidak mencukupi untuk pemotongan.");
            }
        }

        // 3. Simpan data riwayat mutasi stok ke database
        $insSql = "INSERT INTO stock_movements (product_id, movement_type, qty, before_stock, after_stock, reference_type, reference_id, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $insStmt = $conn->prepare($insSql);
        $insStmt->bind_param("isiiisii", $productId, $movementType, $qty, $beforeStock, $afterStock, $referenceType, $referenceId, $userId);
        $insStmt->execute();
        $insStmt->close();

        // 4. Perbarui kuantitas stok di tabel utama produk
        $upStmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $upStmt->bind_param("ii", $afterStock, $productId);
        $upStmt->execute();
        $upStmt->close();

        $conn->commit();
        header("Location: stock_movements.php?status=success");
        exit;

    } catch (Throwable $e) {
        $conn->rollback();
        header("Location: stock_movements.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
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
    <div class="container-fluid rounded-4 p-4 text-white" style="background: transparent !important; border: none !important; box-shadow: none !important;">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15) !important;">
            <div>
                <h2 class="fw-bold m-0 text-white" style="font-size: 2rem;">Stock Movements</h2>
            </div>
            <div>
                <button class="btn btn-success rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalStockMovement">
                    <i class="bi bi-plus-circle"></i> Input Mutasi Stok
                </button>
            </div>
        </div>

        <?php if (isset($_GET['status'])): ?>
            <div class="alert <?= $_GET['status'] === 'success' ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show mb-4" role="alert" style="background-color: #1e1e24; color: #fff; border-color: #2d2d34;">
                <strong>
                    <?php 
                    if ($_GET['status'] === 'success') echo "Data mutasi stok berhasil diproses!";
                    else echo "Operasi gagal: " . htmlspecialchars($_GET['msg'] ?? 'Terjadi kesalahan sistem.');
                    ?>
                </strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="table-responsive" id="dragScrollStockContainer" style="overflow-x: auto; cursor: grab; scrollbar-width: none; -ms-overflow-style: none;">
            <style>
                #dragScrollStockContainer::-webkit-scrollbar { display: none; }
                .table-custom-hover tbody tr:hover { background: rgba(148, 163, 184, 0.15) !important; transition: background 0.15s ease-in-out; }
                /* KUNCI UTAMA: Memaksa semua elemen teks di dalam tabel ini menjadi putih solid */
                .table-force-white th, .table-force-white td { color: #ffffff !important; }
            </style>
            <!-- PERBAIKAN: Menambahkan class text-white dan table-force-white -->
            <table class="table text-white table-force-white align-middle mb-0 table-custom-hover" style="--bs-table-bg: transparent; border-collapse: separate; border-spacing: 0 8px; min-width: 1000px;">
                <thead style="font-size: 0.85rem; background: rgba(30, 41, 59, 0.65) !important;">
                    <tr>
                        <th class="ps-4 py-3" style="border-radius: 10px 0 0 10px; border-bottom: 1px solid rgba(148, 163, 184, 0.2);">Waktu</th>
                        <th class="py-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.2);">Produk</th>
                        <th class="py-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.2);">Tipe</th>
                        <th class="py-3 text-center" style="border-bottom: 1px solid rgba(148, 163, 184, 0.2);">Qty</th>
                        <th class="py-3 text-center" style="border-bottom: 1px solid rgba(148, 163, 184, 0.2);">Sebelum</th>
                        <th class="py-3 text-center" style="border-bottom: 1px solid rgba(148, 163, 184, 0.2);">Sesudah</th>
                        <th class="py-3" style="border-bottom: 1px solid rgba(148, 163, 184, 0.2);">Referensi</th>
                        <th class="pe-4 py-3 text-end" style="border-radius: 0 10px 10px 0; border-bottom: 1px solid rgba(148, 163, 184, 0.2);">Petugas</th>
                    </tr>
                </thead>
                <tbody style="font-size: 0.9rem;">
                    <?php
                    $sql = "SELECT sm.*, p.name AS product_name, u.name AS user_name 
                            FROM stock_movements sm 
                            LEFT JOIN products p ON sm.product_id = p.id 
                            LEFT JOIN users u ON sm.user_id = u.id 
                            ORDER BY sm.id DESC";
                    $query = $conn->query($sql);
                    if ($query && $query->num_rows > 0):
                        while ($row = $query->fetch_assoc()):
                            $badge = 'bg-secondary';
                            if ($row['movement_type'] === 'IN') $badge = 'bg-success';
                            if ($row['movement_type'] === 'OUT') $badge = 'bg-danger';
                            if ($row['movement_type'] === 'ADJUSTMENT') $badge = 'bg-warning text-dark';
                    ?>
                    <tr style="background: rgba(30, 41, 59, 0.65); border: 1px solid rgba(148, 163, 184, 0.2);">
                        <td class="ps-4 py-3 fw-bold" style="border-radius: 10px 0 0 10px;"><?= date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                        <td class="fw-bold" style="color: #ffffff !important;"><?= htmlspecialchars($row['product_name'] ?? 'Produk Dihapus'); ?></td>
                        <td><span class="badge <?= $badge; ?> rounded-2 px-2.5 py-1.5 fw-bold" style="font-size: 0.75rem; color: #fff !important;"><?= $row['movement_type']; ?></span></td>
                        <td class="text-center fw-bold"><?= $row['qty']; ?></td>
                        <td class="text-center fw-bold"><?= $row['before_stock']; ?></td>
                        <!-- Mengamankan text-success agar nilai sesudah tetap hijau cerah mencolok -->
                        <td class="text-center text-success fw-bold" style="color: #22c55e !important;"><?= $row['after_stock']; ?></td>
                        <td>
                            <?php if (!empty($row['reference_type'])): ?>
                                <span class="fw-bold small d-block"><?= htmlspecialchars($row['reference_type']); ?></span> 
                                <span class="badge bg-dark border border-secondary text-white mt-1">#<?= $row['reference_id'] ?? '-'; ?></span>
                            <?php else: ?>
                                <span class="small fw-bold" style="opacity: 0.8;">No Reference</span>
                            <?php endif; ?>
                        </td>
                        <td class="pe-4 py-3 text-end fw-bold" style="border-radius: 0 10px 10px 0;"><?= htmlspecialchars($row['user_name'] ?? 'Sistem'); ?></td>
                    </tr>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-white fw-bold" style="background: rgba(30, 41, 59, 0.2); border-radius: 10px;">
                            <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                            Tidak ada data riwayat mutasi stok ditemukan.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</main>

<!-- MODAL FORM INPUT MUTASI STOK (stock_movements.php) -->
<div class="modal fade" id="modalStockMovement" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form action="stock_movements.php" method="POST" id="formStockMovement" class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(10px); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb; border-radius: 16px;">
            
            <div class="modal-header border-0 pb-0" style="padding: 1.5rem 1.5rem 0 1.5rem;">
                <h5 class="fw-bold text-white m-0">Input Mutasi Stok</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Penanda Aksi Form -->
                <input type="hidden" name="action_move_stock" value="1">
                
                <div class="row g-3">
                    <!-- Pilih Produk -->
                    <div class="col-12">
                        <label class="form-label small text-white-50 fw-medium">Pilih Produk</label>
                        <select name="product_id" id="movement_product_id" class="form-select bg-dark text-white border-secondary rounded-3" style="background-color: rgba(2, 6, 23, 0.4) !important;" required>
                            <option value="">-- Pilih Produk --</option>
                            <?php
                            $prodQuery = $conn->query("SELECT id, name, stock FROM products WHERE deleted_at IS NULL ORDER BY name ASC");
                            if ($prodQuery):
                                while ($p = $prodQuery->fetch_assoc()):
                            ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (Stok Saat Ini: <?= $p['stock'] ?>)</option>
                            <?php 
                                endwhile;
                            endif; 
                            ?>
                        </select>
                    </div>
                    
                    <!-- Tipe Pergerakan Stok -->
                    <div class="col-md-6">
                        <label class="form-label small text-white-50 fw-medium">Tipe Pergerakan</label>
                        <select name="movement_type" id="movement_type" class="form-select bg-dark text-white border-secondary rounded-3" style="background-color: rgba(2, 6, 23, 0.4) !important;" required>
                            <option value="">-- Pilih Tipe --</option>
                            <option value="IN">IN (Stok Masuk / Tambah)</option>
                            <option value="OUT">OUT (Stok Keluar / Kurang)</option>
                            <option value="ADJUSTMENT">ADJUSTMENT (Penyesuaian Selisih)</option>
                        </select>
                    </div>

                    <!-- Kuantitas Jumlah (Qty) -->
                    <div class="col-md-6">
                        <label class="form-label small text-white-50 fw-medium">Jumlah Kuantitas (Qty)</label>
                        <input type="number" name="qty" id="movement_qty" min="1" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none;" required placeholder="0">
                    </div>

                    <!-- Tipe Referensi -->
                    <div class="col-md-6">
                        <label class="form-label small text-white-50 fw-medium">Tipe Referensi (Opsional)</label>
                        <input type="text" name="reference_type" id="movement_reference_type" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none;" placeholder="Contoh: PO, MANUAL_ADJUST, CANCEL_ORDER">
                    </div>

                    <!-- ID Referensi -->
                    <div class="col-md-6">
                        <label class="form-label small text-white-50 fw-medium">ID Referensi / No. Nota (Opsional)</label>
                        <input type="number" name="reference_id" id="movement_reference_id" class="form-control text-white border-secondary rounded-3" style="background: rgba(2, 6, 23, 0.4); box-shadow: none;" placeholder="Contoh: 10024">
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0" style="padding: 0 1.5rem 1.5rem 1.5rem;">
                <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success rounded-3 px-4 fw-medium">Simpan Mutasi</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Konfirmasi Hapus Mutasi Stok (stock_movements.php) -->
<div class="modal fade" id="modalConfirmDeleteStock" tabindex="-1" aria-labelledby="modalConfirmDeleteStockLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-bg-dark border-secondary" style="background-color: #111827 !important; border-color: #374151 !important; border-radius: 16px;">
      
      <div class="modal-header border-bottom border-secondary">
        <h5 class="modal-title text-white fw-bold d-flex align-items-center" id="modalConfirmDeleteStockLabel">
          <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Konfirmasi Hapus Mutasi
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body text-center p-4">
        <div class="mb-3">
          <i class="bi bi-trash3-fill text-danger" style="font-size: 3.5rem;"></i>
        </div>
        <p class="text-white-50 fs-6 mb-1">Apakah Anda yakin ingin menghapus data mutasi stok ini?</p>
        <h6 id="delete_stock_target_name" class="text-warning fw-bold mt-2"></h6>
      </div>
      
      <div class="modal-footer border-top border-secondary justify-content-center">
        <button type="button" class="btn btn-sm btn-secondary px-4 rounded-3 py-2" data-bs-dismiss="modal" style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); color: #94a3b8;">Batal</button>
        <a id="btnConfirmDeleteStockAction" href="#" class="btn btn-sm btn-danger px-4 rounded-3 py-2 fw-bold d-inline-flex align-items-center justify-content-center">Oke, Hapus</a>
      </div>

    </div>
  </div>
</div>

<script>
    let bootstrapStockModalInstance = null;

    document.addEventListener('DOMContentLoaded', function() {
        // Otomatis membersihkan parameter ?status=... dan ?msg=... dari URL bar tanpa reload halaman
        if (window.history.replaceState && (window.location.search.includes('status=') || window.location.search.includes('msg='))) {
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
        }

        const stockSlider = document.getElementById('dragScrollStockContainer');
        if (stockSlider) {
            let isDown = false;
            let startX, scrollLeft;
            
            stockSlider.addEventListener('mousedown', (e) => {
                if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input') || e.target.closest('select')) return;
                
                isDown = true; 
                stockSlider.style.cursor = 'grabbing';
                startX = e.pageX - stockSlider.offsetLeft; 
                scrollLeft = stockSlider.scrollLeft;
            });
            
            stockSlider.addEventListener('mouseleave', () => { 
                isDown = false; 
                stockSlider.style.cursor = 'grab'; 
            });
            
            stockSlider.addEventListener('mouseup', () => { 
                isDown = false; 
                stockSlider.style.cursor = 'grab'; 
            });
            
            stockSlider.addEventListener('mousemove', (e) => {
                if (!isDown) return; 
                e.preventDefault();
                const x = e.pageX - stockSlider.offsetLeft;
                stockSlider.scrollLeft = scrollLeft - ((x - startX) * 2);
            });
        }
    });

    function openInputStockMovement() {
        const formStock = document.getElementById('formStockMovement');
        if (formStock) formStock.reset();
        
        document.getElementById('movement_product_id').value = '';
        document.getElementById('movement_type').value = '';
        document.getElementById('movement_qty').value = '';
        document.getElementById('movement_reference_type').value = '';
        document.getElementById('movement_reference_id').value = '';
        
        const modalStockEl = document.getElementById('modalStockMovement');
        if (modalStockEl) {
            const instance = bootstrap.Modal.getOrCreateInstance(modalStockEl);
            instance.show();
        }
    }

    function triggerDeleteStockMovement(id, infoText) {
        document.getElementById('delete_stock_target_name').innerText = infoText;
        
        const btnConfirm = document.getElementById('btnConfirmDeleteStockAction');
        if (btnConfirm) {
            btnConfirm.setAttribute('href', 'stock_movements.php?action_delete_movement=1&id=' + id);
        }
        
        const modalDeleteEl = document.getElementById('modalConfirmDeleteStock');
        if (modalDeleteEl) {
            const instance = bootstrap.Modal.getOrCreateInstance(modalDeleteEl);
            instance.show();
        }
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Menangkap parameter aksi dari URL
$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

// 1. PROSES HAPUS MENU FAVORIT OLEH ADMIN / KASIR (DELETE)
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id'] ?? 0);

    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM favorites WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            header("Location: favorites.php?status=success_delete");
        } else {
            header("Location: favorites.php?status=error&msg=" . urlencode($stmt->error));
        }
        exit();
    }
}

// 2. PROSES READ DATA DENGAN JOIN RELASI (SUDAH DIPERBAIKI MENGGUNAKAN base_price)
$favorites = [];
$sql = "SELECT f.id AS favorite_id, f.patient_session_id, f.product_id, f.tenant_id,
               p.name AS product_name, p.image AS product_image, p.base_price AS product_price,
               t.name AS tenant_name
        FROM favorites f
        LEFT JOIN products p ON f.product_id = p.id
        LEFT JOIN tenants t ON f.tenant_id = t.id
        ORDER BY f.id DESC";

// Eksekusi query dengan aman
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $favorites[] = $row;
    }
}

// Menangkap status alert notifikasi untuk layout admin
$status = isset($_GET['status']) ? (string)$_GET['status'] : '';
$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';

function renderAlert(string $status, string $msg): void {
    if ($status === '') return;

    $type = 'info';
    $title = 'Info';

    switch ($status) {
        case 'success_delete':
            $type = 'success';
            $title = 'Berhasil';
            break;
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
        default:
            $type = 'info';
            $title = 'Info';
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
    <title>Menu Favorit - RSI Food &amp; Mart</title>
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
        .img-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 12px; border: 1px solid rgba(148,163,184,.2); }
    </style>
</head>
<body>
    <?php require __DIR__ . '/sidebar.php'; ?>

    <main class="content-shift p-4">
        <div class="container-fluid" style="max-width: 1100px;">
            <div class="d-flex align-items-center justify-content-between mb-4 pb-3" style="border-bottom: 1px solid rgba(148,163,184,.15);">
                <div>
                    <h2 class="fw-bold m-0">Menu Favorit</h2>
                    <div class="text-white-50" style="font-size:.9rem;">Daftar menu favorit pelanggan</div>
                </div>
            </div>

            <?php renderAlert($status, $msg); ?>

            <div class="card" style="background: rgba(15,23,42,.55); border:1px solid rgba(148,163,184,.2); border-radius: 18px;">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-dark align-middle mb-0 rounded-4 overflow-hidden">
                            <thead>
                                <tr>
                                    <th style="width:60px;">Gambar</th>
                                    <th>Produk</th>
                                    <th>Tenant</th>
                                    <th style="width:140px;">Harga</th>
                                    <th style="width:120px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($favorites)): ?>
                                    <?php foreach ($favorites as $fav): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($fav['product_image']) && file_exists('uploads/products/' . $fav['product_image'])): ?>
                                                    <img class="img-thumb" src="uploads/products/<?= htmlspecialchars($fav['product_image']) ?>" alt="<?= htmlspecialchars($fav['product_name'] ?? '') ?>" />
                                                <?php else: ?>
                                                    <div class="text-white-50 small">-</div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-semibold">
                                                <?= htmlspecialchars($fav['product_name'] ?? '') ?>
                                                <div class="text-white-50" style="font-size:.78rem;">ID Favorite: <?= htmlspecialchars((string)($fav['favorite_id'] ?? '')) ?></div>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($fav['tenant_name'] ?? '') ?>
                                            </td>
                                            <td class="fw-bold text-success">
                                                Rp <?= number_format((float)($fav['product_price'] ?? 0), 0, ',', '.') ?>
                                            </td>
                                            <td>
                                                <a class="btn btn-sm btn-danger rounded-3" 
                                                   href="favorites.php?action=delete&id=<?= (int)($fav['favorite_id'] ?? 0) ?>"
                                                   onclick="return confirm('Hapus favorit ini?');">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <div class="text-white-50 mb-2"><i class="bi bi-heart" style="font-size:2rem; opacity:.7;"></i></div>
                                            <div class="fw-semibold">Belum ada data favorit</div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



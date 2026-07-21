<?php
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =========================================================================
// Validasi session pasien
// =========================================================================
$isPatient = isset($_SESSION['patient_session_id']) && intval($_SESSION['patient_session_id']) > 0;
if (!$isPatient) {
    header("Location: index.php");
    exit;
}

$patient_session_id = intval($_SESSION['patient_session_id']);

// =========================================================================
// Helper functions
// =========================================================================
function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
function money($val) {
    return 'Rp ' . number_format((float)$val, 0, ',', '.');
}

// =========================================================================
// Ambil status/msg dari query string (setelah redirect dari proses_ulasan_tenant)
// =========================================================================
$fromRedirectStatus = isset($_GET['status']) ? $_GET['status'] : '';
$fromRedirectMsg    = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';

// =========================================================================
// QUERY 1: Ambil tenant dari pesanan SUCCESS milik pasien (unique)
// =========================================================================
$reviewableTenants = [];

$sqlTenants = "
    SELECT DISTINCT
        o.id AS order_id,
        o.order_number,
        o.tenant_id,
        o.created_at AS order_created_at,
        o.status AS order_status,
        t.name AS tenant_name,
        tr.id AS review_id,
        tr.rating AS review_rating,
        tr.review AS review_text
    FROM orders o
    JOIN tenants t ON o.tenant_id = t.id
    LEFT JOIN tenant_reviews tr ON tr.tenant_id = o.tenant_id AND tr.patient_session_id = ?
    WHERE o.patient_session_id = ?
      AND (LOWER(o.status) = 'completed' OR LOWER(o.status) = 'delivered' OR LOWER(o.status) = 'selesai')
    ORDER BY o.created_at DESC
";

$stmt = $conn->prepare($sqlTenants);
$stmt->bind_param('ii', $patient_session_id, $patient_session_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $reviewableTenants[] = $row;
    }
}
$stmt->close();

// =========================================================================
// QUERY 2: Riwayat ulasan tenant milik pasien
// =========================================================================
$historyReviews = [];

$sqlHistory = "
    SELECT tr.*, t.name AS tenant_name
    FROM tenant_reviews tr
    JOIN tenants t ON tr.tenant_id = t.id
    WHERE tr.patient_session_id = ?
    ORDER BY tr.id DESC
";

$stmtHist = $conn->prepare($sqlHistory);
$stmtHist->bind_param('i', $patient_session_id);
$stmtHist->execute();
$resHist = $stmtHist->get_result();
if ($resHist) {
    while ($row = $resHist->fetch_assoc()) {
        $historyReviews[] = $row;
    }
}
$stmtHist->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Ulasan Tenant - RSI Food &amp; Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
    <style>
        :root { --bg:#111827; --text:#e5e7eb; --muted:#94a3b8; --green:#22c55e; }
        body { background:var(--bg) !important; color:var(--text); }
        .card-tenant { background: rgba(31, 41, 55, 0.6) !important; border: 1px solid rgba(148, 163, 184, 0.15) !important; backdrop-filter: blur(8px); border-radius: 16px; transition: transform .15s ease, border-color .15s ease; overflow: hidden; }
        .card-tenant:hover { transform: translateY(-2px); border-color: rgba(34, 197, 94, 0.35) !important; }
        .tenant-logo { width: 56px; height: 56px; border-radius: 14px; object-fit: cover; background: rgba(2,6,23,.4); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .star-btn { background: none; border: none; font-size: 1.6rem; padding: 0 3px; cursor: pointer; color: rgba(148,163,184,0.3); transition: color .1s ease; line-height: 1; }
        .star-btn:hover { color: #fbbf24; }
        .bottom-nav { position: fixed; left:0; right:0; bottom:0; z-index:1035; background:rgba(15,23,42,.88); backdrop-filter:blur(10px); border-top:1px solid rgba(148,163,184,.25); }
        @media (min-width: 992px) { main.content-shift { margin-left: 280px; } .bottom-nav { display:none; } }
        .badge-reviewed { background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
        .status-badge { font-size: 0.7rem; padding: 3px 10px; border-radius: 999px; }
        .table-history { background: transparent !important; }
        .table-history thead th { background: rgba(15, 23, 42, 0.8) !important; border-bottom: 1px solid rgba(148, 163, 184, 0.25) !important; color: #94a3b8 !important; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; }
        .table-history tbody td { background: transparent !important; border-bottom: 1px solid rgba(148, 163, 184, 0.12) !important; color: #e5e7eb !important; }
        .table-history tbody tr:hover { background: rgba(148, 163, 184, 0.05) !important; }
        .textarea-review { background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; color: #e5e7eb !important; border-radius: 12px; resize: vertical; }
        .textarea-review:focus { border-color: rgba(34, 197, 94, 0.5) !important; box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.1) !important; }
        .modal-content-dark { background: #1f2937 !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; border-radius: 16px; }
        .btn-submit { border-radius: 999px; padding: 8px 32px; font-weight: 600; }
    </style>
</head>
<body>

<?php include "sidebar_pasients.php"; ?>

<main class="page-body content-shift">
    <div class="container py-4">

        <!-- HEADER -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
            <div>
                <h4 class="fw-bold text-white mb-1">
                    <i class="bi bi-star me-2 text-warning"></i> Ulasan Tenant
                </h4>
                <p class="text-white-50 small mb-0">Beri rating untuk tenant dari pesanan yang sudah selesai</p>
            </div>
            <a href="home.php" class="btn btn-outline-light btn-sm rounded-pill px-3" style="border: 1px solid rgba(148, 163, 184, 0.3); background: rgba(255, 255, 255, 0.05);">
                <i class="bi bi-shop me-1"></i> Etalase
            </a>
        </div>

        <!-- ALERT STATUS -->
        <?php if ($fromRedirectStatus === 'success'): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert" style="background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3) !important;">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill fs-5"></i>
                    <span class="fw-semibold"><?php echo h($fromRedirectMsg ?: 'Review berhasil dikirim!'); ?></span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif ($fromRedirectStatus === 'error'): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert" style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3) !important;">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <span class="fw-semibold"><?php echo h($fromRedirectMsg ?: 'Terjadi kesalahan.'); ?></span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- ============================================================ -->
        <!-- SECTION 1: DAFTAR TENANT SIAP REVIEW                         -->
        <!-- ============================================================ -->
        <?php if (empty($reviewableTenants)): ?>
            <!-- EMPTY STATE -->
            <div class="text-center rounded-4 p-5 mb-4" style="border: 2px dashed rgba(148, 163, 184, 0.25);">
                <i class="bi bi-shop text-warning mb-3" style="font-size: 3.5rem; opacity: 0.7;"></i>
                <h5 class="text-white-50 fw-medium mb-2">Belum ada tenant yang dapat direview.</h5>
                <p class="text-white-50 small mb-4">Tenant dari pesanan dengan status Selesai akan muncul di sini untuk Anda beri rating.</p>
                <a href="home.php" class="btn btn-success btn-sm rounded-pill px-4 fw-medium shadow-sm">
                    <i class="bi bi-shop me-1"></i> Mulai Belanja
                </a>
            </div>
        <?php else: ?>
            <!-- GRID TENANT -->
            <div class="row g-3 mb-5">
                <?php foreach ($reviewableTenants as $item): 
                    $tenantId    = intval($item['tenant_id'] ?? 0);
                    $orderId     = intval($item['order_id'] ?? 0);
                    $orderNumber = h($item['order_number'] ?? '-');
                    $tenantName  = h($item['tenant_name'] ?? 'Tenant');
                    $orderDate   = date('d M Y H:i', strtotime($item['order_created_at'] ?? 'now'));
                    $orderStatus = h($item['order_status'] ?? 'completed');
                    $alreadyReviewed = !empty($item['review_id']);
                    $existingRating   = intval($item['review_rating'] ?? 0);
                    $existingReview   = h($item['review_text'] ?? '');
                ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card-tenant p-3 h-100 d-flex flex-column">
                            <div class="d-flex align-items-start gap-3 mb-2">
                                <!-- Logo Tenant -->
                                <div class="tenant-logo">
                                    <i class="bi bi-building text-white-50" style="font-size: 1.4rem; opacity: 0.5;"></i>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <h6 class="fw-bold text-white mb-1" style="font-size: 1rem;"><?php echo $tenantName; ?></h6>
                                    <div class="text-white-50 small mb-1">
                                        <i class="bi bi-receipt me-1"></i>#<?php echo $orderNumber; ?>
                                    </div>
                                    <div class="text-white-50 small">
                                        <i class="bi bi-calendar3 me-1"></i><?php echo $orderDate; ?>
                                    </div>
                                </div>
                                <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 status-badge flex-shrink-0">
                                    <i class="bi bi-check-circle me-1"></i><?php echo ucfirst($orderStatus); ?>
                                </span>
                            </div>

                            <div class="mt-auto pt-2">
                                <?php if ($alreadyReviewed): ?>
                                    <!-- SUDAH DIREVIEW -->
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="badge badge-reviewed rounded-pill px-3 py-1">
                                            <i class="bi bi-check-circle-fill me-1"></i> Sudah Direview
                                        </span>
                                        <div class="d-flex gap-0">
                                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                                <i class="bi <?php echo $s <= $existingRating ? 'bi-star-fill text-warning' : 'bi-star text-white-50'; ?>" style="font-size: 0.85rem; <?php echo $s <= $existingRating ? '' : 'opacity:0.3;'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <?php if ($existingReview !== ''): ?>
                                        <div class="text-white-50 small mt-2" style="font-style: italic; background: rgba(0,0,0,0.2); border-radius: 8px; padding: 6px 10px;">
                                            <i class="bi bi-quote me-1"></i><?php echo $existingReview; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <!-- TOMBOL BERI RATING -->
                                    <button type="button" class="btn btn-warning btn-sm rounded-pill px-3 fw-semibold shadow-sm w-100" 
                                            data-bs-toggle="modal" data-bs-target="#modalReviewTenant"
                                            data-tenant-id="<?php echo $tenantId; ?>"
                                            data-order-id="<?php echo $orderId; ?>"
                                            data-order-number="<?php echo $orderNumber; ?>"
                                            data-tenant-name="<?php echo $tenantName; ?>">
                                        <i class="bi bi-star-fill me-1"></i> Beri Rating
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ============================================================ -->
        <!-- SECTION 2: RIWAYAT ULASAN TENANT                             -->
        <!-- ============================================================ -->
        <hr class="text-white-50 my-4" style="opacity: 0.15;">
        
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-clock-history text-white-50"></i>
            <h5 class="fw-bold text-white mb-0">Riwayat Ulasan Tenant</h5>
        </div>

        <?php if (empty($historyReviews)): ?>
            <div class="text-center rounded-4 p-4" style="border: 1px dashed rgba(148, 163, 184, 0.2);">
                <i class="bi bi-star text-white-50 mb-2" style="font-size: 2rem; opacity: 0.4;"></i>
                <p class="text-white-50 small mb-0">Belum ada riwayat ulasan tenant.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive rounded-3" style="border: 1px solid rgba(148, 163, 184, 0.15);">
                <table class="table table-hover align-middle mb-0 table-history" style="min-width: 600px;">
                    <thead>
                        <tr>
                            <th class="py-3 px-3 text-center" style="width: 60px;">No</th>
                            <th class="py-3">Nama Tenant</th>
                            <th class="py-3 text-center" style="width: 180px;">Rating</th>
                            <th class="py-3">Ulasan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $num = 1; ?>
                        <?php foreach ($historyReviews as $hist): 
                            $histRating = intval($hist['rating'] ?? 0);
                            $histReview = h($hist['review'] ?? '');
                            $histTenant = h($hist['tenant_name'] ?? 'Tenant');
                        ?>
                            <tr>
                                <td class="text-center text-white-50" style="font-size: 0.85rem;"><?php echo $num++; ?></td>
                                <td class="fw-semibold text-white"><?php echo $histTenant; ?></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-0">
                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                            <i class="bi <?php echo $s <= $histRating ? 'bi-star-fill text-warning' : 'bi-star text-white-50'; ?>" style="font-size: 0.9rem; <?php echo $s <= $histRating ? '' : 'opacity:0.3;'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </td>
                                <td class="text-white-50 small" style="max-width: 300px;">
                                    <?php echo $histReview !== '' ? $histReview : '<span class="text-white-50" style="font-style: italic;">(Tidak ada ulasan teks)</span>'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Spacer untuk bottom nav -->
        <div class="py-5"></div>
    </div>
</main>

<!-- ================================================================ -->
<!-- MODAL REVIEW TENANT                                              -->
<!-- ================================================================ -->
<div class="modal fade" id="modalReviewTenant" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-dark text-white shadow-lg">
            <div class="modal-header border-0 pb-0" style="border-bottom: 1px solid rgba(148, 163, 184, 0.1) !important;">
                <div>
                    <h5 class="modal-title fw-bold" id="modalReviewLabel">
                        <i class="bi bi-star-fill text-warning me-2"></i>Beri Rating Tenant
                    </h5>
                    <p class="text-white-50 small mb-0 mt-1" id="modalTenantInfo">Tenant: -</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="proses_ulasan_tenant.php" method="POST">
                <div class="modal-body py-4">
                    <!-- Hidden Inputs -->
                    <input type="hidden" name="tenant_id" id="hidden_tenant_id" value="">
                    <input type="hidden" name="order_id" id="hidden_order_id" value="">
                    <input type="hidden" name="patient_session_id" value="<?php echo $patient_session_id; ?>">
                    <input type="hidden" name="rating" class="rating-input" value="0">

                    <!-- Interactive Star Rating -->
                    <div class="mb-3 text-center">
                        <label class="text-white-50 small fw-semibold d-block mb-2">Rating <span class="text-danger">*</span></label>
                        <div class="d-flex justify-content-center gap-1 star-container" data-rating="0">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <button type="button" class="star-btn" data-value="<?php echo $s; ?>">
                                    <i class="bi bi-star"></i>
                                </button>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Textarea Ulasan (Opsional) -->
                    <div class="mb-0">
                        <label class="text-white-50 small fw-semibold d-block mb-1">Ulasan <span class="text-white-50 fw-normal">(Opsional)</span></label>
                        <textarea name="review" class="form-control textarea-review" rows="3" placeholder="Bagikan pengalaman Anda terhadap tenant... (Opsional)"></textarea>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0 d-flex gap-2 justify-content-center" style="border-top: 1px solid rgba(148, 163, 184, 0.1) !important;">
                    <button type="button" class="btn btn-outline-light btn-sm rounded-pill px-4 fw-medium" data-bs-dismiss="modal" style="border-color: rgba(148, 163, 184, 0.3);">
                        Batal
                    </button>
                    <button type="submit" name="submit_review" class="btn btn-warning btn-sm btn-submit shadow-sm" disabled>
                        <i class="bi bi-send me-1"></i> Kirim Rating
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include "bottom_nav.php"; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ====================================================================
    // PASS DATA TENANT KE MODAL via data-* attributes
    // ====================================================================
    const modalReview = document.getElementById('modalReviewTenant');
    if (modalReview) {
        modalReview.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;

            const tenantId    = button.getAttribute('data-tenant-id');
            const orderId     = button.getAttribute('data-order-id');
            const orderNumber = button.getAttribute('data-order-number');
            const tenantName  = button.getAttribute('data-tenant-name');

            document.getElementById('hidden_tenant_id').value  = tenantId;
            document.getElementById('hidden_order_id').value   = orderId;
            document.getElementById('modalTenantInfo').innerHTML = 
                '<i class="bi bi-receipt me-1"></i> #' + orderNumber + ' &middot; ' + tenantName;

            // Reset form di modal setiap kali dibuka
            const form = modalReview.querySelector('form');
            if (form) form.reset();
            
            // Reset star rating
            const container = modalReview.querySelector('.star-container');
            const ratingInput = modalReview.querySelector('.rating-input');
            const submitBtn   = modalReview.querySelector('button[type="submit"]');
            if (container) {
                container.setAttribute('data-rating', '0');
                const stars = container.querySelectorAll('.star-btn');
                stars.forEach(function(btn) {
                    const icon = btn.querySelector('i');
                    icon.className = 'bi bi-star';
                    btn.style.color = 'rgba(148,163,184,0.3)';
                });
            }
            if (ratingInput) ratingInput.value = '0';
            if (submitBtn) submitBtn.disabled = true;
        });
    }

    // ====================================================================
    // INTERACTIVE STAR RATING
    // ====================================================================
    document.querySelectorAll('.star-container').forEach(function(container) {
        const ratingInput = container.closest('form').querySelector('.rating-input');
        const submitBtn   = container.closest('form').querySelector('button[type="submit"]');
        const stars       = container.querySelectorAll('.star-btn');

        if (!ratingInput || !submitBtn) return;

        function updateStars(rating) {
            stars.forEach(function(btn) {
                const val = parseInt(btn.getAttribute('data-value'));
                const icon = btn.querySelector('i');
                if (val <= rating) {
                    icon.className = 'bi bi-star-fill';
                    btn.style.color = '#fbbf24';
                } else {
                    icon.className = 'bi bi-star';
                    btn.style.color = 'rgba(148,163,184,0.3)';
                }
            });
        }

        function setRating(rating) {
            ratingInput.value = rating;
            container.setAttribute('data-rating', rating);
            updateStars(rating);
            submitBtn.disabled = (rating === 0);
        }

        stars.forEach(function(btn) {
            // Click
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const val = parseInt(this.getAttribute('data-value'));
                const currentRating = parseInt(container.getAttribute('data-rating'));
                const newRating = (currentRating === val) ? val : val; // minimal 1, tidak toggle ke 0
                setRating(newRating);
            });

            // Hover
            btn.addEventListener('mouseenter', function() {
                const val = parseInt(this.getAttribute('data-value'));
                stars.forEach(function(s) {
                    const sv = parseInt(s.getAttribute('data-value'));
                    const icon = s.querySelector('i');
                    if (sv <= val) {
                        icon.className = 'bi bi-star-fill';
                        s.style.color = '#fbbf24';
                    } else {
                        icon.className = 'bi bi-star';
                        s.style.color = 'rgba(148,163,184,0.3)';
                    }
                });
            });

            btn.addEventListener('mouseleave', function() {
                const currentRating = parseInt(container.getAttribute('data-rating'));
                updateStars(currentRating);
            });
        });

        // Init
        setRating(0);
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


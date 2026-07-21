<?php
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =========================================================================
// VALIDASI SESI PASIEN
// =========================================================================
$isPatient = isset($_SESSION['patient_session_id']) && intval($_SESSION['patient_session_id']) > 0;
if (!$isPatient) {
    header("Location: index.php");
    exit;
}

$patient_session_id = intval($_SESSION['patient_session_id']);

// =========================================================================
// FUNGSI HELPER
// =========================================================================
function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
function money($val) {
    return 'Rp ' . number_format((float)$val, 0, ',', '.');
}

// =========================================================================
// HANDLER SUBMIT REVIEW (POST)
// =========================================================================
$submitStatus = '';
$submitMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $product_id  = intval($_POST['product_id'] ?? 0);
    $order_id    = intval($_POST['order_id'] ?? 0);
    $rating      = intval($_POST['rating'] ?? 0);
    $review      = trim($_POST['review'] ?? '');

    if ($product_id <= 0 || $order_id <= 0) {
        $submitStatus = 'error';
        $submitMsg = 'Data produk atau pesanan tidak valid.';
    } elseif ($rating < 1 || $rating > 5) {
        $submitStatus = 'error';
        $submitMsg = 'Rating harus dipilih (1-5 bintang).';
    } else {
        // Validasi: Pastikan order milik pasien ini dan statusnya SUCCESS/DELIVERED/SELESAI
        $stmtCheck = $conn->prepare("
            SELECT o.id 
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.id AND oi.product_id = ?
            WHERE o.id = ? 
              AND o.patient_session_id = ?
              AND (LOWER(o.status) = 'completed' OR LOWER(o.status) = 'delivered' OR LOWER(o.status) = 'selesai')
            LIMIT 1
        ");
        $stmtCheck->bind_param('iii', $product_id, $order_id, $patient_session_id);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        $validOrder = $resCheck && $resCheck->num_rows > 0;
        $stmtCheck->close();

        if (!$validOrder) {
            $submitStatus = 'error';
            $submitMsg = 'Pesanan tidak valid atau belum memenuhi syarat untuk direview.';
        } else {
            // Cek apakah produk ini sudah pernah direview oleh pasien
            $stmtDup = $conn->prepare("
                SELECT id FROM product_reviews 
                WHERE product_id = ? AND patient_session_id = ?
                LIMIT 1
            ");
            $stmtDup->bind_param('ii', $product_id, $patient_session_id);
            $stmtDup->execute();
            $resDup = $stmtDup->get_result();
            $alreadyReviewed = $resDup && $resDup->num_rows > 0;
            $stmtDup->close();

            if ($alreadyReviewed) {
                $submitStatus = 'error';
                $submitMsg = 'Produk ini sudah pernah direview.';
            } else {
                // Simpan review
                $stmtInsert = $conn->prepare("
                    INSERT INTO product_reviews (product_id, patient_session_id, rating, review, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmtInsert->bind_param('iiis', $product_id, $patient_session_id, $rating, $review);
                if ($stmtInsert->execute()) {
                    $submitStatus = 'success';
                    $submitMsg = 'Review berhasil dikirim!';
                } else {
                    $submitStatus = 'error';
                    $submitMsg = 'Gagal menyimpan review: ' . $stmtInsert->error;
                }
                $stmtInsert->close();
            }
        }
    }

    // Redirect dengan status flag untuk mencegah resubmission (PRG)
    $qs = http_build_query(['status' => $submitStatus, 'msg' => $submitMsg]);
    header("Location: product_review_patients.php?" . $qs);
    exit;
}

// =========================================================================
// AMBIL STATUS/MSG DARI QUERY STRING (setelah redirect)
// =========================================================================
$fromRedirectStatus = isset($_GET['status']) ? $_GET['status'] : '';
$fromRedirectMsg    = isset($_GET['msg']) ? $_GET['msg'] : '';

// =========================================================================
// QUERY: AMBIL PRODUK DARI PESANAN SUCCESS YANG SIAP DIREVIEW
// =========================================================================
$reviewableItems = [];

$sql = "
    SELECT 
        oi.id AS order_item_id,
        oi.order_id,
        oi.product_id,
        oi.qty,
        oi.price,
        oi.notes,
        o.order_number,
        o.created_at AS order_created_at,
        o.status AS order_status,
        p.name AS product_name,
        p.image AS product_image,
        t.name AS tenant_name,
        pr.id AS review_id,
        pr.rating AS review_rating,
        pr.review AS review_text
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN tenants t ON p.tenant_id = t.id
    LEFT JOIN product_reviews pr ON pr.product_id = oi.product_id AND pr.patient_session_id = ?
    WHERE o.patient_session_id = ?
      AND (LOWER(o.status) = 'completed' OR LOWER(o.status) = 'delivered' OR LOWER(o.status) = 'selesai')
    ORDER BY o.created_at DESC, oi.id ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $patient_session_id, $patient_session_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $reviewableItems[] = $row;
    }
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Ulasan Produk - RSI Food &amp; Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
    <style>
        :root { --bg:#0f172a; --text:#e5e7eb; --muted:#94a3b8; --green:#22c55e; }
        body { background:var(--bg) !important; color:var(--text); }
        .card-review { background: rgba(30, 41, 59, 0.35) !important; border: 1px solid rgba(148, 163, 184, 0.15) !important; backdrop-filter: blur(12px); border-radius: 16px; transition: transform .15s ease, border-color .15s ease; overflow: hidden; }
        .card-review:hover { transform: translateY(-2px); border-color: rgba(34, 197, 94, 0.35) !important; }
        .product-thumb { width: 80px; height: 80px; border-radius: 12px; object-fit: cover; background: rgba(2,6,23,.4); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .star-btn { background: none; border: none; font-size: 1.5rem; padding: 0 2px; cursor: pointer; color: rgba(148,163,184,0.3); transition: color .1s ease; }
        .star-btn:hover, .star-btn.active { color: #fbbf24; }
        .star-btn.active-prev { color: #fbbf24; }
        .star-rating-display i { color: #fbbf24; }
        .bottom-nav { position: fixed; left:0; right:0; bottom:0; z-index:1035; background:rgba(15,23,42,.88); backdrop-filter:blur(10px); border-top:1px solid rgba(148,163,184,.25); }
        @media (min-width: 992px) { main.content-shift { margin-left: 280px; } .bottom-nav { display:none; } }
        .badge-reviewed { background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
        .review-text-preview { background: rgba(0,0,0,0.2); border-radius: 8px; padding: 8px 12px; font-size: 0.85rem; color: #cbd5e1; }
        .status-badge { font-size: 0.7rem; padding: 3px 10px; border-radius: 999px; }
        .textarea-review { background: rgba(2, 6, 23, 0.4) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; color: #e5e7eb !important; border-radius: 12px; resize: vertical; }
        .textarea-review:focus { border-color: rgba(34, 197, 94, 0.5) !important; box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.1) !important; }
        .btn-submit-review { border-radius: 999px; padding: 8px 28px; font-weight: 600; }
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
                    <i class="bi bi-chat-left-text me-2 text-success"></i> Ulasan Produk
                </h4>
                <p class="text-white-50 small mb-0">Berikan penilaian untuk produk yang sudah Anda pesan</p>
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

        <?php if (empty($reviewableItems)): ?>
        <!-- ========== EMPTY STATE ========== -->
        <div class="text-center rounded-4 p-5" style="border: 2px dashed rgba(148, 163, 184, 0.25);">
            <i class="bi bi-inboxes text-success mb-3" style="font-size: 3.5rem; opacity: 0.7;"></i>
            <h5 class="text-white-50 fw-medium mb-2">Belum ada produk yang dapat direview.</h5>
            <p class="text-white-50 small mb-4">Produk dari pesanan dengan status Selesai akan muncul di sini untuk Anda beri ulasan.</p>
            <a href="home.php" class="btn btn-success btn-sm rounded-pill px-4 fw-medium shadow-sm">
                <i class="bi bi-shop me-1"></i> Mulai Belanja
            </a>
        </div>
        <?php else: ?>
        <!-- ========== DAFTAR PRODUK SIAP REVIEW ========== -->
        <div class="row g-3">
            <?php foreach ($reviewableItems as $item): 
                $productId   = intval($item['product_id'] ?? 0);
                $orderId     = intval($item['order_id'] ?? 0);
                $orderNumber = h($item['order_number'] ?? '-');
                $productName = h($item['product_name'] ?? 'Produk Tidak Diketahui');
                $tenantName  = h($item['tenant_name'] ?? 'Tenant');
                $productImg  = $item['product_image'] ?? '';
                $imgPath     = '';
                if ($productImg !== '' && file_exists("uploads/products/" . $productImg)) {
                    $imgPath = "uploads/products/" . $productImg;
                }
                $qty         = intval($item['qty'] ?? 0);
                $price       = floatval($item['price'] ?? 0);
                $orderDate   = date('d M Y H:i', strtotime($item['order_created_at'] ?? 'now'));
                $orderStatus = h($item['order_status'] ?? 'completed');
                $alreadyReviewed = !empty($item['review_id']);
                $existingRating   = intval($item['review_rating'] ?? 0);
                $existingReview   = h($item['review_text'] ?? '');
                $isDifferentOrder = false; // flag khusus jika produk sama dari order berbeda
            ?>
                <div class="col-12">
                    <div class="card-review p-3 p-md-4" data-product-id="<?php echo $productId; ?>" data-order-id="<?php echo $orderId; ?>">
                        <div class="d-flex flex-column flex-md-row gap-3">
                            <!-- Thumbnail Produk -->
                            <div class="product-thumb flex-shrink-0">
                                <?php if ($imgPath !== ''): ?>
                                    <img src="<?php echo $imgPath; ?>" alt="<?php echo $productName; ?>" class="w-100 h-100" style="object-fit: cover; border-radius: 12px;">
                                <?php else: ?>
                                    <i class="bi bi-egg-fried text-white-50" style="font-size: 1.8rem; opacity: 0.4;"></i>
                                <?php endif; ?>
                            </div>

                            <!-- Info Produk & Order -->
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                                    <div>
                                        <h6 class="fw-bold text-white mb-1" style="font-size: 1.05rem;"><?php echo $productName; ?></h6>
                                        <div class="text-white-50 small mb-2">
                                            <i class="bi bi-shop me-1"></i><?php echo $tenantName; ?>
                                        </div>
                                    </div>
                                    <!-- Status Badge -->
                                    <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 status-badge">
                                        <i class="bi bi-check-circle me-1"></i> <?php echo ucfirst($orderStatus); ?>
                                    </span>
                                </div>

                                <!-- Info Order Row -->
                                <div class="d-flex flex-wrap gap-3 gap-md-4 mb-2 small">
                                    <div>
                                        <span class="text-white-50">Pesanan:</span>
                                        <span class="fw-semibold text-white font-monospace ms-1">#<?php echo $orderNumber; ?></span>
                                    </div>
                                    <div>
                                        <span class="text-white-50">Tanggal:</span>
                                        <span class="text-white ms-1"><?php echo $orderDate; ?></span>
                                    </div>
                                    <div>
                                        <span class="text-white-50">Qty:</span>
                                        <span class="fw-semibold text-white ms-1"><?php echo $qty; ?>x</span>
                                    </div>
                                    <div>
                                        <span class="text-white-50">Harga:</span>
                                        <span class="fw-semibold text-success ms-1"><?php echo money($price); ?></span>
                                    </div>
                                </div>

                                <!-- ========== AREA REVIEW ========== -->
                                <?php if ($alreadyReviewed): ?>
                                    <!-- SUDAH DIREVIEW -->
                                    <div class="mt-3 p-3 rounded-3" style="background: rgba(0,0,0,0.25); border: 1px solid rgba(34, 197, 94, 0.15);">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="badge badge-reviewed rounded-pill px-3 py-1">
                                                <i class="bi bi-check-circle-fill me-1"></i> Sudah Direview
                                            </span>
                                            <div class="star-rating-display d-flex gap-0">
                                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                                    <i class="bi <?php echo $s <= $existingRating ? 'bi-star-fill' : 'bi-star'; ?>" style="font-size: 0.9rem; <?php echo $s <= $existingRating ? '' : 'opacity:0.3;'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <?php if ($existingReview !== ''): ?>
                                            <div class="review-text-preview">
                                                <i class="bi bi-quote me-1 text-white-50"></i><?php echo $existingReview; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-white-50 small" style="font-style: italic;">(Tidak ada ulasan teks)</div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <!-- FORM REVIEW -->
                                    <form method="POST" action="product_review_patients.php" class="mt-3 review-form">
                                        <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                                        <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                                        <input type="hidden" name="rating" class="rating-input" value="0">
                                        
                                        <!-- Star Rating -->
                                        <div class="mb-2">
                                            <label class="text-white-50 small fw-semibold d-block mb-1">Rating <span class="text-danger">*</span></label>
                                            <div class="d-flex gap-1 star-container" data-rating="0">
                                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                                    <button type="button" class="star-btn" data-value="<?php echo $s; ?>">
                                                        <i class="bi bi-star"></i>
                                                    </button>
                                                <?php endfor; ?>
                                            </div>
                                        </div>

                                        <!-- Textarea Ulasan (Opsional) -->
                                        <div class="mb-3">
                                            <label class="text-white-50 small fw-semibold d-block mb-1">Ulasan <span class="text-white-50 fw-normal">(Opsional)</span></label>
                                            <textarea name="review" class="form-control textarea-review" rows="2" placeholder="Bagikan pengalaman Anda mengenai produk ini... (Opsional)"></textarea>
                                        </div>

                                        <!-- Tombol Kirim -->
                                        <button type="submit" name="submit_review" class="btn btn-success btn-sm btn-submit-review shadow-sm" disabled>
                                            <i class="bi bi-send me-1"></i> Kirim Review
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Spacer untuk bottom nav -->
        <div class="py-5"></div>
    </div>
</main>

<?php include "bottom_nav.php"; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ====================================================================
    // INTERACTIVE STAR RATING (tanpa reload)
    // ====================================================================
    document.querySelectorAll('.star-container').forEach(function(container) {
        const ratingInput = container.closest('.review-form').querySelector('.rating-input');
        const submitBtn   = container.closest('.review-form').querySelector('button[type="submit"]');
        const stars       = container.querySelectorAll('.star-btn');

        function updateStars(rating) {
            stars.forEach(function(btn, idx) {
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
            // Enable/disable submit button
            if (submitBtn) {
                submitBtn.disabled = (rating === 0);
            }
        }

        stars.forEach(function(btn) {
            // Klik
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const val = parseInt(this.getAttribute('data-value'));
                const currentRating = parseInt(container.getAttribute('data-rating'));
                // Jika klik bintang yang sama, toggle (kecuali jika rating 0)
                const newRating = (currentRating === val) ? 0 : val;
                setRating(newRating);
            });

            // Hover
            btn.addEventListener('mouseenter', function() {
                const val = parseInt(this.getAttribute('data-value'));
                stars.forEach(function(s, idx) {
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

        // Init: set ke 0 (default disabled)
        setRating(0);
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


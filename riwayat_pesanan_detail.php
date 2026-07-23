<?php
/**
 * riwayat_pesanan_detail.php
 * 
 * Halaman detail pesanan (Detail View) — di-include oleh riwayat_pesanan.php
 * saat parameter ?id=... diberikan.
 * 
 * Semua variabel (order, items, payment, delivery, dll.) sudah tersedia
 * dari file induk (riwayat_pesanan.php) sebelum include ini dipanggil.
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan - RSI Food &amp; Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
    <style>
        :root { --bg:#0f172a; --text:#e5e7eb; --muted:#94a3b8; --green:#22c55e; }
        body { background:var(--bg) !important; color:var(--text); }
    </style>
</head>
<body class="bg-dark text-white" style="min-height: 100vh; padding: 2rem 0;">

<?php include "sidebar_pasients.php"; ?>

<main class="page-body content-shift">
    <div class="container">
    <div class="mb-4">
            <a href="riwayat_pesanan.php" class="btn btn-outline-light btn-sm rounded-pill px-3" style="border: 1px solid rgba(148, 163, 184, 0.3); background: rgba(255, 255, 255, 0.05);">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Riwayat
            </a>
        </div>

        <?php if ($cancelSuccess):
            $cancelReason = isset($_GET['reason']) ? urldecode($_GET['reason']) : '';
        ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert" style="max-width: 580px; margin: 0 auto 1rem auto; background: rgba(34, 197, 94, 0.2); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3) !important;">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <span class="fw-semibold">Pesanan berhasil dibatalkan.</span>
            </div>
            <?php if ($cancelReason !== ''): ?>
            <div class="mt-2" style="border-top: 1px solid rgba(34,197,94,0.2); padding-top: 8px;">
                <small><i class="bi bi-chat-quote me-1"></i>Alasan: <em><?php echo h($cancelReason); ?></em></small>
            </div>
            <?php endif; ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php elseif (isset($_GET['error']) && $_GET['error'] === 'cannot_cancel'): ?>
        <div class="alert alert-warning alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert" style="max-width: 580px; margin: 0 auto 1rem auto; background: rgba(255, 193, 7, 0.2); color: #ffc107; border: 1px solid rgba(255, 193, 7, 0.3) !important;">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <span class="fw-semibold">Pesanan tidak dapat dibatalkan karena status sudah berubah.</span>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="card mx-auto border-0 p-4 p-md-5 rounded-4 shadow-lg" style="max-width: 580px; background: rgba(30, 41, 59, 0.35); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.15);">
            
            <!-- Icon dinamis berdasarkan status -->
            <div class="mb-4 text-center">
                <?php 
                $deliveryStatusLower = $deliveryStatus ? strtolower($deliveryStatus) : '';
                $orderStatusLower = strtolower($orderStatus ?? '');
                if ($deliveryStatus === 'Terkirim'): ?>
                    <i class="bi bi-check-circle-fill text-success display-1 d-block"></i>
                <?php elseif ($deliveryStatus === 'Dibatalkan' || $deliveryStatus === 'Gagal Kirim'): ?>
                    <i class="bi bi-x-circle-fill text-danger display-1 d-block"></i>
                <?php elseif ($orderStatusLower === 'cancelled'): ?>
                    <i class="bi bi-x-circle-fill text-danger display-1 d-block"></i>
                <?php elseif ($deliveryStatus): ?>
                    <i class="bi bi-truck text-warning display-1 d-block"></i>
                <?php else: ?>
                    <i class="bi bi-hourglass-split text-warning display-1 d-block"></i>
                <?php endif; ?>
            </div>

            <div class="text-center mb-3">
                <h2 class="fw-bold text-white mb-2">Detail Pesanan</h2>
                <p class="text-white-50 small">Informasi lengkap pesanan Anda</p>
            </div>

            <!-- Info Order -->
            <div class="p-3 rounded-3 mb-3" style="background: rgba(0,0,0,0.3); border: 1px solid rgba(148,163,184,0.15);">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-white-50 small">Nomor Pesanan</span>
                    <span class="fw-bold text-white font-monospace">#<?php echo h($order['order_number'] ?? '-'); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-white-50 small">Tanggal</span>
                    <span class="fw-semibold text-white small"><?php echo h(date('d M Y H:i', strtotime($order['created_at'] ?? 'now'))); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-white-50 small">Status Pembayaran</span>
                    <span class="badge <?php echo statusBadge($paymentStatus); ?> px-3 py-1 rounded-pill"><?php echo h($paymentStatus); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-white-50 small">Status Pesanan</span>
                    <span class="badge <?php echo orderStatusBadge($orderStatus); ?> px-3 py-1 rounded-pill"><?php echo ucfirst(strtolower($orderStatus)); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-white-50 small">Status Pengiriman</span>
                    <span class="badge <?php echo $deliveryStatus ? deliveryStatusBadge($deliveryStatus) : 'bg-secondary bg-opacity-25 text-secondary border-secondary border-opacity-50'; ?> px-3 py-1 rounded-pill">
                        <?php echo $deliveryStatus ? h($deliveryStatus) : 'Belum diproses'; ?>
                    </span>
                </div>
                <?php if ($deliveryTime): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-white-50 small">Waktu Pengiriman</span>
                    <span class="fw-semibold text-white small"><?php echo h(date('d M Y H:i', strtotime($deliveryTime))); ?></span>
                </div>
                <?php endif; ?>
                <!-- Informasi Kurir -->
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-white-50 small">Kurir</span>
                    <span class="fw-semibold text-white text-end small" style="max-width: 60%;">
                        <?php if ($courierData): ?>
                            <div class="d-flex align-items-center gap-2 justify-content-end">
                                <i class="bi bi-person-badge fs-5 text-info"></i>
                                <span><?php echo h($courierData['name']); ?></span>
                            </div>
                            <?php if ($courierData['phone'] && $courierData['phone'] !== '-'): ?>
                            <div class="text-white-50 small mt-1">
                                <i class="bi bi-telephone me-1"></i><?php echo h($courierData['phone']); ?>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-white-50">Kurir belum ditugaskan</span>
                        <?php endif; ?>
                    </span>
                </div>
                <!-- Bukti Foto Pengiriman (Detail View) — selalu ditampilkan -->
                <div class="d-flex justify-content-between align-items-center mb-2 pt-2" style="border-top: 1px solid rgba(148,163,184,0.15);">
                    <span class="text-white-50 small">
                        <i class="bi bi-camera me-1 text-success"></i> Bukti Pengiriman
                    </span>
                    <?php
                    $proofPhotoPath = 'uploads/deliveries/' . ($deliveryProofPhoto ?? '');
                    $proofPhotoExists = !empty($deliveryProofPhoto) && file_exists($proofPhotoPath);
                    ?>
                    <?php if ($proofPhotoExists): ?>
                        <div style="width: 60px; height: 60px; cursor: pointer;" class="rounded-2 overflow-hidden border border-secondary" onclick="zoomProofPhotoDetail('<?= h($proofPhotoPath) ?>')">
                            <img src="<?= h($proofPhotoPath) ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="Bukti Pengiriman">
                        </div>
                    <?php else: ?>
                        <div class="d-flex align-items-center gap-2 text-white-50" style="font-size: 0.8rem;">
                            <i class="bi bi-camera-slash" style="font-size: 1.1rem;"></i>
                            <span><em>Belum ada bukti pengiriman.</em></span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (isset($isCancelled) && $isCancelled && !empty($order['cancel_reason'])): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-white-50 small">Alasan Pembatalan</span>
                    <span class="fw-semibold text-white-50 text-end small" style="max-width: 60%;"><?php echo h($order['cancel_reason']); ?></span>
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-white-50 small">Metode Pembayaran</span>
                    <span class="fw-semibold text-white"><?php echo h($payment_method_name); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-white-50 small">Total Item</span>
                    <span class="fw-semibold text-white"><?php echo intval($totalQty); ?> item</span>
                </div>
                <hr style="border-color: rgba(148,163,184,0.15);">
                <div class="d-flex justify-content-between">
                    <span class="fw-bold text-white">Total Bayar</span>
                    <span class="fw-bold text-success fs-5"><?php echo money($order['grand_total'] ?? 0); ?></span>
                </div>
            </div>

            <!-- Daftar Item -->
            <?php if (!empty($items)): ?>
            <div class="mb-3">
                <div class="text-white-50 small fw-bold mb-2">Pesanan Anda:</div>
                <?php foreach ($items as $it): ?>
                <div class="d-flex justify-content-between align-items-center py-1 px-2 rounded-2 mb-1" style="background: rgba(0,0,0,0.2);">
                    <div>
                        <span class="text-white small"><?php echo h($it['product_name'] ?? 'Produk'); ?></span>
                        <?php if (!empty($it['notes'])): ?>
                        <br><span class="text-white-50" style="font-size:0.7rem;"><?php echo h($it['notes']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="text-end">
                        <span class="text-white-50 small">x<?php echo intval($it['qty'] ?? 0); ?></span>
                        <span class="text-success small ms-2"><?php echo money($it['price'] ?? 0); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="d-flex gap-2 mt-3">
                <?php
                $payStatusUpper = strtoupper($paymentStatus ?? '');
                $orderStatusLower = strtolower($orderStatus ?? '');
                $canCancel = ($orderStatusLower === 'pending');
                $isCancelled = ($orderStatusLower === 'cancelled');
                ?>

                <?php if ($canCancel): ?>
                <!-- Tombol Batalkan Pesanan (aktif, hanya jika status masih PENDING) -->
                <button type="button" class="btn btn-danger rounded-pill px-4 fw-bold flex-fill shadow-sm" data-bs-toggle="modal" data-bs-target="#cancelModal">
                    <i class="bi bi-x-circle me-2"></i>Batalkan Pesanan
                </button>
                <?php elseif ($isCancelled): ?>
                <!-- Tombol Pesanan Dibatalkan (disabled, jika status CANCELLED) -->
                <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold flex-fill shadow-sm" disabled style="opacity: 0.6; cursor: not-allowed;">
                    <i class="bi bi-x-circle me-2"></i>Pesanan Dibatalkan
                </button>
                <?php endif; ?>

                <a href="home.php" class="btn btn-warning rounded-pill px-4 fw-bold flex-fill shadow-sm">
                    <i class="bi bi-shop me-2"></i>Kembali ke Etalase
                </a>
            </div>
        </div>
    </div>
</main>

<!-- Modal Konfirmasi Batalkan Pesanan -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 text-white shadow-lg" style="background: #0b1223; border: 1px solid rgba(148, 163, 184, 0.15) !important;">
            <div class="modal-header border-secondary border-opacity-25">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-4 text-danger"></i>
                    <h5 class="modal-title fw-bold m-0" id="cancelModalLabel">Konfirmasi Pembatalan</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="cancelForm" onsubmit="return validateCancelReason()">
                <div class="modal-body py-4">
                    <div class="text-center mb-3">
                        <i class="bi bi-x-circle-fill text-danger display-4 d-block mb-3"></i>
                        <p class="fw-semibold mb-1">Apakah Anda yakin ingin membatalkan pesanan ini?</p>
                        <p class="text-white-50 small mb-3">Tindakan ini tidak dapat dibatalkan. Pesanan dengan nomor <strong class="text-white">#<?php echo h($order['order_number'] ?? '-'); ?></strong> akan dibatalkan.</p>
                    </div>
                    <hr style="border-color: rgba(148,163,184,0.15);">
                    <div class="mb-0">
                        <label for="alasan_cancel" class="form-label text-white-50 small fw-semibold">
                            <i class="bi bi-pencil-square me-1"></i>Alasan Pembatalan <span class="text-danger">*</span>
                        </label>
                        <textarea name="alasan_cancel" id="alasan_cancel" class="form-control bg-dark bg-opacity-25 text-white border-secondary border-opacity-50 rounded-3" rows="3" placeholder="Tuliskan alasan mengapa Anda membatalkan pesanan ini..." required style="resize: vertical;"></textarea>
                        <div id="alasanError" class="text-danger small mt-1 d-none">
                            <i class="bi bi-exclamation-circle me-1"></i>Alasan pembatalan wajib diisi!
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary border-opacity-25 d-flex gap-2">
                    <button type="button" class="btn btn-outline-light rounded-pill px-4 fw-medium" data-bs-dismiss="modal">
                        <i class="bi bi-arrow-left me-1"></i>Kembali
                    </button>
                    <input type="hidden" name="cancel_order" value="1" />
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm" id="btnConfirmCancel">
                        <i class="bi bi-check2-circle me-1"></i>Ya, Batalkan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Zoom Bukti Foto (Detail View) -->
<div class="modal fade" id="modalZoomProofDetail" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(10px); z-index: 1070;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-transparent border-0 text-center">
            <div class="position-relative d-inline-block mx-auto">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 shadow-none" data-bs-dismiss="modal" style="background-color: rgba(0,0,0,0.5); padding: 10px; border-radius: 50%; z-index: 10;"></button>
                <img id="imgZoomProofTargetDetail" src="" class="img-fluid rounded-4 shadow-lg border border-secondary" style="max-height: 75vh; object-fit: contain;">
            </div>
        </div>
    </div>
</div>

<script>
// Validasi JavaScript: alasan pembatalan tidak boleh kosong
function validateCancelReason() {
    var alasan = document.getElementById('alasan_cancel');
    var errorDiv = document.getElementById('alasanError');
    if (alasan.value.trim() === '') {
        alasan.classList.add('is-invalid');
        errorDiv.classList.remove('d-none');
        // Fokus ke textarea
        alasan.focus();
        return false;
    }
    alasan.classList.remove('is-invalid');
    errorDiv.classList.add('d-none');
    return true;
}

// Reset error saat modal ditutup
document.addEventListener('DOMContentLoaded', function() {
    var cancelModalEl = document.getElementById('cancelModal');
    if (cancelModalEl) {
        cancelModalEl.addEventListener('hidden.bs.modal', function() {
            var alasan = document.getElementById('alasan_cancel');
            var errorDiv = document.getElementById('alasanError');
            if (alasan) {
                alasan.classList.remove('is-invalid');
                alasan.value = '';
            }
            if (errorDiv) {
                errorDiv.classList.add('d-none');
            }
        });
    }
});

// Zoom bukti foto pada detail view
function zoomProofPhotoDetail(srcImage) {
    const modalZoom = new bootstrap.Modal(document.getElementById('modalZoomProofDetail'));
    document.getElementById('imgZoomProofTargetDetail').src = srcImage;
    modalZoom.show();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


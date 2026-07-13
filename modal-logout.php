<!-- modal-logout.php -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true" style="z-index: 10000;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
        <div class="modal-content border-2 border-dark rounded-3 shadow-lg" style="background: #1e293b; color: #fff; box-shadow: 4px 4px #000 !important;">
            
            <div class="modal-header border-0 pb-0 justify-content-end">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body text-center pt-0 px-4">
                <div class="mb-3">
                    <img src="uploads/logo rsi.png" alt="Logo RSI" style="height: 65px; object-fit: contain;">
                </div>
                <h5 class="modal-title fw-bold mb-2" id="logoutModalLabel">Konfirmasi Keluar</h5>
                <p class="text-white-50 small mb-4">Apakah Anda yakin ingin keluar dari sistem keamanan aplikasi RSI FOOD &amp; MART?</p>
                
                <div class="row g-2 justify-content-center mt-3">
                    <div class="col-12" style="max-width: 280px;">
                        <a href="logout.php" class="btn btn-danger fw-bold py-2.5 rounded-3 border-2 border-dark shadow-sm text-white w-100" style="box-shadow: 3px 3px #000 !important; background-color: #dc2626 !important; border-color: #323232 !important;">
                            <i class="bi bi-box-arrow-left me-2"></i> Ya, Keluar Sekarang
                        </a>
                    </div>
                    <div class="col-12" style="max-width: 280px;">
                        <button type="button" class="btn btn-light fw-semibold py-2 rounded-3 border-2 border-dark w-100" data-bs-dismiss="modal" style="color: #323232;">
                            Batal
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

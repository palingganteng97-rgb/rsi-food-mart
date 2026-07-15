<?php
// bottom_nav.php - Reusable Bottom Navigation Component (PC & Mobile Responsif)
?>

<!-- Gaya CSS Khusus untuk Bottom Navigation Menetap di Bawah -->
<style>
  .bottom-nav-fixed {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      z-index: 1020;
      background: #0b1223;
      border-top: 1px solid rgba(148, 163, 184, 0.25);
      transition: margin-left .15s ease;
  }

  /* Mengatur tata letak responsif menetap di bawah layar PC / Desktop (min-width: 992px) */
  @media (min-width: 992px) {
      .bottom-nav-fixed {
          /* Otomatis bergeser ke kanan mengikuti lebar sidebar PC Anda agar tidak tertutup */
          margin-left: var(--sidebar-w, 280px); 
          background: rgba(11, 18, 35, 0.95);
          backdrop-filter: blur(10px);
      }
  }
</style>

<div class="bottom-nav-fixed">
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between gap-3 py-3">
            <div>
                <div class="text-white-50" style="font-size: .82rem;">Total Pesanan</div>
                <!-- PERBAIKAN: Mengubah ID dari cartTotalText menjadi totalCartItemsCount agar sinkron dengan JavaScript -->
                <div class="fw-bold text-white fs-5" id="totalCartItemsCount">
                    0 item
                </div>
            </div>
            
            <!-- PERBAIKAN: Menyatukan kembali tag button yang terpotong dan memasukkan atribut onclick dengan benar -->
            <button class="btn btn-success d-flex align-items-center" onclick="window.location.href='carts.php'">
                <i class="bi bi-basket2 me-2"></i> Lihat Keranjang 
            </button>
        </div>
    </div>
</div>

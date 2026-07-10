<style>
    /* =========================================================================
       STRUKTUR FIX SCROLL AKTIF HANYA DI DALAM MODAL (KONTEN)
       ========================================================================= */
    #modalDetailProduct .modal-body {
        max-height: 65vh !important;
        overflow-y: auto !important;
        -ms-overflow-style: none !important;  
        scrollbar-width: none !important;     
    }
    #modalDetailProduct .modal-body::-webkit-scrollbar {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
    }
    #modalDetailProduct {
        overflow-y: hidden !important;
    }
    #carouselDetailProduct, 
    #detail_product_carousel_inner, 
    .carousel-item {
        overflow: hidden !important;
        white-space: nowrap !important;
        -ms-overflow-style: none !important;  
        scrollbar-width: none !important;     
    }
    #detail_product_carousel_inner::-webkit-scrollbar,
    #carouselDetailProduct::-webkit-scrollbar {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
    }
    /* Mengunci paksa ukuran boks frame gambar */
    #detail_product_carousel_inner img {
        display: block !important;
        width: 250px !important;
        max-width: 250px !important;
        min-width: 250px !important;
        height: 220px !important;
        max-height: 220px !important;
        min-height: 220px !important;
        object-fit: cover !important;
        border-radius: 12px !important;
        margin: 0 auto !important;
    }

    /* PERBAIKAN CUSTOM CSS: Memaksa modal melebar di desktop tanpa merusak responsivitas mobile */
    @media (min-width: 576px) {
        #modalDetailProduct .modal-dialog {
            max-width: 550px !important; 
            width: 550px !important;
        }
    }
</style>

<!-- STRUKTUR UTAMA MODAL -->
<div class="modal fade" id="modalDetailProduct" aria-labelledby="modalDetailProductLabel" role="dialog" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.2); color: #e5e7eb; border-radius: 16px;">
            
            <!-- HEADER MODAL -->
            <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15);">
                <h5 class="modal-title fw-bold text-white" id="modalDetailProductLabel">Detail Produk</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- BODY MODAL -->
            <div class="modal-body text-center p-4">
                
                <!-- Pembungkus Utama Slider dengan Padding Samping -->
                <div class="position-relative px-5 mx-auto mb-3" style="max-width: 350px;">
                    
                    <!-- PERBAIKAN: Menghapus class carousel-fade agar efeknya kembali bergeser (slide) menyamping bawaan Bootstrap -->
                    <div id="carouselDetailProduct" class="carousel slide shadow rounded-3" data-bs-ride="carousel" style="max-width: 250px; margin: 0 auto; overflow: hidden !important;">
                        <div class="carousel-inner" id="detail_product_carousel_inner" style="overflow: hidden !important;">
                            <!-- Kontainer gambar dinamis akan disuntikkan dari berkas JavaScript -->
                        </div>
                    </div>

                    <!-- Tombol Navigasi Panah Kiri -->
                    <button class="carousel-control-prev" type="button" data-bs-target="#carouselDetailProduct" data-bs-slide="prev" id="carousel_btn_prev" 
                            style="width: 40px; height: 40px; top: 50%; transform: translateY(-50%); left: 0; position: absolute; background: rgba(30, 41, 59, 0.8); border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 50%;">
                        <span class="carousel-control-prev-icon" aria-hidden="true" style="width: 20px; height: 20px;"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    
                    <!-- Tombol Navigasi Panah Kanan -->
                    <button class="carousel-control-next" type="button" data-bs-target="#carouselDetailProduct" data-bs-slide="next" id="carousel_btn_next" 
                            style="width: 40px; height: 40px; top: 50%; transform: translateY(-50%); right: 0; position: absolute; background: rgba(30, 41, 59, 0.8); border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 50%;">
                        <span class="carousel-control-next-icon" aria-hidden="true" style="width: 20px; height: 20px;"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                    
                </div>

                <!-- Detail Teks Informasi Makanan -->
                <h4 id="detail_product_name" class="fw-bold text-white mb-1 mt-2"></h4>
                <p id="detail_product_category" class="text-muted small text-uppercase mb-3" style="letter-spacing: 1px;"></p>
                
                <!-- Boks Kontainer Deskripsi Hidangan -->
                <div class="p-3 rounded-3 mb-3 text-start" style="background: rgba(2, 6, 23, 0.4); border: 1px solid rgba(148, 163, 184, 0.1);">
                    <label class="small text-muted d-block mb-1">Deskripsi Hidangan:</label>
                    <span id="detail_product_description" class="text-white-50" style="font-size: 0.9rem;"></span>
                </div>
                
                <!-- HARGA & TOMBOL BELI -->
                <div class="d-flex justify-content-between align-items-center pt-2">
                    <div>
                        <span class="text-muted small d-block text-start">Harga</span>
                        <h4 id="detail_product_price" class="fw-bold text-success m-0"></h4>
                    </div>
                    <button type="button" id="btn_detail_add_cart" class="btn btn-success px-4 py-2 fw-medium rounded-3 d-flex align-items-center gap-2">
                        <i class="bi bi-cart-plus"></i> Tambah ke Keranjang
                    </button>
                </div>

            </div> <!-- /modal-body -->
        </div> <!-- /modal-content -->
    </div> <!-- /modal-dialog -->
</div> <!-- /modal -->

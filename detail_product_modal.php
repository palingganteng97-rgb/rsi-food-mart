<style>
    /* =========================================================================
       STRUKTUR MODAL PREMIUM: FIXED IMAGE LEFT, SCROLLABLE DETAILS RIGHT
       ========================================================================= */
    
    /* Matikan scroll pada body modal utama agar tidak double scrollbar */
    #modalDetailProduct .modal-body {
        overflow: hidden !important;
        padding: 0 !important; /* Diatur ulang di dalam kolom */
    }

    /* Kunci tinggi maksimal kolom kanan dan aktifkan scroll mandiri */
    #modalDetailProduct .scrollable-detail-column {
        max-height: 75vh !important;
        overflow-y: auto !important;
        -ms-overflow-style: none !important;  
        scrollbar-width: none !important;     
    }
    #modalDetailProduct .scrollable-detail-column::-webkit-scrollbar {
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

    /* Mengunci ukuran boks gambar kiri agar kokoh saat dislide */
    #detail_product_carousel_inner img {
        display: block !important;
        width: 100% !important;
        max-width: 380px !important;
        height: 340px !important;
        object-fit: cover !important;
        border-radius: 14px !important;
        margin: 0 auto !important;
    }

    @media (min-width: 1200px) {
        #modalDetailProduct .modal-dialog {
            max-width: 1100px !important; 
            width: 1100px !important;
        }
    }
</style>

<!-- STRUKTUR UTAMA MODAL -->
<div class="modal fade" id="modalDetailProduct" aria-labelledby="modalDetailProductLabel" role="dialog" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.96) !important; backdrop-filter: blur(16px); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb; border-radius: 20px;">
            
            <!-- HEADER MODAL -->
            <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15); padding: 1.25rem 2rem;">
                <h5 class="modal-title fw-bold text-white" id="modalDetailProductLabel">Detail Produk</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- BODY MODAL -->
            <div class="modal-body">
                <div class="row g-0 align-items-center">
                    
                    <!-- SISI KIRI: SLIDER GAMBAR (FIXED / TETAP DI TEMPAT) -->
                    <div class="col-md-5 text-center p-4 p-md-5" style="border-right: 1px solid rgba(148, 163, 184, 0.15);">
                        <div class="position-relative mx-auto" style="max-width: 380px;">
                            <div id="carouselDetailProduct" class="carousel slide shadow-lg rounded-4" data-bs-ride="carousel" style="overflow: hidden !important;">
                                <div class="carousel-inner" id="detail_product_carousel_inner" style="overflow: hidden !important;">
                                    <!-- Kontainer gambar dinamis disuntikkan dari berkas JavaScript -->
                                </div>
                            </div>

                            <!-- Tombol Navigasi Panah Kiri -->
                            <button class="carousel-control-prev" type="button" data-bs-target="#carouselDetailProduct" data-bs-slide="prev" id="carousel_btn_prev" 
                                    style="width: 44px; height: 44px; top: 50%; transform: translateY(-50%); left: -20px; position: absolute; background: rgba(30, 41, 59, 0.9); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 50%;">
                                <span class="carousel-control-prev-icon" aria-hidden="true" style="width: 22px; height: 22px;"></span>
                            </button>
                            
                            <!-- Tombol Navigasi Panah Kanan -->
                            <button class="carousel-control-next" type="button" data-bs-target="#carouselDetailProduct" data-bs-slide="next" id="carousel_btn_next" 
                                    style="width: 44px; height: 44px; top: 50%; transform: translateY(-50%); right: -20px; position: absolute; background: rgba(30, 41, 59, 0.9); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 50%;">
                                <span class="carousel-control-next-icon" aria-hidden="true" style="width: 22px; height: 22px;"></span>
                            </button>
                        </div>
                    </div>

                    <!-- SISI KANAN: DETAIL INFORMASI & MULTI-INPUT (BISA DI-SCROLL MANDIRI) -->
                    <div class="col-md-7 text-start p-4 p-md-5 scrollable-detail-column">
                        <h2 id="detail_product_name" class="fw-bold text-white mb-1" style="font-size: 2.25rem;"></h2>
                        <p id="detail_product_category" class="text-white-50 small text-uppercase mb-4" style="letter-spacing: 1.5px; opacity: 0.8;"></p>
                        
                        <!-- Boks Kontainer Deskripsi Hidangan -->
                        <div class="p-3 rounded-3 mb-4" style="background: rgba(2, 6, 23, 0.4); border: 1px solid rgba(148, 163, 184, 0.12); padding: 1.25rem; border-radius: 12px;">
                            <label class="small text-white-50 d-block mb-1.5" style="opacity: 0.7; font-weight: 500;">Deskripsi Hidangan:</label>
                            <span id="detail_product_description" class="text-light-50" style="font-size: 0.95rem; line-height: 1.6;"></span>
                        </div>

                        <!-- 1. PILIHAN VARIAN PRODUK -->
                        <div class="mb-4">
                            <label id="label_product_variant" for="detail_product_variant_select" class="small text-white-50 d-block mb-2" style="opacity: 0.7; font-weight: 500;">Pilih Varian / Opsi:</label>
                            <select id="detail_product_variant_select" class="form-select text-white border-secondary py-2 px-3" style="background: rgba(2, 6, 23, 0.4); border-radius: 10px; font-size: 0.92rem; box-shadow: none;">
                                <!-- Opsi varian disuntikkan dari JavaScript -->
                            </select>
                        </div>

                        <!-- 2. AREA INPUT TEXT UNTUK CATATAN CUSTOM -->
                        <div class="mb-4">
                            <label for="detail_product_notes_input" class="small text-white-50 d-block mb-2" style="opacity: 0.7; font-weight: 500;">Catatan Tambahan Pembeli (Opsional):</label>
                            <input type="text" id="detail_product_notes_input" class="form-control text-white border-secondary py-2.5 px-3" 
                                   style="background: rgba(2, 6, 23, 0.4); border-radius: 10px; font-size: 0.92rem; box-shadow: none;" 
                                   placeholder="Contoh: tidak usah pake sedotan, sendok plastik, pisah kuah...">
                        </div>
                        
                        <!-- HARGA & TOMBOL BELI -->
                        <div class="d-flex justify-content-between align-items-center pt-3 mt-4" style="border-top: 1px solid rgba(148, 163, 184, 0.15);">
                            <div>
                                <span class="text-white-50 small d-block mb-1" style="opacity: 0.7;">Harga Total</span>
                                <h3 id="detail_product_price" class="fw-bold text-success m-0" style="font-size: 1.75rem;"></h3>
                            </div>
                            <button type="button" id="btn_detail_add_cart" class="btn btn-success px-4 py-2.5 fw-medium rounded-3 d-flex align-items-center gap-2" style="border-radius: 10px !important;">
                                <i class="bi bi-cart-plus-fill"></i> Tambah ke Keranjang
                            </button>
                        </div>
                    </div>

                </div> <!-- /row -->
            </div> <!-- /modal-body -->
        </div> <!-- /modal-content -->
    </div> <!-- /modal-dialog -->
</div> <!-- /modal -->

<style>
    /* Mengaktifkan fungsi gulir/scroll utama pada isi modal */
    #modalDetailProduct .modal-body {
        overflow-y: auto !important;
        padding: 0 !important;
        max-height: 80vh; /* Membatasi tinggi isi modal agar tidak melebihi layar HP */
    }
    
    /* DESKTOP LAYAR LEBAR (Min-width 768px) */
    @media (min-width: 768px) {
        #modalDetailProduct .scrollable-detail-column {
            max-height: 52vh !important;
            overflow-y: auto !important;
            -ms-overflow-style: none !important;  
            scrollbar-width: none !important;     
        }
        #modalDetailProduct .scrollable-detail-column::-webkit-scrollbar {
            display: none !important;
        }
        #modalDetailProduct {
            overflow-y: hidden !important;
        }
        #modalDetailProduct .modal-body {
            max-height: none;
        }
    }

    /* MOBILE / HP (Max-width 767.98px) */
    @media (max-width: 767.98px) {
        #modalDetailProduct .scrollable-detail-column {
            max-height: none !important; 
            overflow-y: visible !important;
        }
        #detail_product_carousel_inner img {
            height: 240px !important; /* Menjaga agar gambar tidak terlalu memakan tempat di HP */
        }
    }

    #carouselDetailProduct, 
    #detail_product_carousel_inner, 
    .carousel-item {
        overflow: hidden !important;
        white-space: nowrap !important;
        -ms-overflow-style: none !important;  
        scrollbar-width: none !important;     
    }
    #detail_product_carousel_inner img {
        display: block !important;
        width: 100% !important;
        max-width: 380px !important;
        height: 340px; 
        object-fit: cover !important;
        border-radius: 14px !important;
        margin: 0 auto !important;
    }
    
    /* PERBAIKAN: Mengunci posisi footer di bawah (Sticky) untuk responsivitas mobile */
    #modalDetailProduct .fixed-product-footer {
        border-top: 1px solid rgba(148, 163, 184, 0.15);
        background: rgba(15, 23, 42, 0.95) !important; /* Dibuat pekat agar konten teks di belakangnya tersamar */
        backdrop-filter: blur(8px);
        margin-top: 20px;
        padding: 15px !important; /* Memastikan ruang klik lega di HP */
        position: sticky;
        bottom: 0;
        z-index: 10;
    }
    
    @media (min-width: 1200px) {
        #modalDetailProduct .modal-dialog {
            max-width: 1100px !important; 
            width: 1100px !important;
        }
    }
</style>

<div class="modal fade" id="modalDetailProduct" aria-labelledby="modalDetailProductLabel" role="dialog" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.96) !important; backdrop-filter: blur(16px); border: 1px solid rgba(148, 163, 184, 0.25); color: #e5e7eb; border-radius: 20px;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.15); padding: 1.25rem 2rem;">
                <h5 class="modal-title fw-bold text-white" id="modalDetailProductLabel">Detail Produk</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0"> 
                <div class="row g-0">
                    
                    <!-- KONTEN KIRI: Bagian Gambar (Dipastikan berpasangan dengan benar) -->
                    <div class="col-md-5 text-center p-4 p-md-5 border-bottom border-md-0 border-md-end" style="border-color: rgba(148, 163, 184, 0.15) !important;">
                        <div class="position-relative mx-auto" style="max-width: 380px;">
                            <div id="carouselDetailProduct" class="carousel slide shadow-lg rounded-4" data-bs-ride="carousel" style="overflow: hidden !important;">
                                <div class="carousel-inner" id="detail_product_carousel_inner" style="overflow: hidden !important;">
                                    <!-- Diisi otomatis oleh JavaScript openDetailProduct -->
                                </div>
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#carouselDetailProduct" data-bs-slide="prev" id="carousel_btn_prev" 
                                    style="width: 44px; height: 44px; top: 50%; transform: translateY(-50%); left: -20px; position: absolute; background: rgba(30, 41, 59, 0.9); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 50%;">
                                <span class="carousel-control-prev-icon" aria-hidden="true" style="width: 22px; height: 22px;"></span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#carouselDetailProduct" data-bs-slide="next" id="carousel_btn_next" 
                                    style="width: 44px; height: 44px; top: 50%; transform: translateY(-50%); right: -20px; position: absolute; background: rgba(30, 41, 59, 0.9); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 50%;">
                                <span class="carousel-control-next-icon" aria-hidden="true" style="width: 22px; height: 22px;"></span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- KONTEN KANAN: Bagian Form Isian dan Tombol -->
                    <div class="col-md-7 p-4 p-md-5 d-md-flex flex-md-column justify-content-between">
                        <div class="scrollable-detail-column pe-2"> 
                            <h2 id="detail_product_name" class="fw-bold text-white mb-1" style="font-size: 2.25rem;"></h2>
                            <p id="detail_product_category" class="text-white-50 small text-uppercase mb-4" style="letter-spacing: 1.5px; opacity: 0.8;"></p>
                            
                            <div class="p-3 rounded-3 mb-4" style="background: rgba(2, 6, 23, 0.4); border: 1px solid rgba(148, 163, 184, 0.12); border-radius: 12px;">
                                <label class="small text-white-50 d-block mb-1.5" style="opacity: 0.7; font-weight: 500;">Deskripsi Hidangan:</label>
                                <span id="detail_product_description" class="text-light-50" style="font-size: 0.95rem; line-height: 1.6;"></span>
                            </div>
                            
                            <div class="mb-4">
                                <label id="label_product_variant" for="detail_product_variant_select" class="small text-white-50 d-block mb-2" style="opacity: 0.7; font-weight: 500;">Pilih Varian / Opsi:</label>
                                <select id="detail_product_variant_select" class="form-select text-white border-secondary py-2 px-3" style="background: rgba(2, 6, 23, 0.4); border-radius: 10px; font-size: 0.92rem; box-shadow: none;">
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="detail_product_addons_select" class="small text-white-50 d-block mb-2" style="opacity: 0.7; font-weight: 500;">Pilih Topping / Tambahan:</label>
                                <select id="detail_product_addons_select" class="form-select bg-dark text-white border-secondary py-2 px-3" style="border-radius: 10px; font-size: 0.92rem; box-shadow: none;">
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="detail_product_notes_input" class="small text-white-50 d-block mb-2" style="opacity: 0.7; font-weight: 500;">Catatan Tambahan Pembeli (Opsional):</label>
                                <input type="text" id="detail_product_notes_input" class="form-control text-white border-secondary py-2.5 px-3" 
                                       style="background: rgba(2, 6, 23, 0.4); border-radius: 10px; font-size: 0.92rem; box-shadow: none;" 
                                       placeholder="Contoh: tidak usah pake sedotan, sendok plastik, pisah kuah...">
                            </div>
                            
                            <div class="mt-4 pt-4" style="border-top: 1px solid rgba(148, 163, 184, 0.15);">
                                <h5 class="fw-bold text-white mb-3 d-flex align-items-center gap-2">
                                    <i class="bi bi-chat-left-heart-fill text-warning"></i> Ulasan & Testimoni Pasien
                                </h5>
                                <div id="detail_product_reviews_container" class="d-flex flex-column gap-3">
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center fixed-product-footer mt-auto pt-3">
                            <div>
                                <span class="text-white-50 small d-block mb-1" style="opacity: 0.7;">Harga Total</span>
                                <h3 id="detail_product_price" class="fw-bold text-success m-0" style="font-size: 1.75rem;"></h3>
                            </div>
                            <button type="button" id="btn_detail_add_cart" class="btn btn-success px-4 py-2.5 fw-medium rounded-3 d-flex align-items-center gap-2" style="border-radius: 10px !important;">
                                <i class="bi bi-cart-plus-fill"></i> Tambah ke Keranjang
                            </button>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

# TODO - Perbaikan Bug Modal Backdrop promos.php

## Root Cause
Fungsi `openEditPromo()` membuat instance `new bootstrap.Modal()` baru setiap kali dipanggil → instance ganda mengelola DOM yang sama → event `hidden.bs.modal` bentrok → backdrop tidak bersih.

## Steps

- [x] Step 1: Hapus `data-bs-toggle` dan `data-bs-target` dari tombol "Tambah Promo" (gunakan JS saja)
- [x] Step 2: Tambah variabel global `bootstrapModalPromoInstance` (singleton)
- [x] Step 3: Inisialisasi singleton modal di `DOMContentLoaded` sekali saja
- [x] Step 4: Tambah event `hidden.bs.modal` untuk cleanup darurat (hapus backdrop, class modal-open, style inline)
- [x] Step 5: Update `openTambahPromo()` — panggil `bootstrapModalPromoInstance.show()`
- [x] Step 6: Update `openEditPromo()` — gunakan singleton instance, jangan buat instance baru
- [x] Step 7: Hapus CSS `.modal-body overflow-y: auto !important` yang bentrok dengan Bootstrap


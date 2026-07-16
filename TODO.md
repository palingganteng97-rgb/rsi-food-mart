# TODO - Fix Keranjang & Alur Checkout (RSI_FOOD&MART)

## Progress
- [x] Periksa dan baca `carts.php`, identifikasi potensi penyebab redirect palsu & parsing JS
- [x] Refactor `carts.php` agar selalu menampilkan item dari database (`carts` + `cart_items`) dan tidak mengandalkan session cart lama
- [x] Pastikan JavaScript di `carts.php` tidak terpotong dan string PHP ke JS memakai `json_encode`

## Remaining (cek lintas file)
- [ ] Audit `checkout_process.php` agar tidak menghapus/invalidasi session sebelum checkout selesai dan agar cart yang valid berdasarkan patient_session_id diambil konsisten
- [ ] Pastikan `api_cart.php` dan tombol "Tambah ke Keranjang" benar-benar menulis ke `cart_items` yang terbaca oleh `carts.php` untuk pasien yang sama
- [ ] Audit file terkait: `home.php`, `detail_product_modal.php`, `catalog_handler.js`, `cart_items.php`, `orders.php`, `order_items.php` (JOIN/kolom & filter)
- [ ] Jalankan uji alur end-to-end: QR Scan → Form Pasien → Home → Detail Produk → Tambah Ke Keranjang → carts.php → Edit → Checkout → orders & order_items


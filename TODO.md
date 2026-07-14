# TODO - Samakan Modal Detail Produk (Home vs Keranjang Edit)

## Step 0 - Verifikasi baseline
- [x] Baca `home.php`, `keranjang.php`, `catalog_handler.js`, `detail_product_modal.php`
- [x] Pastikan `openDetailProduct(data)` dipakai dari home dan tidak dari keranjang saat ini

## Step 1 - Rancang plan identik seperti requirement
- [ ] Ubah `keranjang.php` agar tombol “Edit Pesanan” membuka `#modalDetailProduct`
- [ ] Pastikan keranjang memanggil `openDetailProduct(data)` dengan object yang identik dengan `$prod` home (field utama sama)
- [ ] Atur mode edit: footer berubah jadi “Simpan Perubahan” dan submit melakukan `api_cart.php?action=update_saved`

## Step 2 - Implementasi object produk utama untuk keranjang
- [ ] Kumpulkan field yang diperlukan `openDetailProduct`:
  - `id, name, category_name, description, base_price, image`
- [ ] Dari session cart item, ambil `id` lalu query produk+category untuk membentuk object utama

## Step 3 - Reuse `openDetailProduct()` tanpa duplikasi
- [ ] Pastikan varian/addons/gallery/reviews tetap dimuat oleh `openDetailProduct` yang sama
- [ ] Hanya set data awal (selected variant & selected addons) agar checkbox/option ter-tick sesuai item cart

## Step 4 - Update backend update_saved
- [ ] Pastikan `api_cart.php?action=update_saved` membaca payload `old_key, id, name, price, image, notes, variant, addons[]` dan menyimpan struktur session yang kompatibel

## Step 5 - Cleanup UI konflik
- [ ] Nonaktifkan/ignore modal edit lama `#modalEditPesanan` agar tidak bentrok

## Step 6 - Testing manual
- [ ] Test dari home: klik produk -> modal tampil benar
- [ ] Test dari keranjang: klik “Edit Pesanan” -> modal tampil sama
- [ ] Verifikasi tanda varian & topping terpilih, catatan terisi, dan “Simpan Perubahan” meng-update item (bukan menambah item baru)


# TODO - Varian & Topping POS Cart (carts.php)

- [ ] Buat query SQL: ALTER TABLE cart_items tambah kolom `variant` (jika belum ada)
- [ ] Buat tabel relasi: `cart_item_addons` (id, cart_item_id, addon_item_id) + FK ON DELETE CASCADE
- [ ] Update fungsi PHP `fetch_cart_items($conn,$cart_id)` untuk:
  - [ ] Ambil `variant` dari `cart_items`
  - [ ] Ambil topping terpilih dari `cart_item_addons` join `addon_items`
  - [ ] Kembalikan `base_price_only` (harga produk dasar) dan `addons` terpilih + total topping per item
  - [ ] Set `unit_price` = base_price_only + sum(addon harga)
- [ ] Update handler `update_item`:
  - [ ] UPDATE `cart_items` untuk qty dan variant
  - [ ] DELETE topping lama dari `cart_item_addons`
  - [ ] INSERT topping baru berdasarkan `$_POST['addons']` (array addon_item_id)
- [ ] Ubah HTML modal Edit Pesanan:
  - [ ] Saat modal dibuka, tampilkan semua topping dari master via `fetch_addon_items_by_product`
  - [ ] Checkbox topping di-checked otomatis berdasarkan selected addon yang tersimpan
  - [ ] JS live: subtotal berubah saat checkbox topping diubah (base_price_only + addons_checked_total) * qty
  - [ ] Pastikan submit mengirim `addons[]` (addon_item_id list) + `variant` + `qty`
- [ ] Uji manual di browser sesuai 3 skenario utama


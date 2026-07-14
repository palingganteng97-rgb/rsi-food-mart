- [ ] Buat audit menyeluruh semua penggunaan `$_SESSION['cart']` di repo (tanpa ripgrep)
- [ ] Refactor `api_cart.php`: hapus `$_SESSION['cart']` sepenuhnya, migrasikan `get_item` & `update_saved` ke DB (carts/cart_items)
- [ ] Refactor `carts.php`: ubah seluruh action (add_to_cart, update_qty, update_item, delete) agar CRUD langsung ke DB cart_items/carts; hapus `$_SESSION['cart']`
- [ ] Refactor `cart_item.php`: tampilkan detail dari DB (cart_items + carts + products/addons bila perlu), bukan dari session
- [ ] Refactor `checkout_process.php`: ambil cart dari DB (carts/cart_items) bukan session; simpan ke orders/order_items; bersihkan cart di DB (opsional tapi disarankan)
- [ ] Pastikan JS `openEditPesananFromCart()` dan endpoint yang dipanggil tetap bekerja tanpa ubah tampilan
- [ ] Update `catalog_handler.js` jika perlu untuk payload notes/variant/addons agar update DB sesuai
- [ ] Smoke test: tambah produk, edit item, tambah/kurang qty, hapus item, checkout


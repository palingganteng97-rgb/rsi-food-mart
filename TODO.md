# RSI_FOOD&MART - Tenant Reviews (Admin) Read-only Viewer

## Plan
- [x] Inspect `tenant_reviews.php` (sebelumnya viewer-only tapi masih pakai tabel dan sumber data `tenant_reviews`).
- [x] Identifikasi sumber insert review pasien: `submit_review.php` → `product_reviews`.
- [ ] Update `tenant_reviews.php` agar:
  - [ ] tidak menampilkan struktur tabel CRUD-style
  - [ ] membaca review dari `product_reviews`
  - [ ] join ke `products` → `tenants` untuk nama tenant + nama produk
  - [ ] join ke `patient_sessions` untuk nama pasien (berdasarkan `patient_session_id`)
  - [ ] tampilkan card/list feed seperti marketplace
  - [ ] tampilkan foto review bila kolom tersedia (tanpa membuat kolom baru)
  - [ ] tetap sediakan filter: pencarian, tenant_id, rating, date_from, date_to
- [ ] Test manual di browser.


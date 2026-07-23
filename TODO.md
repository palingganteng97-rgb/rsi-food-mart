# TODO: Perbaikan Notifikasi & Riwayat Pesanan

## 1. deliveries.php — Tambah notifikasi pasien saat CREATE (Kurir ditugaskan)
- [x] Baca file untuk memahami struktur
- [x] Tambah patient notification di blok `action === 'create'` setelah INSERT sukses
  - Ambil data order (order_number, patient_session_id)
  - Panggil `createNotification('patient', ...)` dengan judul "Kurir Ditugaskan"
- [x] Verifikasi `notification_helper.php` sudah di-include

## 2. deliveries.php — Verifikasi notifikasi UPDATE (sudah berjalan)
- [x] Baca file dan verifikasi kode notifikasi pasien di blok `action === 'update'`
- [x] Konfirmasi sudah berfungsi untuk semua perubahan status pengiriman
- [x] Pastikan pesan notifikasi informatif (sudah)

## 3. riwayat_pesanan.php — DETAIL mode: Tambah badge Status Pesanan
- [x] Baca file dan identifikasi lokasi penambahan
- [x] Tambah fungsi `orderStatusBadge()` untuk mapping warna badge
- [x] Tambah baris badge "Status Pesanan" di info card DETAIL view
- [x] Gunakan `orders.status` sebagai sumber data (sudah tersedia di `$order['status']`)

## 4. riwayat_pesanan.php — LIST mode: Tambah badge Status Pesanan
- [x] Baca file dan identifikasi lokasi penambahan
- [x] Verifikasi SQL query sudah select `o.status`
- [x] Tambah badge "Status Pesanan" di card loop LIST view
- [x] Gunakan `$ord['status']` sebagai sumber data

## 5. Testing & Verifikasi
- [x] Cek tidak ada syntax error PHP (No syntax errors detected - deliveries.php ✓, riwayat_pesanan.php ✓)
- [x] Cek styling badge konsisten (menggunakan fungsi yang sama dengan badge yang sudah ada)
- [x] Cek tidak ada perubahan pada file lain
- [x] Cek tidak ada perubahan struktur database


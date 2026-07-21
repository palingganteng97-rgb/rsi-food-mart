# TODO: Fitur Alasan Pembatalan Pesanan

## Step 1 - Database
- [x] Buat file pengecekan kolom (__check_column.php)
- [x] Jalankan ALTER TABLE untuk menambah kolom `cancel_reason TEXT DEFAULT NULL` di tabel `orders`

## Step 2 - riwayat_pesanan.php: Modal Cancel
- [x] Tambahkan textarea `alasan_cancel` di modal konfirmasi
- [x] Tambahkan validasi JavaScript (alasan tidak boleh kosong)
- [x] Update form handler PHP POST untuk menyimpan `cancel_reason` ke database

## Step 3 - riwayat_pesanan.php: Tampilkan Alasan
- [x] Tampilkan alasan pembatalan di detail setelah cancel sukses
- [x] Tampilkan alasan pembatalan di detail order jika status cancelled

## Step 4 - Cleanup
- [x] Hapus file sementara `__check_column.php` dan `_run_alter_cancel_reason.php`


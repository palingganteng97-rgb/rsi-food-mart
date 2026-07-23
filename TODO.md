# TODO: Perbaikan Bug `deliveries.php` - Stuck di `?status=success_create`

## Root Cause
Semua status message (success/error) dikirim via URL query parameter, menyebabkan:
- URL tidak bersih setelah redirect
- Jika user refresh, alert muncul lagi
- Parameter status tetap di URL secara permanen

## Solusi
Ganti URL-based flash messages dengan **Session-based Flash Messages**.

## Status Pengerjaan

### [x] 1. Buat TODO.md

### [x] 2. Edit `deliveries.php` - Ubah semua redirect di blok `action=create`
   - [x] Ganti `header("Location: deliveries.php?status=success_create")` → session flash + `deliveries.php`
   - [x] Ganti `header("Location: deliveries.php?status=error&msg=...")` → session flash
   - [x] Tambahkan validasi fallback redirect untuk `$order_id <= 0` atau `$courier_id <= 0`

### [x] 3. Edit `deliveries.php` - Ubah semua redirect di blok `action=update`
   - [x] Ganti `header("Location: deliveries.php?status=success_update")` → session flash
   - [x] Ganti error redirect → session flash

### [x] 4. Edit `deliveries.php` - Ubah semua redirect di blok `action=delete`
   - [x] Ganti `header("Location: deliveries.php?status=success_delete")` → session flash
   - [x] Ganti error redirect → session flash
   - [x] Fix syntax error (missing `&& isset($_GET['id'])`)

### [x] 5. Edit `deliveries.php` - Ubah rendering alert
   - [x] Baca flash message dari `$_SESSION` bukan `$_GET`
   - [x] Tampilkan alert berdasarkan session flash
   - [x] Hapus/unset flash message setelah ditampilkan
   - [x] Hapus variabel `$status` dan `$msg` dari `$_GET`

### [x] 6. Verifikasi syntax PHP
   - [x] File `deliveries.php` valid, tidak ada syntax error


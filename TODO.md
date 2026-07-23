# TODO: Perbaikan Mekanisme Penghapusan File

## Ringkasan
Berdasarkan hasil analisis dan persetujuan user, file yang perlu diubah:
- **brands.php** (delete operation)
- **master_barcode.php** (edit operation)

**Tidak diubah** (sudah benar atau tidak diperlukan):
- **banners.php** (sudah benar — file referensi)
- **products.php** (soft delete + ada fitur restore → jangan hapus file fisik)
- **deliveries.php** (sudah mengikuti pola yang benar)

---

## Step 1: brands.php — Perbaiki Delete Operation
- [x] Ambil `logo` dari database SEBELUM operasi hapus
- [x] Cek `file_exists()` → `unlink()` file logo terlebih dahulu
- [x] Gunakan transaksi (`mysqli_begin_transaction`)
- [x] `UPDATE products SET brand_id = NULL WHERE brand_id = ?`
- [x] `DELETE FROM brands WHERE id = ?`
- [x] `commit()` jika sukses, `rollback()` jika gagal
- [x] Ganti `@unlink` dengan `error_log()` jika gagal

## Step 2: master_barcode.php — Perbaiki Edit Operation (op=edit)
- [x] Ambil `room_name` lama dari DB sebelum update
- [x] Hitung nama file QR lama
- [x] Update room_name + generate QR baru
- [x] Jika generate QR baru sukses → hapus file QR lama
- [x] Jika generate QR baru gagal → jangan hapus file lama
- [x] Gunakan `file_exists()` dan `error_log()` untuk unlink

## Step 3: Verifikasi Final
- [x] Pastikan path upload benar sesuai masing-masing modul
- [x] Pastikan tidak ada `@unlink()` — ganti dengan `error_log()`
- [x] Pastikan placeholder/default image tidak terhapus


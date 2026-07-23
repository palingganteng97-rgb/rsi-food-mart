# Rencana Perbaikan Notifikasi & Tampilan Data Pasien

## Masalah
1. Home.php menampilkan ID pasien bukan nama
2. Notifikasi tidak masuk ke akun pasien saat status pesanan berubah
3. Tidak ada notifikasi saat pesanan berhasil dibuat
4. Bug pada bind_param di notification_helper.php

## Perbaikan

### 1. notification_helper.php
- **Bug**: `$stmt->bind_param("siss", ...)` — 4 type specifiers untuk 5 parameter
- **Perbaikan**: Ganti menjadi `"sisss"` (string, integer, string, string, string)

### 2. home.php
- Tambahkan fallback query ke tabel `patient_sessions` jika `$_SESSION['patient_name']` tidak ada
- Simpan kembali ke session agar tidak query ulang
- Gunakan `htmlspecialchars()` saat menampilkan

### 3. checkout_process.php
- Setelah COMMIT transaksi berhasil, tambahkan notifikasi ke pasien
- `createNotification('patient', $patient_session_id, 'Pesanan Berhasil Dibuat', '...', 'riwayat_pesanan.php?id=' . $new_order_id)`

### 4. orders.php (action=update_status)
- Sebelum UPDATE, ambil status lama dari database
- Setelah UPDATE, cek apakah status benar-benar berubah
- Jika berubah, ambil `patient_session_id` dari tabel orders
- Buat notifikasi ke pasien
- Tambahkan error_log sementara untuk debugging

### 5. Verifikasi
- Pastikan `get_notifications.php` dan `notifications.php` menggunakan `user_type='patient'` dan `user_reference = patient_session_id` ✅ (sudah benar)
- Pastikan link notifikasi mengarah ke `riwayat_pesanan.php?id=ORDER_ID` ✅


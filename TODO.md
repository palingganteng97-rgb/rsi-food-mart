# TODO - RSI_FOOD&MART (Template Dark Mode)

- [x] Buat `sidebar.php` (mobile topbar + desktop fixed sidebar + offcanvas + active menu via JS)
- [x] Buat `index.php` (redirect ke `home.php` jika session user_id aktif)
- [x] Buat `home.php` (grid katalog dummy + search + filter diet + bottom nav mobile)
- [x] Buat `profile.php` (fetch data user dari MySQL via mysqli berdasarkan `$_SESSION['user_id']`)
- [ ] Uji di browser:
  - [ ] Pastikan redirect di `index.php`
  - [ ] Pastikan sidebar tidak bertabrakan dengan main pada HP & laptop
  - [ ] Pastikan class `.active` hijau muncul sesuai halaman
- [ ] Jika struktur DB/koneksi berbeda, sesuaikan kredensial & nama tabel/kolom di `profile.php`

# TODO - Auth UI
- [ ] Perbaiki tampilan `login.php` jadi Bootstrap dark card layout (tanpa ubah logika).
- [ ] Perbaiki tampilan `register.php` jadi Bootstrap dark card layout (tanpa ubah logika).
- [ ] Perbaiki tampilan `lupa-password.php` jadi Bootstrap dark card layout (tanpa ubah logika).
- [ ] Uji di browser: login/register/lupa-password.


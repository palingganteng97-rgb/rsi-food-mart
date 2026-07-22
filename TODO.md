# TODO - Perbaikan Sistem

## Status: ✅ Selesai / ⏳ Dalam Proses / ❌ Belum

### 1. LOGIN ✅
- [x] `login.php`: Baca `$_SESSION['flash_error']` sebagai `$error`
- [x] `index.php`: Seragamkan pesan error menjadi "Username / Email dan password salah, silahkan coba lagi"

### 2. ROLES ✅
- [x] `roles.php`: Perbaiki JS populateEditRoleModal (edit_role_id → edit-role-id)
- [x] `roles.php`: Ubah name input form (id → edit_id, name → update_name)
- [x] `roles.php`: Tambah validasi duplicate name (case insensitive, exclude current)
- [x] `roles.php`: Perbaiki tombol delete ($row → $roleRow)

### 3. PERMISSIONS ✅
- [x] `permissions.php`: Validasi duplicate module_name (trim, strtolower)
- [x] `permissions.php`: Update validasi exclude record sendiri
- [x] `permissions.php`: Ganti query raw ke prepared statement

### 4. ROLE PERMISSIONS ✅
- [x] `role_permissions.php`: Backend handler action_save_row
- [x] `role_permissions.php`: Checkbox auto-tercentang dari database
- [x] `role_permissions.php`: Simpan relasi tanpa duplicate

### 5. TENANT ✅
- [x] `tenants.php`: Pindahkan field Email & Waktu Persiapan ke dalam modal-body (sebelumnya di bawah footer)
- [x] `tenants.php`: Hapus position:absolute dari footer modal
- [x] `tenants.php`: Perbaiki urutan field: Nama → Email → Phone → Alamat → Waktu Persiapan
- [x] `tenants.php`: Pastikan Edit juga menggunakan urutan yang sama

### Final Check ✅
- [x] `user.php`: Perbaiki $row → $userRow pada tombol delete
- [x] Cek tidak ada error JavaScript - Duplikat modal `modalDeletePermission` sudah dihapus
- [x] Cek tidak ada error PHP - Semua query menggunakan prepared statement
- [x] Cek tidak ada error SQL - Prepared statement mencegah SQL injection
- [x] Cek semua modal dapat dibuka/tutup normal - Footer modal tenant sudah tidak absolute


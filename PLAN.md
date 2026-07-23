# PLAN - Perbaikan Bug & Penyesuaian

## 1. LOGIN - Error Message Fix

**Issues Found:**
- `index.php` sets `$_SESSION['flash_error']` on failed login and redirects to `login.php`
- `login.php` does NOT read `$_SESSION['flash_error']` — it only checks a local `$error` variable
- Error message says "Login gagal. Username/Email atau password salah." instead of requested message

**Fixes:**
1. `login.php`: Add PHP block at top to check `$_SESSION['flash_error']`, set it to `$error`, then unset the session variable
2. `index.php`: Change error message to "Username / Email dan password salah, silahkan coba lagi."

---

## 2. ROLES

### A. Modal Update Issues

**Issues Found:**
1. `populateEditRoleModal()` JS function tries to set `edit_role_id` and `edit_role_name` (underscore format), but the HTML has `id="edit-role-id"` and `id="edit-role-name"` (hyphen format) — mismatched IDs!
2. Form input `name="id"` and `name="name"` don't match PHP handler which expects `$_POST['edit_id']` and `$_POST['update_name']`

**Fixes:**
1. Fix JS `populateEditRoleModal()` to use correct IDs: `document.getElementById('edit-role-id')` and `document.getElementById('edit-role-name')`
2. Change form input names to `edit_id` and `update_name` to match PHP handler
3. Also fix: The button calls both `data-bs-toggle` and `populateEditRoleModal()` onclick — remove `data-bs-toggle` from edit button, let `populateEditRoleModal()` handle showing the modal

### B. Update Data - Validation Fix

**Issues Found:**
- No duplicate name check during update (only in create)
- The error "Input nama role tidak valid." appears because form field names mismatch PHP expected keys

**Fixes:**
1. Add duplicate check in update handler: `SELECT id FROM roles WHERE name = ? AND id != ?`
2. If name exists for another role, show error "Nama role sudah digunakan oleh role lain."

### C. Hapus Data - Delete Button Not Working

**Issues Found:**
1. `$row` is used but the loop variable is `$roleRow` — `$row` is undefined
2. `$row['role_name']` doesn't exist — column is `name`, not `role_name`
3. `$row['id']` should be `$roleRow['id']`
4. Orphaned `</a>` closing tag

**Fixes:**
1. Fix all `$row` references to use `$roleRow`
2. Change `$row['role_name']` to `$roleRow['name']`
3. Fix `$row['id']` to `$roleRow['id']`
4. Remove orphaned `</a>` tag

---

## 3. PERMISSIONS - Duplicate Module Name Validation

**Issues Found:**
- No duplicate `module_name` check on CREATE or UPDATE

**Fixes:**
1. On CREATE: Before insert, check `SELECT id FROM permissions WHERE module_name = ?`. If exists, show error "Module Name sudah terdaftar!"
2. On UPDATE: Before update, check `SELECT id FROM permissions WHERE module_name = ? AND id != ?`. If exists for other record, show error
3. Use prepared statements for security (currently uses mysqli_real_escape_string)
4. Show validation error on the permissions page (use query string status/msg pattern)

---

## 4. TENANT - Phone Field & Form Order

**Issues Found:**
- Phone field already exists in the modal form — but the user asks to add it (maybe it was missing before). It's already present.
- Currently the form order is: Nama Tenant → Tipe Tenant → Upload Logo → Deskripsi → **Phone → Email → Waktu Persiapan**

**Fixes:**
1. Verify and ensure the form order matches: Nama Tenant → ... (other existing fields) → **Phone → Email → Waktu Persiapan (Menit)**
2. The current order in tenants.php is already correct: Nama, Tipe, Logo, Deskripsi, Phone, Email, Prep Time
3. Make sure edit modal (populate) also reads/loads phone correctly — already does with `data.phone`
4. No structural DB changes needed

---

## KETENTUAN UMUM
- Tidak mengubah struktur database
- Tidak mengubah UI di luar bagian yang diminta
- Pertahankan style Bootstrap yang sudah ada
- Pastikan Create, Edit, Delete, dan Validasi berjalan normal


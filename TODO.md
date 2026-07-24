# Implementation Complete: Recycle Bin / Trash for Products

## Changes Made

### 1. `products.php`
- ✅ **Permanent Delete handler** (`action_permanent_delete`): Deletes record + image file from disk
- ✅ **View toggle**: Switches between "Produk Aktif" and "Recycle Bin" via `?view=trash` parameter
- ✅ **Active Products query**: `WHERE p.deleted_at IS NULL` (unchanged)
- ✅ **Trash Products query**: `WHERE p.deleted_at IS NOT NULL ORDER BY p.deleted_at DESC`
- ✅ **Trash count badge**: Shows number of deleted products on the Recycle Bin tab
- ✅ **Recycle Bin table**: Displays photo (with grayscale), name, SKU, category, price, stock, deleted_at date
- ✅ **Restore button**: Uses existing `action_restore` handler with `&view=trash` redirect
- ✅ **Permanent Delete button**: Opens confirmation modal, permanently deletes data + image
- ✅ **Updated soft delete modal**: Clarifies product goes to Recycle Bin (not permanently deleted)
- ✅ **New permanent delete modal**: Warning that action cannot be undone
- ✅ All existing CRUD logic preserved

### 2. `sidebar.php`
- ✅ **Recycle Bin menu item** added under "Produk" group
- ✅ **Smart active detection**: Data Produk highlighted on normal view, Recycle Bin highlighted on trash view
- ✅ **Produk group opens** when on either products page or trash view
- ✅ Works in both desktop sidebar and mobile offcanvas

## Verified
- ✅ No PHP syntax errors in either file
- ✅ Database structure unchanged
- ✅ `home.php` still filters `deleted_at IS NULL` (patient view unaffected)
- ✅ Soft delete mechanism preserved
- ✅ Restore sets `deleted_at = NULL` (product returns to active list)


# TODO - Soft Delete Consistency (Recycle Bin)

## Step 1 (Admin UI)
- Update `products.php`:
  - [x] Show ALL products (aktif + terhapus)
  - [x] Add columns: Created At, Updated At, Deleted At, Status Data
  - [x] Badge: Aktif / Terhapus
  - [x] Row styling for deleted products
  - [x] Disable Edit & Hapus when deleted
  - [x] Add Restore button for deleted products
  - [x] Implement backend action `action_restore`. 


## Step 2 (Admin dropdown relation)
- Update `product_variants.php` and `product_addons.php`:
  - Dropdown products should include ALL products (no `deleted_at IS NULL` filter).

## Step 3 (Operational AJAX filters)
- Update `get_variants.php`:
  - [x] Only return variants whose product is active (`products.deleted_at IS NULL`).
- Update `get_addon_items.php`:
  - [x] Only return addon items whose parent product is active.


## Step 4 (Operational cart & checkout safety)
- Update `api_cart.php`:
  - [x] When adding cart item, reject if product is soft-deleted.
  - [x] When fetching cart items, exclude soft-deleted products.
- Update `checkout_process.php`:
  - [x] Before writing `order_items`, validate every cart item’s product is active.
  - [x] If any item is deleted, skip them and recalc totals (fail-safe).


## Step 5 (Verification)
- Manual testing checklist:
  - Admin products.php shows deleted products and can restore.
  - Home & detail modal never allow deleted products.
  - Dropdown varian/topping from modal never include deleted products.
  - Adding to cart and checkout reject deleted products.


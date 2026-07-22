# TODO: Patient Notification Architecture Fix

## Step 1: Fix `deliveries.php` - Create action
- [x] Remove admin notification (`createNotification` for admin)
- [x] No patient notification on initial Pending creation
- [x] Only redirect with success

## Step 2: Fix `deliveries.php` - Update action
- [x] Fix duplicate prevention: check by `link + message` instead of `title + link`
- [x] Define `$notifMessage` BEFORE the duplicate check SQL
- [x] Keep patient notification logic for status changes
- [x] Ensure `oldStatus != newStatus` check remains

## Step 3: Fix `deliveries.php` - Delete action
- [x] Remove admin notification entirely

## Step 4: Audit `proses_tambah_pengiriman.php`
- [x] No notification logic present - only creates delivery records
- [x] No changes needed

## Step 5: Fix `sidebar_pasients.php`
- [x] Add `.topbar-right` container div in mobile topbar for notification bell
- [x] Add `.sidebar-notification-area` in sidebar footer for desktop patients

## Step 6: Fix `notifications.js`
- [x] Add `.mobile-topbar .topbar-right` selector for patient mobile bell injection
- [x] Add `.sidebar-notification-area` detection for patient desktop sidebar
- [x] Preserve existing `.sidebar-footer` injection for admin pages

## Step 7: Verification
- [x] All changes implemented
- [x] No modifications to home.php, notifications.php, get_notifications.php
- [x] Delivery create (Pending) → No notification
- [x] Delivery status change → One notification per unique status
- [x] Duplicate prevention uses `link + message` for uniqueness
- [x] Admin no longer receives delivery notifications
- [x] Only patient owning the order receives notifications


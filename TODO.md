# TODO - Real-time Notification Feature

## Completed Steps

- [x] **Step 1:** Add `link` column to notifications table
- [x] **Step 2:** Create `notification_helper.php` — Reusable helper function `createNotification()`
- [x] **Step 3:** Create `get_notifications.php` — AJAX JSON endpoint with `since_id` polling support
- [x] **Step 4:** Create `mark_notification_read.php` — AJAX POST endpoint to mark one/all as read
- [x] **Step 5:** Create `notifications.js` — Full client-side with:
  - Browser Notification API (permission only once)
  - Sound notification (notification.wav)
  - Badge counter (🔔 3)
  - Dropdown with scroll, "Lihat Semua", max 10 items
  - Mark as read on click (AJAX)
  - Relative time display (e.g., "2 menit lalu")
  - Polling every 5 seconds
  - Track shown notifications to avoid duplication
- [x] **Step 6:** Modify `sidebar.php` — Added notification bell + dropdown + JS include
- [x] **Step 7:** Modify `sidebar_pasients.php` — Added notification bell + dropdown + JS include
- [x] **Step 8:** Modify `notifications.php` — Full notification history page with pagination
- [x] **Step 9:** Create notification sound file (`assets/notification.wav`)

## Files Created
1. `_add_notification_link.php` — DB migration script (already ran)
2. `notification_helper.php` — Helper function
3. `get_notifications.php` — AJAX JSON endpoint
4. `mark_notification_read.php` — Mark read endpoint
5. `notifications.js` — Client-side JavaScript
6. `assets/notification.wav` — Notification sound
7. `assets/generate_sound.php` — Sound generator script

## Files Modified
8. `sidebar.php` — Added bell icon + dropdown + JS
9. `sidebar_pasients.php` — Added bell icon + dropdown + JS
10. `notifications.php` — Full history page (was empty before)

## How to Use

### Create a notification from anywhere:
```php
include 'notification_helper.php';

// For admin user
createNotification('admin', $userId, 'Pesanan Baru', 'Kamar 302 memesan Nasi Rendah Garam', 'orders.php?id=15');

// For patient
createNotification('patient', $patientSessionId, 'Pesanan Siap', 'Pesanan Anda sudah siap', 'riwayat_pesanan.php?id=10');

// For all admins
createNotificationForAllAdmins('Pembayaran Baru', 'Pembayaran order #INV-2024-1234 diterima', 'payments.php');
```


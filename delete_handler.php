<?php
// =========================================================================
// LOGIKA MODULAR: HAPUS DATA USER DAN BERKAS FISIK FOTO DARI SERVER
// =========================================================================

// Cek apakah parameter delete_id dikirimkan melalui URL (GET)
if (isset($_GET['delete_id'])) {
    // Pastikan koneksi database $conn sudah tersedia sebelum file ini di-include
    if (!isset($conn)) {
        die("Kesalahan Sistem: Koneksi database tidak ditemukan.");
    }

    $deleteId = (int)$_GET['delete_id'];

    try {
        // 1. Ambil path file foto user dari database sebelum datanya dihapus
        $stmtGet = $conn->prepare("SELECT photo FROM users WHERE id = ? LIMIT 1");
        $stmtGet->bind_param("i", $deleteId);
        $stmtGet->execute();
        $result = $stmtGet->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $photoPath = $row['photo'];

            // 2. Hapus file fisik dari folder uploads jika berkas tersedia
            if (!empty($photoPath) && file_exists($photoPath) && is_file($photoPath)) {
                @unlink($photoPath);
            }
        }
        $stmtGet->close();

        // 3. Hapus data baris pengguna dari tabel database setelah file bersih
        $stmtDelete = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmtDelete->bind_param("i", $deleteId);
        
        if ($stmtDelete->execute()) {
            // Dapatkan nama file saat ini untuk return/redirect dinamis
            $currentPage = basename($_SERVER['PHP_SELF']);
            header("Location: " . $currentPage . "?status=success_delete");
            exit;
        }
        $stmtDelete->close();

    } catch (Throwable $e) {
        $currentPage = basename($_SERVER['PHP_SELF']);
        header("Location: " . $currentPage . "?status=error_delete&msg=" . urlencode($e->getMessage()));
        exit;
    }
}

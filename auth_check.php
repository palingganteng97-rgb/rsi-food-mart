<?php
/**
 * auth_check.php
 * 
 * Helper untuk pengecekan session login yang benar.
 * Include file ini di setiap halaman yang membutuhkan autentikasi.
 * 
 * Cara pakai:
 *   require_once __DIR__ . '/auth_check.php';
 *   // atau
 *   include 'auth_check.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Cek apakah user admin sudah login (via users table)
 * Redirect ke login.php jika belum login.
 */
function requireAdminLogin() {
    // Cek apakah session user_id ada dan valid
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        // Hapus session yang tidak valid
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }

    // Opsional: Validasi ke database bahwa user_id masih aktif
    global $conn;
    if (isset($conn) && $conn) {
        $userId = intval($_SESSION['user_id']);
        $stmt = $conn->prepare("SELECT id, status FROM users WHERE id = ? AND status = 'active' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                // User sudah dinonaktifkan atau dihapus dari database
                session_unset();
                session_destroy();
                header("Location: login.php?error=" . urlencode("Sesi Anda telah berakhir. Silakan login ulang."));
                exit;
            }
            $stmt->close();
        }
    }
}

/**
 * Cek apakah session pasien masih valid (via patient_sessions table)
 * Redirect ke index.php (splash screen untuk scan ulang QR) jika tidak valid.
 */
function requirePatientSession() {
    $patient_session_id = isset($_SESSION['patient_session_id']) ? intval($_SESSION['patient_session_id']) : 0;

    // Cek 1: Apakah ada session_id di session?
    if ($patient_session_id <= 0) {
        // Tidak ada session pasien → arahkan ke halaman awal untuk scan QR ulang
        header("Location: index.php");
        exit;
    }

    // Cek 2: Apakah session_id masih valid di database?
    global $conn;
    if (isset($conn) && $conn) {
        $stmt = $conn->prepare("SELECT id, patient_name FROM patient_sessions WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $patient_session_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                // Data session di database sudah dihapus (misal: DB di-reset)
                // Hapus session dan redirect ke halaman awal untuk scan QR ulang
                unset($_SESSION['patient_session_id']);
                header("Location: index.php");
                exit;
            }
            $patientData = $res->fetch_assoc();
            $stmt->close();
            return $patientData; // Return data pasien jika perlu
        }
    }

    return null;
}


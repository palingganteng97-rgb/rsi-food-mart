<?php
include "db.php";

// Hapus semua data di session
$_SESSION = array();

// Hancurkan session cookie jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hancurkan session di server
session_destroy();

// Redirect ke halaman login utama
header("Location: login.php");
exit;
?>

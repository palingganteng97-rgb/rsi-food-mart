<?php
// api_get_gallery.php
include 'db.php';

header('Content-Type: application/json');

$images = [];
if (isset($_GET['product_id'])) {
    $product_id = intval($_GET['product_id']);
    
    // Mengambil berkas galeri foto dari tabel product_images kustom Anda
    $query = mysqli_query($conn, "SELECT image FROM product_images WHERE product_id = $product_id ORDER BY is_primary DESC, id ASC");
    if ($query) {
        while ($row = mysqli_fetch_assoc($query)) {
            $images[] = $row['image'];
        }
    }
}

// Keluarkan hasil enkripsi array data
echo json_encode($images);
exit();
?>

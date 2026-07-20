<?php
// get_addon_items.php
include 'db.php';

// Pastikan browser membaca respons sebagai data JSON murni
header('Content-Type: application/json');

// Ambil ID Produk dengan aman
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$addon_items = [];

if ($product_id > 0) {
    // Kueri JOIN: Mengambil item topping langsung berdasarkan ID produk yang aktif
$query = "SELECT ai.id, ai.item_name, ai.price
              FROM addon_items ai
              INNER JOIN product_addons pa ON ai.addon_id = pa.id
              INNER JOIN products p ON pa.product_id = p.id
              WHERE pa.product_id = $product_id AND p.deleted_at IS NULL";
              
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Pastikan nilai harga dikonversi menjadi angka (bukan string)
            $row['price'] = floatval($row['price']);
            $addon_items[] = $row;
        }
    } else {
        // Jika query gagal karena nama tabel/kolom salah, catat error-nya
        echo json_encode(['error' => 'Query Error: ' . mysqli_error($conn)]);
        exit;
    }
}

// Kembalikan data dalam bentuk flat array JSON sesuai kebutuhan JavaScript terbaru kita
echo json_encode($addon_items);
exit;

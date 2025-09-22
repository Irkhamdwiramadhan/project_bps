<?php
// Path ke folder includes sekarang satu tingkat lebih tinggi
include '../includes/koneksi.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $category_id = $_POST['category_id'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $is_promo = isset($_POST['is_promo']) ? 1 : 0;
    
    // Periksa apakah ada file gambar yang diunggah
    $image_path_for_db = null;
    
    // Periksa apakah file diunggah dengan benar
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == UPLOAD_ERR_OK) {
        $target_dir_absolute = "../assets/img/produk/"; // Path absolut dari file proses ini
        
        // Pastikan direktori upload ada dan dapat ditulis
        if (!is_dir($target_dir_absolute)) {
            mkdir($target_dir_absolute, 0777, true);
        }

        // Buat nama file unik untuk menghindari konflik
        $file_extension = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
        $new_file_name = uniqid('product_', true) . '.' . $file_extension;
        $target_file = $target_dir_absolute . $new_file_name;
        
        // Pindahkan file yang diunggah ke folder tujuan
        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $target_file)) {
            // Simpan path relatif ke database, yang akan digunakan oleh browser
            // Path ini relatif dari root direktori bps_dashboard
            $image_path_for_db = "assets/img/produk/" . $new_file_name;
        } else {
            // Jika gagal upload, kirim error
            header("Location: ../pages/tambah_barang.php?status=error&message=" . urlencode("Gagal mengunggah gambar. Pastikan folder 'assets/img/produk' memiliki izin tulis."));
            exit();
        }
    }

    if (!empty($name) && !empty($category_id) && !empty($price) && !empty($stock) && $image_path_for_db !== null) {
        $sql = "INSERT INTO products (name, category_id, price, stock, image, is_promo) VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $koneksi->prepare($sql);
        // Urutan parameter: name (s), category_id (i), price (d), stock (i), image (s), is_promo (i)
        $stmt->bind_param("sidiis", $name, $category_id, $price, $stock, $image_path_for_db, $is_promo);

        if ($stmt->execute()) {
            header("Location: ../pages/barang_tersedia.php?status=success");
            exit();
        } else {
            header("Location: ../pages/tambah_barang.php?status=error&message=" . urlencode($stmt->error));
            exit();
        }
    } else {
        header("Location: ../pages/tambah_barang.php?status=error&message=" . urlencode("Semua field harus diisi."));
        exit();
    }
} else {
    header("Location: ../pages/tambah_barang.php");
    exit();
}
?>

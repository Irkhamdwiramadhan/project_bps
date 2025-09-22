<?php
// Include koneksi database, header, dan sidebar. Perhatikan path-nya.
ob_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

$success_message = '';
$error_message = '';
$product = null;

// Periksa apakah ID produk tersedia di URL untuk mengambil data produk
if (isset($_GET['id'])) {
    $product_id = intval($_GET['id']);
    
    // Ambil data produk yang akan diedit
    $sql_product = "SELECT * FROM products WHERE id = ?";
    $stmt_product = $koneksi->prepare($sql_product);
    $stmt_product->bind_param("i", $product_id);
    $stmt_product->execute();
    $result_product = $stmt_product->get_result();
    $product = $result_product->fetch_assoc();
    $stmt_product->close();

    if (!$product) {
        header("Location: ../pages/barang_tersedia.php");
        exit();
    }
} else {
    header("Location: ../pages/barang_tersedia.php");
    exit();
}

// Ambil data kategori untuk dropdown
$sql_categories = "SELECT id, name FROM categories ORDER BY name ASC";
$result_categories = $koneksi->query($sql_categories);

// Proses form jika di-submit (untuk update data)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = intval($_POST['id']);
    $name = mysqli_real_escape_string($koneksi, $_POST['name']);
    $category_id = intval($_POST['category_id']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $is_promo = isset($_POST['is_promo']) ? 1 : 0;
    
    // Ambil nilai promo_price, set NULL jika tidak ada promo
    $promo_price = ($is_promo == 1 && !empty($_POST['promo_price'])) ? floatval($_POST['promo_price']) : NULL;

    $image_name = $product['image'];
    
    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
        $target_dir = "../assets/img/produk/";
        $file_name = basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $file_name;
        $image_name = $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $uploadOk = 1;
        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if ($check === false) { $uploadOk = 0; }
        if ($_FILES["image"]["size"] > 5000000) { $uploadOk = 0; }
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) { $uploadOk = 0; }

        if ($uploadOk == 1) {
            if ($product['image'] && file_exists($target_dir . $product['image'])) {
                unlink($target_dir . $product['image']);
            }
            if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $error_message = "Maaf, ada error saat mengunggah file baru.";
                $image_name = $product['image'];
            }
        } else {
            $image_name = $product['image'];
        }
    }

    // Perbarui query untuk menyertakan is_promo dan promo_price
    $sql_update = "UPDATE products SET name = ?, category_id = ?, price = ?, stock = ?, image = ?, is_promo = ?, promo_price = ? WHERE id = ?";
    $stmt_update = $koneksi->prepare($sql_update);
    
    if ($stmt_update) {
        // 'siiisidi' -> string, int, int, int, string, int, double, int
        // Pastikan urutan dan tipe data parameter cocok
        $stmt_update->bind_param("siiisidi", $name, $category_id, $price, $stock, $image_name, $is_promo, $promo_price, $product_id);
        
        if ($stmt_update->execute()) {
            // Setelah update, alihkan ke halaman barang tersedia
            header("Location: ../pages/barang_tersedia.php");
            exit();
        } else {
            $error_message = "Error: " . $stmt_update->error;
        }
        $stmt_update->close();
    } else {
        $error_message = "Error dalam menyiapkan query: " . $koneksi->error;
    }
}
?>

<main class="main-content">
    <h2>Edit Barang</h2>
    <div class="row">
        <div class="col-md-6">
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Formulir ini akan mengirimkan data ke halaman ini sendiri -->
            <form action="../proses/proses_edit_barang.php?id=<?php echo $product['id']; ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($product['id']); ?>">
                <div class="form-group">
                    <label for="name">Nama Barang</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="category_id">Kategori</label>
                    <select class="form-control" id="category_id" name="category_id" required>
                        <option value="">Pilih Kategori</option>
                        <?php
                        if ($result_categories->num_rows > 0) {
                            while($row = $result_categories->fetch_assoc()) {
                                $selected = ($row['id'] == $product['category_id']) ? 'selected' : '';
                                echo "<option value='" . $row['id'] . "' $selected>" . htmlspecialchars($row['name']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="price">Harga Normal</label>
                    <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                </div>
                <div class="form-group form-check">
                    <input type="checkbox" class="form-check-input" id="is_promo" name="is_promo" <?php echo ($product['is_promo'] == 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_promo">Status Promo</label>
                </div>
                <!-- Input baru untuk harga promo -->
                <div class="form-group" id="promo-price-group">
                    <label for="promo_price">Harga Promo</label>
                    <input type="number" step="0.01" class="form-control" id="promo_price" name="promo_price" value="<?php echo htmlspecialchars($product['promo_price']); ?>" <?php echo ($product['is_promo'] == 0) ? 'disabled' : ''; ?>>
                    <small class="form-text text-muted">Isi harga promo jika produk dalam status promo.</small>
                </div>
                <div class="form-group">
                    <label for="stock">Stok</label>
                    <input type="number" class="form-control" id="stock" name="stock" value="<?php echo htmlspecialchars($product['stock']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="image">Gambar Produk</label>
                    <input type="file" class="form-control-file" id="image" name="image">
                    <small class="form-text text-muted">Biarkan kosong jika tidak ingin mengubah gambar.</small>
                    <?php if ($product['image']): ?>
                        <div class="mt-2">
                            <img src="../assets/img/produk/<?php echo htmlspecialchars($product['image']); ?>" alt="Gambar Produk" style="width: 100px; height: auto;">
                            <p class="mt-1"><small>Gambar saat ini: <?php echo htmlspecialchars($product['image']); ?></small></p>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                <a href="../pages/barang_tersedia.php" class="btn btn-secondary">Batal</a>
            </form>
        </div>
    </div>
</main>

<script>
    // Script untuk mengaktifkan/menonaktifkan input harga promo
    document.addEventListener('DOMContentLoaded', function() {
        const promoCheckbox = document.getElementById('is_promo');
        const promoPriceInput = document.getElementById('promo_price');

        function togglePromoPrice() {
            promoPriceInput.disabled = !promoCheckbox.checked;
            if (!promoCheckbox.checked) {
                promoPriceInput.value = ''; // Kosongkan nilai saat dinonaktifkan
            }
        }

        promoCheckbox.addEventListener('change', togglePromoPrice);
        togglePromoPrice(); // Jalankan saat halaman dimuat
    });
</script>

<?php
$koneksi->close();
include '../includes/footer.php';
?>

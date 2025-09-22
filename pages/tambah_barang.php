<?php
// Mulai buffer untuk menghindari "headers already sent"
ob_start();

include '../includes/koneksi.php';

// Menangani data form yang dikirim
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $category_id = $_POST['category_id'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $is_promo = isset($_POST['is_promo']) ? 1 : 0;
    $promo_price = $is_promo ? $_POST['promo_price'] : null;

    // Upload gambar
    $image = '';
    if (!empty($_FILES['image']['name'])) {
        $image = basename($_FILES["image"]["name"]);
        $target_dir = __DIR__ . "/../assets/img/produk/";
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_dir . $image)) {
            // upload sukses
        } else {
            echo "Gagal upload gambar";
        }
    }


    // Simpan data
    $sql = "INSERT INTO products (name, category_id, price, stock, image, is_promo, promo_price) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("sidissi", $name, $category_id, $price, $stock, $image, $is_promo, $promo_price);

    
    if ($stmt->execute()) {
        header("Location: ../pages/barang_tersedia.php");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Ambil kategori
$sql_categories = "SELECT id, name FROM categories ORDER BY name ASC";
$result_categories = $koneksi->query($sql_categories);

// Setelah semua proses selesai, baru load header & sidebar
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
    .form-container {
        max-width: 800px;
        margin: 20px auto;
        padding: 30px;
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .form-container h2 {
        text-align: center;
        margin-bottom: 30px;
        color: #333;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        font-weight: bold;
        color: #555;
    }
    .form-control {
        border-radius: 5px;
        border: 1px solid #ddd;
    }
    .btn-submit {
        width: 100%;
        padding: 12px;
        font-size: 16px;
        font-weight: bold;
        border-radius: 5px;
    }
</style>

<div class="form-container">
    <h2>Tambah Barang Baru</h2>
    <form action="" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="name">Nama Barang</label>
            <input type="text" class="form-control" id="name" name="name" required>
        </div>
        <div class="form-group">
            <label for="category_id">Kategori</label>
            <select class="form-control" id="category_id" name="category_id" required>
                <?php
                if ($result_categories && $result_categories->num_rows > 0) {
                    while($row = $result_categories->fetch_assoc()) {
                        echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['name']) . "</option>";
                    }
                }
                ?>
            </select>
        </div>
        <div class="form-group">
            <label for="price">Harga (Rp) - isi tanpa titik</label>
            <input type="number" class="form-control" id="price" name="price" step="any" required>
        </div>
        <div class="form-group">
            <label for="stock">Stok</label>
            <input type="number" class="form-control" id="stock" name="stock" required>
        </div>
        <div class="form-group form-check">
            <input type="checkbox" class="form-check-input" id="is_promo" name="is_promo">
            <label class="form-check-label" for="is_promo">Barang Promo?</label>
        </div>
        <div class="form-group" id="promo_price_field" style="display: none;">
            <label for="promo_price">Harga Promo (Rp)</label>
            <input type="number" class="form-control" id="promo_price" name="promo_price" step="any">
        </div>
        <div class="form-group">
            <label for="image">Gambar Produk</label>
            <input type="file" class="form-control-file" id="image" name="image">
        </div>
        <button type="submit" class="btn btn-primary btn-submit">Simpan Barang</button>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const promoCheckbox = document.getElementById('is_promo');
        const promoPriceField = document.getElementById('promo_price_field');
        const promoPriceInput = document.getElementById('promo_price');

        function togglePromoPriceField() {
            if (promoCheckbox.checked) {
                promoPriceField.style.display = 'block';
                promoPriceInput.setAttribute('required', 'required');
            } else {
                promoPriceField.style.display = 'none';
                promoPriceInput.removeAttribute('required');
                promoPriceInput.value = '';
            }
        }
        togglePromoPriceField();
        promoCheckbox.addEventListener('change', togglePromoPriceField);
    });
</script>

<?php include '../includes/footer.php'; ?>
<?php ob_end_flush(); ?>

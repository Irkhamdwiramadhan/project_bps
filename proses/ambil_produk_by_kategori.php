<?php
include '../includes/koneksi.php';

$kategoriId = isset($_GET['kategori_id']) ? (int)$_GET['kategori_id'] : 0;

$sql = "SELECT id, name, price, is_promo, promo_price, stock, image, category_id FROM products";
$params = [];
$types  = "";

if ($kategoriId > 0) {
    $sql .= " WHERE category_id = ?";
    $params[] = $kategoriId;
    $types   .= "i";
}

$stmt = mysqli_prepare($koneksi, $sql);
if (!$stmt) {
    http_response_code(500);
    echo "<p>Gagal menyiapkan query produk.</p>";
    exit;
}

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) === 0) {
    echo '<p class="no-product">Tidak ada produk pada kategori ini.</p>';
    exit;
}

while ($row = mysqli_fetch_assoc($result)) {
    $id    = (int)$row['id'];
    $name  = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
    $price = (float)$row['price'];
    $stock = (int)$row['stock'];
    $promoActive = (int)$row['is_promo'] === 1;
    $promoPrice  = isset($row['promo_price']) ? (float)$row['promo_price'] : 0.0;

    $finalPrice = ($promoActive && $promoPrice > 0) ? $promoPrice : $price;

    $imgFile = trim((string)$row['image']);
    $imgSrc  = $imgFile !== '' ? '../assets/img/produk/' . $imgFile : '../assets/img/produk/no-image.png';

    if ($promoActive && $promoPrice > 0) {
        $priceLabel = '<span style="text-decoration: line-through; color:#999;">Rp ' . number_format($price, 0, ',', '.') . '</span><br>'
                    . '<span style="font-weight:700;">Rp ' . number_format($promoPrice, 0, ',', '.') . '</span>';
    } else {
        $priceLabel = '<span style="font-weight:700;">Rp ' . number_format($price, 0, ',', '.') . '</span>';
    }

    $stockLabel = $stock > 0
        ? '<span class="text-success">Stok: ' . $stock . '</span>'
        : '<span class="text-danger">Stok Habis</span>';

    echo '<div class="product-card">';
    echo '  <img src="' . $imgSrc . '" alt="' . $name . '" class="product-image">';
    echo '  <h4>' . $name . '</h4>';
    echo '  <p class="price">' . $priceLabel . '</p>';
    echo '  <p class="stock">' . $stockLabel . '</p>';
    echo '  <div class="card-footer">';
    echo '      <button class="btn btn-success btn-sm tambah-produk"'
       . '              data-id="' . $id . '"'
       . '              data-name="' . $name . '"'
       . '              data-price="' . $finalPrice . '"'
       . '              data-stock="' . $stock . '">' // Pastikan data-stock ada
       . '          <i class="fa fa-plus"></i> Tambah'
       . '      </button>';
    echo '  </div>';
    echo '</div>';
}

mysqli_stmt_close($stmt);
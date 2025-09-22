<?php 
// Sertakan file koneksi database dan layout utama
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil data user dari sesi
$user_role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? '';

// Daftar peran yang tidak diizinkan
$forbidden_roles = ['super_admin'];

// Cek apakah ada peran pengguna yang cocok dengan peran yang dilarang
if (array_intersect((array)$user_role, $forbidden_roles)) {
    echo "<main class=\"main-content\"><div class=\"card card-access-denied\"><h2 class=\"text-center text-danger\">Akses Ditolak</h2><p class=\"text-center\">Halaman ini hanya bisa diakses oleh pegawai untuk melakukan pembelian.</p></div></main>";
    include '../includes/footer.php';
    exit; // Keluar dari skrip
}


// Ambil data kategori dari database
$query_kategori = "SELECT id, name FROM categories ORDER BY name ASC";
$result_kategori = mysqli_query($koneksi, $query_kategori);

// Ambil data produk berdasarkan filter
$filter_category_id = isset($_GET['kategori_id']) ? intval($_GET['kategori_id']) : 0;
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

$sql_products = "
    SELECT p.id, p.name, p.price, p.is_promo, p.promo_price, p.stock, p.image, c.name AS category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE 1=1
";

$params = [];
$types = "";

if ($filter_category_id > 0) {
    $sql_products .= " AND p.category_id = ?";
    $params[] = $filter_category_id;
    $types .= "i";
}

if ($keyword !== '') {
    $sql_products .= " AND p.name LIKE ?";
    $params[] = '%' . $keyword . '%';
    $types .= "s";
}

$sql_products .= " ORDER BY p.name ASC";

// Gunakan prepared statement untuk keamanan
$stmt_products = $koneksi->prepare($sql_products);
if (!empty($params)) {
    $stmt_products->bind_param($types, ...$params);
}
$stmt_products->execute();
$result_products = $stmt_products->get_result();

?>

<div class="main-content">
    <section class="content-header">
        <h1>
            <i class="fa fa-shopping-cart"></i> Tambah Penjualan
        </h1>
    </section>
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">Formulir Penjualan</h3>
                    </div>
                    <div class="box-body">
                       <div class="form-card">
                         <form id="form_penjualan" method="POST" action="../proses/proses_tambah_penjualan.php">
                            <input type="hidden" name="pegawai_id" value="<?php echo htmlspecialchars($user_id); ?>">
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="pegawai">Nama Pegawai:</label>
                                        <input type="text" class="form-control" id="nama_pegawai" value="<?php echo htmlspecialchars($user_name); ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label for="tanggal_waktu">Tanggal & Waktu Transaksi:</label>
                                        <input type="text" class="form-control" id="tanggal_waktu" readonly>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="kategori">Pilih Kategori Produk:</label>
                                        <select class="form-control select2" id="kategori_produk" onchange="filterProducts()" style="width: 100%;">
                                            <option value="">-- Semua Kategori --</option>
                                            <?php while ($row = mysqli_fetch_assoc($result_kategori)) { ?>
                                                <option value="<?php echo $row['id']; ?>" <?php echo ($filter_category_id == $row['id']) ? 'selected' : ''; ?>><?php echo $row['name']; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <div class="input-group">
                                            <input type="text" id="searchInput" class="form-control" placeholder="Cari produk..." value="<?php echo htmlspecialchars($keyword); ?>">
                                            <span class="input-group-btn">
                                                <button type="button" class="btn btn-primary btn-flat" onclick="filterProducts()">
                                                    <i class="fa fa-search"></i>
                                                </button>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div id="product-list" class="row">
                                <?php
                                if ($result_products->num_rows > 0) {
                                    while ($product = $result_products->fetch_assoc()) {
                                        $isPromo = $product['is_promo'] == 1;
                                        $price = $isPromo ? $product['promo_price'] : $product['price'];
                                        $imgSrc = empty($product['image']) ? 'https://placehold.co/250x250/E0E0E0/888888?text=No+Image' : '../assets/img/produk/' . htmlspecialchars($product['image']);
                                ?>
                                <div class="col-lg-2 col-md-3 col-sm-4 col-xs-6 mb-3">
                                    <div class="product-card">
                                        <img src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                                        <div class="product-info">
                                            <span class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                            <h5 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h5>
                                            <p class="product-price">
                                                <?php if ($isPromo) { ?>
                                                    <span class="original-price">Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></span>
                                                <?php } ?>
                                                <span class="promo-price">Rp <?php echo number_format($price, 0, ',', '.'); ?></span>
                                            </p>
                                            <p>Stok: <?php echo htmlspecialchars($product['stock']); ?></p>
                                        </div>
                                        <div class="product-actions">
                                            <button type="button" class="btn btn-primary btn-sm tambah-produk" 
                                                data-id="<?php echo htmlspecialchars($product['id']); ?>" 
                                                data-name="<?php echo htmlspecialchars($product['name']); ?>" 
                                                data-price="<?php echo htmlspecialchars($price); ?>" 
                                                data-stock="<?php echo htmlspecialchars($product['stock']); ?>" 
                                                <?php echo ($product['stock'] <= 0) ? 'disabled' : ''; ?>>
                                                <i class="fa fa-plus"></i> Tambah
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php
                                    }
                                } else {
                                    echo '<p class="text-center w-100">Tidak ada produk ditemukan.</p>';
                                }
                                ?>
                            </div>
                            <hr>
                            <div class="row" id="keranjang-container">
                                <div class="col-md-12">
                                    <div class="box box-primary">
                                        <div class="box-header with-border">
                                            <h3 class="box-title">Keranjang Belanja</h3>
                                        </div>
                                        <div class="box-body no-padding">
                                            <table class="table table-striped table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Produk</th>
                                                        <th>Harga Satuan</th>
                                                        <th>Jumlah</th>
                                                        <th>Subtotal</th>
                                                        <th>Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="cart-table">
                                                    </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="3" class="text-right"><strong>Total:</strong></td>
                                                        <td colspan="2" id="cart-total-display">Rp 0</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                        <div class="box-footer">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <a href="history_penjualan.php" class="btn btn-default btn-flat">Batal</a>
                                                </div>
                                                <div class="col-md-6 text-right">
                                                    <button type="submit" class="btn btn-success btn-flat btn-lg" id="checkout-btn">Checkout</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                         </form>
                       </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    let cart = {}; // Objek untuk menyimpan keranjang belanja

    // Fungsi untuk menampilkan tanggal dan waktu saat ini
    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        document.getElementById('tanggal_waktu').value = now.toLocaleDateString('id-ID', options);
    }
    
    // Fungsi untuk menyimpan keranjang ke localStorage
    function saveCart() {
        localStorage.setItem('shopping_cart', JSON.stringify(cart));
    }
    
    // Fungsi untuk memuat keranjang dari localStorage
    function loadCart() {
        const storedCart = localStorage.getItem('shopping_cart');
        if (storedCart) {
            cart = JSON.parse(storedCart);
            renderCart();
        }
    }

    // Panggil fungsi saat halaman dimuat
    $(document).ready(function() {
        updateDateTime();
        loadCart(); // Muat keranjang saat halaman dimuat
    });
    
    // Fungsi untuk mengarahkan ke halaman dengan filter yang diperbarui
    function filterProducts() {
        const categoryId = $('#kategori_produk').val();
        const keyword = $('#searchInput').val();
        window.location.href = `tambah_penjualan.php?kategori_id=${categoryId}&keyword=${keyword}`;
    }

    // Fungsi untuk menghitung total belanja
    function calculateTotal() {
        let total = 0;
        for (const productId in cart) {
            total += cart[productId].price * cart[productId].qty;
        }
        return total;
    }

    // Fungsi untuk merender keranjang belanja
    function renderCart() {
        const cartTable = $('#cart-table');
        cartTable.empty();
        let total = 0;

        for (const productId in cart) {
            const item = cart[productId];
            const subtotal = item.price * item.qty;
            total += subtotal;
            const newRow = `
                <tr data-id="${productId}">
                    <td>${item.name}</td>
                    <td>Rp ${formatRupiah(item.price)}</td>
                    <td>
                        <input type="number" class="form-control form-control-sm cart-qty" style="width: 80px;" value="${item.qty}" min="1" max="${item.stock}">
                    </td>
                    <td>Rp ${formatRupiah(subtotal)}</td>
                    <td><button type="button" class="btn btn-danger btn-sm remove-item"><i class="fa fa-trash"></i> Hapus</button></td>
                </tr>
            `;
            cartTable.append(newRow);
        }

        $('#cart-total-display').text(`Rp ${formatRupiah(total)}`);
    }

    // Fungsi untuk format rupiah
    function formatRupiah(angka) {
        var reverse = angka.toString().split('').reverse().join(''),
            ribuan = reverse.match(/\d{1,3}/g);
        ribuan = ribuan.join('.').split('').reverse().join('');
        return ribuan;
    }
    
    // Fungsi untuk menggulirkan halaman ke bagian keranjang
    function scrollToCart() {
        $('html, body').animate({
            scrollTop: $('#keranjang-container').offset().top 
        }, 800);
    }

    // Listeners Event
    // Tangani klik tombol "Tambah"
    $(document).on('click', '.tambah-produk', function() {
        const productId = $(this).data('id');
        const productName = $(this).data('name');
        const productPrice = parseFloat($(this).data('price'));
        const productStock = parseInt($(this).data('stock'));

        if (productStock <= 0) {
            alert('Stok produk habis!');
            return;
        }

        if (cart[productId]) {
            alert('Produk sudah ada di keranjang!');
            scrollToCart();
            return;
        }

        // Tambahkan item ke objek cart
        cart[productId] = {
            id: productId,
            name: productName,
            price: productPrice,
            qty: 1,
            stock: productStock
        };

        renderCart();
        saveCart(); // Simpan keranjang setelah menambahkan item
        
        // Panggil fungsi untuk menggulir halaman setelah item ditambahkan
        scrollToCart();
    });

    // Tangani perubahan jumlah di keranjang
    $(document).on('change', '.cart-qty', function() {
        const productId = $(this).closest('tr').data('id');
        const newQty = parseInt($(this).val());
        const maxStock = parseInt($(this).attr('max'));
        
        if (isNaN(newQty) || newQty < 1) {
            alert('Jumlah tidak boleh kurang dari 1.');
            $(this).val(cart[productId].qty);
            return;
        }

        if (newQty > maxStock) {
            alert('Jumlah melebihi stok yang tersedia (' + maxStock + ')!');
            $(this).val(cart[productId].qty);
            return;
        }

        cart[productId].qty = newQty;
        renderCart();
        saveCart(); // Simpan keranjang setelah mengubah jumlah
    });

    // Tangani penghapusan item dari keranjang
    $(document).on('click', '.remove-item', function() {
        const productId = $(this).closest('tr').data('id');
        delete cart[productId];
        renderCart();
        saveCart(); // Simpan keranjang setelah menghapus item
    });
    
    // Logika untuk menangani pengiriman form
    $('#form_penjualan').on('submit', function(e) {
        if (Object.keys(cart).length === 0) {
            alert('Keranjang belanja kosong! Tambahkan produk sebelum checkout.');
            e.preventDefault();
            return;
        }

        const form = $(this);
        let totalPenjualan = 0;

        // Loop melalui objek cart dan tambahkan input tersembunyi
        for (const productId in cart) {
            const item = cart[productId];
            
            // Tambahkan input tersembunyi untuk setiap produk
            form.append(`<input type="hidden" name="produk[${item.id}][qty]" value="${item.qty}">`);
            
            totalPenjualan += item.price * item.qty;
        }

        // Tambahkan input tersembunyi untuk total penjualan
        form.append(`<input type="hidden" name="total_penjualan" value="${totalPenjualan}">`);

        // Kosongkan keranjang dari localStorage setelah berhasil checkout
        localStorage.removeItem('shopping_cart');
    });
</script>

<style>
/* Main Content and Header Styling */
.main-content {
    padding: 20px;
}

.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.box {
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    background-color: #fff;
    margin-bottom: 20px;
}

.box-header {
    border-bottom: 1px solid #f4f4f4;
    padding: 10px 15px;
}

.box-title {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.box-body {
    padding: 15px;
}

.form-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
}

.form-group {
    margin-bottom: 15px;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

.select2 {
    width: 100% !important;
}

.input-group {
    display: flex;
}

.input-group .form-control {
    flex-grow: 1;
}

.input-group-btn {
    width: 1%;
    white-space: nowrap;
}

.btn {
    padding: 8px 12px;
    border-radius: 4px;
    cursor: pointer;
    border: none;
    font-size: 14px;
}

.btn-primary {
    background-color: #007bff;
    color: #fff;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.btn-flat {
    border-radius: 0;
}

.row {
    display: flex;
    flex-wrap: wrap;
    margin-left: -15px;
    margin-right: -15px;
}

.col-md-4, .col-md-8, .col-lg-2, .col-md-3, .col-sm-4, .col-xs-6 {
    padding-left: 10px;
    padding-right: 10px;
}
.col-md-4 {
    flex-basis: 33.3333%;
    max-width: 33.3333%;
}
.col-md-8 {
    flex-basis: 66.6667%;
    max-width: 66.6667%;
}
.col-md-12 {
    flex-basis: 100%;
    max-width: 100%;
}

@media (min-width: 1200px) {
    .col-lg-2 {
        flex: 0 0 16.66667%;
        max-width: 16.66667%;
    }
}
@media (min-width: 992px) and (max-width: 1199px) {
    .col-md-3 {
        flex: 0 0 25%;
        max-width: 25%;
    }
}
@media (min-width: 768px) and (max-width: 991px) {
    .col-sm-4 {
        flex: 0 0 33.33333%;
        max-width: 33.33333%;
    }
}
@media (max-width: 767px) {
    .col-xs-6 {
        flex: 0 0 50%;
        max-width: 50%;
    }
}

/* Product Card Styling */
.product-card {
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    overflow: hidden;
    padding: 10px;
    text-align: center;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 15px rgba(0,0,0,0.1);
}

.product-image {
    width: 100%;
    height: 120px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 10px;
}

.product-info {
    text-align: left;
}

.product-category {
    font-size: 0.7rem;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.product-name {
    margin: 5px 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: #333;
}

.product-price {
    margin: 5px 0 10px;
    font-size: 0.9rem;
    font-weight: bold;
    color: #212529;
}

.product-price .original-price {
    text-decoration: line-through;
    color: #888;
    font-weight: normal;
    font-size: 0.8em;
    margin-right: 5px;
}

.product-actions {
    margin-top: 10px;
}

.tambah-produk {
    width: 100%;
}

.table {
    width: 100%;
    margin-bottom: 1rem;
    background-color: transparent;
    border-collapse: collapse;
}

.table th, .table td {
    padding: .75rem;
    vertical-align: top;
    border-top: 1px solid #dee2e6;
}

.table thead th {
    vertical-align: bottom;
    border-bottom: 2px solid #dee2e6;
}

.table tbody + tbody {
    border-top: 2px solid #dee2e6;
}

.table-bordered {
    border: 1px solid #dee2e6;
}

.table-bordered th, .table-bordered td {
    border: 1px solid #dee2e6;
}

.text-right {
    text-align: right;
}

.text-center {
    text-align: center;
}

.btn-danger {
    background-color: #dc3545;
    color: #fff;
}

.btn-success {
    background-color: #28a745;
    color: #fff;
}

.btn-default {
    background-color: #e9ecef;
    color: #212529;
}

.box-footer {
    padding: 15px;
    border-top: 1px solid #f4f4f4;
}
</style>
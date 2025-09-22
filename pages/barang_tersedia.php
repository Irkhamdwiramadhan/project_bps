<?php
// Masukkan file koneksi database dan layout utama
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil peran pengguna dari sesi. Jika tidak ada, atur sebagai array kosong.
$user_roles = $_SESSION['user_role'] ?? [];

// Tentukan peran mana saja yang diizinkan untuk mengakses fitur ini
$allowed_roles_for_action = ['super_admin', 'admin_koperasi'];

// Periksa apakah pengguna memiliki salah satu peran yang diizinkan untuk melihat aksi
$has_access_for_action = false;
foreach ($user_roles as $role) {
    if (in_array($role, $allowed_roles_for_action)) {
        $has_access_for_action = true;
        break; // Keluar dari loop setelah menemukan kecocokan
    }
}

// Menyiapkan variabel untuk filter
$filter_category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;
$show_promo = isset($_GET['promo']) ? true : false;

// Mengambil data produk
$sql_products = "SELECT p.id, p.name, c.name AS category_name, p.price, p.stock, p.image, p.is_promo, p.promo_price 
                 FROM products p 
                 JOIN categories c ON p.category_id = c.id";

// Menambahkan kondisi WHERE sesuai filter yang dipilih
if ($show_promo) {
    $sql_products .= " WHERE p.is_promo = 1";
} elseif ($filter_category_id) {
    $sql_products .= " WHERE p.category_id = ?";
}

$sql_products .= " ORDER BY p.name ASC";

// Menggunakan Prepared Statement untuk mencegah SQL Injection
$stmt_products = $koneksi->prepare($sql_products);
if ($filter_category_id) {
    $stmt_products->bind_param("i", $filter_category_id);
}
$stmt_products->execute();
$result_products = $stmt_products->get_result();

// Mengambil daftar kategori dan jumlah produk di setiap kategori
$sql_categories_count = "SELECT c.id, c.name, COUNT(p.id) AS product_count 
                         FROM categories c
                         LEFT JOIN products p ON c.id = p.category_id
                         GROUP BY c.id, c.name
                         ORDER BY c.name ASC";
$result_categories_count = $koneksi->query($sql_categories_count);

// Mengambil total produk untuk link 'Semua'
$sql_total_products = "SELECT COUNT(*) AS total_products FROM products";
$result_total_products = $koneksi->query($sql_total_products);
$total_products = $result_total_products->fetch_assoc()['total_products'];

// Mengambil total produk promo
$sql_total_promo = "SELECT COUNT(*) AS total_promo FROM products WHERE is_promo = 1";
$result_total_promo = $koneksi->query($sql_total_promo);
$total_promo = $result_total_promo->fetch_assoc()['total_promo'];
?>

<main class="main-content">
    <div class="header-content">
        <h2>Stok Barang</h2>
        <?php  if ($has_access_for_action): ?>
            <a href="tambah_barang.php" class="btn btn-primary">Tambah Barang Baru</a>
        <?php endif; ?>
    </div>

    <div class="content-wrapper">
        <aside class="category-sidebar">
            <h4>Kategori Produk</h4>
            <ul class="category-list">
                <li class="category-item">
                    <a href="barang_tersedia.php" class="category-link <?php echo (!$filter_category_id && !$show_promo) ? 'active' : ''; ?>">
                        <span>Semua</span>
                        <span class='category-count'><?php echo $total_products; ?></span>
                    </a>
                </li>
                <li class="category-item">
                    <a href="barang_tersedia.php?promo=true" class="category-link <?php echo ($show_promo) ? 'active' : ''; ?>">
                        <span>Promo</span>
                        <span class='category-count'><?php echo $total_promo; ?></span>
                    </a>
                </li>
                <?php
                if ($result_categories_count && $result_categories_count->num_rows > 0) {
                    while($row = $result_categories_count->fetch_assoc()) {
                        $active_class = ($row['id'] == $filter_category_id) ? 'active' : '';
                        echo "<li class='category-item'>";
                        echo "<a href='barang_tersedia.php?category_id=" . $row['id'] . "' class='category-link " . $active_class . "'>";
                        echo "<span>" . htmlspecialchars($row['name']) . "</span>";
                        echo "<span class='category-count'>" . htmlspecialchars($row['product_count']) . "</span>";
                        echo "</a>";
                        echo "</li>";
                    }
                }
                ?>
            </ul>
        </aside>
        <div class="stok-barang-page">
            <div class="product-grid">
                <?php
                if ($result_products && $result_products->num_rows > 0) {
                    while($row = $result_products->fetch_assoc()) {
                        $image_filename = htmlspecialchars($row['image']);
                        $final_image_path = "../assets/img/produk/" . $image_filename;
                        // Fallback image jika file tidak ditemukan
                        if (empty($row['image']) || !file_exists($final_image_path)) {
                             $final_image_path = "https://placehold.co/250x250/E0E0E0/888888?text=No+Image";
                        }
                        
                ?>
                <div class="product-card">
                    <img src="<?php echo $final_image_path; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" class="product-image">
                    <div class="product-info">
                        <span class="product-category"><?php echo htmlspecialchars($row['category_name']); ?></span>
                        <h4 class="product-name"><?php echo htmlspecialchars($row['name']); ?></h4>
                        <p class="product-price">
                            <?php if ($row['is_promo']) { ?>
                                <span class="original-price">Rp <?php echo number_format($row['price'], 0, ',', '.'); ?></span>
                                <span class="promo-price">Rp <?php echo number_format($row['promo_price'], 0, ',', '.'); ?></span>
                            <?php } else { ?>
                                Rp <?php echo number_format($row['price'], 0, ',', '.'); ?>
                            <?php } ?>
                        </p>
                        <p>Stok: <?php echo htmlspecialchars($row['stock']); ?></p>
                        <p>Status: <?php echo ($row['is_promo'] ? '<span class="badge bg-warning text-dark">Promo</span>' : '<span class="badge bg-success text-white">Normal</span>'); ?></p>
                    </div>
                    <?php
                    if ($has_access_for_action) {  ?> 
                        `<div class="product-actions">
                            <a href="../proses/proses_edit_barang.php?id=<?php echo $row['id']; ?>" class="btn-action edit">Edit</a>
                            <button type="button" class="btn-action delete-btn" data-id="<?php echo $row['id']; ?>">Hapus</button>
                        </div>`
                    <?php    
                    }
                    ?>

                </div>
                <?php
                    }
                } else {
                    echo "<p style='text-align:center; width:100%;'>Tidak ada data barang.</p>";
                }
                ?>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteModalLabel">Konfirmasi Hapus</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        Apakah Anda yakin ingin menghapus data barang ini?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <a href="#" id="confirmDeleteButton" class="btn btn-danger">Hapus</a>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Script untuk menangani modal hapus
    $(document).ready(function() {
        const deleteModal = $('#deleteModal');
        const confirmDeleteButton = $('#confirmDeleteButton');

        // Fungsi untuk menampilkan modal
        function showModal() {
            deleteModal.css('display', 'block');
            setTimeout(function() {
                deleteModal.addClass('show');
            }, 10);
        }

        // Fungsi untuk menyembunyikan modal
        function hideModal() {
            deleteModal.removeClass('show');
            setTimeout(function() {
                deleteModal.css('display', 'none');
            }, 300);
        }

        // Tangani klik pada tombol 'Hapus'
        $(document).on('click', '.delete-btn', function() {
            const productId = $(this).data('id');
            const deleteUrl = '../proses/proses_hapus_barang.php?id=' + productId;
            confirmDeleteButton.attr('href', deleteUrl);
            showModal();
        });

        // Tangani klik pada tombol Batal atau tombol tutup 'x'
        $(document).on('click', '[data-dismiss="modal"]', function() {
            hideModal();
        });

        // Tangani klik di luar modal
        $(window).on('click', function(event) {
            if ($(event.target).is(deleteModal)) {
                hideModal();
            }
        });
    });
</script>

<style>
/* Main Content and Container Styling */
.main-content {
    padding: 2rem;
    background-color: #f4f6f9;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.header-content h2 {
    font-weight: 600;
    color: #333;
    margin: 0;
}

.content-wrapper {
    display: flex;
    gap: 20px;
}

.category-sidebar {
    width: 250px;
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    height: fit-content;
}

.category-sidebar h4 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 1.1rem;
    font-weight: 600;
    color: #555;
}

.category-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.category-item {
    margin-bottom: 5px;
}

.category-link {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    border-radius: 8px;
    text-decoration: none;
    color: #333;
    background-color: #f8f9fa;
    transition: background-color 0.2s ease, color 0.2s ease;
}

.category-link:hover, .category-link.active {
    background-color: #007bff;
    color: #fff;
}

.category-count {
    background-color: #e9ecef;
    color: #007bff;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 600;
}

.category-link.active .category-count {
    background-color: #fff;
    color: #007bff;
}

.stok-barang-page {
    flex-grow: 1;
}

/* Product Grid Styling */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    padding: 15px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
}

.product-card {
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    overflow: hidden;
    padding: 15px;
    text-align: center;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 15px rgba(0,0,0,0.1);
}

.product-image {
    width: 100%;
    aspect-ratio: 1 / 1;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 10px;
}

.product-info {
    flex-grow: 1;
    text-align: left;
}

.product-category {
    font-size: 0.8rem;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.product-name {
    margin: 5px 0;
    font-size: 1.2rem;
    font-weight: 600;
    color: #333;
}

.product-price {
    margin: 5px 0 10px;
    font-size: 1.1rem;
    font-weight: bold;
    color: #212529;
}

.product-price .original-price {
    text-decoration: line-through;
    color: #888;
    font-weight: normal;
    font-size: 0.9em;
    margin-right: 5px;
}

/* Badge Styling (Ditambahkan dari kode asli Anda) */
.badge {
    display: inline-block;
    padding: .35em .65em;
    font-size: .75em;
    font-weight: 700;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: .25rem;
}
.bg-warning {
    background-color: #ffc107;
}
.bg-success {
    background-color: #28a745;
}
.text-dark {
    color: #212529 !important;
}
.text-white {
    color: #fff !important;
}


.product-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 15px;
}

.btn-action {
    display: inline-block;
    padding: 8px 15px;
    border-radius: 8px;
    text-decoration: none;
    color: #fff;
    font-weight: 500;
    text-align: center;
    transition: background-color 0.3s ease;
    border: none;
}

.btn-action.edit {
    background-color: #17a2b8;
}

.btn-action.edit:hover {
    background-color: #138496;
}

.btn-action.delete-btn {
    background-color: #dc3545;
    cursor: pointer;
}

.btn-action.delete-btn:hover {
    background-color: #c82333;
}

/* Modal Styling */
.modal {
    display: none;
    position: fixed;
    z-index: 1050;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    outline: 0;
    background-color: rgba(0,0,0,0.5);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modal.show {
    display: block;
    opacity: 1;
}

.modal-dialog {
    position: relative;
    width: auto;
    margin: 1.75rem auto;
    transform: translate(0, -50px);
    transition: transform 0.3s ease-out;
}

.modal.show .modal-dialog {
    transform: none;
}

.modal-dialog-centered {
    display: flex;
    align-items: center;
    min-height: calc(100% - 3.5rem);
}

.modal-content {
    position: relative;
    background-color: #fff;
    border: 1px solid rgba(0,0,0,.2);
    border-radius: 12px;
    outline: 0;
    width: 90%;
    max-width: 500px;
    margin: 0 auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid #e9ecef;
}

.modal-body {
    position: relative;
    flex: 1 1 auto;
    padding: 1rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    padding: 1rem;
    border-top: 1px solid #e9ecef;
}

.close {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
    color: #000;
    text-shadow: 0 1px 0 #fff;
    opacity: .5;
    cursor: pointer;
    background-color: transparent;
    border: 0;
}


/* Media Queries for Responsiveness */
@media (max-width: 768px) {
    .main-content {
        padding: 10px;
    }

    .header-content h2 {
        font-size: 1.5rem;
    }

    .content-wrapper {
        flex-direction: column;
        gap: 15px;
    }

    .category-sidebar {
        width: 100%;
        padding: 15px;
        order: 1; /* Pindahkan sidebar ke atas */
    }

    .stok-barang-page {
        order: 2; /* Pindahkan halaman stok ke bawah */
    }

    .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 10px;
        padding: 10px;
    }

    .product-card {
        padding: 10px;
    }

    .product-name {
        font-size: 1rem;
    }
}
</style>
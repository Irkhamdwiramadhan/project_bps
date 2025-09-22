<?php
// Set zona waktu ke Asia/Jakarta (WIB)
date_default_timezone_set('Asia/Jakarta');

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
// Ambil keyword pencarian jika ada
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

$sql_sales_history = "
    SELECT 
        s.id, 
        s.date, 
        s.total, 
        p.nama AS nama_pegawai
    FROM sales s
    JOIN pegawai p ON s.pegawai_id = p.id
";

// Tambahkan filter pencarian dengan prepared statement untuk keamanan
$where_clause = '';
$params = [];
$types = '';

if ($keyword !== '') {
    $where_clause = " WHERE s.id LIKE ? OR p.nama LIKE ? OR s.date LIKE ?";
    $params = ["%$keyword%", "%$keyword%", "%$keyword%"];
    $types = "sss";
}

$sql_sales_history .= $where_clause . " ORDER BY s.date DESC";

$stmt_sales = $koneksi->prepare($sql_sales_history);
if ($stmt_sales) {
    if ($keyword !== '') {
        $stmt_sales->bind_param($types, ...$params);
    }
    $stmt_sales->execute();
    $result_sales_history = $stmt_sales->get_result();
} else {
    $result_sales_history = false;
}
?>

<main class="main-content">
    <div class="header-content">
        <h2>Riwayat Penjualan</h2>
    </div>
    
    <div class="card search-card">
        <form method="get" action="" class="search-form">
            <input type="text" name="keyword" placeholder="Cari ID, Nama Pegawai, atau Tanggal..."
                   value="<?php echo htmlspecialchars($keyword); ?>" 
                   class="form-control search-input">
            <button type="submit" class="btn btn-primary">Cari</button>
            <?php if ($keyword !== ''): ?>
                <a href="history_penjualan.php" class="btn btn-secondary">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
        <div class="alert alert-success">Penjualan berhasil dihapus.</div>
    <?php endif; ?>
    <?php if (isset($_GET['status']) && $_GET['status'] == 'error'): ?>
        <div class="alert alert-danger">Gagal menghapus penjualan: <?php echo htmlspecialchars($_GET['message']); ?></div>
    <?php endif; ?>
    
    <div class="card data-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID Penjualan</th>
                    <th>Nama Pegawai</th>
                    <th>Tanggal & Waktu</th>
                    <th>Total</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
<?php
// Pastikan variabel `$has_access_for_action` sudah didefinisikan
// dari file atau logika sebelumnya.

// Periksa apakah hasil query penjualan tersedia dan memiliki baris data
if ($result_sales_history && $result_sales_history->num_rows > 0) {
    // Loop melalui setiap baris data
    while ($row = $result_sales_history->fetch_assoc()) {
?>
        <tr>
            <td><?php echo htmlspecialchars($row['id']); ?></td>
            <td><?php echo htmlspecialchars($row['nama_pegawai']); ?></td>
            <td><?php echo htmlspecialchars($row['date']); ?></td>
            <td>Rp <?php echo number_format($row['total'], 0, ',', '.'); ?></td>
            <td>
                <a href='detail_penjualan.php?id=<?php echo $row['id']; ?>' class='btn-action'>Detail</a>
                <?php
                // Periksa apakah pengguna memiliki akses untuk melakukan aksi
                if ($has_access_for_action) {
                    // Tombol 'Hapus' akan ditampilkan jika kondisi di atas terpenuhi
                    echo "<button type='button' class='btn-action delete-btn' data-id='" . htmlspecialchars($row['id']) . "'>Hapus</button>";
                }
                ?>
            </td>
        </tr>
<?php
    } // Akhir dari while loop
} else {
    // Tampilkan pesan jika tidak ada data riwayat penjualan
?>
    <tr>
        <td colspan='5' style='text-align:center;'>Tidak ada riwayat penjualan.</td>
    </tr>
<?php
}
?>
            </tbody>
        </table>
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
                Apakah Anda yakin ingin menghapus data penjualan ini? Tindakan ini tidak dapat dibatalkan.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-btn" data-dismiss="modal">Batal</button>
                <a href="#" id="confirmDeleteButton" class="btn btn-danger">Hapus</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        // Tangani klik pada tombol 'Hapus'
        $(document).on('click', '.delete-btn', function() {
            const penjualanId = $(this).data('id');
            const deleteUrl = '../proses/proses_hapus_penjualan.php?id=' + penjualanId;
            
            // Atur URL hapus pada tombol di modal
            $('#confirmDeleteButton').attr('href', deleteUrl);
            
            // Tampilkan modal dengan menambahkan kelas 'show'
            $('#deleteModal').addClass('show');
        });

        // Tangani klik tombol 'Batal' atau 'x' untuk menutup modal
        $(document).on('click', '.close, .close-btn', function() {
            $('#deleteModal').removeClass('show');
        });

        // Tangani klik di luar modal untuk menutupnya
        $(window).on('click', function(event) {
            if ($(event.target).is('#deleteModal')) {
                $('#deleteModal').removeClass('show');
            }
        });
    });
</script>
<style>
/* Reset dan dasar */
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    background-color: #f4f6f9;
    color: #495057;
}

/* Tata letak utama */
.wrapper {
    display: flex;
    min-height: 100vh;
}

.main-content {
    flex-grow: 1;
    padding: 20px;
}

/* Header konten */
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

/* Kartu */
.card {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px;
}

.search-card {
    padding: 15px;
}

.search-form {
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-input {
    flex-grow: 1;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
}

/* Tabel */
.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.data-table thead th {
    background-color: #007bff;
    color: #fff;
    padding: 12px;
    text-align: left;
    border: 1px solid #007bff;
}

.data-table tbody td {
    padding: 12px;
    border: 1px solid #e0e0e0;
    vertical-align: middle;
}

.data-table tbody tr:nth-child(even) {
    background-color: #f8f9fa;
}

/* Tombol Aksi */
.btn {
    padding: 8px 12px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-weight: 500;
    text-align: center;
    transition: background-color 0.3s ease;
}

.btn-primary {
    background-color: #007bff;
    color: #fff;
}

.btn-secondary {
    background-color: #6c757d;
    color: #fff;
}

.btn-danger {
    background-color: #dc3545;
    color: #fff;
}

.btn-action {
    display: inline-block;
    padding: 8px 12px;
    border-radius: 8px;
    text-decoration: none;
    color: #fff;
    font-weight: 500;
    text-align: center;
    transition: background-color 0.3s ease;
    margin-right: 5px;
    background-color: #007bff;
    border: 1px solid #007bff;
}

.btn-action:hover {
    background-color: #0056b3;
    border-color: #0056b3;
}

.btn-action.delete-btn {
    background-color: #dc3545;
    border: none;
    cursor: pointer;
}

.btn-action.delete-btn:hover {
    background-color: #c82333;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 8px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Modal */
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
    transition: opacity 0.3s ease;
    opacity: 0;
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
</style>
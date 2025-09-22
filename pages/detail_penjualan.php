<?php
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

$sale_id = $_GET['id'] ?? null;
$sale_details = null;
$sale_items = [];

if ($sale_id) {
    // Ambil detail penjualan, termasuk nama pegawai
    $sql_sale = "SELECT s.id, s.date, s.total, p.nama AS pegawai_nama 
                 FROM sales s
                 JOIN pegawai p ON s.pegawai_id = p.id
                 WHERE s.id = ?";
    $stmt_sale = $koneksi->prepare($sql_sale);
    $stmt_sale->bind_param("i", $sale_id);
    $stmt_sale->execute();
    $result_sale = $stmt_sale->get_result();
    $sale_details = $result_sale->fetch_assoc();
    $stmt_sale->close();

    // Ambil semua item yang ada dalam penjualan ini
    $sql_items = "SELECT si.qty, si.price, p.name AS product_name 
                  FROM sales_items si 
                  JOIN products p ON si.product_id = p.id 
                  WHERE si.sale_id = ?";
    $stmt_items = $koneksi->prepare($sql_items);
    $stmt_items->bind_param("i", $sale_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    while ($row = $result_items->fetch_assoc()) {
        $sale_items[] = $row;
    }
    $stmt_items->close();
}
?>

<main class="main-content">
    <div class="header-content">
        <h2>Detail Penjualan #<?php echo htmlspecialchars($sale_id); ?></h2>
    </div>

    <?php if ($sale_details) : ?>
        <div class="card p-4 mb-4">
            <h4>Informasi Transaksi</h4>
            <p><strong>Nama Pegawai:</strong> <?php echo htmlspecialchars($sale_details['pegawai_nama']); ?></p>
            <p><strong>Tanggal & Waktu:</strong> <?php echo htmlspecialchars($sale_details['date']); ?></p>
            <p><strong>Total Penjualan:</strong> Rp <?php echo number_format($sale_details['total'], 0, ',', '.'); ?></p>
        </div>

        <div class="card">
            <h4>Daftar Barang yang Terjual</h4>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nama Produk</th>
                        <th>Jumlah Beli</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($sale_items)) : ?>
                        <?php foreach ($sale_items as $item) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['qty']); ?></td>
                                <td>Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="3" style="text-align:center;">Tidak ada barang yang terjual.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <div class="card p-4">
            <p>Data penjualan tidak ditemukan.</p>
        </div>
    <?php endif; ?>
</main>

<?php include '../includes/footer.php'; ?>  
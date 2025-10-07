<?php
include '../includes/koneksi.php';

// Memastikan request berasal dari method POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sale_id = $_POST['sale_id'] ?? null;
    $product_id = $_POST['product_id'] ?? null;

    if ($sale_id && $product_id) {
        // Memulai transaction untuk menjaga integritas data
        $koneksi->begin_transaction();

        try {
            // 1. Ambil subtotal (price) dari item yang akan dihapus
            $price_to_deduct = 0;
            $sql_get_price = "SELECT price FROM sales_items WHERE sale_id = ? AND product_id = ?";
            $stmt_get_price = $koneksi->prepare($sql_get_price);
            $stmt_get_price->bind_param("ii", $sale_id, $product_id);
            $stmt_get_price->execute();
            $result_price = $stmt_get_price->get_result();
            if ($row = $result_price->fetch_assoc()) {
                $price_to_deduct = $row['price'];
            }
            $stmt_get_price->close();

            // 2. Hapus item dari tabel sales_items
            $sql_delete = "DELETE FROM sales_items WHERE sale_id = ? AND product_id = ?";
            $stmt_delete = $koneksi->prepare($sql_delete);
            $stmt_delete->bind_param("ii", $sale_id, $product_id);
            $stmt_delete->execute();
            $stmt_delete->close();

            // 3. Update total pada tabel sales
            $sql_update_total = "UPDATE sales SET total = total - ? WHERE id = ?";
            $stmt_update_total = $koneksi->prepare($sql_update_total);
            $stmt_update_total->bind_param("di", $price_to_deduct, $sale_id);
            $stmt_update_total->execute();
            $stmt_update_total->close();

            // Jika semua query berhasil, commit transaction
            $koneksi->commit();

            // Redirect kembali ke halaman detail dengan pesan sukses (opsional)
            header("Location: ../pages/detail_penjualan.php?id=" . $sale_id . "&status=deletesuccess");
            exit();

        } catch (Exception $e) {
            // Jika terjadi error, rollback semua perubahan
            $koneksi->rollback();
            
            // Redirect kembali dengan pesan error (opsional)
            header("Location: detail_penjualan.php?id=" . $sale_id . "&status=deletefailed");
            exit();
        }
    }
} else {
    // Jika bukan method POST, redirect ke halaman utama atau halaman lain
    header("Location: ../pages/history_penjualan.php");
    exit();
}
?>
<?php
session_start();
include '../includes/koneksi.php';

// 1. Validasi ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: ../pages/kegiatan.php?status=error&message=' . urlencode('ID tidak ditemukan.'));
    exit;
}

$id_honor = (int)$_GET['id'];

try {
    // 2. Mulai Transaksi (Penting agar data aman)
    $koneksi->begin_transaction();

    // 3. Ambil informasi relasi sebelum menghapus (mitra_survey_id dan mitra_id)
    // Kita butuh mitra_id untuk redirect kembali ke halaman detail yang benar
    $sql_info = "SELECT mitra_survey_id, mitra_id FROM honor_mitra WHERE id = ?";
    $stmt_info = $koneksi->prepare($sql_info);
    $stmt_info->bind_param("i", $id_honor);
    $stmt_info->execute();
    $result_info = $stmt_info->get_result();
    $data = $result_info->fetch_assoc();
    $stmt_info->close();

    if (!$data) {
        throw new Exception("Data kegiatan tidak ditemukan.");
    }

    $id_mitra_survey = $data['mitra_survey_id'];
    $mitra_id_redirect = $data['mitra_id'];

    // 4. Hapus data Honor (Anak)
    $sql_del_honor = "DELETE FROM honor_mitra WHERE id = ?";
    $stmt_del_honor = $koneksi->prepare($sql_del_honor);
    $stmt_del_honor->bind_param("i", $id_honor);
    if (!$stmt_del_honor->execute()) {
        throw new Exception("Gagal menghapus data honor.");
    }
    $stmt_del_honor->close();

    // 5. Hapus data Survey (Induk)
    // Kita hapus ini juga agar tidak jadi data sampah di tabel mitra_surveys
    if (!empty($id_mitra_survey)) {
        $sql_del_survey = "DELETE FROM mitra_surveys WHERE id = ?";
        $stmt_del_survey = $koneksi->prepare($sql_del_survey);
        $stmt_del_survey->bind_param("i", $id_mitra_survey);
        if (!$stmt_del_survey->execute()) {
            throw new Exception("Gagal menghapus data survey.");
        }
        $stmt_del_survey->close();
    }

    // 6. Commit Transaksi (Simpan Perubahan)
    $koneksi->commit();

    // 7. Redirect kembali ke Halaman Detail Mitra
    // Menggunakan mitra_id yang kita ambil di langkah 3
    header("Location: ../pages/detail_rekap_kegiatan_tim.php?id=" . $mitra_id_redirect . "&status=success&message=" . urlencode('Kegiatan berhasil dihapus.'));
    exit;

} catch (Exception $e) {
    // Jika ada error, batalkan semua perubahan
    $koneksi->rollback();
    
    // Kembalikan ke halaman referer atau default
    $redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../pages/kegiatan.php';
    
    // Bersihkan URL dari parameter status lama agar tidak menumpuk
    $redirect_url = strtok($redirect_url, '?'); 
    
    header("Location: " . $redirect_url . "?status=error&message=" . urlencode($e->getMessage()));
    exit;
} finally {
    $koneksi->close();
}
?>
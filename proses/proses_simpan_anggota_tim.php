<?php
// File: proses/simpan_anggota_tim.php
session_start();
include '../includes/koneksi.php';

// 1. Ambil tim_id (bukan jenis_id)
$tim_id = isset($_POST['tim_id']) ? intval($_POST['tim_id']) : 0;
$mitra_terpilih_ids = isset($_POST['mitra_terpilih']) ? $_POST['mitra_terpilih'] : [];

if ($tim_id === 0) {
    header('Location: ../pages/kelola_jenis_mitra.php?status=error&message=Tim_tidak_valid');
    exit;
}

$koneksi->begin_transaction();

try {
    // 2. Hapus data lama di tabel 'mitra_tim'
    $sql_delete = "DELETE FROM mitra_tim WHERE tim_id = ?";
    $stmt_delete = $koneksi->prepare($sql_delete);
    $stmt_delete->bind_param("i", $tim_id);
    $stmt_delete->execute();
    $stmt_delete->close();

    // 3. Insert data baru ke tabel 'mitra_tim'
    if (!empty($mitra_terpilih_ids)) {
        $placeholders = implode(', ', array_fill(0, count($mitra_terpilih_ids), '(?, ?)'));
        $sql_insert = "INSERT INTO mitra_tim (mitra_id, tim_id) VALUES $placeholders";
        
        $types = str_repeat('ii', count($mitra_terpilih_ids));
        $values = [];
        foreach ($mitra_terpilih_ids as $mitra_id) {
            $values[] = intval($mitra_id);
            $values[] = $tim_id;
        }
        
        $stmt_insert = $koneksi->prepare($sql_insert);
        $stmt_insert->bind_param($types, ...$values);
        $stmt_insert->execute();
        $stmt_insert->close();
    }
    
    $koneksi->commit();
    header('Location: ../pages/kelola_jenis_mitra.php?status=sukses&message=Anggota_tim_berhasil_disimpan');

} catch (Exception $e) {
    $koneksi->rollback();
    header('Location: ../pages/kelola_jenis_mitra.php?status=error&message=' . $e->getMessage());
}
?>
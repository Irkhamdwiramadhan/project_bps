<?php
// File: proses/proses_simpan_anggota_tim.php
session_start();
include '../includes/koneksi.php';

// 1. Ambil tim_id
$tim_id = isset($_POST['tim_id']) ? intval($_POST['tim_id']) : 0;

// 2. Ambil JSON string dari input hidden (BUKAN dari select array lagi)
$json_data = isset($_POST['mitra_terpilih_json']) ? $_POST['mitra_terpilih_json'] : '[]';

// 3. Decode JSON menjadi Array PHP
$mitra_terpilih_ids = json_decode($json_data, true);

// Validasi hasil decode, pastikan berupa array
if (!is_array($mitra_terpilih_ids)) {
    $mitra_terpilih_ids = [];
}

if ($tim_id === 0) {
    header('Location: ../pages/kelola_jenis_mitra.php?status=error&message=Tim_tidak_valid');
    exit;
}

$koneksi->begin_transaction();

try {
    // 4. Hapus data lama di tabel 'mitra_tim' untuk tim ini
    $sql_delete = "DELETE FROM mitra_tim WHERE tim_id = ?";
    $stmt_delete = $koneksi->prepare($sql_delete);
    $stmt_delete->bind_param("i", $tim_id);
    $stmt_delete->execute();
    $stmt_delete->close();

    // 5. Insert data baru dengan teknik BATCHING (Chunking)
    // Teknik ini mencegah error "Placeholder limit exceeded" atau "Packet too large" di hosting
    // jika data yang dikirim sangat banyak (misal > 1000).
    
    if (!empty($mitra_terpilih_ids)) {
        // Kita pecah array menjadi potongan-potongan kecil berisi 500 ID
        $batch_size = 500; 
        $chunks = array_chunk($mitra_terpilih_ids, $batch_size);

        foreach ($chunks as $chunk) {
            $placeholders = [];
            $types = "";
            $values = [];

            foreach ($chunk as $mitra_id) {
                $placeholders[] = "(?, ?)";
                $types .= "ii"; // integer, integer
                $values[] = intval($mitra_id);
                $values[] = $tim_id;
            }

            // Gabungkan placeholder: (?, ?), (?, ?), ...
            $sql_insert = "INSERT INTO mitra_tim (mitra_id, tim_id) VALUES " . implode(', ', $placeholders);
            
            $stmt_insert = $koneksi->prepare($sql_insert);
            
            // Menggunakan operator unpacking (...) untuk bind parameter dinamis
            $stmt_insert->bind_param($types, ...$values);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
    }
    
    $koneksi->commit();
    header('Location: ../pages/kelola_jenis_mitra.php?status=sukses&message=Anggota_tim_berhasil_disimpan');

} catch (Exception $e) {
    $koneksi->rollback();
    // Log error untuk debugging developer (opsional)
    // error_log($e->getMessage()); 
    header('Location: ../pages/kelola_jenis_mitra.php?status=error&message=' . urlencode('Terjadi kesalahan sistem: ' . $e->getMessage()));
}
?>
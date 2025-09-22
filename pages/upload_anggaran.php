<?php
session_start();
include '../includes/koneksi.php';

require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;

// Validasi awal: Pastikan file sudah diunggah dan tidak ada error
if (!isset($_FILES["excelFile"]) || $_FILES["excelFile"]["error"] != UPLOAD_ERR_OK) {
    die("Error: Tidak ada file yang diunggah atau terjadi kesalahan. Silakan coba lagi.");
}

$target_dir = "../uploads/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

$target_file = $target_dir . basename($_FILES["excelFile"]["name"]);
$fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

// Periksa jenis file
if ($fileType != "xlsx" && $fileType != "xls") {
    die("Maaf, hanya file XLSX & XLS yang diperbolehkan.");
}

// Pindahkan file ke direktori uploads
if (!move_uploaded_file($_FILES["excelFile"]["tmp_name"], $target_file)) {
    die("Maaf, ada kesalahan saat mengunggah file Anda.");
}

try {
    // Hapus data lama sebelum mengunggah yang baru
    $koneksi->query("TRUNCATE TABLE anggaran");

    $spreadsheet = IOFactory::load($target_file);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();
    
    $parent_stack = [];
    $data_to_insert = [];

    foreach ($rows as $rowIndex => $row) {
        if ($rowIndex < 7) {
            continue;
        }

        $kode = trim($row[1]);
        $uraian = trim($row[0]);
        $anggaran = (float) str_replace(['.', ','], ['', '.'], $row[15]);
        $realisasi = (float) str_replace(['.', ','], ['', '.'], $row[18]);

        if (empty($uraian) || (!is_numeric($anggaran) && $anggaran != 0) || (!is_numeric($realisasi) && $realisasi != 0)) {
            continue;
        }

        // Tentukan level berdasarkan format kode
        $level = 1;
        if (!empty($kode)) {
            $level = count(explode('.', $kode));
        }

        $parent_id = null;
        if ($level > 1) {
            $parent_kode = implode('.', array_slice(explode('.', $kode), 0, $level - 1));
            // Cari parent_id berdasarkan parent_kode
            $stmt_parent = $koneksi->prepare("SELECT id FROM anggaran WHERE kode = ? ORDER BY id DESC LIMIT 1");
            $stmt_parent->bind_param("s", $parent_kode);
            $stmt_parent->execute();
            $result_parent = $stmt_parent->get_result();
            if ($result_parent->num_rows > 0) {
                $parent_row = $result_parent->fetch_assoc();
                $parent_id = $parent_row['id'];
            }
            $stmt_parent->close();
        }

        $sisa_anggaran = $anggaran - $realisasi;
        $persentase_realisasi = ($anggaran > 0) ? ($realisasi / $anggaran) * 100 : 0;
        
        $data_to_insert[] = [
            'kode' => $kode,
            'uraian' => $uraian,
            'anggaran' => $anggaran,
            'realisasi' => $realisasi,
            'sisa_anggaran' => $sisa_anggaran,
            'persentase_realisasi' => $persentase_realisasi,
            'parent_id' => $parent_id,
            'level' => $level
        ];
    }

    // Masukkan data ke database
    $stmt = $koneksi->prepare("INSERT INTO anggaran (kode, uraian, anggaran, realisasi, sisa_anggaran, persentase_realisasi, parent_id, level) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($data_to_insert as $data) {
        $stmt->bind_param("ssdddsii", $data['kode'], $data['uraian'], $data['anggaran'], $data['realisasi'], $data['sisa_anggaran'], $data['persentase_realisasi'], $data['parent_id'], $data['level']);
        $stmt->execute();
    }

    $stmt->close();
    echo "File berhasil diunggah dan data anggaran berhasil disimpan.";

} catch (Exception $e) {
    die("Terjadi kesalahan saat membaca file Excel: " . $e->getMessage());
} finally {
    unlink($target_file);
}
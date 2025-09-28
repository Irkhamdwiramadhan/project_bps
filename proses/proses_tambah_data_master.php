<?php
// proses/proses_tambah_data_master.php

require '../vendor/autoload.php';
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Fungsi create_or_update tidak perlu diubah, sudah cukup fleksibel.
function create_or_update($koneksi, $tableName, $conditions, $dataToModify) {
    // ... (kode fungsi ini tetap sama seperti sebelumnya)
    $sqlSelect = "SELECT id FROM $tableName WHERE ";
    $whereClauses = [];
    $bindTypes = '';
    $bindValues = [];
    foreach ($conditions as $column => $value) {
        $whereClauses[] = "$column = ?";
        $bindTypes .= is_int($value) ? 'i' : 's';
        $bindValues[] = $value;
    }
    $sqlSelect .= implode(' AND ', $whereClauses);
    $stmtSelect = $koneksi->prepare($sqlSelect);
    if ($stmtSelect === false) throw new Exception("Prepare failed (SELECT) on $tableName: " . $koneksi->error);
    $stmtSelect->bind_param($bindTypes, ...$bindValues);
    $stmtSelect->execute();
    $result = $stmtSelect->get_result();
    if ($row = $result->fetch_assoc()) {
        $existing_id = $row['id'];
        if (!empty($dataToModify)) {
            $updateClauses = [];
            $updateTypes = '';
            $updateValues = [];
            foreach ($dataToModify as $column => $value) {
                $updateClauses[] = "$column = ?";
                $updateTypes .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
                $updateValues[] = $value;
            }
            $sqlUpdate = "UPDATE $tableName SET " . implode(', ', $updateClauses) . " WHERE id = ?";
            $updateTypes .= 'i';
            $updateValues[] = $existing_id;
            $stmtUpdate = $koneksi->prepare($sqlUpdate);
            if ($stmtUpdate === false) throw new Exception("Prepare failed (UPDATE) on $tableName: " . $koneksi->error);
            $stmtUpdate->bind_param($updateTypes, ...$updateValues);
            $stmtUpdate->execute();
            if ($stmtUpdate->error) throw new Exception("Update failed on $tableName: " . $stmtUpdate->error);
        }
        return $existing_id;
    } else {
        $allData = array_merge($conditions, $dataToModify);
        $columns = implode(', ', array_keys($allData));
        $placeholders = implode(', ', array_fill(0, count($allData), '?'));
        $sqlInsert = "INSERT INTO $tableName ($columns) VALUES ($placeholders)";
        $stmtInsert = $koneksi->prepare($sqlInsert);
        if ($stmtInsert === false) throw new Exception("Prepare failed (INSERT) on $tableName: " . $koneksi->error);
        $insertTypes = '';
        $insertValues = [];
        foreach ($allData as $value) {
            $insertTypes .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
            $insertValues[] = $value;
        }
        $stmtInsert->bind_param($insertTypes, ...$insertValues);
        $stmtInsert->execute();
        if ($stmtInsert->error) throw new Exception("Insert failed on $tableName: " . $stmtInsert->error);
        return $koneksi->insert_id;
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tahun_anggaran = (int)$_POST['tahun'];

    if (isset($_FILES['file_excel']) && $_FILES['file_excel']['error'] == 0) {
        $file_tmp_path = $_FILES['file_excel']['tmp_name'];

        try {
            $koneksi->begin_transaction();
            $spreadsheet = IOFactory::load($file_tmp_path);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            
            // REVISI: Variabel untuk menyimpan ID dan KODE hierarki saat ini
            $current_ids = [];
            $current_codes = [];
            
            echo "Memulai proses impor...<br>";

            for ($row = 1; $row <= $highestRow; $row++) {
                $kode = trim((string)$sheet->getCell('B' . $row)->getValue());
                $nama = trim((string)$sheet->getCell('C' . $row)->getValue());
                
                if (empty($kode) && empty($nama)) continue;
                
                // Mengabaikan baris total
                if (stripos($nama, 'JUMLAH') !== false || stripos($nama, 'TOTAL') !== false) {
                    echo "Melewati baris total: {$nama}<br>";
                    continue;
                }
                
                echo "Memproses baris $row: Kode='{$kode}', Nama='{$nama}'<br>";

                if (preg_match('/^\d{3}\.\d{2}\.[A-Z]{2}$/', $kode)) {
                    $current_codes = ['program' => $kode]; // Reset & set kode program
                    $current_ids = ['program' => create_or_update($koneksi, 'master_program', ['kode' => $kode, 'tahun' => $tahun_anggaran], ['nama' => $nama])];
                } elseif (preg_match('/^\d{4}$/', $kode) && isset($current_ids['program'])) {
                    $current_codes['kegiatan'] = $kode;
                    $current_ids['kegiatan'] = create_or_update($koneksi, 'master_kegiatan', ['kode' => $kode, 'program_id' => $current_ids['program'], 'tahun' => $tahun_anggaran], ['nama' => $nama]);
                } elseif (preg_match('/^\d{4}\.[A-Z]{3}$/', $kode) && isset($current_ids['kegiatan'])) {
                    $current_codes['output'] = $kode;
                    $current_ids['output'] = create_or_update($koneksi, 'master_output', ['kode' => $kode, 'kegiatan_id' => $current_ids['kegiatan'], 'tahun' => $tahun_anggaran], ['nama' => $nama]);
                } elseif (preg_match('/^[A-Z]{3}\.\d{3}$/', $kode) && isset($current_ids['output'])) {
                    $current_codes['sub_output'] = $kode;
                    $current_ids['sub_output'] = create_or_update($koneksi, 'master_sub_output', ['kode' => $kode, 'output_id' => $current_ids['output'], 'tahun' => $tahun_anggaran], ['nama' => $nama]);
                } elseif (preg_match('/^\d{3}$/', $kode) && isset($current_ids['sub_output'])) {
                    $current_codes['komponen'] = $kode;
                    $current_ids['komponen'] = create_or_update($koneksi, 'master_komponen', ['kode' => $kode, 'sub_output_id' => $current_ids['sub_output'], 'tahun' => $tahun_anggaran], ['nama' => $nama]);
                } elseif (preg_match('/^[A-Z]$/', $kode) && isset($current_ids['komponen'])) {
                    $current_codes['sub_komponen'] = $kode;
                    $current_ids['sub_komponen'] = create_or_update($koneksi, 'master_sub_komponen', ['kode' => $kode, 'komponen_id' => $current_ids['komponen'], 'tahun' => $tahun_anggaran], ['nama' => $nama]);
                } elseif (preg_match('/^\d{6}$/', $kode) && isset($current_ids['sub_komponen'])) {
                    $current_codes['akun'] = $kode;
                    $current_ids['akun'] = create_or_update($koneksi, 'master_akun', ['kode' => $kode, 'sub_komponen_id' => $current_ids['sub_komponen'], 'tahun' => $tahun_anggaran], ['nama' => $nama]);
                } 
                elseif (empty($kode) && !empty($nama) && isset($current_ids['akun'])) {
                    // =========================================================================
                    // REVISI: Membuat dan Menyimpan Kode Unik untuk Item
                    // =========================================================================
                    
                    // 1. Bangun Kode Unik Gabungan
                    $kode_unik_parts = [
                        $tahun_anggaran,
                        $current_codes['program'] ?? '', $current_codes['kegiatan'] ?? '',
                        $current_codes['output'] ?? '', $current_codes['sub_output'] ?? '',
                        $current_codes['komponen'] ?? '', $current_codes['sub_komponen'] ?? '',
                        $current_codes['akun'] ?? '',
                        $nama // Nama item adalah bagian terakhir dari keunikan
                    ];
                    $kode_unik = implode('-', $kode_unik_parts);

                    // Ambil detail item dari kolom J, K, L
                    $raw_volume_satuan = trim((string)$sheet->getCell('J' . $row)->getValue());
                    $raw_harga = $sheet->getCell('K' . $row)->getValue();
                    $raw_pagu = $sheet->getCell('L' . $row)->getValue();
                    
                    $volume = 0; $satuan = ''; $harga = 0; $pagu = 0;

                    if (preg_match('/^(\d+[\.,\d]*)\s*(.*)$/', $raw_volume_satuan, $matches)) {
                        $volume = (int)str_replace(['.', ','], '', $matches[1]);
                        $satuan = trim($matches[2]);
                    }
                    $harga_bersih = (int)filter_var($raw_harga, FILTER_SANITIZE_NUMBER_INT);
                    $harga = $harga_bersih * 1000;
                    $pagu_bersih = (int)filter_var($raw_pagu, FILTER_SANITIZE_NUMBER_INT);
                    $pagu = $pagu_bersih * 1000;

                    // Data untuk di-insert atau di-update
                    $item_data_to_modify = [
                        'akun_id' => $current_ids['akun'],
                        'tahun' => $tahun_anggaran,
                        'nama_item' => $nama,
                        'satuan' => $satuan,
                        'volume' => $volume,
                        'harga' => $harga,
                        'pagu' => $pagu
                    ];
                    
                    // Gunakan kode_unik sebagai KUNCI PENCARIAN
                    create_or_update(
                        $koneksi, 'master_item',
                        ['kode_unik' => $kode_unik],
                        $item_data_to_modify
                    );
                    
                    echo "-> Menyimpan Item: $nama | Kode Unik: $kode_unik<br>";
                }
            }

            $koneksi->commit();
            echo "Proses impor selesai!<br>Data berhasil diimpor dengan Kode Unik!";

        } catch (\Exception $e) {
            $koneksi->rollback();
            echo "Error: Terjadi kesalahan fatal. Proses impor dibatalkan. Pesan: " . $e->getMessage();
        }
    } else {
        echo "Error: Tidak ada file yang diunggah.";
    }
}
?>
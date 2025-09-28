<?php
// processes/proses_tambah_data_master.php

// Sertakan autoloader PhpSpreadsheet dan koneksi
require '../vendor/autoload.php';
include '../includes/koneksi.php';

// Gunakan class yang diperlukan dari PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
/**
 * Fungsi utama untuk membuat data jika belum ada, atau memperbaruinya jika sudah ada.
 * @param mysqli $koneksi Objek koneksi database.
 * @param string $tableName Nama tabel target.
 * @param array $conditions Kolom dan nilai untuk klausa WHERE (untuk mencari).
 * @param array $dataToModify Kolom dan nilai yang akan di-INSERT atau di-UPDATE.
 * @return int ID dari baris yang diproses.
 * @throws Exception Jika query gagal.
 */
function create_or_update($koneksi, $tableName, $conditions, $dataToModify) {
    // 1. SELECT: Cek apakah data sudah ada
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
        // 2a. UPDATE: Data sudah ada, perbarui baris yang ada
        $existing_id = $row['id'];
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

        return $existing_id;

    } else {
        // 2b. INSERT: Data tidak ada, buat baris baru
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

// Mulai logika utama
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tahun_anggaran = (int)$_POST['tahun'];

    if (isset($_FILES['file_excel']) && $_FILES['file_excel']['error'] == 0) {
        $file_tmp_path = $_FILES['file_excel']['tmp_name'];

        try {
            // Memulai transaksi database untuk keamanan data
            $koneksi->begin_transaction();

            $spreadsheet = IOFactory::load($file_tmp_path);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            
            // =========================================================================
            // LANGKAH 1: DETEKSI HEADER SECARA DINAMIS
            // =========================================================================
            $headerMap = [];
            $headerRow = 0;
            for ($row = 1; $row <= 15; $row++) {
                $foundKode = false;
                $foundUraian = false;
                for ($col = 1; $col <= 10; $col++) {
                    $cellValue = strtoupper(trim((string)$sheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getValue()));
                    if (strpos($cellValue, 'KODE') !== false) $foundKode = true;
                    if (strpos($cellValue, 'URAIAN') !== false) $foundUraian = true;
                }
                if ($foundKode && $foundUraian) {
                    $headerRow = $row;
                    break;
                }
            }

            if ($headerRow === 0) throw new Exception("Header tidak ditemukan. Pastikan file Excel memiliki baris header dengan kata 'KODE' dan 'URAIAN'.");

            // Petakan kolom KODE dan URAIAN/NAMA
            $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn());
            for ($col = 1; $col <= $highestColumn; $col++) {
                $cellValue = strtoupper(trim((string)$sheet->getCell(Coordinate::stringFromColumnIndex($col) . $headerRow)->getValue()));
                if (strpos($cellValue, 'KODE') !== false) $headerMap['KODE'] = $col;
                if (strpos($cellValue, 'URAIAN') !== false) $headerMap['NAMA'] = $col;
            }

            // Inisialisasi variabel ID hierarki
            $program_id = $kegiatan_id = $output_id = $sub_output_id = $komponen_id = $sub_komponen_id = $akun_id = null;
            
            echo "Memulai proses impor...<br>";

            // =========================================================================
            // LANGKAH 2: PROSES SETIAP BARIS
            // =========================================================================
            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                $kode = trim((string)$sheet->getCell(Coordinate::stringFromColumnIndex($headerMap['KODE']) . $row)->getValue());
                $nama = trim((string)$sheet->getCell(Coordinate::stringFromColumnIndex($headerMap['NAMA']) . $row)->getValue());
                
                if (empty($kode) && empty($nama)) continue;
                
                echo "Memproses baris $row: Kode='{$kode}', Nama='{$nama}'<br>";

                // Logika deteksi hierarki
                if (preg_match('/^\d{3}\.\d{2}\.[A-Z]{2}$/', $kode)) {
                    $program_id = create_or_update($koneksi, 'master_program', ['kode' => $kode, 'tahun' => $tahun_anggaran], ['nama' => $nama]);
                    $kegiatan_id = $output_id = $sub_output_id = $komponen_id = $sub_komponen_id = $akun_id = null;
                    echo "-> Ditemukan Program: {$nama}<br>";
                } elseif (preg_match('/^\d{4}$/', $kode) && $program_id) {
                    $kegiatan_id = create_or_update($koneksi, 'master_kegiatan', ['kode' => $kode, 'tahun' => $tahun_anggaran, 'program_id' => $program_id], ['nama' => $nama]);
                    $output_id = $sub_output_id = $komponen_id = $sub_komponen_id = $akun_id = null;
                    echo "-> Ditemukan Kegiatan: {$nama}<br>";
                } elseif (preg_match('/^\d{4}\.[A-Z]{3}$/', $kode) && $kegiatan_id) {
                    $output_id = create_or_update($koneksi, 'master_output', ['kode' => $kode, 'tahun' => $tahun_anggaran, 'kegiatan_id' => $kegiatan_id], ['nama' => $nama]);
                    $sub_output_id = $komponen_id = $sub_komponen_id = $akun_id = null;
                    echo "-> Ditemukan Output: {$nama}<br>";
                } elseif (preg_match('/^[A-Z]{3}\.\d{3}$/', $kode) && $output_id) {
                    $sub_output_id = create_or_update($koneksi, 'master_sub_output', ['kode' => $kode, 'tahun' => $tahun_anggaran, 'output_id' => $output_id], ['nama' => $nama]);
                    $komponen_id = $sub_komponen_id = $akun_id = null;
                    echo "-> Ditemukan Sub-output: {$nama}<br>";
                } elseif (preg_match('/^\d{3}$/', $kode) && $sub_output_id) {
                    $komponen_id = create_or_update($koneksi, 'master_komponen', ['kode' => $kode, 'tahun' => $tahun_anggaran, 'sub_output_id' => $sub_output_id], ['nama' => $nama]);
                    $sub_komponen_id = $akun_id = null;
                    echo "-> Ditemukan Komponen: {$nama}<br>";
                } elseif (preg_match('/^[A-Z]$/', $kode) && $komponen_id) {
                    $sub_komponen_id = create_or_update($koneksi, 'master_sub_komponen', ['kode' => $kode, 'tahun' => $tahun_anggaran, 'komponen_id' => $komponen_id], ['nama' => $nama]);
                    $akun_id = null;
                    echo "-> Ditemukan Sub-komponen: {$nama}<br>";
                } elseif (preg_match('/^\d{6}$/', $kode) && $sub_komponen_id) {
                    $akun_id = create_or_update($koneksi, 'master_akun', ['kode' => $kode, 'tahun' => $tahun_anggaran, 'sub_komponen_id' => $sub_komponen_id], ['nama' => $nama]);
                    echo "-> Ditemukan Akun: {$nama}<br>";
                } elseif (empty($kode) && !empty($nama) && $akun_id) {
                    // =========================================================================
                    // REVISI KRITIS FINAL DENGAN LOGGING DETAIL
                    // =========================================================================
                    
                    $nama_item_valid = $nama;
                    echo "-> Ditemukan Item: <b>{$nama_item_valid}</b><br>";

                    // --- LANGKAH 1: MEMBACA DATA MENTAH DARI KOLOM J, K, L ---
                    $raw_volume_satuan = trim((string)$sheet->getCell('J' . $row)->getValue());
                    $raw_harga = $sheet->getCell('K' . $row)->getValue();
                    $raw_pagu = $sheet->getCell('L' . $row)->getValue();
                    
                    echo " &nbsp; &nbsp; ├─ Membaca dari Kolom J (Vol/Satuan): '{$raw_volume_satuan}'<br>";
                    echo " &nbsp; &nbsp; ├─ Membaca dari Kolom K (Harga): '{$raw_harga}'<br>";
                    echo " &nbsp; &nbsp; └─ Membaca dari Kolom L (Pagu): '{$raw_pagu}'<br>";

                    // --- LANGKAH 2: PARSING DAN MEMBERSIHKAN DATA ---
                    $volume = 0;
                    $satuan = '';
                    $harga = 0;
                    $pagu = 0;

                    // Parsing Volume dan Satuan dari Kolom J
                    if (!empty($raw_volume_satuan) && preg_match('/^(\d+[\.,\d]*)\s*(.*)$/', $raw_volume_satuan, $matches)) {
                        $volume = (int)str_replace(['.', ','], '', $matches[1]);
                        $satuan = trim($matches[2]);
                        echo " &nbsp; &nbsp; ├─ Hasil Parsing J: Volume = {$volume}, Satuan = '{$satuan}'<br>";
                    } else {
                        echo " &nbsp; &nbsp; ├─ <b style='color:red;'>PERINGATAN:</b> Gagal mem-parsing Volume/Satuan dari '{$raw_volume_satuan}'<br>";
                    }

                    // Membersihkan Harga dari Kolom K
                    if (!empty($raw_harga)) {
                        $harga = (int)filter_var($raw_harga, FILTER_SANITIZE_NUMBER_INT);
                        echo " &nbsp; &nbsp; ├─ Hasil Pembersihan K: Harga = {$harga}<br>";
                    }

                    // Membersihkan Pagu dari Kolom L
                    if (!empty($raw_pagu)) {
                        $pagu = (int)filter_var($raw_pagu, FILTER_SANITIZE_NUMBER_INT);
                        echo " &nbsp; &nbsp; └─ Hasil Pembersihan L: Pagu = {$pagu}<br>";
                    }

                    // --- LANGKAH 3: MENYIMPAN KE DATABASE ---
                    create_or_update(
                        $koneksi, 
                        'master_item',
                        ['akun_id' => $akun_id, 'nama_item' => $nama_item_valid, 'tahun' => $tahun_anggaran],
                        ['volume' => $volume, 'satuan' => $satuan, 'harga' => $harga, 'pagu' => $pagu]
                    );
                    
                    echo " &nbsp; &nbsp; <b>-> SUKSES DISIMPAN.</b><br>";
                }
            }

            // Jika semua berjalan lancar, simpan perubahan ke database
            $koneksi->commit();
            echo "Proses impor selesai!<br>Data berhasil diimpor!";

        } catch (\Exception $e) {
            // Jika terjadi error, batalkan semua perubahan
            $koneksi->rollback();
            echo "Error: Terjadi kesalahan fatal. Proses impor dibatalkan. Pesan: " . $e->getMessage();
        }
    } else {
        echo "Error: Tidak ada file yang diunggah atau terjadi kesalahan upload.";
    }
}
?>
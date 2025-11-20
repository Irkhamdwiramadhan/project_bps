<?php
// proses/proses_tambah_data_master.php

session_start();
require '../vendor/autoload.php';
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// ==========================================================================
// HELPER FUNCTIONS
// ==========================================================================

// Helper: menentukan tipe bind untuk bind_param
function param_types_for_values(array $values): string {
    $types = '';
    foreach ($values as $v) {
        if (is_int($v)) {
            $types .= 'i';
            continue;
        }
        if ($v === null) {
            $types .= 's';
            continue;
        }
        if (is_numeric($v) && strpos((string)$v, '.') !== false) {
            $types .= 'd';
            continue;
        }
        if (is_string($v) && ctype_digit($v)) {
            if (strlen($v) > 1 && $v[0] === '0') {
                $types .= 's';
            } else {
                $types .= 'i';
            }
            continue;
        }
        if (is_numeric($v) && (int)$v == $v) {
            $types .= 'i';
            continue;
        }
        $types .= 's';
    }
    return $types;
}

function insert_rpd($koneksi, $kode_unik, $bulan, $jumlah, $tahun) {
    // Jika jumlah 0 atau null, skip saja agar database tidak penuh sampah
    if ($jumlah <= 0 || $jumlah == null) return;

    // Cek apakah sudah ada data rpd untuk item yang sama di bulan & tahun yang sama
    $stmt = $koneksi->prepare("
        SELECT id, jumlah FROM rpd_sakti
        WHERE kode_unik_item = ? AND bulan = ? AND tahun = ?
        LIMIT 1
    ");
    if ($stmt === false) throw new Exception("Prepare failed (select rpd): " . $koneksi->error);
    $stmt->bind_param("sii", $kode_unik, $bulan, $tahun);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Jika ada -> tambahkan jumlahnya (akumulasi)
        $row = $result->fetch_assoc();
        $new_jumlah = $row['jumlah'] + $jumlah;

        $update = $koneksi->prepare("UPDATE rpd_sakti SET jumlah = ? WHERE id = ?");
        if ($update === false) throw new Exception("Prepare failed (update rpd): " . $koneksi->error);
        $update->bind_param("di", $new_jumlah, $row['id']);
        $update->execute();
    } else {
        // Jika belum ada -> insert baru
        $insert = $koneksi->prepare("
            INSERT INTO rpd_sakti (kode_unik_item, bulan, jumlah,tahun)
            VALUES (?, ?, ?, ?)
        ");
        if ($insert === false) throw new Exception("Prepare failed (insert rpd): " . $koneksi->error);
        $insert->bind_param("sidi", $kode_unik, $bulan, $jumlah, $tahun);
        $insert->execute();
    }
}

function create_or_update($koneksi, $tableName, $conditions, $dataToModify) {
    // Build SELECT
    $whereClauses = [];
    $bindValues = [];
    foreach ($conditions as $col => $val) {
        $whereClauses[] = "$col = ?";
        $bindValues[] = $val;
    }
    $sqlSelect = "SELECT id FROM $tableName WHERE " . implode(' AND ', $whereClauses);
    $stmtSelect = $koneksi->prepare($sqlSelect);
    if ($stmtSelect === false) throw new Exception("Prepare failed (SELECT) on $tableName: " . $koneksi->error);

    $types = param_types_for_values($bindValues);
    $stmtSelect->bind_param($types, ...$bindValues);
    $stmtSelect->execute();
    $res = $stmtSelect->get_result();

    if ($row = $res->fetch_assoc()) {
        $existing_id = $row['id'];
        if (!empty($dataToModify)) {
            $updateClauses = [];
            $updateValues = [];
            foreach ($dataToModify as $col => $val) {
                $updateClauses[] = "$col = ?";
                $updateValues[] = $val;
            }
            $sqlUpdate = "UPDATE $tableName SET " . implode(', ', $updateClauses) . " WHERE id = ?";
            $updateValues[] = $existing_id;
            $typesUpdate = param_types_for_values($updateValues);

            $stmtUpdate = $koneksi->prepare($sqlUpdate);
            if ($stmtUpdate === false) throw new Exception("Prepare failed (UPDATE) on $tableName: " . $koneksi->error);
            $stmtUpdate->bind_param($typesUpdate, ...$updateValues);
            $stmtUpdate->execute();
        }
        return $existing_id;
    } else {
        // Insert
        $allData = array_merge($conditions, $dataToModify);
        $cols = implode(', ', array_keys($allData));
        $placeholders = implode(', ', array_fill(0, count($allData), '?'));
        $sqlInsert = "INSERT INTO $tableName ($cols) VALUES ($placeholders)";

        $stmtInsert = $koneksi->prepare($sqlInsert);
        if ($stmtInsert === false) throw new Exception("Prepare failed (INSERT) on $tableName: " . $koneksi->error);

        $values = array_values($allData);
        $typesInsert = param_types_for_values($values);
        $stmtInsert->bind_param($typesInsert, ...$values);
        $stmtInsert->execute();

        return $koneksi->insert_id;
    }
}

function find_master_item($koneksi, $kode_unik) {
    $stmt = $koneksi->prepare("SELECT * FROM master_item WHERE kode_unik = ? LIMIT 1");
    if ($stmt === false) throw new Exception("Prepare failed (find_master_item): " . $koneksi->error);
    $stmt->bind_param("s", $kode_unik);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->fetch_assoc() ?: null;
}

function insert_or_merge_master_item($koneksi, $data) {
    // Menggunakan kode_unik sebagai kunci identifikasi item yang unik sampai level komponen
    if (empty($data['kode_unik'])) {
        // Jika tidak ada kode_unik, fallback: buat string yang mencakup parent minimal (kurang ideal).
        // Namun jalur normal di kode utama sudah memastikan kode_unik ada.
        throw new Exception("insert_or_merge_master_item: kode_unik kosong.");
    }

    $existing = find_master_item($koneksi, $data['kode_unik']);
    if ($existing) {
        // Merge hanya jika kode_unik sama persis (berarti sama hierarchy termasuk komponen)
        $new_volume = (int)$existing['volume'] + (int)$data['volume'];
        $new_pagu = (int)$existing['pagu'] + (int)$data['pagu'];

        $stmt = $koneksi->prepare("UPDATE master_item SET volume = ?, pagu = ? WHERE id = ?");
        if ($stmt === false) throw new Exception("Prepare failed (update master_item merge): " . $koneksi->error);
        $stmt->bind_param("iii", $new_volume, $new_pagu, $existing['id']);
        $stmt->execute();
        return $existing['id'];
    } else {
        // Jika tidak ada existing berdasarkan kode_unik, gunakan create_or_update dengan kondisi kode_unik
        return create_or_update($koneksi, 'master_item', ['kode_unik' => $data['kode_unik']], [
            'akun_id' => $data['akun_id'],
            'tahun' => $data['tahun'],
            'nama_item' => $data['nama_item'],
            'satuan' => $data['satuan'],
            'volume' => $data['volume'],
            'harga' => $data['harga'],
            'pagu' => $data['pagu']
        ]);
    }
}

// ==========================================================================
// MAIN PROCESS
// ==========================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_FILES['file_excel']) || $_FILES['file_excel']['error'] != 0) {
        $_SESSION['flash_message'] = "Error: Tidak ada file yang diunggah.";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: ../pages/upload.php");
        exit();
    }

    $file_tmp_path = $_FILES['file_excel']['tmp_name'];
    $tahun_anggaran = (int)($_POST['tahun'] ?? 0);

    try {
        $spreadsheet = IOFactory::load($file_tmp_path);
        $sheet = $spreadsheet->getActiveSheet();

        // 1. Cek Header Tahun
        $header_string = '';
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        for ($col = 2; $col <= $highestColumnIndex; $col++) {
            $columnLetter = Coordinate::stringFromColumnIndex($col);
            $v = $sheet->getCell($columnLetter . '1')->getValue();
            if (!empty($v)) $header_string .= (string)$v . ' ';
        }
        $tahun_dari_excel = null;
        if (preg_match('/\b(20\d{2})\b/', $header_string, $m)) {
            $tahun_dari_excel = (int)$m[1];
        }
        if ($tahun_dari_excel === null) throw new Exception("Tidak dapat menemukan tahun di baris pertama Excel.");
        if ($tahun_dari_excel !== $tahun_anggaran) throw new Exception("Tahun pada Excel (TA {$tahun_dari_excel}) tidak sesuai dengan input ({$tahun_anggaran}).");

        // 2. Mulai Transaksi
        $koneksi->begin_transaction();
        $highestRow = $sheet->getHighestRow();

        // Variabel State
        $current_codes = [];
        $current_ids = [];

        // Variabel Snapshot Item Terakhir
        $last_item_db_id = null;
        $last_item_volume = null;
        $last_item_pagu = null;
        $last_item_ppk = null;
        $last_item_nama = null;
        $last_item_codes = [];

        // Closure untuk mereset item terakhir (Dipanggil saat ganti hierarki)
        $reset_last_item = function() use (&$last_item_db_id, &$last_item_volume, &$last_item_pagu, &$last_item_ppk, &$last_item_nama, &$last_item_codes) {
            $last_item_db_id = null;
            $last_item_volume = null;
            $last_item_pagu = null;
            $last_item_ppk = null;
            $last_item_nama = null;
            $last_item_codes = [];
        };

        // 3. Loop Baris
        for ($row = 2; $row <= $highestRow; $row++) {
            // Bersihkan input
            $kode_raw = $sheet->getCell('B' . $row)->getValue();
            $kode = trim((string)$kode_raw);
            $kode = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $kode); // Hapus invisible characters

            $nama = trim((string)$sheet->getCell('C' . $row)->getValue());
            $ppk = trim((string)$sheet->getCell('H' . $row)->getValue());

            if (empty($kode) && empty($nama)) continue;
            if (!empty($nama) && (stripos($nama, 'JUMLAH') !== false || stripos($nama, 'TOTAL') !== false)) continue;

            // --- HIERARCHY DETECTION ---
            // Setiap kali masuk blok hierarchy, kita RESET item terakhir agar tidak dianggap lanjutan.

            if (preg_match('/^\d{3}\.\d{2}\.[A-Z]{2}$/', $kode)) {
                // PROGRAM
                $reset_last_item();
                // Reset ID anak-anaknya
                unset($current_ids['kegiatan'], $current_ids['output'], $current_ids['sub_output'], $current_ids['komponen'], $current_ids['sub_komponen'], $current_ids['akun']);
                
                $current_codes = ['program' => $kode];
                $current_ids['program'] = create_or_update($koneksi, 'master_program', ['kode' => $kode, 'tahun' => $tahun_anggaran], ['nama' => $nama]);

            } elseif (preg_match('/^\d{4}$/', $kode) && isset($current_ids['program'])) {
                // KEGIATAN
                $reset_last_item();
                unset($current_ids['output'], $current_ids['sub_output'], $current_ids['komponen'], $current_ids['sub_komponen'], $current_ids['akun']);

                $current_codes['kegiatan'] = $kode;
                $current_ids['kegiatan'] = create_or_update($koneksi, 'master_kegiatan', ['kode' => $kode, 'program_id' => $current_ids['program'], 'tahun' => $tahun_anggaran], ['nama' => $nama]);

            } elseif (preg_match('/^\d{4}\.[A-Z]{3}$/', $kode) && isset($current_ids['kegiatan'])) {
                // OUTPUT
                $reset_last_item();
                unset($current_ids['sub_output'], $current_ids['komponen'], $current_ids['sub_komponen'], $current_ids['akun']);

                $current_codes['output'] = $kode;
                $current_ids['output'] = create_or_update($koneksi, 'master_output', ['kode' => $kode, 'kegiatan_id' => $current_ids['kegiatan'], 'tahun' => $tahun_anggaran], ['nama' => $nama]);

            } elseif (preg_match('/^[A-Z]{3}\.\d{3}$/', $kode) && isset($current_ids['output'])) {
                // SUB OUTPUT
                $reset_last_item();
                unset($current_ids['komponen'], $current_ids['sub_komponen'], $current_ids['akun']);

                $current_codes['sub_output'] = $kode;
                $current_ids['sub_output'] = create_or_update($koneksi, 'master_sub_output', ['kode' => $kode, 'output_id' => $current_ids['output'], 'tahun' => $tahun_anggaran], ['nama' => $nama]);

            } elseif (preg_match('/^\d{3}$/', $kode) && isset($current_ids['sub_output'])) {
                // KOMPONEN
                $reset_last_item();
                unset($current_ids['sub_komponen'], $current_ids['akun']);

                $current_codes['komponen'] = $kode;
                $current_ids['komponen'] = create_or_update($koneksi, 'master_komponen', ['kode' => $kode, 'sub_output_id' => $current_ids['sub_output'], 'tahun' => $tahun_anggaran], ['nama' => $nama]);

            } elseif (preg_match('/^[A-Z]$/', $kode) && isset($current_ids['komponen'])) {
                // SUB KOMPONEN
                $reset_last_item();
                unset($current_ids['akun']);

                $current_codes['sub_komponen'] = $kode;
                $current_ids['sub_komponen'] = create_or_update($koneksi, 'master_sub_komponen', ['kode' => $kode, 'komponen_id' => $current_ids['komponen'], 'tahun' => $tahun_anggaran], ['nama' => $nama]);

            } elseif (preg_match('/^\d{6}$/', $kode) && isset($current_ids['sub_komponen'])) {
                // === AKUN (Ini yang paling krusial) ===
                $reset_last_item(); 
                
                $current_codes['akun'] = $kode;
                $current_ids['akun'] = create_or_update($koneksi, 'master_akun', ['kode' => $kode, 'sub_komponen_id' => $current_ids['sub_komponen'], 'tahun' => $tahun_anggaran], ['nama' => $nama]);

            } elseif (empty($kode) && !empty($nama) && isset($current_ids['akun'])) {
                // === ITEM PROCESSING BLOCK ===

                $raw_volume_satuan = trim((string)$sheet->getCell('J' . $row)->getValue());
                $raw_harga = $sheet->getCell('K' . $row)->getValue();
                $raw_pagu = $sheet->getCell('L' . $row)->getValue();

                $volume = 0; $satuan = ''; $harga = 0; $pagu = 0;
                if (preg_match('/^(\d+[\\.,\\d]*)\\s*(.*)$/', $raw_volume_satuan, $matches)) {
                    $volume = (int)str_replace(['.', ','], '', $matches[1]);
                    $satuan = trim($matches[2]);
                }
                $harga = (int)filter_var($raw_harga, FILTER_SANITIZE_NUMBER_INT) * 1000;
                $pagu = (int)filter_var($raw_pagu, FILTER_SANITIZE_NUMBER_INT) * 1000;

                // --- LOGIKA LANJUTAN (CONTINUATION) ---
                $is_continuation = false;

                // Hanya cek lanjutan JIKA $last_item_db_id TIDAK NULL.
                // Karena sudah di-reset saat ganti AKUN, maka item pertama di akun baru
                // TIDAK AKAN PERNAH masuk sini.
                if ($last_item_db_id !== null) {
                    // Pastikan lanjutan hanya jika parent/hierarchy juga sama (program,kegiatan,...,akun)
                    $same_hierarchy = ($last_item_codes === $current_codes);

                    // Jika PPK kosong (artinya baris kemungkinan lanjutan), volume & pagu sama,
                    // dan hierarchy juga identik => ini lanjutan (append nama).
                    if ($ppk === '' && $volume == $last_item_volume && $pagu == $last_item_pagu && $same_hierarchy) {
                        $is_continuation = true;
                    }
                }

                if ($is_continuation) {
                    // UPDATE ITEM SEBELUMNYA (GABUNG NAMA)
                    $append_text = ' ' . $nama;
                    $stmtGet = $koneksi->prepare("SELECT nama_item FROM master_item WHERE id = ?");
                    if ($stmtGet === false) throw new Exception("Prepare failed (select master_item for append): " . $koneksi->error);
                    $stmtGet->bind_param("i", $last_item_db_id);
                    $stmtGet->execute();
                    $resGet = $stmtGet->get_result();
                    
                    if ($existingRow = $resGet->fetch_assoc()) {
                        $new_nama = trim($existingRow['nama_item'] . $append_text);

                        // Simpan nama baru, tetapi JANGAN ubah kode_unik.
                        $stmtUpd = $koneksi->prepare("UPDATE master_item SET nama_item = ? WHERE id = ?");
                        if ($stmtUpd === false) throw new Exception("Prepare failed (update master_item nama): " . $koneksi->error);
                        $stmtUpd->bind_param("si", $new_nama, $last_item_db_id);
                        $stmtUpd->execute();
                        
                        $last_item_nama = $new_nama;
                    }
                    // Skip RPD karena ini cuma lanjutan teks
                    continue; 
                }

                // --- ITEM BARU (Bukan Lanjutan) ---
                $kode_unik = implode('-', [
                    $tahun_anggaran,
                    $current_codes['program'] ?? '',
                    $current_codes['kegiatan'] ?? '',
                    $current_codes['output'] ?? '',
                    $current_codes['sub_output'] ?? '',
                    $current_codes['komponen'] ?? '',
                    $current_codes['sub_komponen'] ?? '',
                    $current_codes['akun'] ?? '',
                    $nama
                ]);

                $item_data = [
                    'kode_unik' => $kode_unik,
                    'akun_id' => $current_ids['akun'],
                    'tahun' => $tahun_anggaran,
                    'nama_item' => $nama,
                    'satuan' => $satuan,
                    'volume' => $volume,
                    'harga' => $harga,
                    'pagu' => $pagu
                ];

                // Insert / Merge Item
                $affected_id = insert_or_merge_master_item($koneksi, $item_data);

                // --- SIMPAN RPD ---
                $monthly_map = [
                    1  => 'R', 2  => 'S', 3  => 'T', 4  => 'U', 5  => 'V', 6  => 'W',
                    7  => ['X','Y','Z'], 8  => 'AA', 9  => 'AB', 10 => 'AC', 11 => ['AD','AE'], 12 => ['AF','AH'],
                ];

                foreach ($monthly_map as $bulan => $cols) {
                    // Jika 1 bulan ada 2 kolom (misal Juni & Des), jumlahkan dulu
                    $jumlah_rpd = 0;
                    if (is_array($cols)) {
                        foreach ($cols as $col) {
                            $rawVal = $sheet->getCell($col . $row)->getCalculatedValue();
                            $jumlah_rpd += floatval($rawVal);
                        }
                    } else {
                        $rawVal = $sheet->getCell($cols . $row)->getCalculatedValue();
                        $jumlah_rpd = floatval($rawVal);
                    }
                    $jumlah_rpd = $jumlah_rpd * 1000; // Konversi x1000

                    // Simpan ke DB menggunakan kode_unik (unik sampai level komponen)
                    insert_rpd($koneksi, $kode_unik, $bulan, $jumlah_rpd, $tahun_anggaran);
                }

                // Set Snapshot untuk baris berikutnya
                $last_item_db_id = $affected_id;
                $last_item_volume = $volume;
                $last_item_pagu = $pagu;
                $last_item_ppk = $ppk;
                $last_item_nama = $nama;
                $last_item_codes = $current_codes;
            }
        } // end loop

        $koneksi->commit();
        $_SESSION['flash_message'] = "Data master untuk tahun {$tahun_anggaran} berhasil diimpor.";
        $_SESSION['flash_message_type'] = "success";
        header("Location: ../pages/master_data.php");
        exit();

    } catch (Throwable $e) {
        if ($koneksi && $koneksi->connect_errno == 0) {
            $koneksi->rollback();
        }
        $_SESSION['flash_message'] = "Error: " . $e->getMessage();
        $_SESSION['flash_message_type'] = "danger";
        header("Location: ../pages/upload.php");
        exit();
    }
} else {
    header("Location: ../pages/upload.php");
    exit();
}

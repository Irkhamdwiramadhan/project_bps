<?php
// Pastikan sesi sudah dimulai dan pengguna adalah admin
session_start();
include '../includes/koneksi.php';

// Validasi hak akses
if (!isset($_SESSION['user_role']) || !in_array('super_admin', $_SESSION['user_role'])) {
    die("Akses Ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.");
}

// Sertakan autoloader Composer
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Clean numeric values from Excel:
 * - Jika value sudah numeric -> pakai langsung
 * - Jika berisi pemisah (',' atau '.') -> deteksi apakah pemisah adalah thousand separator atau decimal separator
 * - Return float
 */
function cleanNumber($value) {
    if ($value === null || $value === '') {
        return 0.0;
    }

    // Jika PhpSpreadsheet sudah mengembalikan numeric type, langsung kembalikan
    if (is_int($value) || is_float($value) || is_numeric($value)) {
        return (float)$value;
    }

    $v = (string)$value;
    $v = trim($v);

    // Hapus currency, spasi, dan karakter selain digit, titik, koma, minus
    $v = preg_replace('/[^\d\-\.,]/u', '', $v);

    $hasComma = strpos($v, ',') !== false;
    $hasDot   = strpos($v, '.') !== false;

    // Jika ada keduanya, tentukan mana decimal:
    if ($hasComma && $hasDot) {
        // contoh: "1.234,56" -> dot thousand, comma decimal
        // atau       "1,234.56" -> comma thousand, dot decimal
        if (strpos($v, '.') < strrpos($v, ',')) {
            // dot sebelum comma => dot adalah thousand, comma decimal
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        } else {
            // comma sebelum dot => comma thousand, dot decimal
            $v = str_replace(',', '', $v);
            // dot tetap sebagai decimal
        }
        return (float)$v;
    }

    // Hanya comma
    if ($hasComma && !$hasDot) {
        $parts = explode(',', $v);
        $last = end($parts);
        // jika bagian terakhir panjang 3 dan ada lebih dari 1 bagian => kemungkinan comma sebagai thousand separator
        if (strlen($last) === 3 && count($parts) > 1) {
            $v = str_replace(',', '', $v); // hapus semua comma (thousand separators)
            return (float)$v;
        } else {
            // anggap comma sebagai decimal separator (contoh "150,50" => 150.50)
            $v = str_replace(',', '.', $v);
            return (float)$v;
        }
    }

    // Hanya dot
    if ($hasDot && !$hasComma) {
        $parts = explode('.', $v);
        $last = end($parts);
        if (strlen($last) === 3 && count($parts) > 1) {
            // dot digunakan sebagai thousand separator -> hapus semua dot
            $v = str_replace('.', '', $v);
            return (float)$v;
        } else {
            // dot sebagai decimal separator
            return (float)$v;
        }
    }

    // Tidak ada separator -> just cast
    return (float)$v;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["file_excel"])) {
    $tahun = isset($_POST['tahun']) ? (int) $_POST['tahun'] : (int)date('Y');
    
    // Validasi file yang diunggah
    $allowed_file_types = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv'
    ];
    $file_info = pathinfo($_FILES['file_excel']['name']);
    $file_ext = strtolower($file_info['extension']);
    
    if (!in_array($_FILES['file_excel']['type'], $allowed_file_types) && !in_array($file_ext, ['xls', 'xlsx', 'csv'])) {
        die("Tipe file tidak valid. Hanya izinkan .xlsx, .xls atau .csv.");
    }

    $inputFileName = $_FILES['file_excel']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($inputFileName);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, true);
        
        $koneksi->begin_transaction();

        // Lewati baris header (asumsi baris pertama adalah header)
        $header = array_shift($rows);

        foreach ($rows as $row) {
            // Mapping kolom dari Excel ke variabel (sesuaikan kolom A..L sesuai file)
            $unit_nama     = isset($row['A']) ? trim($row['A']) : '';
            $program_nama  = isset($row['B']) ? trim($row['B']) : '';
            $output_nama   = isset($row['C']) ? trim($row['C']) : '';
            $komponen_nama = isset($row['D']) ? trim($row['D']) : '';
            $akun_nama     = isset($row['E']) ? trim($row['E']) : '';
            $item_nama     = isset($row['F']) ? trim($row['F']) : '';
            $satuan        = isset($row['G']) ? trim($row['G']) : '';
            $volume_raw    = $row['H'] ?? null;
            $harga_raw     = $row['I'] ?? null;
            $pagu_raw      = $row['J'] ?? null;

            // Bersihkan angka dengan fungsi yang lebih cerdas
            $volume        = cleanNumber($volume_raw);
            $harga         = cleanNumber($harga_raw);
            $pagu          = cleanNumber($pagu_raw);

            // Validasi data dasar untuk item
            if (empty($item_nama) || empty($akun_nama)) {
                continue; // Lewati baris jika data utama kosong
            }

            // 1. UNIT
            $unit_id = null;
            if (!empty($unit_nama)) {
                $sql = "SELECT id FROM master_unit WHERE nama = ?";
                $stmt = $koneksi->prepare($sql);
                $stmt->bind_param("s", $unit_nama);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $unit_id = $result->fetch_assoc()['id'];
                } else {
                    $sql = "INSERT INTO master_unit (nama) VALUES (?)";
                    $stmt = $koneksi->prepare($sql);
                    $stmt->bind_param("s", $unit_nama);
                    $stmt->execute();
                    $unit_id = $koneksi->insert_id;
                }
                if ($result) $result->free();
            }

            // 2. PROGRAM
            $program_id = null;
            if (!empty($program_nama) && $unit_id !== null) {
                $sql = "SELECT id FROM master_program WHERE id_unit = ? AND nama = ?";
                $stmt = $koneksi->prepare($sql);
                $stmt->bind_param("is", $unit_id, $program_nama);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $program_id = $result->fetch_assoc()['id'];
                } else {
                    $sql = "INSERT INTO master_program (id_unit, nama) VALUES (?, ?)";
                    $stmt = $koneksi->prepare($sql);
                    $stmt->bind_param("is", $unit_id, $program_nama);
                    $stmt->execute();
                    $program_id = $koneksi->insert_id;
                }
                if ($result) $result->free();
            }

            // 3. OUTPUT
            $output_id = null;
            if (!empty($output_nama) && $program_id !== null) {
                $sql = "SELECT id FROM master_output WHERE id_program = ? AND nama = ?";
                $stmt = $koneksi->prepare($sql);
                $stmt->bind_param("is", $program_id, $output_nama);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $output_id = $result->fetch_assoc()['id'];
                } else {
                    $sql = "INSERT INTO master_output (id_program, nama) VALUES (?, ?)";
                    $stmt = $koneksi->prepare($sql);
                    $stmt->bind_param("is", $program_id, $output_nama);
                    $stmt->execute();
                    $output_id = $koneksi->insert_id;
                }
                if ($result) $result->free();
            }

            // 4. KOMPONEN
            $komponen_id = null;
            if (!empty($komponen_nama) && $output_id !== null) {
                $sql = "SELECT id FROM master_komponen WHERE id_output = ? AND nama = ?";
                $stmt = $koneksi->prepare($sql);
                $stmt->bind_param("is", $output_id, $komponen_nama);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $komponen_id = $result->fetch_assoc()['id'];
                } else {
                    $sql = "INSERT INTO master_komponen (id_output, nama) VALUES (?, ?)";
                    $stmt = $koneksi->prepare($sql);
                    $stmt->bind_param("is", $output_id, $komponen_nama);
                    $stmt->execute();
                    $komponen_id = $koneksi->insert_id;
                }
                if ($result) $result->free();
            }

            // 5. AKUN
            $akun_id = null;
            if (!empty($akun_nama) && $komponen_id !== null) {
                $sql = "SELECT id FROM master_akun WHERE id_komponen = ? AND nama = ?";
                $stmt = $koneksi->prepare($sql);
                $stmt->bind_param("is", $komponen_id, $akun_nama);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $akun_id = $result->fetch_assoc()['id'];
                } else {
                    $sql = "INSERT INTO master_akun (id_komponen, nama) VALUES (?, ?)";
                    $stmt = $koneksi->prepare($sql);
                    $stmt->bind_param("is", $komponen_id, $akun_nama);
                    $stmt->execute();
                    $akun_id = $koneksi->insert_id;
                }
                if ($result) $result->free();
            }

            // 6. ITEM (simpan)
            if ($akun_id !== null) {
                $sql = "INSERT INTO master_item 
                        (id_akun, tahun, nama_item, satuan, volume, harga, pagu) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $koneksi->prepare($sql);
                $stmt->bind_param(
                    "iissddd",
                    $akun_id,
                    $tahun,
                    $item_nama,
                    $satuan,
                    $volume,
                    $harga,
                    $pagu
                );
                $stmt->execute();
            }
        }
        
        $koneksi->commit();
        header("Location: ../pages/master_data.php?status=upload_success");
        exit();

    } catch (Exception $e) {
        $koneksi->rollback();
        die("Error saat memproses file: " . $e->getMessage());
    }

} else {
    header("Location: ../pages/tambah_master_data.php?status=no_file");
    exit();
}

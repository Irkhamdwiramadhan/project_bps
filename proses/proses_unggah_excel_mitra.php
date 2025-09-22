<?php
session_start();
include '../includes/koneksi.php';

// Pastikan request POST + ada file
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_FILES['excel_file'])) {
    header('Location: ../pages/tambah_mitra.php?status=error&message=Permintaan_tidak_valid');
    exit;
}

require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

try {
    // Validasi jenis file
    $allowedFileTypes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel'
    ];
    if (!in_array($_FILES['excel_file']['type'], $allowedFileTypes)) {
        throw new Exception("Jenis file tidak didukung. Gunakan file Excel (.xlsx / .xls).");
    }

    $inputFileName = $_FILES['excel_file']['tmp_name'];
    $spreadsheet   = IOFactory::load($inputFileName);
    $sheetData     = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

    if (empty($sheetData) || count($sheetData) < 2) {
        throw new Exception("File Excel kosong atau tidak ada data.");
    }

    // Mulai transaksi
    $koneksi->begin_transaction();

    // Perbaikan: Tambahkan kolom 'tahun' ke dalam query
    $sql = "INSERT INTO mitra (
        id_mitra, nama_lengkap, nik, tanggal_lahir, jenis_kelamin, agama,
        status_perkawinan, pendidikan, pekerjaan, deskripsi_pekerjaan_lain,
        npwp, no_telp, email, alamat_provinsi, alamat_kabupaten, nama_kecamatan,
        alamat_desa, alamat_detail, domisili_sama, posisi, mengikuti_pendataan_bps,
        sp, st, se, susenas, sakernas, sbh, tahun, tanggal_registrasi
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )";

    $stmt = $koneksi->prepare($sql);
    if (!$stmt) {
        throw new Exception("Gagal menyiapkan statement: " . $koneksi->error);
    }

    $inserted_rows = 0;
    $skipped_rows  = [];

    // Iterasi setiap baris (mulai baris ke-2 = setelah header)
    foreach ($sheetData as $row_index => $row) {
        if ($row_index < 2) continue;

        // Pemetaan dari Kolom Excel ke variabel PHP
        $id_mitra                  = trim($row['A'] ?? '');
        $nama_lengkap              = trim($row['B'] ?? '');
        $nik                       = trim($row['C'] ?? '');
        $tanggal_lahir             = trim($row['D'] ?? '');
        $jenis_kelamin             = trim($row['E'] ?? '');
        $agama                     = trim($row['F'] ?? '');
        $status_perkawinan         = trim($row['G'] ?? '');
        $pendidikan                = trim($row['H'] ?? '');
        $pekerjaan                 = trim($row['I'] ?? '');
        $deskripsi_pekerjaan_lain  = trim($row['J'] ?? '');
        $npwp                      = trim($row['K'] ?? '');
        $no_telp                   = trim($row['L'] ?? '');
        $email                     = trim($row['M'] ?? '');
        $alamat_provinsi           = trim($row['N'] ?? '');
        $alamat_kabupaten          = trim($row['O'] ?? '');
        $nama_kecamatan            = trim($row['P'] ?? '');
        $alamat_desa               = trim($row['Q'] ?? ''); // Alamat desa dari kolom Q
        $alamat_detail             = trim($row['R'] ?? ''); // Alamat detail dari kolom R
        $domisili_sama             = (strtolower(trim($row['S'] ?? '')) === 'ya') ? 1 : 0; 
        $posisi                    = trim($row['T'] ?? '');
        $mengikuti_pendataan_bps   = trim($row['U'] ?? ''); 
        
        $sp      = (trim($row['V'] ?? '0') === '1') ? 1 : 0;
        $st      = (trim($row['W'] ?? '0') === '1') ? 1 : 0;
        $se      = (trim($row['X'] ?? '0') === '1') ? 1 : 0;
        $susenas = (trim($row['Y'] ?? '0') === '1') ? 1 : 0;
        $sakernas= (trim($row['Z'] ?? '0') === '1') ? 1 : 0;
        $sbh     = (trim($row['AA'] ?? '0') === '1') ? 1 : 0;
        $tahun   = trim($row['AB'] ?? date('Y'));
        $tanggal_registrasi = date('Y-m-d H:i:s'); // Menggunakan waktu saat ini

        // Lewati jika kolom wajib kosong
        if (empty($id_mitra) || empty($nama_lengkap) || empty($nik)) {
            $skipped_rows[] = "Baris {$row_index} (data wajib kosong)";
            continue;
        }

        // Konversi tanggal
        $tanggal_lahir_formatted = null;
        if (!empty($tanggal_lahir)) {
            if (is_numeric($tanggal_lahir)) {
                $tanggal_lahir_formatted = Date::excelToDateTimeObject($tanggal_lahir)->format('Y-m-d');
            } else {
                $tanggal_lahir_formatted = date('Y-m-d', strtotime($tanggal_lahir));
            }
        }

        // Perbaikan: Sesuaikan string tipe data dengan 29 parameter
        $types = "ssssssssssssssssssisssiiiiiii"; 

        // Perbaikan: Sesuaikan urutan parameter dengan query
        $stmt->bind_param(
            $types,
            $id_mitra, $nama_lengkap, $nik, $tanggal_lahir_formatted,
            $jenis_kelamin, $agama, $status_perkawinan, $pendidikan,
            $pekerjaan, $deskripsi_pekerjaan_lain, $npwp, $no_telp, $email,
            $alamat_provinsi, $alamat_kabupaten, $nama_kecamatan,
            $alamat_desa, $alamat_detail, // Urutan ini harus sama dengan di query
            $domisili_sama,
            $posisi, $mengikuti_pendataan_bps,
            $sp, $st, $se, $susenas, $sakernas, $sbh,
            $tahun,
            $tanggal_registrasi
        );

        if (!$stmt->execute()) {
            $skipped_rows[] = "Baris {$row_index} (SQL Error: " . $stmt->error . ")";
            continue;
        }
        $inserted_rows++;
    }

    $koneksi->commit();
    $stmt->close();

    $message = "Berhasil menambahkan {$inserted_rows} data mitra.";
    if (!empty($skipped_rows)) {
        $message .= " Baris dilewati: " . implode(', ', $skipped_rows) . ".";
    }

    header("Location: ../pages/mitra.php?status=success&message=" . urlencode($message));
    exit;

} catch (Exception $e) {
    if ($koneksi) {
        $koneksi->rollback();
    }
    $message = urlencode($e->getMessage());
    header("Location: ../pages/tambah_mitra.php?status=error&message={$message}");
    exit;
}
?>

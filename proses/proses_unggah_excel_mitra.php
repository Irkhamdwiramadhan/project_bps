<?php
session_start();
include '../includes/koneksi.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_FILES['excel_file'])) {
    header('Location: ../pages/tambah_mitra.php?status=error&message=Permintaan_tidak_valid');
    exit;
}

require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

try {

    // ============================
    // VALIDASI FILE
    // ============================
    $allowedFileTypes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel'
    ];

    if (!in_array($_FILES['excel_file']['type'], $allowedFileTypes)) {
        throw new Exception("File tidak valid. Harus .xlsx atau .xls");
    }

    $inputFileName = $_FILES['excel_file']['tmp_name'];
    $spreadsheet   = IOFactory::load($inputFileName);
    $sheetData     = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

    if (empty($sheetData) || count($sheetData) < 2) {
        throw new Exception("File Excel kosong.");
    }

    $koneksi->begin_transaction();


    // ============================
    // PREPARED STATEMENT
    // ============================

    // Check existing
    $sql_check = "SELECT id FROM mitra WHERE nama_lengkap = ? AND email = ?";
    $stmt_check = $koneksi->prepare($sql_check);

    // INSERT (31 kolom)
    $sql_insert = "INSERT INTO mitra (
        id_mitra, nama_lengkap, nik, tanggal_lahir, jenis_kelamin, agama,
        status_perkawinan, pendidikan, pekerjaan, deskripsi_pekerjaan_lain,
        npwp, no_telp, email, alamat_provinsi, alamat_kabupaten, nama_kecamatan,
        alamat_desa, alamat_detail, domisili_sama, posisi, mengikuti_pendataan_bps,
        sp, st, se, susenas, sakernas, sbh, tahun, norek, bank, tanggal_registrasi
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt_insert = $koneksi->prepare($sql_insert);

    // UPDATE (29 kolom)
    $sql_update = "UPDATE mitra SET
        id_mitra = ?, nik = ?, tanggal_lahir = ?, jenis_kelamin = ?, agama = ?,
        status_perkawinan = ?, pendidikan = ?, pekerjaan = ?, deskripsi_pekerjaan_lain = ?,
        npwp = ?, no_telp = ?, alamat_provinsi = ?, alamat_kabupaten = ?, nama_kecamatan = ?,
        alamat_desa = ?, alamat_detail = ?, domisili_sama = ?, posisi = ?, mengikuti_pendataan_bps = ?,
        sp = ?, st = ?, se = ?, susenas = ?, sakernas = ?, sbh = ?,
        tahun = ?, norek = ?, bank = ?
    WHERE id = ?";

    $stmt_update = $koneksi->prepare($sql_update);

    if (!$stmt_check || !$stmt_insert || !$stmt_update) {
        throw new Exception("Gagal menyiapkan prepared statement");
    }


    // ============================
    // LOOP DATA EXCEL
    // ============================
    $inserted = 0;
    $updated  = 0;
    $skipped  = [];

    foreach ($sheetData as $i => $row) {

        if ($i < 2) continue; // skip header

        // Ambil semua kolom
        $id_mitra       = trim($row['A'] ?? '');
        $nama_lengkap   = trim($row['B'] ?? '');
        $nik            = trim($row['C'] ?? '');

        // Tanggal lahir
        $tgl_raw = trim($row['D'] ?? '');
        $tanggal_lahir = null;
        if (!empty($tgl_raw)) {
            if (is_numeric($tgl_raw)) {
                $tanggal_lahir = Date::excelToDateTimeObject($tgl_raw)->format('Y-m-d');
            } else {
                $ts = strtotime(str_replace('/', '-', $tgl_raw));
                if ($ts) $tanggal_lahir = date('Y-m-d', $ts);
            }
        }

        $jenis_kelamin  = trim($row['E'] ?? '');
        $agama          = trim($row['F'] ?? '');
        $status_kawin   = trim($row['G'] ?? '');
        $pendidikan     = trim($row['H'] ?? '');
        $pekerjaan      = trim($row['I'] ?? '');
        $desc_pekerjaan = trim($row['J'] ?? '');
        $npwp           = trim($row['K'] ?? '');
        $no_telp        = trim($row['L'] ?? '');
        $email          = trim($row['M'] ?? '');

        if (empty($nama_lengkap) || empty($email)) {
            $skipped[] = "Baris $i: Nama atau Email kosong";
            continue;
        }

        $provinsi   = trim($row['N'] ?? '');
        $kabupaten  = trim($row['O'] ?? '');
        $kecamatan  = trim($row['P'] ?? '');
        $desa       = trim($row['Q'] ?? '');
        $detail     = trim($row['R'] ?? '');
        $domisili   = (strtolower(trim($row['S'] ?? '')) == 'ya') ? 1 : 0;
        $posisi     = trim($row['T'] ?? '');
        $ikut_bps   = trim($row['U'] ?? '');

        // checkbox
        $sp       = (trim($row['V'] ?? '0') == '1') ? 1 : 0;
        $st       = (trim($row['W'] ?? '0') == '1') ? 1 : 0;
        $se       = (trim($row['X'] ?? '0') == '1') ? 1 : 0;
        $susenas  = (trim($row['Y'] ?? '0') == '1') ? 1 : 0;
        $sakernas = (trim($row['Z'] ?? '0') == '1') ? 1 : 0;
        $sbh      = (trim($row['AA'] ?? '0') == '1') ? 1 : 0;

        $tahun = trim($row['AB'] ?? date('Y'));
        $norek = trim($row['AC'] ?? '');
        $bank  = trim($row['AD'] ?? '');
        $tgl_reg = date('Y-m-d H:i:s');


        // ==================================
        // CHECK APAKAH SUDAH ADA
        // ==================================
        $stmt_check->bind_param("ss", $nama_lengkap, $email);
        $stmt_check->execute();

        $result = $stmt_check->get_result();
        $exist = $result->fetch_assoc();


        // =======================
        // UPDATE (29 kolom)
        // =======================
        if ($exist) {

            $id_db = $exist['id'];

            $types_update =
                "ssssssssss" .  // 10
                "sssssss"    .  // 17
                "i"          .  // 18
                "ss"         .  // 20
                "iiiiii"     .  // 26
                "sss";          // 29

            $stmt_update->bind_param(
                $types_update,
                $id_mitra, $nik, $tanggal_lahir, $jenis_kelamin, $agama,
                $status_kawin, $pendidikan, $pekerjaan, $desc_pekerjaan,
                $npwp, $no_telp,
                $provinsi, $kabupaten, $kecamatan,
                $desa, $detail,
                $domisili,
                $posisi, $ikut_bps,
                $sp, $st, $se, $susenas, $sakernas, $sbh,
                $tahun, $norek, $bank,
                $id_db
            );

            if ($stmt_update->execute()) {
                $updated++;
            } else {
                $skipped[] = "Baris $i gagal UPDATE: " . $stmt_update->error;
            }

        }

        // =======================
        // INSERT (31 kolom)
        // =======================
        else {

            $types_insert =
                "ssssssssssssssssss" .  // 18
                "i" .                  // 19
                "ss" .                 // 21
                "iiiiii" .             // 27
                "ssss";                // 31

            $stmt_insert->bind_param(
                $types_insert,
                $id_mitra, $nama_lengkap, $nik, $tanggal_lahir, $jenis_kelamin, $agama,
                $status_kawin, $pendidikan, $pekerjaan, $desc_pekerjaan,
                $npwp, $no_telp, $email, $provinsi, $kabupaten, $kecamatan,
                $desa, $detail, 
                $domisili,
                $posisi, $ikut_bps,
                $sp, $st, $se, $susenas, $sakernas, $sbh,
                $tahun, $norek, $bank, $tgl_reg
            );

            if ($stmt_insert->execute()) {
                $inserted++;
            } else {
                $skipped[] = "Baris $i gagal INSERT: " . $stmt_insert->error;
            }
        }
    }

    $koneksi->commit();


    // ========================
    // SUCCESS MESSAGE
    // ========================
    $msg = "Berhasil! INSERT: $inserted, UPDATE: $updated";
    if (!empty($skipped)) {
        $msg .= " | Warning: " . implode(" | ", array_slice($skipped, 0, 3));
    }

    header("Location: ../pages/mitra.php?status=success&message=" . urlencode($msg));
    exit;


} catch (Exception $e) {
    $koneksi->rollback();
    header("Location: ../pages/tambah_mitra.php?status=error&message=" . urlencode($e->getMessage()));
    exit;
}
?>

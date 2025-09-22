<?php
session_start();
include '../includes/koneksi.php';

// Pastikan parameter bulan dan tahun ada di URL
if (!isset($_GET['month']) || !isset($_GET['year'])) {
    header('Location: ../pages/apel.php?status=error&message=' . urlencode('Filter_tidak_lengkap'));
    exit;
}

$month = $_GET['month'];
$year = $_GET['year'];

// Buat nama file yang akan diunduh
$month_name_indo = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$file_name = "Rekap_Kehadiran_Apel_{$month_name_indo[$month]}_{$year}.csv";

// Set header HTTP untuk memaksa unduhan file CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Buka output stream
$output = fopen('php://output', 'w');

// Definisi status kehadiran untuk header CSV
$status_kehadiran = [
    'hadir_awal' => 'Hadir Awal',
    'hadir' => 'Hadir',
    'telat_1' => 'Telat 1',
    'telat_2' => 'Telat 2',
    'telat_3' => 'Telat 3',
    'izin' => 'Izin',
    'absen' => 'Absen',
    'dinas_luar' => 'Dinas Luar',
    'sakit' => 'Sakit',
    'cuti' => 'Cuti',
    'tugas' => 'Tugas'
];

// Tulis header CSV yang baru
$header_row = array_merge(['Nama Pegawai'], array_values($status_kehadiran));
fputcsv($output, $header_row);

try {
    // 1. Ambil daftar semua nama pegawai beserta ID-nya dari tabel `pegawai`
    $sql_pegawai = "SELECT id, nama FROM pegawai ORDER BY nama ASC";
    $result_pegawai = $koneksi->query($sql_pegawai);
    
    $rekap_pegawai = [];
    $pegawai_map = [];
    if ($result_pegawai->num_rows > 0) {
        while ($row = $result_pegawai->fetch_assoc()) {
            $id = $row['id'];
            $nama = $row['nama'];
            // Simpan nama pegawai berdasarkan ID
            $pegawai_map[$id] = $nama;
            // Inisialisasi data rekap untuk setiap pegawai dengan nilai 0
            $rekap_pegawai[$id] = array_fill_keys(array_keys($status_kehadiran), 0);
        }
    }

    // Tambahkan entri khusus untuk ID yang tidak terdaftar
    $rekap_pegawai['unknown'] = array_fill_keys(array_keys($status_kehadiran), 0);
    $pegawai_map['unknown'] = 'Nama Tidak Diketahui';

    // 2. Ambil seluruh data kehadiran dari tabel `apel` untuk bulan yang dipilih
    $sql_apel = "SELECT kehadiran FROM apel WHERE tanggal LIKE ? AND kondisi_apel IN ('ada', 'lupa_didokumentasikan')";
    $stmt_apel = $koneksi->prepare($sql_apel);
    $search_date = "{$year}-{$month}-%";
    $stmt_apel->bind_param("s", $search_date);
    $stmt_apel->execute();
    $result_apel = $stmt_apel->get_result();

    // 3. Proses data dan tambahkan ke rekapitulasi berdasarkan ID
    if ($result_apel->num_rows > 0) {
        while ($row = $result_apel->fetch_assoc()) {
            $kehadiran_data = json_decode($row['kehadiran'], true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($kehadiran_data)) {
                foreach ($kehadiran_data as $kehadiran) {
                    // PERBAIKAN: Ambil ID dari JSON, baik itu 'id_pegawai' atau 'id'
                    $id = $kehadiran['id_pegawai'] ?? $kehadiran['id'] ?? 'unknown';
                    $status = $kehadiran['status'] ?? 'absen';
                    
                    if (isset($rekap_pegawai[$id]) && array_key_exists($status, $rekap_pegawai[$id])) {
                        $rekap_pegawai[$id][$status]++;
                    } else if (!isset($rekap_pegawai[$id])) {
                        // Jika ID tidak ada di daftar pegawai, masukkan ke "Nama Tidak Diketahui"
                        $rekap_pegawai['unknown'][$status]++;
                    }
                }
            }
        }
    }
    
    // 4. Tulis data rekapitulasi ke file CSV
    if (!empty($rekap_pegawai)) {
        // Gabungkan data pegawai dan rekapitulasi
        $final_report = [];
        foreach($pegawai_map as $id_pegawai => $nama_pegawai) {
            $counts = $rekap_pegawai[$id_pegawai] ?? array_fill_keys(array_keys($status_kehadiran), 0);
            $final_report[$nama_pegawai] = $counts;
        }

        ksort($final_report); // Urutkan data berdasarkan nama pegawai

        foreach ($final_report as $nama_pegawai => $counts) {
            $data_row = [$nama_pegawai];
            foreach ($status_kehadiran as $key => $value) {
                $data_row[] = $counts[$key] ?? 0;
            }
            fputcsv($output, $data_row);
        }
    } else {
        fputcsv($output, ['Tidak ada data kehadiran untuk bulan yang dipilih.']);
    }

    $stmt_apel->close();
} catch (Exception $e) {
    fputcsv($output, ["Error: " . $e->getMessage()]);
}

fclose($output);
$koneksi->close();
exit;
?>
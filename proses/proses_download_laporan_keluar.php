<?php
session_start();
include '../includes/koneksi.php';



// 1. Siapkan header HTTP untuk memberitahu browser agar men-download file
$filename = "Laporan_Keluar_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// 2. Buka output stream PHP untuk menulis file
$output = fopen('php://output', 'w');

// 3. Tulis baris header (judul kolom) untuk file CSV
fputcsv($output, [
    'Nama Pegawai', 
    'Tanggal', 
    'Jam', 
    'Tujuan Keluar', 
    'Link GPS'
]);

// 4. Ambil data dari database (query yang sama seperti di halaman list)
$sql = "SELECT p.nama AS nama_pegawai, lk.tanggal_laporan, lk.jam_laporan, lk.tujuan_keluar, lk.link_gps
        FROM laporan_keluar lk
        LEFT JOIN pegawai p ON lk.pegawai_id = p.id
        ORDER BY lk.tanggal_laporan DESC, lk.jam_laporan DESC";

$result = $koneksi->query($sql);

// 5. Tulis setiap baris data ke file CSV
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
}

// 6. Tutup koneksi dan output stream
fclose($output);
$koneksi->close();
exit(); // Hentikan eksekusi skrip setelah file selesai dibuat
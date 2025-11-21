<?php
// File: pages/get_status_penilaian_tim.php

ob_start();
include '../includes/koneksi.php';

$tim_id = isset($_GET['tim_id']) ? intval($_GET['tim_id']) : 0;
$tahun  = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y'); // Default tahun ini

if ($tim_id === 0) {
    ob_end_clean();
    echo json_encode([]);
    exit;
}

function formatPeriodeApi($jenis, $nilai) {
    $jenis = ucfirst($jenis);
    if (empty($nilai)) return "";
    
    $nama_bulan = [
        1=>'Jan', '01'=>'Jan', 2=>'Feb', '02'=>'Feb', 3=>'Mar', '03'=>'Mar',
        4=>'Apr', '04'=>'Apr', 5=>'Mei', '05'=>'Mei', 6=>'Jun', '06'=>'Jun',
        7=>'Jul', '07'=>'Jul', 8=>'Ags', '08'=>'Ags', 9=>'Sep', '09'=>'Sep',
        10=>'Okt', 11=>'Nov', 12=>'Des'
    ];

    if (strtolower($jenis) == 'bulanan') return $nama_bulan[$nilai] ?? $nilai;
    if (strtolower($jenis) == 'triwulan') return "TW $nilai";
    if (strtolower($jenis) == 'subron') return "Subron $nilai";
    if (strtolower($jenis) == 'tahunan') return "Thn $nilai";
    return "$jenis $nilai";
}

// Query Utama (Update Filter Tahun)
$sql = "
    SELECT 
        ms.id AS survey_id,
        m.nama_lengkap,
        m.id AS mitra_id,
        
        -- Info Kegiatan
        (SELECT nama FROM master_kegiatan WHERE kode = ms.kegiatan_id AND tahun = hm.tahun_pembayaran LIMIT 1) AS nama_kegiatan,
        
        -- Info Item
        (SELECT nama_item FROM master_item 
         WHERE kode_unik LIKE CONCAT(hm.item_kode_unik, '%') 
         AND tahun = hm.tahun_pembayaran
         ORDER BY LENGTH(kode_unik) DESC 
         LIMIT 1
        ) AS nama_item,
        
        ms.periode_jenis,
        ms.periode_nilai,
        hm.tahun_pembayaran,
        
        mpk.id AS penilaian_id,
        (mpk.kualitas + mpk.volume_pemasukan + mpk.perilaku) / 3 AS rata_rata
        
    FROM mitra_surveys ms
    JOIN mitra m ON ms.mitra_id = m.id
    JOIN honor_mitra hm ON ms.id = hm.mitra_survey_id
    LEFT JOIN mitra_penilaian_kinerja mpk ON ms.id = mpk.mitra_survey_id
    
    WHERE ms.tim_id = ? 
      AND hm.tahun_pembayaran = ? -- REVISI: Filter Tahun
    
    GROUP BY ms.id 
    ORDER BY m.nama_lengkap ASC, ms.id DESC
";

$stmt = $koneksi->prepare($sql);
$stmt->bind_param("ii", $tim_id, $tahun); // Bind Tim ID dan Tahun
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $periodeLabel = formatPeriodeApi($row['periode_jenis'], $row['periode_nilai']);
    $row['label_periode'] = "{$periodeLabel} {$row['tahun_pembayaran']}";
    
    $row['status_teks'] = $row['penilaian_id'] ? "Sudah Dinilai" : "Belum Dinilai";
    $row['skor_akhir'] = $row['penilaian_id'] ? number_format($row['rata_rata'], 2) : "-";
    
    $keg = $row['nama_kegiatan'] ?? 'Kegiatan Tidak Ditemukan';
    $item = $row['nama_item'] ?? 'Item Tidak Ditemukan';
    $row['pekerjaan_lengkap'] = "$keg - $item";
    
    $data[] = $row;
}

ob_end_clean();
header('Content-Type: application/json');
echo json_encode($data);
?>
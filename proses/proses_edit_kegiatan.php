<?php
session_start();
include '../includes/koneksi.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. TANGKAP ACUAN DATA LAMA
    $old_tim_id     = $_POST['old_tim_id'];
    $old_keg_kode   = $_POST['old_keg_kode'];
    $old_item_kode  = $_POST['old_item_kode'];
    $old_bulan      = $_POST['old_bulan'];
    $old_tahun      = $_POST['old_tahun'];

    // 2. TANGKAP DATA BARU DARI FORM
    $tim_id_baru = $_POST['tim_id'];
    $kegiatan_id_input = $_POST['kegiatan_id']; 
    $item_kode = $_POST['item_id'];
    $harga_satuan = $_POST['harga_per_satuan']; 
    
    // --- TRANSLATE KEGIATAN ID -> KODE ---
    $kegiatan_kode = $kegiatan_id_input;
    $q_keg = $koneksi->query("SELECT kode FROM master_kegiatan WHERE id = '$kegiatan_id_input' OR kode = '$kegiatan_id_input' LIMIT 1");
    if ($q_keg && $q_keg->num_rows > 0) {
        $kegiatan_kode = $q_keg->fetch_assoc()['kode'];
    }

    // Periode Pelaksanaan
    $periode_jenis = $_POST['periode_jenis'];
    $periode_nilai = '';
    if ($periode_jenis == 'bulanan' && isset($_POST['periode_nilai_bulanan'])) {
        $periode_nilai = implode(',', $_POST['periode_nilai_bulanan']);
    } elseif ($periode_jenis == 'triwulan' && isset($_POST['periode_nilai_triwulan'])) {
        $periode_nilai = implode(',', $_POST['periode_nilai_triwulan']);
    } elseif ($periode_jenis == 'subron' && isset($_POST['periode_nilai_subron'])) {
        $periode_nilai = implode(',', $_POST['periode_nilai_subron']);
    } elseif ($periode_jenis == 'tahunan') {
        $periode_nilai = $_POST['periode_nilai_tahunan'];
    }

    // Waktu Pencairan
    $tahun_pembayaran = $_POST['tahun_pembayaran'];
    $bulan_pembayaran_arr = $_POST['bulan_pembayaran'] ?? [];

    // Mitra & Jumlah Satuan
    $mitra_id_arr = $_POST['mitra_id'] ?? [];
    $jumlah_satuan_arr = $_POST['jumlah_satuan'] ?? [];

    $koneksi->begin_transaction();

    try {
        // ==========================================
        // STEP 1: HAPUS DATA LAMA
        // ==========================================
        $stmt_find = $koneksi->prepare("
            SELECT DISTINCT ms.id 
            FROM mitra_surveys ms
            JOIN honor_mitra hm ON ms.id = hm.mitra_survey_id
            WHERE ms.tim_id = ? AND ms.kegiatan_id = ? AND hm.item_kode_unik = ?
              AND (hm.bulan_pembayaran = ? OR hm.bulan_pembayaran = ?) AND hm.tahun_pembayaran = ?
        ");
        
        $bulan_str = str_pad($old_bulan, 2, '0', STR_PAD_LEFT);
        $bulan_int = (int)$old_bulan;

        $stmt_find->bind_param("issssi", $old_tim_id, $old_keg_kode, $old_item_kode, $bulan_str, $bulan_int, $old_tahun);
        $stmt_find->execute();
        $res_find = $stmt_find->get_result();
        
        $ms_ids_to_delete = [];
        while($row = $res_find->fetch_assoc()){
            $ms_ids_to_delete[] = $row['id'];
        }
        $stmt_find->close();

        if (!empty($ms_ids_to_delete)) {
            $ids_str = implode(',', $ms_ids_to_delete);
            
            $koneksi->query("DELETE FROM honor_mitra WHERE mitra_survey_id IN ($ids_str) AND item_kode_unik = '$old_item_kode' AND (bulan_pembayaran = '$bulan_str' OR bulan_pembayaran = '$bulan_int') AND tahun_pembayaran = '$old_tahun'");
            
            foreach ($ms_ids_to_delete as $id_survey) {
                $cek_anak = $koneksi->query("SELECT id FROM honor_mitra WHERE mitra_survey_id = $id_survey")->num_rows;
                if ($cek_anak == 0) {
                    $koneksi->query("DELETE FROM mitra_surveys WHERE id = $id_survey");
                }
            }
        }

        // ==========================================
        // STEP 2: INSERT DATA BARU (Dengan jumlah_satuan & honor_per_satuan)
        // ==========================================
        
        $stmt_survey = $koneksi->prepare("INSERT INTO mitra_surveys (tim_id, kegiatan_id, periode_jenis, periode_nilai) VALUES (?, ?, ?, ?)");
        if (!$stmt_survey) throw new Exception("Error Survey: " . $koneksi->error);
        
        $stmt_survey->bind_param("isss", $tim_id_baru, $kegiatan_kode, $periode_jenis, $periode_nilai);
        $stmt_survey->execute();
        $mitra_survey_id = $stmt_survey->insert_id;

        // QUERY SUDAH FIX SESUAI NAMA KOLOM DARI ANDA!
        $stmt_honor = $koneksi->prepare("INSERT INTO honor_mitra (mitra_survey_id, mitra_id, item_kode_unik, jumlah_satuan, honor_per_satuan, total_honor, bulan_pembayaran, tahun_pembayaran) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt_honor) throw new Exception("Error Honor: " . $koneksi->error);

        foreach ($bulan_pembayaran_arr as $bulan_bayar) {
            foreach ($mitra_id_arr as $index => $mitra_id) {
                if (empty($mitra_id)) continue;
                
                $jumlah_satuan = floatval($jumlah_satuan_arr[$index]);
                $honor_per_satuan = floatval($harga_satuan);
                $total_honor = $jumlah_satuan * $honor_per_satuan;

                // Parameter: (Int, Int, String, Double, Double, Double, String, Int)
                $stmt_honor->bind_param("iisdddsi", 
                    $mitra_survey_id, 
                    $mitra_id, 
                    $item_kode, 
                    $jumlah_satuan,
                    $honor_per_satuan, 
                    $total_honor, 
                    $bulan_bayar, 
                    $tahun_pembayaran
                );
                if (!$stmt_honor->execute()) throw new Exception("Insert failed: " . $stmt_honor->error);
            }
        }

        $koneksi->commit();
        echo "<script>alert('Data kegiatan berhasil diperbarui!'); window.location.href='../pages/rekap_kegiatan_tim.php';</script>";

    } catch (Exception $e) {
        $koneksi->rollback();
        $msg = addslashes($e->getMessage());
        $msg = str_replace(["\r", "\n"], ' ', $msg);
        echo "<script>alert('Gagal menyimpan: $msg'); window.history.back();</script>";
    }

} else {
    header("Location: ../pages/rekap_kegiatan_tim.php");
}
?>
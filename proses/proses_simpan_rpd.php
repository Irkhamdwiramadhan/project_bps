<?php
// File: proses_simpan_rpd.php
session_start();
include '../includes/koneksi.php';

// Pastikan data dikirim melalui metode POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_pengelola = $_SESSION['user_id'];
    $tahun = (int) $_POST['tahun'];
    $data_rpd_form = $_POST['rpd'] ?? [];

    // Ambil data RPD yang sudah ada untuk tahun ini
    $sql_existing_rpd = "SELECT id_item, bulan, jumlah FROM rpd WHERE id_pengaju = ? AND tahun = ?";
    $stmt_existing = $koneksi->prepare($sql_existing_rpd);
    $stmt_existing->bind_param("ii", $id_pengelola, $tahun);
    $stmt_existing->execute();
    $result_existing = $stmt_existing->get_result();
    $existing_rpd_data = [];
    while ($row = $result_existing->fetch_assoc()) {
        $existing_rpd_data[$row['id_item']][$row['bulan']] = (float) $row['jumlah'];
    }
    $stmt_existing->close();

    // Ambil semua data pagu anggaran untuk validasi
    $sql_pagu = "SELECT mi.id, mi.pagu FROM master_item mi
                 LEFT JOIN akun_pengelola_tahun apt ON mi.id_akun = apt.akun_id AND apt.tahun = mi.tahun
                 WHERE apt.id_pengelola = ? AND mi.tahun = ?";
    $stmt_pagu = $koneksi->prepare($sql_pagu);
    $stmt_pagu->bind_param("ii", $id_pengelola, $tahun);
    $stmt_pagu->execute();
    $result_pagu = $stmt_pagu->get_result();
    $pagu_data = [];
    while ($row = $result_pagu->fetch_assoc()) {
        $pagu_data[$row['id']] = (float) $row['pagu'];
    }
    $stmt_pagu->close();

    // Mulai transaksi untuk memastikan konsistensi data
    $koneksi->begin_transaction();

    try {
        // Validasi 1: Total RPD tidak boleh melebihi pagu
        foreach ($data_rpd_form as $id_item => $rpd_bulan) {
            $total_rpd_item = 0;
            $pagu_item = $pagu_data[$id_item] ?? 0;

            // Hitung total RPD dari data yang dikirim dan data lama
            foreach ($rpd_bulan as $bulan => $jumlah) {
                $jumlah_bersih = (float) str_replace('.', '', trim($jumlah));
                $data_rpd_form[$id_item][$bulan] = $jumlah_bersih; // Bersihkan dan simpan
            }

            // Gabungkan data lama dan data baru untuk validasi
            $merged_rpd = array_merge($existing_rpd_data[$id_item] ?? [], $data_rpd_form[$id_item]);
            $total_rpd_item = array_sum($merged_rpd);

            if ($total_rpd_item > $pagu_item) {
                // Jangan tampilkan pesan error detail ke pengguna, cukup pesan umum
                throw new Exception("Total RPD untuk item ini melebihi pagu anggaran.");
            }
        }
        
        // Validasi 2: Tidak bisa mengubah RPD untuk bulan yang sudah berlalu
        $current_month = date('n');
        $current_year = date('Y');
        foreach ($data_rpd_form as $id_item => $rpd_bulan) {
            foreach ($rpd_bulan as $bulan => $jumlah) {
                 if ($tahun < $current_year || ($tahun == $current_year && $bulan < $current_month)) {
                    // Jika ada data yang dikirim untuk bulan yang sudah berlalu, tolak
                    throw new Exception("Tidak dapat mengubah RPD untuk bulan yang sudah berlalu.");
                 }
            }
        }
        
        // Perbaikan: Hapus semua data RPD untuk tahun dan pengelola ini.
        // Ini adalah cara paling aman untuk memastikan tidak ada data yang tersisa atau terduplikasi.
        $sql_delete_all = "DELETE FROM rpd WHERE id_pengaju = ? AND tahun = ?";
        $stmt_delete_all = $koneksi->prepare($sql_delete_all);
        $stmt_delete_all->bind_param("ii", $id_pengelola, $tahun);
        $stmt_delete_all->execute();
        $stmt_delete_all->close();
        
        // Simpan data RPD yang sudah divalidasi dan digabungkan
        $sql_insert = "INSERT INTO rpd (id_item, id_pengaju, bulan, tahun, jumlah) VALUES (?, ?, ?, ?, ?)";
        $stmt_insert = $koneksi->prepare($sql_insert);
        
        // Loop melalui data yang sudah digabungkan dan divalidasi untuk disimpan
        foreach ($data_rpd_form as $id_item => $rpd_bulan) {
            foreach ($rpd_bulan as $bulan => $jumlah) {
                if ($jumlah > 0) {
                    // Menggunakan variabel eksplisit untuk bind_param
                    $temp_id_item = $id_item;
                    $temp_id_pengelola = $id_pengelola;
                    $temp_bulan = $bulan;
                    $temp_tahun = $tahun;
                    $temp_jumlah = $jumlah;
                    
                    $stmt_insert->bind_param("iiisd", $temp_id_item, $temp_id_pengelola, $temp_bulan, $temp_tahun, $temp_jumlah);
                    $stmt_insert->execute();
                }
            }
        }
        
        $stmt_insert->close();
        
        // Commit transaksi
        $koneksi->commit();
        
        // Alihkan pengguna ke halaman RPD setelah berhasil
        header("Location: ../pages/rpd.php?status=success_simpan");
        exit();

    } catch (Exception $e) {
        $koneksi->rollback();
        die("Error: " . $e->getMessage());
    }
} else {
    // Jika bukan metode POST, berikan respons error
    die("Metode permintaan tidak valid.");
}
?>
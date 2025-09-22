<?php
session_start();
include '../includes/koneksi.php';

// Pastikan request method adalah POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tahun = $_POST['tahun'] ?? null;
    $realisasi_data = $_POST['realisasi'] ?? [];

    if (empty($tahun) || empty($realisasi_data)) {
        header("Location: ../pages/tambah_realisasi.php?status=error&message=Data tidak lengkap.");
        exit();
    }

    $koneksi->begin_transaction();
    $berhasil = true;

    try {
        // Prepare statement untuk mendapatkan id_rpd berdasarkan id_item, tahun, dan bulan
        $sql_rpd_id = "SELECT id FROM rpd WHERE id_item = ? AND tahun = ? AND bulan = ?";
        $stmt_rpd_id = $koneksi->prepare($sql_rpd_id);

        // Prepare statement untuk memeriksa apakah realisasi sudah ada
        $sql_check = "SELECT id FROM realisasi WHERE id_rpd = ?";
        $stmt_check = $koneksi->prepare($sql_check);

        // Prepare statement untuk INSERT data realisasi baru
        $sql_insert = "INSERT INTO realisasi (id_rpd, jumlah, tanggal_realisasi) VALUES (?, ?, NOW())";
        $stmt_insert = $koneksi->prepare($sql_insert);

        // Prepare statement untuk UPDATE data realisasi yang sudah ada
        $sql_update = "UPDATE realisasi SET jumlah = ?, tanggal_realisasi = NOW() WHERE id_rpd = ?";
        $stmt_update = $koneksi->prepare($sql_update);

        // Prepare statement untuk DELETE realisasi jika jumlahnya 0
        $sql_delete = "DELETE FROM realisasi WHERE id_rpd = ?";
        $stmt_delete = $koneksi->prepare($sql_delete);

        foreach ($realisasi_data as $id_item => $bulan_data) {
            foreach ($bulan_data as $bulan => $jumlah_realisasi_string) {
                $jumlah_realisasi = (float) str_replace(['.', ','], ['', '.'], $jumlah_realisasi_string);

                $stmt_rpd_id->bind_param("iii", $id_item, $tahun, $bulan);
                $stmt_rpd_id->execute();
                $rpd_result = $stmt_rpd_id->get_result();
                $rpd_row = $rpd_result->fetch_assoc();
                $id_rpd = $rpd_row['id'] ?? null;

                if ($id_rpd) {
                    $stmt_check->bind_param("i", $id_rpd);
                    $stmt_check->execute();
                    $check_result = $stmt_check->get_result();
                    $realisasi_sudah_ada = $check_result->num_rows > 0;

                    if ($jumlah_realisasi > 0) {
                        if ($realisasi_sudah_ada) {
                            $stmt_update->bind_param("di", $jumlah_realisasi, $id_rpd);
                            if (!$stmt_update->execute()) { $berhasil = false; break 2; }
                        } else {
                            $stmt_insert->bind_param("id", $id_rpd, $jumlah_realisasi);
                            if (!$stmt_insert->execute()) { $berhasil = false; break 2; }
                        }
                    } else {
                        if ($realisasi_sudah_ada) {
                            $stmt_delete->bind_param("i", $id_rpd);
                            if (!$stmt_delete->execute()) { $berhasil = false; break 2; }
                        }
                    }
                }
            }
        }

        $stmt_rpd_id->close();
        $stmt_check->close();
        $stmt_insert->close();
        $stmt_update->close();
        $stmt_delete->close();

        if ($berhasil) {
            $koneksi->commit();
            header("Location: ../pages/realisasi_admin.php?status=success_simpan");
            exit();
        } else {
            $koneksi->rollback();
            header("Location: ../pages/tambah_realisasi.php?status=error_simpan");
            exit();
        }

    } catch (Exception $e) {
        $koneksi->rollback();
        header("Location: ../pages/tambah_realisasi.php?status=error_simpan");
        exit();
    }
} else {
    header("Location: ../pages/tambah_realisasi.php?status=error&message=Metode request tidak valid.");
    exit();
}
?>
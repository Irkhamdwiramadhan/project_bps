<?php
session_start();
require '../vendor/autoload.php';
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// --- FUNGSI HELPER ---

function getMemberInfo($koneksi, $nama_anggota) {
    $nama_anggota = trim($nama_anggota);
    if (empty($nama_anggota)) return null;

    // Cek Pegawai
    $stmt = $koneksi->prepare("SELECT id FROM pegawai WHERE TRIM(nama) = ? LIMIT 1");
    $stmt->bind_param("s", $nama_anggota);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) return $row['id'];

    // Cek Mitra
    $stmt2 = $koneksi->prepare("SELECT id FROM mitra WHERE TRIM(nama_lengkap) = ? LIMIT 1");
    $stmt2->bind_param("s", $nama_anggota);
    $stmt2->execute();
    if ($row2 = $stmt2->get_result()->fetch_assoc()) return $row2['id'];

    return null; 
}

function getKegiatanInfo($koneksi, $nama_kegiatan, $nama_tim, $batas_waktu) {
    // Pastikan pencarian memperhitungkan Tanggal agar unik
    $stmt = $koneksi->prepare("
        SELECT k.id 
        FROM kegiatan k 
        JOIN tim t ON k.tim_id = t.id 
        WHERE k.nama_kegiatan = ? 
          AND t.nama_tim = ? 
          AND k.batas_waktu = ? 
        LIMIT 1
    ");
    $stmt->bind_param("sss", $nama_kegiatan, $nama_tim, $batas_waktu);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) return $row['id'];
    return null;
}

// --- MULAI PROSES ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_excel'])) {
    
    $file = $_FILES['file_excel']['tmp_name'];
    $error_messages = [];
    $koneksi->begin_transaction();

    try {
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        array_shift($rows); // Hapus Header

        $updated_count = 0;
        $affected_kegiatan_ids = []; 

        foreach ($rows as $i => $row) {
            $row_num = $i + 2;
            
            // Mapping Kolom
            $nama_keg   = trim($row[0] ?? ''); 
            $nama_tim   = trim($row[1] ?? ''); 
            $batas_raw  = $row[2] ?? '';       
            $nama_ang   = trim($row[3] ?? ''); 
            $realisasi_input  = (int)($row[4] ?? 0); // Nilai tambahan dari Excel

            if (empty($nama_keg) && empty($nama_ang)) continue; 

            // 1. Validasi & Format Tanggal
            $batas_waktu = null;
            if (!empty($batas_raw)) {
                try {
                    $batas_waktu = is_numeric($batas_raw) 
                        ? Date::excelToDateTimeObject($batas_raw)->format('Y-m-d') 
                        : (new DateTime($batas_raw))->format('Y-m-d');
                } catch (Exception $e) {
                     $error_messages[] = "Baris $row_num: Format tanggal salah.";
                     continue;
                }
            } else {
                $error_messages[] = "Baris $row_num: Batas Waktu wajib diisi.";
                continue;
            }

            // 2. Cari ID (Kegiatan & Anggota)
            $kegiatan_id = getKegiatanInfo($koneksi, $nama_keg, $nama_tim, $batas_waktu);
            $anggota_id  = getMemberInfo($koneksi, $nama_ang);

            if (!$kegiatan_id) {
                $error_messages[] = "Baris $row_num: Kegiatan tidak ditemukan (Cek Nama/Tim/Tanggal).";
                continue;
            }
            if (!$anggota_id) {
                $error_messages[] = "Baris $row_num: Anggota '$nama_ang' tidak ditemukan.";
                continue;
            }

            // 3. Cek Apakah Anggota Memang Ditugaskan?
            $stmt_check = $koneksi->prepare("SELECT id FROM kegiatan_anggota WHERE kegiatan_id = ? AND anggota_id = ?");
            $stmt_check->bind_param("ii", $kegiatan_id, $anggota_id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows === 0) {
                $error_messages[] = "Baris $row_num: Anggota ini tidak terdaftar di tugas tersebut.";
                continue;
            }

            // 4. UPDATE REALISASI (LOGIKA AKUMULASI)
            // Menggunakan: realisasi_anggota = realisasi_anggota + ?
            // COALESCE digunakan untuk jaga-jaga jika data lama NULL, dianggap 0
            
            $sql_update = "UPDATE kegiatan_anggota 
                           SET realisasi_anggota = COALESCE(realisasi_anggota, 0) + ? 
                           WHERE kegiatan_id = ? AND anggota_id = ?";
                           
            $stmt_upd = $koneksi->prepare($sql_update);
            $stmt_upd->bind_param("iii", $realisasi_input, $kegiatan_id, $anggota_id);
            
            if ($stmt_upd->execute()) {
                $updated_count++;
                $affected_kegiatan_ids[$kegiatan_id] = true; // Tandai untuk hitung ulang total nanti
            } else {
                $error_messages[] = "Baris $row_num: Gagal update DB.";
            }
        }

        // --- FINALISASI ---
        if (count($error_messages) > 0) {
            $koneksi->rollback();
            $_SESSION['import_errors'] = $error_messages;
            $_SESSION['error'] = "Dibatalkan! Ada " . count($error_messages) . " data bermasalah.";
        } else {
            
            // Hitung Ulang Total Realisasi pada Tabel Utama (Kegiatan)
            if (!empty($affected_kegiatan_ids)) {
                $stmt_sum = $koneksi->prepare("
                    UPDATE kegiatan k 
                    SET k.realisasi = (
                        SELECT COALESCE(SUM(ka.realisasi_anggota), 0) 
                        FROM kegiatan_anggota ka 
                        WHERE ka.kegiatan_id = k.id
                    ) 
                    WHERE k.id = ?
                ");

                foreach (array_keys($affected_kegiatan_ids) as $kid) {
                    $stmt_sum->bind_param("i", $kid);
                    $stmt_sum->execute();
                }
            }

            $koneksi->commit();
            // Pesan Sukses diperjelas agar user tahu datanya DITAMBAHKAN
            $_SESSION['success'] = "Sukses! $updated_count data realisasi berhasil DITAMBAHKAN (Akumulasi).";
        }

    } catch (Exception $e) {
        $koneksi->rollback();
        $_SESSION['error'] = "System Error: " . $e->getMessage();
    }

    $_SESSION['tab_target'] = 'realisasi';
    header("Location: ../pages/import_kegiatan_lengkap.php");
    exit;
}
?>
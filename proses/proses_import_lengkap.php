<?php
session_start();
require '../vendor/autoload.php';
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// --- 1. FUNGSI HELPER PARSING TANGGAL (PENTING) ---
function parseExcelDate($raw_date) {
    if (empty($raw_date)) return date('Y-m-d'); // Default hari ini

    // Cek jika format Excel Serial Number (Angka, misal 45321)
    if (is_numeric($raw_date)) {
        return Date::excelToDateTimeObject($raw_date)->format('Y-m-d');
    }

    // Cek format text umum
    try {
        // Coba format Y-m-d
        $d = DateTime::createFromFormat('Y-m-d', $raw_date);
        if ($d && $d->format('Y-m-d') === $raw_date) return $raw_date;

        // Coba format d/m/Y (Indonesia)
        $d = DateTime::createFromFormat('d/m/Y', $raw_date);
        if ($d) return $d->format('Y-m-d');

        // Coba format m/d/Y (US Excel)
        $d = DateTime::createFromFormat('n/j/Y', $raw_date);
        if ($d) return $d->format('Y-m-d');
        
        // Fallback: strtotime
        $ts = strtotime($raw_date);
        if ($ts) return date('Y-m-d', $ts);

    } catch (Exception $e) {
        return date('Y-m-d');
    }
    return date('Y-m-d');
}

// --- 2. FUNGSI HELPER DATABASE ---

function getTimInfo($koneksi, $nama_tim) {
    $nama_tim = trim($nama_tim);
    $stmt = $koneksi->prepare("SELECT id, is_active FROM tim WHERE nama_tim LIKE ? LIMIT 1");
    $param = "%" . $nama_tim . "%";
    $stmt->bind_param("s", $param);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) return $row; 
    return null;
}

function getMemberInfo($koneksi, $nama_anggota) {
    $nama_anggota = trim($nama_anggota);
    if (empty($nama_anggota)) return null;

    $stmt = $koneksi->prepare("SELECT id FROM pegawai WHERE nama LIKE ? LIMIT 1");
    $stmt->bind_param("s", $nama_anggota);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) return ['id' => $row['id'], 'type' => 'pegawai'];

    $stmt2 = $koneksi->prepare("SELECT id FROM mitra WHERE nama_lengkap LIKE ? LIMIT 1");
    $stmt2->bind_param("s", $nama_anggota);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    if ($row2 = $res2->fetch_assoc()) return ['id' => $row2['id'], 'type' => 'mitra'];

    return null; 
}

function checkMembership($koneksi, $tim_id, $member_id, $member_type) {
    $stmt = $koneksi->prepare("SELECT id FROM anggota_tim WHERE tim_id = ? AND member_id = ? AND member_type = ? LIMIT 1");
    $stmt->bind_param("iis", $tim_id, $member_id, $member_type);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->num_rows > 0;
}

// Cek apakah kegiatan sudah ada di DB (Nama + Tim + Tanggal)
function checkExistingKegiatan($koneksi, $nama_kegiatan, $tim_id, $batas_waktu) {
    $sql = "SELECT id FROM kegiatan WHERE nama_kegiatan = ? AND tim_id = ? AND batas_waktu = ? LIMIT 1";
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("sis", $nama_kegiatan, $tim_id, $batas_waktu);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) return $row['id'];
    return null;
}

// --- 3. MULAI PROSES ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_excel'])) {
    
    $file = $_FILES['file_excel']['tmp_name'];
    $error_messages = []; 
    $koneksi->begin_transaction();

    try {
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        array_shift($rows); // Hapus Header

        $current_kegiatan_id = 0;
        $current_tim_id = 0;
        $current_tim_name = "";
        $sukses_kegiatan = 0;
        $sukses_anggota = 0;
        
        // Cache ID kegiatan yang sedang diproses di sesi ini
        $processed_activities = []; 

        foreach ($rows as $i => $row) {
            $row_excel_num = $i + 2;

            // Mapping Kolom
            $nama_keg   = trim($row[0] ?? ''); 
            $nama_tim   = trim($row[1] ?? ''); 
            $satuan     = trim($row[2] ?? ''); 
            $batas_raw  = $row[3] ?? '';       
            $deskripsi  = trim($row[4] ?? ''); 
            $nama_ang   = trim($row[5] ?? ''); 
            $target_ang = (int)($row[6] ?? 0); 

            // ==========================================
            // LOGIKA 1: PROSES KEGIATAN
            // ==========================================
            if (!empty($nama_keg)) {
                
                // [LANGKAH KRUSIAL 1] PARSING TANGGAL DULUAN
                // Kita butuh tanggal yang valid untuk membuat Unique Key
                $batas_waktu = parseExcelDate($batas_raw);

                // [LANGKAH KRUSIAL 2] BUAT UNIQUE KEY YANG BENAR
                // Kunci = Nama + Tim + TANGGAL.
                // Ini yang membedakan kegiatan Januari dan Februari.
                $unique_key = md5(strtolower($nama_keg . $nama_tim . $batas_waktu));

                // Cek Cache (Apakah baris ini kelanjutan dari baris sebelumnya?)
                if (isset($processed_activities[$unique_key])) {
                    // YA: Gunakan ID yang sudah ada
                    $current_kegiatan_id = $processed_activities[$unique_key];
                    
                    // Ambil info tim lagi untuk validasi anggota
                    $tim_info = getTimInfo($koneksi, $nama_tim);
                    if ($tim_info) {
                        $current_tim_id = $tim_info['id'];
                        $current_tim_name = $nama_tim;
                    }

                } else {
                    // TIDAK: Ini Kegiatan Baru (atau Bulan Baru)
                    
                    // --- VALIDASI TIM ---
                    if (empty($nama_tim)) {
                        $error_messages[] = "Baris $row_excel_num: Nama Tim kosong.";
                        $current_kegiatan_id = 0; continue; 
                    }

                    $tim_info = getTimInfo($koneksi, $nama_tim);
                    if (!$tim_info) {
                        $error_messages[] = "Baris $row_excel_num: Tim '$nama_tim' tidak ditemukan.";
                        $current_kegiatan_id = 0; continue;
                    }

                    if ($tim_info['is_active'] == 0) {
                        $error_messages[] = "Baris $row_excel_num: Tim '$nama_tim' NON-AKTIF.";
                        $current_kegiatan_id = 0; continue;
                    }

                    $current_tim_id = $tim_info['id'];
                    $current_tim_name = $nama_tim;

                    // Cek DB: Apakah kegiatan ini sudah ada di database (dari upload sebelumnya)?
                    $existing_id = checkExistingKegiatan($koneksi, $nama_keg, $current_tim_id, $batas_waktu);

                    if ($existing_id) {
                        // SUDAH ADA DI DB: Pakai ID lama (Mode Append)
                        $current_kegiatan_id = $existing_id;
                    } else {
                        // BELUM ADA: Insert Baru
                        $stmt_keg = $koneksi->prepare("INSERT INTO kegiatan (nama_kegiatan, tim_id, satuan, target, realisasi, batas_waktu, keterangan, created_at) VALUES (?, ?, ?, 0, 0, ?, ?, NOW())");
                        $stmt_keg->bind_param("sisss", $nama_keg, $current_tim_id, $satuan, $batas_waktu, $deskripsi);
                        
                        if (!$stmt_keg->execute()) {
                            throw new Exception("Database Error Baris $row_excel_num: " . $stmt_keg->error);
                        }
                        
                        $current_kegiatan_id = $koneksi->insert_id;
                        $sukses_kegiatan++;
                    }

                    // Simpan ke Cache agar baris selanjutnya (temannya) tahu ID ini
                    $processed_activities[$unique_key] = $current_kegiatan_id;
                }
            }

            // ==========================================
            // LOGIKA 2: PROSES ANGGOTA
            // ==========================================
            if (!empty($nama_ang)) {

                if ($current_kegiatan_id == 0) continue;

                $member_data = getMemberInfo($koneksi, $nama_ang);
                
                if (!$member_data) {
                    $error_messages[] = "Baris $row_excel_num: Anggota '$nama_ang' tidak ditemukan.";
                    continue;
                }

                $is_member = checkMembership($koneksi, $current_tim_id, $member_data['id'], $member_data['type']);
                
                if (!$is_member) {
                    $error_messages[] = "Baris $row_excel_num: '$nama_ang' bukan anggota tim '$current_tim_name'.";
                    continue;
                }

                if (count($error_messages) == 0) {
                    $member_id = $member_data['id'];
                    
                    // Cek Duplikasi: Apakah anggota ini SUDAH ada di kegiatan ID ini?
                    $stmt_cek = $koneksi->prepare("SELECT id FROM kegiatan_anggota WHERE kegiatan_id = ? AND anggota_id = ? LIMIT 1");
                    $stmt_cek->bind_param("ii", $current_kegiatan_id, $member_id);
                    $stmt_cek->execute();
                    
                    if ($stmt_cek->get_result()->num_rows == 0) {
                        // Belum ada -> Insert
                        $stmt_ang = $koneksi->prepare("INSERT INTO kegiatan_anggota (kegiatan_id, anggota_id, target_anggota, realisasi_anggota) VALUES (?, ?, ?, 0)");
                        $stmt_ang->bind_param("iii", $current_kegiatan_id, $member_id, $target_ang);
                        
                        if ($stmt_ang->execute()) {
                            $sukses_anggota++;
                            
                            // Auto Update Total Target
                            if ($target_ang > 0) {
                                $stmt_upd = $koneksi->prepare("UPDATE kegiatan SET target = target + ? WHERE id = ?");
                                $stmt_upd->bind_param("ii", $target_ang, $current_kegiatan_id);
                                $stmt_upd->execute();
                            }
                        } else {
                            throw new Exception("Gagal insert anggota: " . $stmt_ang->error);
                        }
                    }
                }
            }
        }

        if (count($error_messages) > 0) {
            $koneksi->rollback();
            $_SESSION['import_errors'] = $error_messages;
            $_SESSION['error'] = "Import Dibatalkan! Ada kesalahan data.";
        } else {
            $koneksi->commit();
            $_SESSION['success'] = "Sukses! $sukses_kegiatan Kegiatan dan $sukses_anggota Anggota berhasil diimport.";
        }

    } catch (Exception $e) {
        $koneksi->rollback();
        $_SESSION['error'] = "System Error: " . $e->getMessage();
    }

    $_SESSION['tab_target'] = 'kegiatan';
    header("Location: ../pages/import_kegiatan_lengkap.php");
    exit;
}
?>
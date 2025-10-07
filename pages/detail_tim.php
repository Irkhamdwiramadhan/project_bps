<?php
// detail_tim.php

session_start();
include '../includes/koneksi.php';

// ===================================================================
// LOGIKA PHP DIJALANKAN SEBELUM HTML
// ===================================================================

// Pastikan pengguna sudah login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Ambil ID tim dari URL dan validasi
$tim_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($tim_id === 0) {
    $_SESSION['error_message'] = "ID Tim tidak valid.";
    header('Location: halaman_tim.php');
    exit;
}

// Ambil data utama tim beserta nama ketua (tanpa foto)
$stmt_tim = $koneksi->prepare(
    "SELECT t.nama_tim, p.nama AS nama_ketua, p.jabatan AS jabatan_ketua
     FROM tim t
     LEFT JOIN pegawai p ON t.ketua_tim_id = p.id
     WHERE t.id = ?"
);
$stmt_tim->bind_param("i", $tim_id);
$stmt_tim->execute();
$result_tim = $stmt_tim->get_result();

if ($result_tim->num_rows === 0) {
    $_SESSION['error_message'] = "Data tim tidak ditemukan.";
    header('Location: halaman_tim.php');
    exit;
}
$tim = $result_tim->fetch_assoc();
$stmt_tim->close();

// Ambil daftar anggota tim
$anggota_list = [];
$sql_anggota = "SELECT member_id, member_type FROM anggota_tim WHERE tim_id = ?";
$stmt_anggota = $koneksi->prepare($sql_anggota);
$stmt_anggota->bind_param("i", $tim_id);
$stmt_anggota->execute();
$result_anggota = $stmt_anggota->get_result();

if ($result_anggota->num_rows > 0) {
    while($row = $result_anggota->fetch_assoc()) {
        $detail = null;
        if ($row['member_type'] === 'pegawai') {
            // Ambil detail dari tabel pegawai (tanpa foto)
            $stmt_detail = $koneksi->prepare("SELECT nama, nip_bps, jabatan FROM pegawai WHERE id = ?");
            $stmt_detail->bind_param("i", $row['member_id']);
            $stmt_detail->execute();
            $detail = $stmt_detail->get_result()->fetch_assoc();
            $stmt_detail->close();
            if ($detail) {
                $detail['status'] = 'Pegawai';
            }
        } elseif ($row['member_type'] === 'mitra') {
            // Ambil detail dari tabel mitra (menyesuaikan nama kolom)
            $stmt_detail = $koneksi->prepare("SELECT nama_lengkap as nama, nik, alamat_detail as alamat FROM mitra WHERE id = ?");
            $stmt_detail->bind_param("i", $row['member_id']);
            $stmt_detail->execute();
            $detail = $stmt_detail->get_result()->fetch_assoc();
            $stmt_detail->close();
            if ($detail) {
                $detail['status'] = 'Mitra';
            }
        }

        if ($detail) {
            $anggota_list[] = $detail;
        }
    }
}
$stmt_anggota->close();

// ===================================================================
// TAMPILKAN KONTEN HTML
// ===================================================================

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
    /* Tata Letak Utama */
    .main-content {
        background-color: #f8f9fa;
        padding-bottom: 20px;
    }
    /* Header Halaman */
    .header-content {
        background-color: #ffffff;
        border-bottom: 1px solid #dee2e6;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    /* Kustomisasi Kartu (Card) */
    .card {
        border: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        border-radius: 0.5rem;
        transition: transform 0.2s ease-in-out;
    }
    .card:hover {
        transform: translateY(-3px);
    }
    .card-header {
        background-color: #f1f5f9;
        font-weight: 600;
        color: #334155;
        border-bottom: 1px solid #e2e8f0;
    }
    /* Informasi Ketua Tim */
    .ketua-info strong {
        display: inline-block;
        width: 80px;
        color: #64748b;
    }
    /* Kustomisasi Tabel Anggota */
    .table thead th {
        background-color: #e2e8f0;
        color: #475569;
        font-weight: 600;
    }
    .table tbody td {
        vertical-align: middle;
    }
    .table-hover tbody tr:hover {
        background-color: #f1f5f9;
    }
</style>

<main class="main-content">
    <div class="header-content" style="display: flex; align-items: center; gap: 10px; padding: 15px 20px;">
        <a href="halaman_tim.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
        <h2>Detail Tim</h2>
    </div>

    <div class="p-3">
        <div class="card mb-4">
            <div class="card-header">
                <h4><?= htmlspecialchars($tim['nama_tim']) ?></h4>
            </div>
            <div class="card-body ketua-info">
                <h5>Ketua Tim</h5>
                <p class="mb-0">
                    <strong>Nama</strong>: <?= htmlspecialchars($tim['nama_ketua'] ?? 'Belum Ditentukan') ?><br>
                    <strong>Jabatan</strong>: <?= htmlspecialchars($tim['jabatan_ketua'] ?? '-') ?>
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5>Daftar Anggota (<?= count($anggota_list) ?> Orang)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($anggota_list)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Nama</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($anggota_list as $index => $anggota): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($anggota['nama']) ?></td>
                                        <td>
                                            <span class="badge <?= $anggota['status'] == 'Pegawai' ? 'bg-primary' : 'bg-success' ?>">
                                                <?= htmlspecialchars($anggota['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                                if ($anggota['status'] == 'Pegawai') {
                                                    echo "Jabatan: " . htmlspecialchars($anggota['jabatan']);
                                                } else {
                                                    echo "NIK: " . htmlspecialchars($anggota['nik']);
                                                }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted">Tim ini belum memiliki anggota.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
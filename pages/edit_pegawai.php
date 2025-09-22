<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Pastikan ada ID pegawai yang dikirim melalui URL
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Gunakan Prepared Statement untuk keamanan
    $sql = "SELECT * FROM pegawai WHERE id = ?";
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("i", $id); // "i" menandakan tipe data integer
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $pegawai = $result->fetch_assoc();
    } else {
        echo "<main class='main-content'><div class='card'>ID pegawai tidak ditemukan.</div></main>";
        include '../includes/footer.php';
        exit;
    }
    $stmt->close();
} else {
    echo "<main class='main-content'><div class='card'>ID pegawai tidak ditemukan.</div></main>";
    include '../includes/footer.php';
    exit;
}
?>

<style>
    /* Mengatur tata letak halaman utama */
    .main-content {
        padding: 20px;
        background-color: #f4f7f9;
        min-height: calc(100vh - 80px); /* Menyesuaikan tinggi dengan header dan footer */
    }
    
    /* Mengatur header konten dengan tombol Kembali */
    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 15px 20px 0;
    }

    /* Mengatur judul halaman */
    .header-content h2 {
        font-size: 1.8rem;
        color: #333;
        margin: 0;
        font-weight: 600;
        border-bottom: 3px solid #007bff;
        padding-bottom: 5px;
    }

    /* Mengatur tombol Kembali */
    .btn-secondary {
        background-color: #f8f9fa;
        color: #6c757d;
        border: 1px solid #ced4da;
        padding: 8px 16px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s ease-in-out;
    }

    .btn-secondary:hover {
        background-color: #e2e6ea;
        color: #5a6268;
    }

    /* Mengatur card utama */
    .card {
        background-color: #ffffff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    }
    
    /* Mengatur tata letak grid untuk form */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
        align-items: flex-start; /* Menjaga keselarasan atas elemen form */
    }

    /* Mengatur grup form */
    .form-group {
        display: flex;
        flex-direction: column;
    }

    /* Mengatur label */
    label {
        font-weight: 500;
        color: #555;
        margin-bottom: 8px;
    }
    
    /* Mengatur input dan select */
    input[type="text"],
    input[type="date"],
    input[type="number"],
    input[type="email"],
    input[type="file"],
    select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        box-sizing: border-box;
        font-size: 1rem;
        transition: all 0.3s ease-in-out;
    }

    input[type="text"]:focus,
    input[type="date"]:focus,
    input[type="number"]:focus,
    input[type="email"]:focus,
    select:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }

    /* Mengatur grup tombol aksi */
    .btn-action-group {
        margin-top: 30px;
    }
    
    /* Mengatur tombol utama */
    .btn-primary {
        background-color: #007bff;
        color: #fff;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        transition: background-color 0.3s ease;
    }

    .btn-primary:hover {
        background-color: #0056b3;
    }

    /* Media query untuk tampilan mobile */
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr; /* Mengubah menjadi satu kolom di layar kecil */
        }
    }
</style>

<main class="main-content">
    <div class="header-content">
        <h2>Edit Data Pegawai</h2>
        <a href="pegawai.php" class="btn btn-secondary">Kembali</a>
    </div>

    <div class="card">
        <form action="../proses/proses_edit.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $pegawai['id']; ?>">
            <input type="hidden" name="foto_lama" value="<?php echo $pegawai['foto']; ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label for="nama">Nama Lengkap:</label>
                    <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($pegawai['nama']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="ttl">Tanggal Lahir:</label>
                    <input type="date" id="ttl" name="ttl" value="<?php echo htmlspecialchars($pegawai['ttl']); ?>">
                </div>

                <div class="form-group">
                    <label for="kecamatan">Kecamatan:</label>
                    <input type="text" id="kecamatan" name="kecamatan" value="<?php echo htmlspecialchars($pegawai['kecamatan']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="inisial">Inisial:</label>
                    <input type="text" id="inisial" name="inisial" value="<?php echo htmlspecialchars($pegawai['inisial']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="no_urut">No Urut:</label>
                    <input type="number" id="no_urut" name="no_urut" value="<?php echo htmlspecialchars($pegawai['no_urut']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="nip">NIP:</label>
                    <input type="text" id="nip" name="nip" value="<?php echo htmlspecialchars($pegawai['nip']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="nip_bps">NIP BPS:</label>
                    <input type="text" id="nip_bps" name="nip_bps" value="<?php echo htmlspecialchars($pegawai['nip_bps']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="gol_akhir">Golongan Akhir:</label>
                    <input type="text" id="gol_akhir" name="gol_akhir" value="<?php echo htmlspecialchars($pegawai['gol_akhir']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="status">Status:</label>
                    <input type="text" id="status" name="status" value="<?php echo htmlspecialchars($pegawai['status']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="tmt_cpns">TMT CPNS:</label>
                    <input type="date" id="tmt_cpns" name="tmt_cpns" value="<?php echo htmlspecialchars($pegawai['tmt_cpns']); ?>">
                </div>
                <div class="form-group">
                    <label for="pendidikan">Pendidikan Terakhir:</label>
                    <input type="text" id="pendidikan" name="pendidikan" value="<?php echo htmlspecialchars($pegawai['pendidikan']); ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($pegawai['email']); ?>">
                </div>

                <div class="form-group">
                    <label for="gender">Jenis Kelamin:</label>
                    <select id="gender" name="gender">
                        <option value="Laki-laki" <?php if ($pegawai['gender'] == 'Laki-laki') echo 'selected'; ?>>Laki-laki</option>
                        <option value="Perempuan" <?php if ($pegawai['gender'] == 'Perempuan') echo 'selected'; ?>>Perempuan</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="jabatan">Jabatan:</label>
                    <input type="text" id="jabatan" name="jabatan" value="<?php echo htmlspecialchars($pegawai['jabatan']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="sebagai">Sebagai:</label>
                    <select id="sebagai" name="sebagai">
                        <option value="Anggota" <?php if ($pegawai['sebagai'] == 'Anggota') echo 'selected'; ?>>Anggota</option>
                        <option value="Pembina" <?php if ($pegawai['sebagai'] == 'Pembina') echo 'selected'; ?>>Pembina</option>
                        <option value="Staff" <?php if ($pegawai['sebagai'] == 'Staff') echo 'selected'; ?>>Staff</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="seksi">Seksi:</label>
                    <input type="text" id="seksi" name="seksi" value="<?php echo htmlspecialchars($pegawai['seksi']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="alamat">Tempat Lahir:</label>
                    <input type="text" id="alamat" name="alamat" value="<?php echo htmlspecialchars($pegawai['alamat']); ?>">
                </div>

                <div class="form-group">
                    <label for="foto">Foto Pegawai (kosongkan jika tidak diubah):</label>
                    <input type="file" id="foto" name="foto">
                </div>
            </div>

            <div class="btn-action-group">
                <button type="submit" class="btn btn-primary">Update Data</button>
            </div>
        </form>
    </div>
</main>

<?php include '../includes/footer.php'; ?>

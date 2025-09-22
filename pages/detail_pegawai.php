<?php
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Pastikan ada ID pegawai yang dikirim melalui URL
if (isset($_GET['id'])) {
    // Ambil ID dari URL
    $id = $_GET['id'];

    // Gunakan Prepared Statement untuk keamanan
    $sql = "SELECT * FROM pegawai WHERE id = ?";
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("i", $id); // "i" menandakan tipe data integer
    $stmt->execute();
    $result = $stmt->get_result();

    // Cek apakah data ditemukan
    if ($result->num_rows > 0) {
        $pegawai = $result->fetch_assoc();
    } else {
        // Jika data tidak ditemukan, tampilkan pesan error
        echo "<main class='main-content'><div class='card'>Data pegawai tidak ditemukan.</div></main>";
        include '../includes/footer.php';
        exit;
    }
    $stmt->close();
} else {
    // Jika ID tidak ada di URL, tampilkan pesan error
    echo "<main class='main-content'><div class='card'>ID pegawai tidak ditemukan.</div></main>";
    include '../includes/footer.php';
    exit;
}

// Menambahkan CSS khusus untuk tampilan detail
?>
<style>
    .detail-card {
        padding: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-radius: 12px;
    }
    .profile-header {
        text-align: center;
        margin-bottom: 20px;
    }
    .profile-img-lg {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #f1f1f1;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 10px;
    }
    .profile-header h3 {
        margin: 10px 0 5px;
        font-size: 1.5rem;
        color: #333;
    }
    .profile-header p {
        margin: 0;
        color: #777;
    }
    .profile-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 15px 40px; /* Jarak antar baris dan kolom */
        padding-top: 15px;
    }
    .profile-info p {
        margin: 0;
        padding: 10px 0;
        border-bottom: 1px solid #eee; /* Garis pemisah antar info */
    }
    .profile-info p:last-child {
        border-bottom: none; /* Hapus garis di item terakhir */
    }
    .profile-info p strong {
        display: block;
        font-weight: 600;
        color: #555;
        margin-bottom: 3px;
    }
    hr {
        border: 0;
        height: 1px;
        background: #e0e0e0;
        margin: 20px 0;
    }
    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        margin-bottom: 20px;
    }
</style>

<main class="main-content">
    <div class="header-content">
        <h2>Detail Pegawai</h2>
        <a href="pegawai.php" class="btn btn-primary">Kembali ke Daftar</a>
    </div>

    <div class="card detail-card">
        <div class="profile-header">
            <!-- Menambahkan fallback image jika foto tidak ada -->
            <?php
            $foto = !empty($pegawai['foto']) ? "../assets/img/pegawai/" . $pegawai['foto'] : "../assets/img/pegawai/default.png";
            ?>
            <img src="<?php echo htmlspecialchars($foto); ?>" alt="Foto <?php echo htmlspecialchars($pegawai['nama']); ?>" class="profile-img-lg">
            <h3><?php echo htmlspecialchars($pegawai['nama']); ?></h3>
            <p><?php echo htmlspecialchars($pegawai['jabatan']); ?></p>
        </div>
        <hr>
        <div class="profile-info">
            <p><strong>NIP:</strong> <?php echo htmlspecialchars($pegawai['nip']); ?></p>
            <p><strong>NIP BPS:</strong> <?php echo htmlspecialchars($pegawai['nip_bps']); ?></p>
            <p><strong>Sebagai:</strong> <?php echo htmlspecialchars($pegawai['sebagai']); ?></p>
            <p><strong>Tanggal Lahir:</strong> <?php echo htmlspecialchars($pegawai['ttl']); ?></p>
            <p><strong>TMT CPNS:</strong> <?php echo htmlspecialchars($pegawai['tmt_cpns']); ?></p>
            <p><strong>Jabatan:</strong> <?php echo htmlspecialchars($pegawai['jabatan']); ?></p>
            <p><strong>Golongan Akhir:</strong> <?php echo htmlspecialchars($pegawai['gol_akhir']); ?></p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($pegawai['status']); ?></p>
            <p><strong>Pendidikan:</strong> <?php echo htmlspecialchars($pegawai['pendidikan']); ?></p>
            <p><strong>Jenis Kelamin:</strong> <?php echo htmlspecialchars($pegawai['gender']); ?></p>
            <p><strong>Inisial:</strong> <?php echo htmlspecialchars($pegawai['inisial']); ?></p>
            <p><strong>No. Urut:</strong> <?php echo htmlspecialchars($pegawai['no_urut']); ?></p>
            <p><strong>Seksi:</strong> <?php echo htmlspecialchars($pegawai['seksi']); ?></p>
            <p><strong>Kecamatan:</strong> <?php echo htmlspecialchars($pegawai['kecamatan']); ?></p>
            <p><strong>Alamat:</strong> <?php echo htmlspecialchars($pegawai['alamat']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($pegawai['email']); ?></p>
        </div>
    </div>
</main>

<?php
include '../includes/footer.php';
?>

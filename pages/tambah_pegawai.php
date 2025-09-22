<?php
// PHP includes are left here as placeholders.
// include '../includes/koneksi.php';
// include '../includes/header.php';
// include '../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pegawai Baru</title>
    <!-- Impor font modern dari Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4A90E2;
            --secondary-color: #50E3C2;
            --background-color: #f4f7f9;
            --card-background: #ffffff;
            --text-color: #333333;
            --light-text-color: #777777;
            --border-color: #e0e6ed;
            --shadow-light: 0 4px 6px rgba(0, 0, 0, 0.05);
            --shadow-strong: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }

        .main-content {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            min-height: 100vh;
        }

        .header-content {
            margin-bottom: 2rem;
            text-align: center;
        }

        .header-content h2 {
            font-size: 2.5rem;
            font-weight: 600;
            color: var(--primary-color);
            position: relative;
            padding-bottom: 0.5rem;
            display: inline-block;
        }

        .header-content h2::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background-color: var(--secondary-color);
            border-radius: 2px;
        }

        .card {
            background-color: var(--card-background);
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            padding: 2.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-strong);
        }

        .tambah-pegawai-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 30px;
        }

        @media (max-width: 768px) {
            .tambah-pegawai-form {
                grid-template-columns: 1fr; /* Form menjadi 1 kolom di layar kecil */
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--light-text-color);
            transition: color 0.3s ease;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            color: var(--text-color);
            background-color: #fcfdff;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.2);
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .btn-submit {
            grid-column: 1 / -1;
            justify-self: center;
            width: 100%;
            max-width: 300px;
            padding: 15px 20px;
            font-size: 1rem;
            font-weight: 600;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-top: 1rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <main class="main-content">
        <div class="header-content">
            <h2>Tambah Pegawai Baru</h2>
        </div>
        <div class="card">
            <form action="../proses/proses_tambah.php" method="POST" enctype="multipart/form-data" class="tambah-pegawai-form">
                <!-- FORM INPUTS -->
                <div class="form-group">
                    <label for="nama">Nama Lengkap:</label>
                    <input type="text" id="nama" name="nama" required>
                </div>
                <div class="form-group">
                    <label for="ttl">Tanggal Lahir:</label>
                    <input type="date" id="ttl" name="ttl">
                </div>
                <div class="form-group">
                    <label for="kecamatan">Kecamatan:</label>
                    <input type="text" id="kecamatan" name="kecamatan">
                </div>
                <div class="form-group">
                    <label for="inisial">Inisial:</label>
                    <input type="text" id="inisial" name="inisial">
                </div>
                <div class="form-group">
                    <label for="no_urut">No Urut:</label>
                    <input type="number" id="no_urut" name="no_urut">
                </div>
                <div class="form-group">
                    <label for="nip">NIP:</label>
                    <input type="text" id="nip" name="nip">
                </div>
                <div class="form-group">
                    <label for="nip_bps">NIP BPS:</label>
                    <input type="text" id="nip_bps" name="nip_bps">
                </div>
                <div class="form-group">
                    <label for="gol_akhir">Golongan Akhir:</label>
                    <input type="text" id="gol_akhir" name="gol_akhir">
                </div>
                <div class="form-group">
                    <label for="tmt_gol_akhir">TMT Golongan Akhir:</label>
                    <input type="date" id="tmt_gol_akhir" name="tmt_gol_akhir">
                </div>
                <div class="form-group">
                    <label for="tmt_cpns">TMT CPNS:</label>
                    <input type="date" id="tmt_cpns" name="tmt_cpns">
                </div>
                <div class="form-group">
                    <label for="pendidikan">Pendidikan Terakhir:</label>
                    <input type="text" id="pendidikan" name="pendidikan">
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email">
                </div>
                <div class="form-group">
                    <label for="gender">Jenis Kelamin:</label>
                    <select id="gender" name="gender">
                        <option value="Laki-laki">Laki-laki</option>
                        <option value="Perempuan">Perempuan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="jabatan">Jabatan:</label>
                    <input type="text" id="jabatan" name="jabatan">
                </div>
                <div class="form-group">
                    <label for="alamat">Tempat Lahir:</label>
                    <input type="text" id="alamat" name="alamat">
                </div>
                <div class="form-group">
                    <label for="sebagai">Sebagai:</label>
                    <select id="sebagai" name="sebagai">
                        <option value="Anggota">Anggota</option>
                        <option value="Pembina">Pembina</option>
                        <option value="Staff">Staff</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="seksi">Seksi:</label>
                    <input type="text" id="seksi" name="seksi">
                </div>
                <div class="form-group full-width">
                    <label for="foto">Foto Pegawai:</label>
                    <input type="file" id="foto" name="foto" accept="image/*">
                </div>
                <div class="form-group full-width">
                    <button type="submit" class="btn-submit">Simpan</button>
                </div>
            </form>
        </div>
    </main>

    <?php 
    // include '../includes/footer.php'; 
    ?>
</body>
</html>

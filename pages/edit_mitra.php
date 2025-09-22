<?php
session_start();
include '../includes/koneksi.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: mitra.php?status=error&message=ID_mitra_tidak_ditemukan');
    exit;
}

$id_url = $_GET['id'];

$sql = "SELECT * FROM mitra WHERE id = ?";
$stmt = $koneksi->prepare($sql);

if ($stmt === false) {
    header('Location: mitra.php?status=error&message=Kesalahan_menyiapkan_query');
    exit;
}

$stmt->bind_param("s", $id_url);
$stmt->execute();
$result = $stmt->get_result();
$mitra = $result->fetch_assoc();

if (!$mitra) {
    header('Location: mitra.php?status=error&message=Data_mitra_tidak_ditemukan');
    exit;
}

$stmt->close();
$koneksi->close();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
    body {
        font-family: 'Poppins', sans-serif;
        background: #eef2f5;
    }

    .content-wrapper {
        padding: 1rem;
        transition: margin-left 0.3s ease;
    }

    @media (min-width: 640px) {
        .content-wrapper {
            margin-left: 16rem;
            padding-top: 2rem;
        }
    }

    .form-container {
        max-width: 900px;
        margin: auto;
        background-color: #ffffff;
        border-radius: 1.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        padding: 2.5rem;
        margin-top: 2rem;
        margin-bottom: 2rem;
    }

    .form-section {
        background-color: #f9fafb;
        padding: 1.5rem;
        border-radius: 0.75rem;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06);
    }

    .form-title {
        font-size: 2.25rem;
        font-weight: 700;
        text-align: center;
        color: #1f2937;
        margin-bottom: 2.5rem;
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .input-group {
        display: flex;
        flex-direction: column;
    }

    .input-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: #374151;
        margin-bottom: 0.25rem;
    }

    .input-field, .input-select {
        display: block;
        width: 100%;
        padding: 0.75rem;
        border-radius: 0.5rem;
        border: 1px solid #d1d5db;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        transition: all 0.2s ease-in-out;
    }

    .input-field:focus, .input-select:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
        outline: none;
    }

    .btn-gradient {
        padding: 0.75rem 1.5rem;
        border-radius: 9999px;
        font-weight: 600;
        color: #ffffff;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .btn-primary {
        background: linear-gradient(to right, #3b82f6, #2563eb);
    }

    .btn-primary:hover {
        background: linear-gradient(to right, #2563eb, #1d4ed8);
        transform: translateY(-2px);
    }

    .btn-secondary {
        background: linear-gradient(to right, #6b7280, #4b5563);
    }

    .btn-secondary:hover {
        background: linear-gradient(to right, #4b5563, #374151);
        transform: translateY(-2px);
    }

    .photo-preview {
        width: 150px;
        height: 150px;
        object-fit: cover;
        border-radius: 0.75rem;
        border: 2px solid #d1d5db;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
</style>

<div class="content-wrapper">
    <div class="form-container">
        <h1 class="form-title">Edit Data Mitra</h1>

        <?php
        if (isset($_GET['status']) && isset($_GET['message'])) {
            $status = $_GET['status'];
            $message = str_replace('_', ' ', $_GET['message']);
            if ($status === 'success') {
                echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert"><p class="font-bold">Sukses!</p><p>' . htmlspecialchars($message) . '</p></div>';
            } else if ($status === 'error') {
                echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert"><p class="font-bold">Error!</p><p>' . htmlspecialchars($message) . '</p></div>';
            }
        }
        ?>

        <form action="../proses/proses_edit_mitra.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($mitra['id']); ?>">

            <div class="form-section mb-8">
                <h2 class="section-title">Data Pribadi</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="input-group">
                        <label for="id_mitra" class="input-label">ID Mitra</label>
                        <input type="text" id="id_mitra" name="id_mitra" value="<?php echo htmlspecialchars($mitra['id_mitra']); ?>" class="input-field">
                    </div>

                    <div class="input-group flex flex-col items-center gap-4">
                        <div class="flex-shrink-0">
                            <img id="photo-preview-image" src="<?php echo !empty($mitra['foto']) ? '../uploads/' . htmlspecialchars($mitra['foto']) : 'https://placehold.co/150x150/e5e7eb/6b7280?text=No+Photo'; ?>" alt="Foto Mitra" class="photo-preview">
                        </div>
                        <div class="flex-grow w-full">
                            <label for="foto" class="input-label">Ganti Foto</label>
                            <input type="file" id="foto" name="foto" accept="image/*" class="input-field">
                        </div>
                    </div>

                    <div class="input-group lg:col-span-2">
                        <label for="nama_lengkap" class="input-label">Nama Lengkap</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($mitra['nama_lengkap']); ?>" class="input-field">
                    </div>

                    <div class="input-group">
                        <label for="nik" class="input-label">NIK</label>
                        <input type="text" id="nik" name="nik" value="<?php echo htmlspecialchars($mitra['nik']); ?>" class="input-field">
                    </div>

                    <div class="input-group">
                        <label for="tanggal_lahir" class="input-label">Tanggal Lahir</label>
                        <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?php echo htmlspecialchars($mitra['tanggal_lahir']); ?>" class="input-field">
                    </div>

                    <div class="input-group">
                        <label for="jenis_kelamin" class="input-label">Jenis Kelamin</label>
                        <select id="jenis_kelamin" name="jenis_kelamin" class="input-field">
                            <option value="">Pilih</option>
                            <option value="Laki-laki" <?php echo ($mitra['jenis_kelamin'] == 'Laki-laki') ? 'selected' : ''; ?>>Laki-laki</option>
                            <option value="Perempuan" <?php echo ($mitra['jenis_kelamin'] == 'Perempuan') ? 'selected' : ''; ?>>Perempuan</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label for="agama" class="input-label">Agama</label>
                        <select id="agama" name="agama" class="input-field">
                            <option value="">Pilih</option>
                            <option value="Islam" <?php echo ($mitra['agama'] == 'Islam') ? 'selected' : ''; ?>>Islam</option>
                            <option value="Kristen" <?php echo ($mitra['agama'] == 'Kristen') ? 'selected' : ''; ?>>Kristen</option>
                            <option value="Katolik" <?php echo ($mitra['agama'] == 'Katolik') ? 'selected' : ''; ?>>Katolik</option>
                            <option value="Hindu" <?php echo ($mitra['agama'] == 'Hindu') ? 'selected' : ''; ?>>Hindu</option>
                            <option value="Buddha" <?php echo ($mitra['agama'] == 'Buddha') ? 'selected' : ''; ?>>Buddha</option>
                            <option value="Konghucu" <?php echo ($mitra['agama'] == 'Konghucu') ? 'selected' : ''; ?>>Konghucu</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label for="status_perkawinan" class="input-label">Status Perkawinan</label>
                        <select id="status_perkawinan" name="status_perkawinan" class="input-field">
                            <option value="">Pilih</option>
                            <option value="Belum Menikah" <?php echo ($mitra['status_perkawinan'] == 'Belum Menikah') ? 'selected' : ''; ?>>Belum Menikah</option>
                            <option value="Menikah" <?php echo ($mitra['status_perkawinan'] == 'Menikah') ? 'selected' : ''; ?>>Menikah</option>
                            <option value="Cerai Hidup" <?php echo ($mitra['status_perkawinan'] == 'Cerai Hidup') ? 'selected' : ''; ?>>Cerai Hidup</option>
                            <option value="Cerai Mati" <?php echo ($mitra['status_perkawinan'] == 'Cerai Mati') ? 'selected' : ''; ?>>Cerai Mati</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label for="pendidikan" class="input-label">Pendidikan Terakhir</label>
                        <input type="text" id="pendidikan" name="pendidikan" value="<?php echo htmlspecialchars($mitra['pendidikan']); ?>" class="input-field">
                    </div>

                    <div class="input-group">
                        <label for="pekerjaan" class="input-label">Pekerjaan Utama</label>
                        <input type="text" id="pekerjaan" name="pekerjaan" value="<?php echo htmlspecialchars($mitra['pekerjaan']); ?>" class="input-field">
                    </div>

                    <div class="input-group">
                        <label for="deskripsi_pekerjaan_lain" class="input-label">Deskripsi Pekerjaan Lain</label>
                        <input type="text" id="deskripsi_pekerjaan_lain" name="deskripsi_pekerjaan_lain" value="<?php echo htmlspecialchars($mitra['deskripsi_pekerjaan_lain']); ?>" class="input-field">
                    </div>

                    <div class="input-group">
                        <label for="npwp" class="input-label">NPWP</label>
                        <input type="text" id="npwp" name="npwp" value="<?php echo htmlspecialchars($mitra['npwp']); ?>" class="input-field">
                    </div>

                    <div class="input-group">
                        <label for="no_telp" class="input-label">Nomor Telepon</label>
                        <input type="text" id="no_telp" name="no_telp" value="<?php echo htmlspecialchars($mitra['no_telp']); ?>" class="input-field">
                    </div>

                    <div class="input-group lg:col-span-2">
                        <label for="email" class="input-label">Alamat Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($mitra['email']); ?>" class="input-field">
                    </div>
                </div>
            </div>

            <div class="form-section mb-8">
                <h2 class="section-title">Data Alamat</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="input-group">
                        <label for="alamat_provinsi" class="input-label">Provinsi</label>
                        <input type="text" id="alamat_provinsi" name="alamat_provinsi" value="<?php echo htmlspecialchars($mitra['alamat_provinsi']); ?>" class="input-field">
                    </div>
                    <div class="input-group">
                        <label for="alamat_kabupaten" class="input-label">Kabupaten</label>
                        <input type="text" id="alamat_kabupaten" name="alamat_kabupaten" value="<?php echo htmlspecialchars($mitra['alamat_kabupaten']); ?>" class="input-field">
                    </div>
                    <div class="input-group">
                        <label for="nama_kecamatan" class="input-label">Kecamatan</label>
                        <input type="text" id="nama_kecamatan" name="nama_kecamatan" value="<?php echo htmlspecialchars($mitra['nama_kecamatan']); ?>" class="input-field">
                    </div>
                    <div class="input-group">
                        <label for="alamat_desa" class="input-label">Kode/Nama Desa</label>
                        <input type="text" id="alamat_desa" name="alamat_desa" value="<?php echo htmlspecialchars($mitra['alamat_desa']); ?>" class="input-field">
                    </div>
                    <div class="input-group md:col-span-2">
                        <label for="nama_desa" class="input-label">Nama Desa</label>
                        <input type="text" id="nama_desa" name="nama_desa" value="<?php echo htmlspecialchars($mitra['nama_desa']); ?>" class="input-field">
                    </div>
                    <div class="input-group md:col-span-2">
                        <label for="alamat_detail" class="input-label">Detail Alamat</label>
                        <textarea id="alamat_detail" name="alamat_detail" rows="3" class="input-field"><?php echo htmlspecialchars($mitra['alamat_detail']); ?></textarea>
                    </div>
                    <div class="input-group md:col-span-2 flex items-center">
                        <input type="checkbox" id="domisili_sama" name="domisili_sama" **value="1"** <?php echo ($mitra['domisili_sama'] == 1) ? 'checked' : ''; ?> class="form-checkbox">
                        <label for="domisili_sama" class="ml-2 input-label !text-base">Alamat domisili sama dengan KTP</label>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2 class="section-title">Pengalaman Survei</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="input-group">
                        <label for="mengikuti_pendataan_bps" class="input-label">Mengikuti Pendataan BPS</label>
                        <select id="mengikuti_pendataan_bps" name="mengikuti_pendataan_bps" class="input-select">
                            <option value="">Pilih</option>
                            <option value="Ya" <?php echo ($mitra['mengikuti_pendataan_bps'] == 'Ya') ? 'selected' : ''; ?>>Ya</option>
                            <option value="Tidak" <?php echo ($mitra['mengikuti_pendataan_bps'] == 'Tidak') ? 'selected' : ''; ?>>Tidak</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="posisi" class="input-label">Posisi</label>
                        <select id="posisi" name="posisi" class="input-select">
                            <option value="">Pilih</option>
                            <option value="mitra_pendataan" <?php echo ($mitra['posisi'] == 'mitra_pendataan') ? 'selected' : ''; ?>>Mitra Pendataan</option>
                            <option value="mitra_pengolahan" <?php echo ($mitra['posisi'] == 'mitra_pengolahan') ? 'selected' : ''; ?>>Mitra Pengolahan</option>
                            <option value="mitra_pendataan_dan_pengolahan" <?php echo ($mitra['posisi'] == 'mitra_pendataan_dan_pengolahan') ? 'selected' : ''; ?>>Mitra Pendataan Dan Pengolahan</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label class="input-label">Pengalaman Survei Lainnya (Jika ada, beri tanda centang)</label>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="flex items-center">
                                <input type="checkbox" id="sp" name="sp" **value="1"** <?php echo ($mitra['sp'] == 1) ? 'checked' : ''; ?> class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <label for="sp" class="ml-2 input-label !text-base !mb-0">SP</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="st" name="st" **value="1"** <?php echo ($mitra['st'] == 1) ? 'checked' : ''; ?> class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <label for="st" class="ml-2 input-label !text-base !mb-0">ST</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="se" name="se" **value="1"** <?php echo ($mitra['se'] == 1) ? 'checked' : ''; ?> class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <label for="se" class="ml-2 input-label !text-base !mb-0">SE</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="susenas" name="susenas" **value="1"** <?php echo ($mitra['susenas'] == 1) ? 'checked' : ''; ?> class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <label for="susenas" class="ml-2 input-label !text-base !mb-0">Susenas</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="sakernas" name="sakernas" **value="1"** <?php echo ($mitra['sakernas'] == 1) ? 'checked' : ''; ?> class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <label for="sakernas" class="ml-2 input-label !text-base !mb-0">Sakernas</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="sbh" name="sbh" **value="1"** <?php echo ($mitra['sbh'] == 1) ? 'checked' : ''; ?> class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <label for="sbh" class="ml-2 input-label !text-base !mb-0">SBH</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-10 flex justify-end space-x-6">
                <button type="button" onclick="window.location.href='mitra.php'" class="btn-gradient btn-secondary">Batal</button>
                <button type="submit" class="btn-gradient btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    const fotoInput = document.getElementById('foto');
    const photoPreviewImage = document.getElementById('photo-preview-image');

    fotoInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                photoPreviewImage.src = e.target.result;
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
</script>
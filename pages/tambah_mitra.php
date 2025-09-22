<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
    
    body {
        font-family: 'Poppins', sans-serif;
        background: #f0f4f8; /* A very light blue-gray background */
    }

    .content-wrapper {
        padding: 1rem;
    }
    
    @media (min-width: 640px) {
        .content-wrapper {
            margin-left: 16rem;
            padding-top: 2rem;
        }
    }

    /* Main form container with elevated design */
    .form-container {
        background-color: #ffffff;
        border-radius: 1rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        padding: 2.5rem;
        margin-top: 2rem;
        margin-bottom: 2rem;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
    }

    /* Form section styling with a light background and a subtle inner shadow */
    .form-section {
        background-color: #f9fbfd;
        border-radius: 0.75rem;
        padding: 2rem;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    
    /* Input and select styling */
    .form-input {
        @apply mt-1 block w-full rounded-lg border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-3 transition-colors duration-200;
    }
    .form-input:focus {
        border-color: #4c84ff;
        box-shadow: 0 0 0 3px rgba(76, 132, 255, 0.25);
    }

    .form-label {
        @apply block text-sm font-medium text-gray-700;
        margin-bottom: 0.5rem;
    }
    .form-checkbox {
        @apply h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded;
    }

    .form-input.error {
        @apply border-red-500;
    }
    .error-message {
        @apply text-red-500 text-sm mt-1;
    }

    /* Message box styling */
    .message-box {
        @apply p-4 mb-4 text-sm rounded-lg;
    }
    .message-box.error {
        @apply bg-red-100 text-red-700;
    }
    .message-box.success {
        @apply bg-green-100 text-green-700;
    }
    
    /* Tombol Aksi - Desain Modern */
    .btn-custom {
        padding: 0.8rem 2rem;
        border: none;
        border-radius: 9999px; /* Pill shape */
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        color: white;
        cursor: pointer;
        transition: all 0.4s ease;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    /* Tombol Utama (Simpan) */
    .btn-primary {
        background-image: linear-gradient(135deg, #3f566bff);
        color: white;
    }

    .btn-primary:hover {
        background-image: linear-gradient(135deg, #3222c5ff 0%, #0b1785ff 100%);
        box-shadow: 0 8px 25px rgba(34, 197, 94, 0.4);
        transform: translateY(-2px);
    }
    
    /* Tombol Sekunder (Kembali) */
    .btn-secondary {
        background: #3f566bff;
        color: #ffffffff;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        text-transform: capitalize;
    }

    .btn-secondary:hover {
        background: #3b6091ff;
        box-shadow: 0 8px 25px rgba(75, 85, 99, 0.2);
        transform: translateY(-2px);
    }
    
</style>

    <div class="content-wrapper">
        <div class="mt-10 flex justify-end gap-4">
            <a href="mitra.php" class="btn-custom btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i> Kembali
            </a>
                
    </div>
    <div class="form-container">
        <h1 class="text-3xl font-bold text-gray-800 mb-6 text-center">Formulir Tambah Mitra</h1>
        
        <div id="status-message">
            <?php if (isset($_GET['status']) && isset($_GET['message'])): ?>
                <?php
                    $status_class = ($_GET['status'] == 'success') ? 'success' : 'error';
                    $message_text = htmlspecialchars(str_replace('_', ' ', $_GET['message']));
                ?>
                <div class="message-box <?php echo $status_class; ?>">
                    <p><?php echo $message_text; ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-section mb-8">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6 border-b pb-2">Tambah Mitra Massal via Excel</h2>
            
            <div class="flex flex-col items-center">
                <p class="text-gray-600 mb-4 text-center">Gunakan fitur ini untuk menambahkan banyak mitra sekaligus. Unduh template di bawah ini untuk memastikan format data yang benar.</p>
                <a href="../assets/template_unggah_mitra.xlsx" download class="btn-custom btn-primary mb-6">Unduh Template Excel</a>
            </div>
            
            <form action="../proses/proses_unggah_excel_mitra.php" method="POST" enctype="multipart/form-data" class="w-full max-w-lg mx-auto p-6 bg-gray-50 rounded-lg shadow">
                <div class="form-group mb-4">
                    <label for="excel_file" class="form-label text-center block">Pilih File Excel (.xlsx)</label>
                    <input type="file" id="excel_file" name="excel_file" accept=".xlsx, .xls" class="form-input" required>
                </div>
                <div class="flex justify-center">
                    <button type="submit" class="btn-custom btn-primary">Unggah dan Simpan</button>
                </div>
            </form>
        </div>
        
        <hr class="my-10 border-gray-300">
        <form action="../proses/proses_tambah_mitra.php" method="POST" enctype="multipart/form-data" id="formTambahMitra" class="space-y-8">
            <div class="form-section">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6 border-b pb-2">Data Pribadi</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="form-group">
                        <label for="id_mitra" class="form-label">ID Mitra<span class="text-red-500">*</span></label>
                        <input type="text" id="id_mitra" name="id_mitra" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="nama_lengkap" class="form-label">Nama Lengkap<span class="text-red-500">*</span></label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="nik" class="form-label">NIK<span class="text-red-500">*</span></label>
                        <input type="text" id="nik" name="nik" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="tanggal_lahir" class="form-label">Tanggal Lahir</label>
                        <input type="date" id="tanggal_lahir" name="tanggal_lahir" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="jenis_kelamin" class="form-label">Jenis Kelamin</label>
                        <select id="jenis_kelamin" name="jenis_kelamin" class="form-input">
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="agama" class="form-label">Agama</label>
                        <input type="text" id="agama" name="agama" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="status_perkawinan" class="form-label">Status Perkawinan</label>
                        <input type="text" id="status_perkawinan" name="status_perkawinan" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="pendidikan" class="form-label">Pendidikan Terakhir</label>
                        <select id="pendidikan" name="pendidikan" class="form-input">
                            <option value="SD">SD/Sederajat</option>
                            <option value="SMP">SMP/Sederajat</option>
                            <option value="SMA">SMA/Sederajat</option>
                            <option value="D1">D1</option>
                            <option value="D2">D2</option>
                            <option value="D3">D3</option>
                            <option value="D4">D4</option>
                            <option value="S1">S1/Sederajat</option>
                            <option value="S2">S2</option>
                            <option value="S3">S3</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="pekerjaan" class="form-label">Pekerjaan</label>
                        <input type="text" id="pekerjaan" name="pekerjaan" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="deskripsi_pekerjaan_lain" class="form-label">Deskripsi Pekerjaan Lain</label>
                        <textarea id="deskripsi_pekerjaan_lain" name="deskripsi_pekerjaan_lain" class="form-input" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="npwp" class="form-label">NPWP</label>
                        <input type="text" id="npwp" name="npwp" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="no_telp" class="form-label">No. Telepon</label>
                        <input type="text" id="no_telp" name="no_telp" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="foto" class="form-label">Unggah Foto Profil</label>
                        <input type="file" id="foto" name="foto" accept="image/*" class="form-input text-gray-900 bg-white cursor-pointer focus:outline-none">
                    </div>
                    <div class="form-group">
                        <label for="tahun" class="form-label">Tahun Registrasi</label>
                        <input type="number" id="tahun" name="tahun" class="form-input" value="<?= date('Y') ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6 border-b pb-2">Data Alamat</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label for="alamat_provinsi" class="form-label">Provinsi</label>
                        <input type="text" id="alamat_provinsi" name="alamat_provinsi" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="alamat_kabupaten" class="form-label">Kabupaten</label>
                        <input type="text" id="alamat_kabupaten" name="alamat_kabupaten" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="nama_kecamatan" class="form-label">Kecamatan</label>
                        <input type="text" id="nama_kecamatan" name="nama_kecamatan" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="alamat_desa" class="form-label">Desa (Kode)</label>
                        <input type="text" id="alamat_desa" name="alamat_desa" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="nama_desa" class="form-label">Desa (Nama)</label>
                        <input type="text" id="nama_desa" name="nama_desa" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="alamat_detail" class="form-label">Alamat Detail</label>
                        <textarea id="alamat_detail" name="alamat_detail" class="form-input" rows="3"></textarea>
                    </div>
                    <div class="form-group flex items-center mt-4">
                        <input type="checkbox" id="domisili_sama" name="domisili_sama" class="form-checkbox">
                        <label for="domisili_sama" class="ml-2 form-label">Domisili Sama dengan KTP</label>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6 border-b pb-2">Pengalaman Survei</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label for="mengikuti_pendataan_bps" class="form-label">Mengikuti Pendataan BPS</label>
                        <select id="mengikuti_pendataan_bps" name="mengikuti_pendataan_bps" class="form-input">
                            <option value="Ya">Ya</option>
                            <option value="Tidak">Tidak</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="posisi" class="form-label">Posisi</label>
                        <select id="posisi" name="posisi" class="form-input">
                            <option value="PCL">PCL</option>
                            <option value="PML">PML</option>
                            <option value="Pengolah">Pengolah</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="sp" class="form-label">SP</label>
                        <input type="number" id="sp" name="sp" min="0" value="0" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="st" class="form-label">ST</label>
                        <input type="number" id="st" name="st" min="0" value="0" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="se" class="form-label">SE</label>
                        <input type="number" id="se" name="se" min="0" value="0" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="susenas" class="form-label">Susenas</label>
                        <input type="number" id="susenas" name="susenas" min="0" value="0" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="sakernas" class="form-label">Sakernas</label>
                        <input type="number" id="sakernas" name="sakernas" min="0" value="0" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="sbh" class="form-label">SBH</label>
                        <input type="number" id="sbh" name="sbh" min="0" value="0" class="form-input">
                    </div>
                </div>
            </div>

            <div class="mt-10 flex justify-end gap-4">
                <button type="submit" class="btn-custom btn-primary">
                    Simpan Mitra <i class="fas fa-save ml-2"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fungsi untuk menampilkan pesan error di dalam div
        function showMessage(type, message) {
            const statusDiv = document.getElementById('status-message');
            statusDiv.innerHTML = ''; // Hapus pesan sebelumnya
            const messageBox = document.createElement('div');
            messageBox.className = `message-box ${type}`;
            messageBox.innerHTML = `<p>${message}</p>`;
            statusDiv.appendChild(messageBox);
        }

        const form = document.getElementById('formTambahMitra');
        form.addEventListener('submit', function(event) {
            let isValid = true;
            const requiredFields = ['id_mitra', 'nama_lengkap', 'nik'];

            document.querySelectorAll('.form-input').forEach(input => {
                input.classList.remove('error');
            });
            document.querySelectorAll('.error-message').forEach(msg => {
                msg.remove();
            });

            requiredFields.forEach(fieldId => {
                const input = document.getElementById(fieldId);
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('error');
                    const errorMessage = document.createElement('p');
                    errorMessage.className = 'error-message';
                    errorMessage.textContent = 'Kolom ini wajib diisi.';
                    input.parentNode.appendChild(errorMessage);
                }
            });

            if (!isValid) {
                event.preventDefault();
                showMessage('error', 'Silakan lengkapi semua kolom yang wajib diisi.');
            }
        });
    });
</script>

<?php include '../includes/footer.php'; ?>
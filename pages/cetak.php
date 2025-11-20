<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Cek hak akses
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_dipaku', 'admin_tu', 'pegawai'];
if (empty(array_intersect($user_roles, $allowed_roles))) {
    die("Akses ditolak.");
}

// Ambil daftar tahun unik dari master_program untuk konsistensi
$tahun_result = $koneksi->query("SELECT DISTINCT tahun FROM master_program ORDER BY tahun DESC");
$daftar_tahun = [];
if ($tahun_result) {
    while ($row = $tahun_result->fetch_assoc()) {
        $daftar_tahun[] = $row['tahun'];
    }
}
// Jika tidak ada data, gunakan tahun sekarang sebagai default
if (empty($daftar_tahun)) {
    $daftar_tahun[] = date('Y');
}
?>

<style>
    /* CSS Anda yang sudah ada di sini, tidak perlu diubah */
    :root {
        --primary-blue: #0A2E5D;
        --secondary-blue: #4A90E2;
        --light-bg: #F7F9FC;
        --border-color: #E0E7FF;
        --text-dark: #2c3e50;
        --text-light: #7f8c8d;
        --primary-blue-light: #e7eff8;
    }

    .main-content {
        background-color: var(--light-bg);
    }

    .form-container-modern {
        max-width: 600px;
        margin: 40px auto;
    }

    .card-download {
        border: none;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }

    .card-header-custom {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: white;
        padding: 25px;
        text-align: center;
    }

    .card-header-custom i {
        font-size: 2.5rem;
        margin-bottom: 10px;
        opacity: 0.8;
    }

    .card-header-custom h4 {
        font-weight: 700;
        margin: 0;
    }

    .card-body-custom {
        padding: 30px 40px;
    }

    .form-step {
        position: relative;
        padding-left: 50px;
        margin-bottom: 25px;
    }

    .form-step::before {
        content: attr(data-step);
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 35px;
        height: 35px;
        background-color: var(--primary-blue-light);
        color: var(--primary-blue);
        border-radius: 50%;
        font-weight: 700;
        font-size: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .form-step label {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 8px;
        display: block;
    }

    /* Kustomisasi Dropdown */
    select.form-control {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%234A90E2' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 1rem center;
        background-size: 1em;
        padding: 0.75rem 1rem;
        border-color: var(--border-color);
        box-shadow: none;
        transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
    }

    select.form-control:focus {
        border-color: var(--secondary-blue);
        box-shadow: 0 0 0 0.2rem rgba(74, 144, 226, 0.25);
    }

    select.form-control:disabled {
        background-color: #e9ecef;
        opacity: 1;
    }

    /* Kustomisasi Tombol Download */
    .btn-download {
        background: var(--primary-blue);
        color: white;
        font-weight: 600;
        font-size: 1.1rem;
        padding: 12px;
        border-radius: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(10, 46, 93, 0.2);
    }

    .btn-download:hover {
        color: white;
        transform: translateY(-3px);
        box-shadow: 0 7px 20px rgba(10, 46, 93, 0.3);
    }

    .btn-download:disabled {
        background-color: #bdc3c7;
        box-shadow: none;
        transform: none;
        cursor: not-allowed;
    }
</style>

<main class="main-content">
    <div class="container-fluid">
        <div class="form-container-modern">
            <div class="card card-download">
                <div class="card-header-custom">
                    <i class="fas fa-cloud-download-alt"></i>
                    <h4>Menu Unduh Laporan</h4>
                </div>
                <div class="card-body card-body-custom">
                    <form id="cetakForm" action="" method="GET" target="_blank">

                        <div class="form-step" data-step="1">
                            <label for="laporan">Jenis Laporan</label>
                            <select class="form-control" id="laporan" name="laporan" required>
                                <option value="">-- Pilih Jenis Laporan --</option>
                                <option value="rpd">1. Laporan RPD</option>
                                <option value="rpd_sakti_vs_sitik">2. Laporan RPD SAKTI vs RPD SITIK</option>
                                <option value="realisasi_saja">3. Laporan Realisasi Saja</option>
                                <option value="realisasi_gabungan">4. Laporan Realisasi vs RPD</option>
                            </select>

                        </div>

                        <div class="form-step" data-step="2">
                            <label for="tahun">Tahun Anggaran</label>
                            <select class="form-control" id="tahun" name="tahun" required>
                                <?php foreach ($daftar_tahun as $thn) : ?>
                                    <option value="<?= htmlspecialchars($thn) ?>" <?= ($thn == date('Y')) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($thn) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-step" data-step="3">
                            <label>Filter Data Spesifik (Opsional)</label>

                            <div class="mb-3">
                                <select id="program_id" name="program_id" class="form-control">
                                    <option value="">-- Semua Program --</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <select id="kegiatan_id" name="kegiatan_id" class="form-control" disabled>
                                    <option value="">-- Semua Kegiatan --</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <select id="output_id" name="output_id" class="form-control" disabled>
                                    <option value="">-- Semua Output --</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <select id="sub_output_id" name="sub_output_id" class="form-control" disabled>
                                    <option value="">-- Semua Sub Output --</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <select id="komponen_id" name="komponen_id" class="form-control" disabled>
                                    <option value="">-- Semua Komponen --</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <select id="sub_komponen_id" name="sub_komponen_id" class="form-control" disabled>
                                    <option value="">-- Semua Sub Komponen --</option>
                                </select>
                            </div>
                            <div>
                                <select id="akun_id" name="akun_id" class="form-control" disabled>
                                    <option value="">-- Semua Akun --</option>
                                </select>
                            </div>

                        </div>
                        <div class="form-step" data-step="4">
                            <label for="format">Format Laporan</label>
                            <select class="form-control" id="format" name="format" required>
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel</option>
                            </select>
                        </div>

                        <div class="form-step" data-step="5">
                            <label><strong>Pilih Level Detail Laporan</strong></label>
                            <div class="form-group d-flex flex-wrap gap-3 mt-2">
                                <label class="form-check-label"><input type="checkbox" class="form-check-input" name="level_detail[]" value="program"> Program</label>
                                <label class="form-check-label"><input type="checkbox" class="form-check-input" name="level_detail[]" value="kegiatan"> Kegiatan</label>
                                <label class="form-check-label"><input type="checkbox" class="form-check-input" name="level_detail[]" value="output"> Output</label>
                                <label class="form-check-label"><input type="checkbox" class="form-check-input" name="level_detail[]" value="suboutput"> Sub Output</label>
                                <label class="form-check-label"><input type="checkbox" class="form-check-input" name="level_detail[]" value="komponen"> Komponen</label>
                                <label class="form-check-label"><input type="checkbox" class="form-check-input" name="level_detail[]" value="subkomponen"> Sub Komponen</label>
                                <label class="form-check-label"><input type="checkbox" class="form-check-input" name="level_detail[]" value="akun"> Akun</label>
                                <label class="form-check-label"><input type="checkbox" class="form-check-input" name="level_detail[]" value="item"> Item</label>
                            </div>
                        </div>

                        <div class="mt-4 pt-3">
                            <button type="submit" id="downloadBtn" class="btn btn-download btn-block">
                                <i class="fas fa-download mr-2"></i>Unduh Laporan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const cetakForm = document.getElementById('cetakForm');
        const laporanSelect = document.getElementById('laporan');
        const formatSelect = document.getElementById('format');
        const checkboxes = document.querySelectorAll('input[name="level_detail[]"]');
        const downloadBtn = document.getElementById('downloadBtn');
        const tahunSelect = document.getElementById('tahun');

        // === START: LOGIKA BARU UNTUK FILTER BERTINGKAT ===

        const dropdownChain = [{
                id: 'program_id',
                type: 'program',
                child: 'kegiatan_id'
            },
            {
                id: 'kegiatan_id',
                type: 'kegiatan',
                child: 'output_id'
            },
            {
                id: 'output_id',
                type: 'output',
                child: 'sub_output_id'
            },
            {
                id: 'sub_output_id',
                type: 'sub_output',
                child: 'komponen_id'
            },
            {
                id: 'komponen_id',
                type: 'komponen',
                child: 'sub_komponen_id'
            },
            {
                id: 'sub_komponen_id',
                type: 'sub_komponen',
                child: 'akun_id'
            },
            {
                id: 'akun_id',
                type: 'akun',
                child: null
            }
        ];

        function loadDropdownData(type, parentId, tahunValue, targetId) {
            const targetSelect = document.getElementById(targetId);
            // Pastikan URL ini benar sesuai struktur folder Anda
            let apiUrl = `get_data1.php?type=${type}&tahun=${tahunValue}`;

            if (parentId) {
                apiUrl += `&parent_id=${parentId}`;
            }

            fetch(apiUrl)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    targetSelect.innerHTML = `<option value="">-- Semua ${type.replace(/_/g, ' ')} --</option>`;

                    data.forEach(item => {
                        // PENTING: value diisi dengan 'id' untuk query selanjutnya
                        targetSelect.innerHTML += `<option value="${item.id}">${item.kode} - ${item.nama}</option>`;
                    });

                    targetSelect.disabled = false;
                })
                .catch(error => {
                    console.error(`Error fetching data for ${type}:`, error);
                    targetSelect.innerHTML = `<option value="">-- Gagal memuat data --</option>`;
                    targetSelect.disabled = false; // Aktifkan agar user tahu ada masalah
                });
        }

        function resetChildDropdowns(startIndex) {
            for (let i = startIndex; i < dropdownChain.length; i++) {
                const select = document.getElementById(dropdownChain[i].id);
                const type = dropdownChain[i].type;
                select.innerHTML = `<option value="">-- Semua ${type.replace(/_/g, ' ')} --</option>`;
                select.disabled = true;
            }
        }

        tahunSelect.addEventListener('change', () => {
            const tahun = tahunSelect.value;
            resetChildDropdowns(0);
            if (tahun) {
                loadDropdownData('program', null, tahun, 'program_id');
            }
        });

        dropdownChain.forEach((item, index) => {
            const currentSelect = document.getElementById(item.id);
            currentSelect.addEventListener('change', () => {
                const selectedValue = currentSelect.value;
                const tahun = tahunSelect.value;

                resetChildDropdowns(index + 1);

                if (item.child && selectedValue) {
                    const childType = dropdownChain[index + 1].type;
                    loadDropdownData(childType, selectedValue, tahun, item.child);
                }
            });
        });

        tahunSelect.dispatchEvent(new Event('change'));

        // === END: LOGIKA BARU ===

        const fileMap = {
            'rpd': 'cetak_rpd',
            'rpd_sakti_vs_sitik': 'cetak_rpdsakti_vs_rpdsitik', // new
            'realisasi_saja': 'cetak_hanya_realisasi',
            'realisasi_gabungan': 'cetak_realisasi'
        };

        function updateFormAction() {
            const jenisLaporan = laporanSelect.value;
            const format = formatSelect.value;
            const anyChecked = Array.from(checkboxes).some(cb => cb.checked);

            if (jenisLaporan && format && anyChecked) {
                const baseFileName = fileMap[jenisLaporan];
                const formatSuffix = (format === 'excel') ? '_excel' : '_pdf';
                cetakForm.action = `../proses/${baseFileName}${formatSuffix}.php`;
                downloadBtn.disabled = false;
            } else {
                cetakForm.action = '';
                downloadBtn.disabled = true;
            }
        }

        laporanSelect.addEventListener('change', updateFormAction);
        formatSelect.addEventListener('change', updateFormAction);
        checkboxes.forEach(cb => cb.addEventListener('change', updateFormAction));

        updateFormAction();
    });
</script>

<?php include '../includes/footer.php'; ?>
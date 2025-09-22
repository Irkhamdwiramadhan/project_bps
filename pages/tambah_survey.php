<?php
// Mulai sesi dan sertakan file koneksi database, header, dan sidebar
session_start();
include '../includes/koneksi.php';
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
    }
    @media (min-width: 640px) {
        .content-wrapper {
            margin-left: 16rem;
            padding-top: 2rem;
        }
    }
    .form-container {
        background-color: #ffffff;
        border-radius: 1rem;
        padding: 2rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    .form-label {
        font-weight: 500;
        color: #4b5563;
    }
    .form-input, .form-select {
        display: block;
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        font-size: 1rem;
        margin-top: 0.5rem;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .form-input:focus, .form-select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        outline: none;
    }
    .btn-primary {
        background-color: #2563eb;
        color: #fff;
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        font-weight: 600;
        transition: background-color 0.2s;
    }
    .btn-primary:hover {
        background-color: #1d4ed8;
    }
    .btn-secondary {
        background-color: #6b7280;
        color: #fff;
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        font-weight: 600;
        transition: background-color 0.2s;
    }
    .btn-secondary:hover {
        background-color: #4b5563;
    }
</style>

<div class="content-wrapper">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Tambah Survei Baru</h1>
        <div class="form-container">
            <form action="../proses/proses_tambah_survey.php" method="POST">
                <div class="mb-6">
                    <label for="nama_survei" class="form-label">Nama Survei</label>
                    <input type="text" id="nama_survei" name="nama_survei" class="form-input" required>
                </div>
                <div class="mb-6">
                    <label for="singkatan_survei" class="form-label">Singkatan Survei</label>
                    <input type="text" id="singkatan_survei" name="singkatan_survei" class="form-input">
                </div>
                <div class="mb-6">
                    <label for="satuan" class="form-label">Satuan</label>
                    <input type="text" id="satuan" name="satuan" class="form-input">
                </div>
                <div class="mb-6">
                    <label for="seksi_terdahulu" class="form-label">Seksi Terdahulu</label>
                    <input type="text" id="seksi_terdahulu" name="seksi_terdahulu" class="form-input">
                </div>
                <div class="mb-6">
                    <label for="nama_tim_sekarang" class="form-label">Nama Tim Sekarang</label>
                    <input type="text" id="nama_tim_sekarang" name="nama_tim_sekarang" class="form-input">
                </div>

                <div class="flex justify-end space-x-4 mt-8">
                    <a href="jenis_surveys.php" class="btn-secondary">Batal</a>
                    <button type="submit" class="btn-primary">Simpan Survei</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
include '../includes/footer.php'; 
?>
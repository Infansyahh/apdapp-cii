-- Skema database APD CII  --  versi deploy: STRUKTUR SAJA, tanpa data awal
--
-- ===== IMPORT DI HOSTING (phpMyAdmin) =====
--   1. Klik database Anda di panel kiri (mis. if0_43089536_schema)
--   2. Tab "Import" -> Choose File -> pilih file ini -> Go
--   3. CREATE DATABASE sengaja TIDAK dipakai: di hosting bersama (InfinityFree dll)
--      user tidak punya hak membuat database baru, import akan gagal #1044.
--
-- ===== IMPORT LOKAL (XAMPP) =====
--   phpMyAdmin -> New (apd_cii) -> tab Import -> pilih file ini -> Go
--   atau lewat CLI, buat database dulu lalu import:
--     cmd /c "D:\xampp\mysql\bin\mysql.exe -uroot -e \"CREATE DATABASE IF NOT EXISTS apd_cii CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci\""
--     cmd /c "D:\xampp\mysql\bin\mysql.exe -uroot apd_cii < sql\schema.sql"
--
-- CREATE DATABASE IF NOT EXISTS apd_cii CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
-- USE apd_cii;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE apd_master (
    id INT AUTO_INCREMENT PRIMARY KEY,
    -- nama TIDAK unique di level DB: cek duplikat dilakukan aplikasi (hanya utk baris aktif),
    -- sehingga nama jenis APD yang sudah dihapus (deleted_at terisi) bisa dipakai lagi.
    nama VARCHAR(100) NOT NULL,
    satuan_pack VARCHAR(30) NOT NULL DEFAULT 'Pack',
    jumlah_satuan INT NOT NULL DEFAULT 1,
    harga DECIMAL(15,2) NOT NULL DEFAULT 0,
    stok_awal INT NOT NULL DEFAULT 0,
    minimum_stok INT NOT NULL DEFAULT 0,
    deleted_at TIMESTAMP NULL DEFAULT NULL -- soft delete: riwayat transaksi tetap utuh
) ENGINE=InnoDB;

-- Opsi pilihan pada Form Pengambilan (dikelola via tab "Pengaturan Form")
-- monitoring: nilai '1'/'2' terkunci karena dipakai CASE WHEN p.monitoring = 1
CREATE TABLE IF NOT EXISTS form_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kategori VARCHAR(20) NOT NULL,  -- departemen | factory | monitoring | alasan
    nilai VARCHAR(50) NOT NULL,     -- nilai tersimpan di form (monitoring: '1'/'2')
    label VARCHAR(100) NOT NULL,    -- teks yang tampil di form
    urutan INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_kat_nilai (kategori, nilai)
) ENGINE=InnoDB;

CREATE TABLE pengambilan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tanggal DATE NOT NULL,
    nama_karyawan VARCHAR(120) NOT NULL,
    nomor_bundy VARCHAR(50) NOT NULL,
    departemen VARCHAR(60) NOT NULL,
    factory VARCHAR(50) NOT NULL,
    jenis_apd_id INT NOT NULL,
    jumlah INT NOT NULL,
    monitoring TINYINT NOT NULL DEFAULT 1, -- 1 = Pengambilan APD, 2 = Salah Ambil / Kelebihan Ambil
    alasan VARCHAR(80) NOT NULL,
    catatan VARCHAR(255) DEFAULT NULL,
    INDEX idx_created (created_at),
    FOREIGN KEY (jenis_apd_id) REFERENCES apd_master(id)
) ENGINE=InnoDB;

CREATE TABLE apd_masuk (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    email VARCHAR(120) NOT NULL,
    jenis_apd_id INT NOT NULL,
    quantity INT NOT NULL,
    INDEX idx_created (created_at),
    FOREIGN KEY (jenis_apd_id) REFERENCES apd_master(id)
) ENGINE=InnoDB;

CREATE TABLE stocktake (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bulan CHAR(7) NOT NULL, -- YYYY-MM
    jenis_apd_id INT NOT NULL,
    stok_sistem_sebelum INT NOT NULL DEFAULT 0,
    actual INT NOT NULL DEFAULT 0,
    keterangan VARCHAR(255) DEFAULT NULL,
    email VARCHAR(120) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bulan_apd (bulan, jenis_apd_id),
    FOREIGN KEY (jenis_apd_id) REFERENCES apd_master(id)
) ENGINE=InnoDB;

-- ===== LOG TRANSAKSI: TANPA VIEW =====
-- Dulu berupa VIEW v_log_transaksi. Hosting gratis (InfinityFree) MENOLAK
-- CREATE VIEW (#1142 command denied), jadi sekarang log digabungkan oleh
-- aplikasi lewat fungsi log_transaksi() di config.php (subquery biasa).
-- Tidak ada perubahan tampilan/fitur; view di bawah hanya arsip, opsional
-- dipakai lagi kalau DB Anda mendukung CREATE VIEW.
--
-- CREATE OR REPLACE VIEW v_log_transaksi AS
-- SELECT p.created_at AS waktu,
--        'Pengambilan APD' AS sumber,
--        CASE WHEN p.monitoring = 1 THEN 'Barang Keluar' ELSE 'Koreksi Salah / Lebih Ambil' END AS jenis_transaksi,
--        a.nama AS jenis_apd,
--        p.jumlah AS quantity,
--        IF(p.monitoring = 1, -1, 1) AS sign,
--        IF(p.monitoring = 1, -p.jumlah, p.jumlah) AS net_quantity,
--        CONCAT(p.alasan, CASE WHEN p.catatan IS NULL OR p.catatan = '' THEN '' ELSE CONCAT(' - ', p.catatan) END) AS catatan,
--        'OK' AS status
-- FROM pengambilan p
-- JOIN apd_master a ON a.id = p.jenis_apd_id
-- UNION ALL
-- SELECT m.created_at,
--        'Data Masuk',
--        'Barang Masuk',
--        a.nama,
--        m.quantity,
--        1,
--        m.quantity,
--        '',
--        'OK'
-- FROM apd_masuk m
-- JOIN apd_master a ON a.id = m.jenis_apd_id
-- UNION ALL
-- SELECT s.updated_at,
--        'Stocktake Bulanan',
--        'Adjustment Stocktake',
--        a.nama,
--        ABS(s.actual - s.stok_sistem_sebelum),
--        IF(s.actual - s.stok_sistem_sebelum >= 0, 1, -1),
--        s.actual - s.stok_sistem_sebelum,
--        IFNULL(s.keterangan, ''),
--        'OK'
-- FROM stocktake s
-- JOIN apd_master a ON a.id = s.jenis_apd_id;

-- ============ DATA AWAL: KOSONG TOTAL (struktur saja) ============
-- Import ini tidak menanam satu baris pun data:
--   * users        -> kosong (buat akun lewat blok INSERT di bawah)
--   * apd_master   -> kosong (daftarkan jenis APD lewat menu Stock APD)
--   * form_options -> kosong (aplikasi otomatis pakai opsi default:
--                      departemen/factory/monitoring/alasan lihat config.php)
--
-- >>> SETELAH IMPORT, BUAT AKUN ADMIN supaya bisa login <<<
-- Jalankan SQL berikut di tab "SQL" phpMyAdmin:
--
-- INSERT INTO users (email, password_hash, nama) VALUES
-- ('admin@apdcii.local', 'admin123', 'Admin');
--
-- Catatan: password disimpan sebagai teks biasa (tanpa hash),
-- sesuai pengaturan login di login.php.

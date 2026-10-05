-- Skema database APD CII
-- Import: cmd /c "D:\xampp\mysql\bin\mysql.exe -uroot < sql\schema.sql"

CREATE DATABASE IF NOT EXISTS apd_cii CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE apd_cii;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE apd_master (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL UNIQUE,
    satuan_pack VARCHAR(30) NOT NULL DEFAULT 'Pack',
    jumlah_satuan INT NOT NULL DEFAULT 1,
    harga DECIMAL(15,2) NOT NULL DEFAULT 0,
    stok_awal INT NOT NULL DEFAULT 0,
    minimum_stok INT NOT NULL DEFAULT 0
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

-- Log transaksi gabungan (pengambilan, data masuk, adjustment stocktake)
CREATE OR REPLACE VIEW v_log_transaksi AS
SELECT p.created_at AS waktu,
       'Pengambilan APD' AS sumber,
       CASE WHEN p.monitoring = 1 THEN 'Barang Keluar' ELSE 'Koreksi Salah / Lebih Ambil' END AS jenis_transaksi,
       a.nama AS jenis_apd,
       p.jumlah AS quantity,
       IF(p.monitoring = 1, -1, 1) AS sign,
       IF(p.monitoring = 1, -p.jumlah, p.jumlah) AS net_quantity,
       CONCAT(p.alasan, CASE WHEN p.catatan IS NULL OR p.catatan = '' THEN '' ELSE CONCAT(' - ', p.catatan) END) AS catatan,
       'OK' AS status
FROM pengambilan p
JOIN apd_master a ON a.id = p.jenis_apd_id
UNION ALL
SELECT m.created_at,
       'Data Masuk',
       'Barang Masuk',
       a.nama,
       m.quantity,
       1,
       m.quantity,
       '',
       'OK'
FROM apd_masuk m
JOIN apd_master a ON a.id = m.jenis_apd_id
UNION ALL
SELECT s.updated_at,
       'Stocktake Bulanan',
       'Adjustment Stocktake',
       a.nama,
       ABS(s.actual - s.stok_sistem_sebelum),
       IF(s.actual - s.stok_sistem_sebelum >= 0, 1, -1),
       s.actual - s.stok_sistem_sebelum,
       IFNULL(s.keterangan, ''),
       'OK'
FROM stocktake s
JOIN apd_master a ON a.id = s.jenis_apd_id;

-- Seed
INSERT INTO users (email, password_hash, nama) VALUES
('admin@apdcii.local', '$2y$10$mk3/ziZyuWkpBpZMNU/fdOYA5YJqRXwIo/KiAQFawFNQ20b4ZFyZu', 'Admin');

INSERT INTO apd_master (nama, satuan_pack, jumlah_satuan, harga, stok_awal, minimum_stok) VALUES
('Helm Safety',      'Pack', 1, 150000, 50, 10),
('Sepatu Safety',    'Pack', 1, 250000, 40, 8),
('Sarung Tangan',    'Pack', 12, 60000, 30, 10),
('Kacamata Safety',  'Pack', 1, 45000, 60, 15),
('Rompi Safety',     'Pack', 1, 90000, 25, 5),
('Masker Debu',      'Pack', 20, 50000, 80, 20);

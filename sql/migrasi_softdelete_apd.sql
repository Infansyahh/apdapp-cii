-- Migrasi soft delete jenis APD (jalankan sekali pada database apd_cii yang sudah jalan)
-- Import: cmd /c "D:\xampp\mysql\bin\mysql.exe -uroot apd_cii < sql\migrasi_softdelete_apd.sql"
--
-- Tujuan: jenis APD bisa dihapus meskipun sudah punya riwayat transaksi.
-- Baris apd_master TIDAK dihapus, hanya ditandai deleted_at, sehingga:
--   - FK di pengambilan / apd_masuk / stocktake tetap valid
--   - nama di riwayat (v_log_transaksi, tab Log/Monitoring/Masuk) tetap tampil

USE apd_cii;

-- 1) Tandai soft delete
ALTER TABLE apd_master ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;

-- 2) Lepas unique constraint pada nama, supaya nama terhapus bisa dipakai lagi
--    (pengecekan duplikat kini dilakukan aplikasi, hanya untuk baris aktif)
ALTER TABLE apd_master DROP INDEX nama;

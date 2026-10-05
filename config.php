<?php
session_start();

const DB_HOST = 'sql309.infinityfree.com';
const DB_NAME = 'if0_43089536_schema';
const DB_USER = 'if0_43089536';
const DB_PASS = 'ISI_PASSWORD_INFINITYFREE_ANDA';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );  
    }
    return $pdo;
}

function e($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Div #flash -> dibaca toast.iife.js (vue3-toastify) saat load
function flash_div(string $pesan, string $warna = 'success'): string {
    $tipe = match ($warna) {
        'danger' => 'error',
        'warning' => 'warning',
        'info' => 'info',
        default => 'success',
    };
    return '<div id="flash" hidden data-type="' . $tipe . '" data-msg="' . e($pesan) . '"></div>';
}

function require_login(): void {
    if (empty($_SESSION['email'])) {
        header('Location: login.php');
        exit;
    }
}

// Opsi Form Pengambilan (disimpan di tabel form_options).
// Kalau tabel belum ada / grup kosong, fungsi opsi_* tetap pakai default di bawah.
const FORM_OPSI_KATEGORI = ['departemen', 'factory', 'monitoring', 'alasan'];

const FORM_OPSI_DEFAULT = [
    'departemen' => ['Produksi', 'Gudang', 'Maintenance', 'Quality', 'HRD', 'Engineering'],
    'factory'    => ['CII Bogor', 'CII 2'],
    'monitoring' => ['1' => '1 Pengambilan APD', '2' => '2 Salah Ambil / Kelebihan Ambil'],
    'alasan'     => ['Pengambilan Baru', 'Pengganti Rusak', 'Pengganti Hilang', 'Stocktake Koreksi', 'Lainnya'],
];

// Bikin tabel + seed sekali per request (gagal pun aman: fallback ke default)
function form_opsi_siap(): bool {
    static $siap = null;
    if ($siap !== null) return $siap;
    $siap = false;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS form_options (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kategori VARCHAR(20) NOT NULL,
            nilai VARCHAR(50) NOT NULL,
            label VARCHAR(100) NOT NULL,
            urutan INT NOT NULL DEFAULT 0,
            UNIQUE KEY uq_kat_nilai (kategori, nilai)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        if ((int) db()->query('SELECT COUNT(*) FROM form_options')->fetchColumn() === 0) {
            $ins = db()->prepare('INSERT INTO form_options (kategori, nilai, label, urutan) VALUES (?,?,?,?)');
            foreach (FORM_OPSI_DEFAULT as $kat => $opsi) {
                $urut = 0;
                foreach ($opsi as $k => $label) {
                    $nilai = ($kat === 'monitoring') ? (string) $k : $label;
                    $ins->execute([$kat, $nilai, $label, $urut++]);
                }
            }
        }
        $siap = true;
    } catch (PDOException $ex) {
        $siap = false;
    }
    return $siap;
}

// Semua opsi per kategori => [nilai => label]. Cache per request.
function form_opsi_semua(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $cache = FORM_OPSI_DEFAULT;
    if (form_opsi_siap()) {
        foreach (FORM_OPSI_KATEGORI as $kat) $cache[$kat] = [];
        $sql = 'SELECT kategori, nilai, label FROM form_options ORDER BY kategori, urutan, id';
        foreach (db()->query($sql) as $r) {
            if (in_array($r['kategori'], FORM_OPSI_KATEGORI, true)) {
                $cache[$r['kategori']][(string) $r['nilai']] = $r['label'];
            }
        }
        foreach ($cache as $kat => $opsi) { // grup kosong -> kembali ke default
            if ($opsi === []) $cache[$kat] = FORM_OPSI_DEFAULT[$kat];
        }
    }
    return $cache;
}

function opsi_departemen(): array {
    return array_values(form_opsi_semua()['departemen']);
}

function opsi_factory(): array {
    return array_values(form_opsi_semua()['factory']);
}

function opsi_monitoring(): array {
    return form_opsi_semua()['monitoring'];
}

function opsi_alasan(): array {
    return array_values(form_opsi_semua()['alasan']);
}

function daftar_apd(): array {
    return db()->query('SELECT id, nama FROM apd_master WHERE deleted_at IS NULL ORDER BY nama')->fetchAll();
}

// Log transaksi gabungan: pengambilan + data masuk + adjustment stocktake.
// Dipakai sebagai SUBQUERY biasa, bukan VIEW — hosting gratis (InfinityFree)
// menolak CREATE VIEW (#1142 command denied), jadi view tidak bisa dibuat di server.
// Struktur kolom sama persis dengan v_log_transaksi lama (waktu, sumber,
// jenis_transaksi, jenis_apd, quantity, sign, net_quantity, catatan, status).
function log_transaksi(int $limit = 300): array {
    $limit = max(1, $limit);
    $sql = "SELECT * FROM (
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
        SELECT m.created_at, 'Data Masuk', 'Barang Masuk', a.nama, m.quantity, 1, m.quantity, '', 'OK'
        FROM apd_masuk m
        JOIN apd_master a ON a.id = m.jenis_apd_id
        UNION ALL
        SELECT s.updated_at, 'Stocktake Bulanan', 'Adjustment Stocktake', a.nama,
               ABS(s.actual - s.stok_sistem_sebelum),
               IF(s.actual - s.stok_sistem_sebelum >= 0, 1, -1),
               s.actual - s.stok_sistem_sebelum,
               IFNULL(s.keterangan, ''),
               'OK'
        FROM stocktake s
        JOIN apd_master a ON a.id = s.jenis_apd_id
    ) AS gabungan
    ORDER BY waktu DESC
    LIMIT $limit";
    return db()->query($sql)->fetchAll();
}

function bulan_ini(): string {
    return date('Y-m');
}

<?php
// Koneksi + helper bersama
session_start();

const DB_HOST = '127.0.0.1';
const DB_NAME = 'apd_cii';
const DB_USER = 'root';
const DB_PASS = '';

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

// Opsi dropdown (ubah di sini saja)
function opsi_departemen(): array {
    return ['Produksi', 'Gudang', 'Maintenance', 'Quality', 'HRD', 'Engineering'];
}

function opsi_factory(): array {
    return ['CII Bogor', 'CII 2'];
}

function opsi_monitoring(): array {
    return [
        '1' => '1 Pengambilan APD',
        '2' => '2 Salah Ambil / Kelebihan Ambil',
    ];
}

function opsi_alasan(): array {
    return ['Pengambilan Baru', 'Pengganti Rusak', 'Pengganti Hilang', 'Stocktake Koreksi', 'Lainnya'];
}

function daftar_apd(): array {
    return db()->query('SELECT id, nama FROM apd_master ORDER BY nama')->fetchAll();
}

function bulan_ini(): string {
    return date('Y-m');
}

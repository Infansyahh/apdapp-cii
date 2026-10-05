<?php
require __DIR__ . '/config.php';
require_login();
define('APDCII', true); // penanda konteks untuk include pages/*.php

// ---- Hitung stok per APD (dipakai tab Stok, Stocktake, KPI) ----
function stok_rows(string $bulan): array {
    $sql = "SELECT m.id, m.nama, m.satuan_pack, m.jumlah_satuan, m.harga, m.stok_awal, m.minimum_stok,
        IFNULL((SELECT SUM(x.quantity) FROM apd_masuk x WHERE x.jenis_apd_id = m.id
                AND DATE_FORMAT(x.created_at, '%Y-%m') <= '$bulan'), 0) AS barang_masuk,
        IFNULL((SELECT SUM(CASE WHEN p.monitoring = 1 THEN p.jumlah ELSE -p.jumlah END)
                FROM pengambilan p WHERE p.jenis_apd_id = m.id
                AND DATE_FORMAT(p.tanggal, '%Y-%m') <= '$bulan'), 0) AS keluar_net,
        IFNULL((SELECT SUM(s.actual - s.stok_sistem_sebelum) FROM stocktake s
                WHERE s.jenis_apd_id = m.id AND s.bulan < '$bulan'), 0) AS adj_kumulatif,
        (SELECT s.actual FROM stocktake s WHERE s.jenis_apd_id = m.id AND s.bulan = '$bulan') AS actual_bulan,
        (SELECT s.keterangan FROM stocktake s WHERE s.jenis_apd_id = m.id AND s.bulan = '$bulan') AS keterangan_bulan
    FROM apd_master m
    ORDER BY m.nama";
    $rows = db()->query($sql)->fetchAll();
    foreach ($rows as &$r) {
        $r['stok_sistem'] = $r['stok_awal'] + $r['barang_masuk'] - $r['keluar_net'] + $r['adj_kumulatif'];
        $r['selisih'] = ($r['actual_bulan'] === null) ? null : $r['actual_bulan'] - $r['stok_sistem'];
    }
    return $rows;
}

function status_stok(int $stok, int $min): string {
    if ($stok <= 0) return 'HABIS';
    if ($stok <= $min) return 'MENIPIS';
    return 'OK';
}

function badge_status(string $s): string {
    $cls = ($s === 'OK' || $s === 'Sesuai') ? 'bg-success' : (($s === 'MENIPIS' || $s === 'Belum input' || $s === 'Lebih') ? 'bg-warning' : 'bg-danger');
    return '<span class="badge ' . $cls . '">' . e($s) . '</span>';
}

// ---- Statistik: top pengambilan (hanya monitoring=1, koreksi tidak dihitung) ----
function stat_top(string $col, string $where, int $limit = 10): array {
    $sql = "SELECT $col AS label, SUM(p.jumlah) AS total
            FROM pengambilan p
            JOIN apd_master m ON m.id = p.jenis_apd_id
            WHERE p.monitoring = 1" . ($where !== '' ? " AND ($where)" : '') . "
            GROUP BY $col
            ORDER BY total DESC" . ($limit > 0 ? " LIMIT $limit" : '');
    return db()->query($sql)->fetchAll();
}

// ---- Akurasi berbobot (formula Excel): 1 - Σ|aktual-sistem| / Σ MAX(|sistem|,|aktual|,1) ----
function akurasi_dari(array $pairs): ?float {
    $sumSel = 0.0;
    $sumMax = 0.0;
    foreach ($pairs as [$s, $a]) {
        $sumSel += abs($a - $s);
        $sumMax += max(abs($s), abs($a), 1);
    }
    return ($pairs && $sumMax > 0) ? (1 - $sumSel / $sumMax) * 100 : null;
}

// Akurasi dari baris stocktake tersimpan pada bulan $b
function akurasi_bulan_tersimpan(string $b): ?float {
    $st = db()->prepare('SELECT stok_sistem_sebelum, actual FROM stocktake WHERE bulan = ?');
    $st->execute([$b]);
    $pairs = [];
    foreach ($st->fetchAll() as $r) {
        $pairs[] = [(int)$r['stok_sistem_sebelum'], (int)$r['actual']];
    }
    return akurasi_dari($pairs);
}

// ---- Bulan aktif stocktake (bisa bulan lampau via ?bulan= / POST) ----
$bulan = $_POST['bulan'] ?? $_GET['bulan'] ?? bulan_ini();
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
    $bulan = bulan_ini();
}

// ---- Proses POST ----
$pesan = '';
$warna = 'success';
if (($_GET['login'] ?? '') === '1') {
    $pesan = 'Login berhasil. Selamat datang!';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    if ($aksi === 'masuk') {
        $apd = (int)($_POST['jenis_apd'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);
        if ($apd >= 1 && $qty >= 1) {
            db()->prepare('INSERT INTO apd_masuk (email, jenis_apd_id, quantity) VALUES (?,?,?)')
                ->execute([$_SESSION['email'], $apd, $qty]);
            $pesan = 'Data APD masuk tersimpan.';
        } else {
            $pesan = 'Pilih jenis APD dan quantity minimal 1.';
            $warna = 'danger';
        }
    } elseif ($aksi === 'stocktake') {
        $stokSistem = [];
        foreach (stok_rows($bulan) as $r) {
            $stokSistem[(int)$r['id']] = (int)$r['stok_sistem'];
        }
        $actuals = $_POST['actual'] ?? [];
        $ketList = $_POST['keterangan'] ?? [];
        $ins = db()->prepare('INSERT INTO stocktake (bulan, jenis_apd_id, stok_sistem_sebelum, actual, keterangan, email)
                              VALUES (?,?,?,?,?,?)
                              ON DUPLICATE KEY UPDATE stok_sistem_sebelum = VALUES(stok_sistem_sebelum),
                                  actual = VALUES(actual), keterangan = VALUES(keterangan), email = VALUES(email)');
        $del = db()->prepare('DELETE FROM stocktake WHERE bulan = ? AND jenis_apd_id = ?');
        $n = 0;
        foreach ($stokSistem as $id => $sistem) {
            $val = trim($actuals[$id] ?? '');
            if ($val === '') {
                $del->execute([$bulan, $id]);
                continue;
            }
            $ins->execute([$bulan, $id, $sistem, (int)$val, trim($ketList[$id] ?? '') ?: null, $_SESSION['email']]);
            $n++;
        }
        $pesan = "Stocktake bulan $bulan tersimpan ($n baris).";
    }
}

// ---- Data per tab ----
$tab = $_GET['tab'] ?? 'monitoring';
$tabs = ['monitoring', 'masuk', 'stok', 'log', 'stocktake', 'statistik'];
if (!in_array($tab, $tabs, true)) $tab = 'monitoring';

$judul = [
    'monitoring' => ['Monitoring', 'Riwayat pengambilan APD dari form'],
    'masuk'      => ['Data Masuk', 'Barang masuk dari pemasok'],
    'stok'       => ['Stock APD', 'Posisi stok sistem per jenis APD'],
    'log'        => ['Log Transaksi APD', 'Semua mutasi masuk / keluar / koreksi'],
    'stocktake'  => ['Stocktake Bulanan', 'Opname bulan ' . $bulan],
    'statistik'  => ['Statistik Pengambilan', 'Analisa pengambilan APD per periode'],
];

$navItems = [
    'monitoring' => ['Monitoring', 'activity'],
    'masuk'      => ['Data Masuk', 'download'],
    'stok'       => ['Stock APD', 'box'],
    'log'        => ['Log Transaksi', 'file-text'],
    'stocktake'  => ['Stocktake Bulanan', 'check-square'],
    'statistik'  => ['Statistik', 'bar-chart-2'],
];

$stokRows = stok_rows($bulan);

// Notifikasi: 4 transaksi terakhir
$qNotif = db()->query('SELECT * FROM v_log_transaksi ORDER BY waktu DESC LIMIT 4')->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= e($judul[$tab][0]) ?> - APD CII BOGOR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/adminkit/css/app.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/css/vue3-toastify.css">
    <script>
        (function() {
            var t = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
</head>
<body data-theme="default" data-layout="fluid" data-sidebar-position="left" data-sidebar-layout="default">
<div class="wrapper">

    <!-- Sidebar -->
    <nav id="sidebar" class="sidebar js-sidebar">
        <div class="sidebar-content js-simplebar">
            <a class="sidebar-brand" href="dashboard.php">
                <span class="sidebar-brand-text align-middle">APD CII BOGOR</span>
                <svg class="sidebar-brand-icon align-middle" width="32px" height="32px" viewBox="0 0 24 24" fill="none"
                     stroke="#FFFFFF" stroke-width="1.5" stroke-linecap="square" stroke-linejoin="miter" color="#FFFFFF"
                     style="margin-left: -3px">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
            </a>

            <ul class="sidebar-nav">
                <li class="sidebar-header">Menu Utama</li>
                <?php foreach ($navItems as $key => [$label, $icon]): ?>
                    <li class="sidebar-item <?= $tab === $key ? 'active' : '' ?>">
                        <a class="sidebar-link" href="?tab=<?= $key ?>">
                            <i class="align-middle" data-feather="<?= e($icon) ?>"></i>
                            <span class="align-middle"><?= e($label) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li class="sidebar-header">Lainnya</li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="index.php">
                        <i class="align-middle" data-feather="plus-square"></i>
                        <span class="align-middle">Form Pengambilan</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="logout.php">
                        <i class="align-middle" data-feather="log-out"></i>
                        <span class="align-middle">Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="main">
        <!-- Navbar -->
        <nav class="navbar navbar-expand navbar-light navbar-bg">
            <a class="sidebar-toggle js-sidebar-toggle">
                <i class="hamburger align-self-center"></i>
            </a>

            <div class="navbar-collapse collapse">
                <ul class="navbar-nav navbar-align ms-auto">
                    <!-- Toggle Tema Gelap -->
                    <li class="nav-item me-1">
                        <button type="button" class="nav-icon btn btn-link border-0 theme-toggle-btn" title="Beralih Tema" aria-label="Beralih Tema">
                            <i class="align-middle" data-feather="moon"></i>
                        </button>
                    </li>
                    <!-- Notifikasi -->
                    <li class="nav-item dropdown">
                        <a class="nav-icon dropdown-toggle" href="#" id="notifDropdown" data-bs-toggle="dropdown">
                            <div class="position-relative">
                                <i class="align-middle" data-feather="bell"></i>
                                <?php if ($qNotif): ?>
                                    <span class="indicator"><?= count($qNotif) ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end py-0" aria-labelledby="notifDropdown">
                            <div class="dropdown-menu-header">
                                <?= $qNotif ? count($qNotif) . ' Notifikasi Terbaru' : 'Notifikasi' ?>
                            </div>
                            <div class="list-group">
                                <?php if (!$qNotif): ?>
                                    <div class="list-group-item text-muted">Belum ada notifikasi.</div>
                                <?php endif; ?>
                                <?php foreach ($qNotif as $n): ?>
                                    <a href="?tab=log" class="list-group-item">
                                        <div class="row g-0 align-items-center">
                                            <div class="col-2">
                                                <i class="<?= $n['net_quantity'] >= 0 ? 'text-success' : 'text-danger' ?>" data-feather="<?= $n['net_quantity'] >= 0 ? 'arrow-down' : 'arrow-up' ?>"></i>
                                            </div>
                                            <div class="col-10">
                                                <div class="text-dark"><?= e($n['jenis_transaksi']) ?> - <?= e($n['jenis_apd']) ?></div>
                                                <div class="text-muted small mt-1"><?= e($n['sumber']) ?> &middot; Net <?= (int)$n['net_quantity'] > 0 ? '+' : '' ?><?= (int)$n['net_quantity'] ?></div>
                                                <div class="text-muted small mt-1"><?= e(date('d M Y H:i', strtotime($n['waktu']))) ?></div>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <div class="dropdown-menu-footer">
                                <a href="?tab=log" class="text-muted">Lihat semua transaksi</a>
                            </div>
                        </div>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle px-2" href="#" id="userDropdown" data-bs-toggle="dropdown">
                            <i class="align-middle me-1" data-feather="user"></i><?= e($_SESSION['nama']) ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <div class="dropdown-item-text small"><?= e($_SESSION['email']) ?></div>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="index.php"><i class="align-middle me-1" data-feather="plus-square"></i> Form Pengambilan</a>
                            <a class="dropdown-item" href="logout.php"><i class="align-middle me-1" data-feather="log-out"></i> Logout</a>
                        </div>
                    </li>

                    <li class="nav-item">
                        <span class="nav-link text-muted"><?= e(date('d M Y')) ?></span>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Konten -->
        <main class="content">
            <div class="container-fluid p-0">
                <h1 class="h3 mb-0"><?= e($judul[$tab][0]) ?></h1>
                <p class="text-muted mb-3"><?= e($judul[$tab][1]) ?></p>

                <?php if ($pesan) echo flash_div($pesan, $warna); ?>
                <?php include __DIR__ . "/pages/$tab.php"; ?>
            </div>
        </main>

        <footer class="footer">
            <div class="container-fluid">
                <div class="row text-muted">
                    <div class="col-6 text-start">
                        <p class="mb-0"><strong>APD CII BOGOR</strong> &copy; <?= date('Y') ?></p>
                    </div>
                    <div class="col-6 text-end">
                        <ul class="list-inline">
                            <li class="list-inline-item"><a class="text-muted" href="index.php">Form Pengambilan</a></li>
                            <li class="list-inline-item"><a class="text-muted" href="logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</div>

<script src="assets/adminkit/js/app.js"></script>
<script src="assets/js/toast.iife.js"></script>
<script src="assets/js/theme-toggle.js"></script>
</body>
</html>

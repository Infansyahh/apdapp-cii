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
    WHERE m.deleted_at IS NULL
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
    } elseif ($aksi === 'edit_apd') {
        $id         = (int)($_POST['id'] ?? 0);
        $nama       = trim($_POST['nama'] ?? '');
        $satuanPack = trim($_POST['satuan_pack'] ?? 'Pack');
        $jmlSatuan  = max(1, (int)($_POST['jumlah_satuan'] ?? 1));
        $harga      = max(0.0, (float)($_POST['harga'] ?? 0));
        $stokAwal   = max(0, (int)($_POST['stok_awal'] ?? 0));
        $minStok    = max(0, (int)($_POST['minimum_stok'] ?? 0));

        if ($id >= 1 && $nama !== '') {
            // Duplikat hanya dicek antar APD aktif (APD terhapus boleh memakai nama yang sama)
            $stDup = db()->prepare('SELECT COUNT(*) FROM apd_master WHERE LOWER(nama) = LOWER(?) AND id <> ? AND deleted_at IS NULL');
            $stDup->execute([$nama, $id]);
            if ($stDup->fetchColumn() > 0) {
                $pesan = "Gagal: Jenis APD '$nama' sudah terdaftar di sistem.";
                $warna = 'danger';
            } else {
                try {
                    $st = db()->prepare('UPDATE apd_master SET nama = ?, satuan_pack = ?, jumlah_satuan = ?, harga = ?, stok_awal = ?, minimum_stok = ? WHERE id = ?');
                    $st->execute([$nama, $satuanPack, $jmlSatuan, $harga, $stokAwal, $minStok, $id]);
                    $pesan = "Data APD '$nama' berhasil diperbarui.";
                    $warna = 'success';
                } catch (PDOException $ex) {
                    $pesan = "Gagal memperbarui APD: " . $ex->getMessage();
                    $warna = 'danger';
                }
            }
        } else {
            $pesan = 'Nama APD wajib diisi.';
            $warna = 'danger';
        }
    } elseif ($aksi === 'hapus_apd') {
        // Soft delete: baris tetap ada (riwayat transaksi & FK aman), hanya ditandai deleted_at
        $id = (int)($_POST['id'] ?? 0);
        if ($id >= 1) {
            $stName = db()->prepare('SELECT nama, deleted_at FROM apd_master WHERE id = ?');
            $stName->execute([$id]);
            $row = $stName->fetch();

            if (!$row) {
                $pesan = "Jenis APD tidak ditemukan.";
                $warna = 'danger';
            } elseif ($row['deleted_at'] !== null) {
                $pesan = "Jenis APD '" . $row['nama'] . "' sudah pernah dihapus sebelumnya.";
                $warna = 'warning';
            } else {
                try {
                    db()->prepare('UPDATE apd_master SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
                    $pesan = "Jenis APD '" . $row['nama'] . "' berhasil dihapus. Riwayat transaksi tetap tersimpan.";
                    $warna = 'success';
                } catch (PDOException $ex) {
                    $pesan = "Gagal menghapus APD: " . $ex->getMessage();
                    $warna = 'danger';
                }
            }
        }
    } elseif ($aksi === 'tambah_apd') {
        $nama       = trim($_POST['nama'] ?? '');
        $satuanPack = trim($_POST['satuan_pack'] ?? 'Pack') ?: 'Pack';
        $jmlSatuan  = max(1, (int)($_POST['jumlah_satuan'] ?? 1));
        $harga      = max(0.0, (float)($_POST['harga'] ?? 0));
        $stokAwal   = max(0, (int)($_POST['stok_awal'] ?? 0));
        $minStok    = max(0, (int)($_POST['minimum_stok'] ?? 0));

        if ($nama === '') {
            $pesan = 'Nama APD wajib diisi.';
            $warna = 'danger';
        } else {
            $stCek = db()->prepare('SELECT COUNT(*) FROM apd_master WHERE LOWER(nama) = LOWER(?) AND deleted_at IS NULL');
            $stCek->execute([$nama]);
            if ($stCek->fetchColumn() > 0) {
                $pesan = "Gagal: Jenis APD '$nama' sudah terdaftar di sistem.";
                $warna = 'danger';
            } else {
                try {
                    $st = db()->prepare('INSERT INTO apd_master (nama, satuan_pack, jumlah_satuan, harga, stok_awal, minimum_stok) VALUES (?,?,?,?,?,?)');
                    $st->execute([$nama, $satuanPack, $jmlSatuan, $harga, $stokAwal, $minStok]);
                    $pesan = "Jenis APD baru '$nama' berhasil ditambahkan.";
                    $warna = 'success';
                } catch (PDOException $ex) {
                    $pesan = "Gagal menambahkan APD: " . $ex->getMessage();
                    $warna = 'danger';
                }
            }
        }
    } elseif (in_array($aksi, ['tambah_opsi', 'edit_opsi', 'hapus_opsi'], true)) {
        // ---- Pengaturan Form: kelola opsi dropdown form pengambilan ----
        $kat     = $_POST['kategori'] ?? '';
        $id      = (int) ($_POST['id'] ?? 0);
        $label   = trim($_POST['label'] ?? '');
        $panjang = function_exists('mb_strlen') ? mb_strlen($label) : strlen($label);

        if (!in_array($kat, FORM_OPSI_KATEGORI, true)) {
            $pesan = 'Kategori opsi tidak dikenal.';
            $warna = 'danger';
        } elseif (!form_opsi_siap()) {
            $pesan = 'Tabel form_options belum siap di database.';
            $warna = 'danger';
        } elseif ($panjang > 100) {
            $pesan = 'Label opsi maksimal 100 karakter.';
            $warna = 'danger';
        } elseif ($aksi === 'tambah_opsi') {
            if ($kat === 'monitoring') {
                $pesan = 'Grup Monitoring tidak bisa ditambah: nilai 1 & 2 terkunci untuk perhitungan transaksi.';
                $warna = 'warning';
            } elseif ($label === '') {
                $pesan = 'Label opsi wajib diisi.';
                $warna = 'danger';
            } else {
                $dup = db()->prepare('SELECT COUNT(*) FROM form_options WHERE kategori = ? AND LOWER(label) = LOWER(?)');
                $dup->execute([$kat, $label]);
                if ($dup->fetchColumn() > 0) {
                    $pesan = "Opsi '$label' sudah ada di grup ini.";
                    $warna = 'danger';
                } else {
                    $mx = db()->prepare('SELECT IFNULL(MAX(urutan), 0) FROM form_options WHERE kategori = ?');
                    $mx->execute([$kat]);
                    db()->prepare('INSERT INTO form_options (kategori, nilai, label, urutan) VALUES (?,?,?,?)')
                        ->execute([$kat, $label, $label, (int) $mx->fetchColumn() + 1]);
                    $pesan = "Opsi '$label' berhasil ditambahkan.";
                }
            }
        } elseif ($aksi === 'edit_opsi') {
            $st = db()->prepare('SELECT kategori, nilai FROM form_options WHERE id = ?');
            $st->execute([$id]);
            $lama = $st->fetch();
            if (!$lama) {
                $pesan = 'Opsi tidak ditemukan.';
                $warna = 'danger';
            } elseif ($lama['kategori'] !== $kat) {
                $pesan = 'Opsi tidak cocok dengan kategori.';
                $warna = 'danger';
            } elseif ($label === '') {
                $pesan = 'Label opsi wajib diisi.';
                $warna = 'danger';
            } else {
                $cek = db()->prepare('SELECT COUNT(*) FROM form_options WHERE kategori = ? AND LOWER(label) = LOWER(?) AND id <> ?');
                $cek->execute([$kat, $label, $id]);
                if ($cek->fetchColumn() > 0) {
                    $pesan = "Opsi '$label' sudah ada di grup ini.";
                    $warna = 'danger';
                } elseif ($kat === 'monitoring') {
                    // nilai 1/2 terkunci (dipakai CASE WHEN di log_transaksi())
                    db()->prepare('UPDATE form_options SET label = ? WHERE id = ?')->execute([$label, $id]);
                    $pesan = "Label monitoring diperbarui menjadi '$label'.";
                } else {
                    db()->prepare('UPDATE form_options SET label = ?, nilai = ? WHERE id = ?')
                        ->execute([$label, $label, $id]);
                    $pesan = "Opsi '$label' berhasil diperbarui.";
                }
            }
        } elseif ($aksi === 'hapus_opsi') {
            if ($kat === 'monitoring') {
                $pesan = 'Grup Monitoring tidak bisa dihapus karena jadi dasar hitungan +/- transaksi.';
                $warna = 'warning';
            } else {
                $jml = db()->prepare('SELECT COUNT(*) FROM form_options WHERE kategori = ?');
                $jml->execute([$kat]);
                if ((int) $jml->fetchColumn() <= 1) {
                    $pesan = 'Tidak bisa menghapus opsi terakhir pada satu grup, karena form jadi kosong.';
                    $warna = 'danger';
                } else {
                    db()->prepare('DELETE FROM form_options WHERE id = ? AND kategori = ?')->execute([$id, $kat]);
                    $pesan = 'Opsi berhasil dihapus.';
                }
            }
        }
    }
}

// ---- Data per tab ----
$tab = $_POST['tab'] ?? $_GET['tab'] ?? 'monitoring';
$tabs = ['monitoring', 'masuk', 'stok', 'log', 'stocktake', 'statistik', 'pengaturan'];
if (!in_array($tab, $tabs, true)) $tab = 'monitoring';

$judul = [
    'monitoring' => ['Monitoring', 'Riwayat pengambilan APD dari form'],
    'masuk'      => ['Data Masuk', 'Barang masuk dari pemasok'],
    'stok'       => ['Stock APD', 'Posisi stok sistem per jenis APD'],
    'log'        => ['Log Transaksi APD', 'Semua mutasi masuk / keluar / koreksi'],
    'stocktake'  => ['Stocktake Bulanan', 'Opname bulan ' . $bulan],
    'statistik'  => ['Statistik Pengambilan', 'Analisa pengambilan APD per periode'],
    'pengaturan' => ['Pengaturan Form', 'Kustomisasi pilihan pada Form Pengambilan'],
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

// Notifikasi: 4 transaksi terakhir (query subquery, tanpa VIEW - lihat config.php)
$qNotif = log_transaksi(4);
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
                <img class="brand-logo" src="assets/img/logo.png" alt="AMBIL APD" width="303" height="238">
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
                <li class="sidebar-item <?= $tab === 'pengaturan' ? 'active' : '' ?>">
                    <a class="sidebar-link" href="?tab=pengaturan">
                        <i class="align-middle" data-feather="settings"></i>
                        <span class="align-middle">Pengaturan Form</span>
                    </a>
                </li>
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
                    <li class="nav-item">
                        <button type="button" class="nav-icon theme-toggle-btn" title="Beralih Tema" aria-label="Beralih Tema">
                            <i data-feather="moon"></i>
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
                            <i class="align-middle me-1" data-feather="user"></i><span class="d-none d-sm-inline"><?= e($_SESSION['nama']) ?></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <div class="dropdown-item-text small"><?= e($_SESSION['email']) ?></div>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="index.php"><i class="align-middle me-1" data-feather="plus-square"></i> Form Pengambilan</a>
                            <a class="dropdown-item" href="logout.php"><i class="align-middle me-1" data-feather="log-out"></i> Logout</a>
                        </div>
                    </li>

                    <li class="nav-item d-none d-lg-inline">
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
<script>
// Sidebar mobile: overlay + backdrop + tutup otomatis. Tabel: indikasi scroll.
(function () {
    var sidebar = document.querySelector('.js-sidebar');
    var toggle = document.querySelector('.js-sidebar-toggle');
    var mqMobile = window.matchMedia('(max-width: 991.98px)');

    if (sidebar && toggle) {
        var backdrop = document.createElement('div');
        backdrop.className = 'sidebar-backdrop';
        document.body.appendChild(backdrop);

        var sync = function () {
            var open = mqMobile.matches && sidebar.classList.contains('collapsed');
            backdrop.classList.toggle('show', open);
            document.body.classList.toggle('sidebar-open', open);
        };
        var close = function () {
            sidebar.classList.remove('collapsed');
            sync();
        };

        toggle.addEventListener('click', function () { setTimeout(sync, 0); });
        backdrop.addEventListener('click', close);
        sidebar.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () { if (mqMobile.matches) close(); });
        });
        window.addEventListener('resize', sync);
        sync();
    }

    document.querySelectorAll('.table-responsive').forEach(function (wrap) {
        var host = wrap.parentElement;
        if (!host) return;
        host.classList.add('scroll-host');
        var ind = document.createElement('div');
        ind.className = 'scroll-indicator';
        host.appendChild(ind);
        var upd = function () {
            var can = wrap.scrollWidth - wrap.clientWidth > 2;
            var end = wrap.scrollLeft + wrap.clientWidth >= wrap.scrollWidth - 2;
            ind.style.top = wrap.offsetTop + 'px';
            ind.style.height = wrap.offsetHeight + 'px';
            ind.classList.toggle('show', can && !end);
        };
        wrap.addEventListener('scroll', upd, { passive: true });
        window.addEventListener('resize', upd);
        upd();
    });
})();
</script>
</body>
</html>

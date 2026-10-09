<?php
require __DIR__ . '/config.php';

$pesan = '';
$warna = 'success';

if (($_GET['logout'] ?? '') === '1' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $pesan = 'Berhasil logout.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama       = trim($_POST['nama_karyawan'] ?? '');
    $bundy      = trim($_POST['nomor_bundy'] ?? '');
    $tanggal    = $_POST['tanggal'] ?? '';
    $departemen = $_POST['departemen'] ?? '';
    $factory    = $_POST['factory'] ?? '';
    $apd        = (int)($_POST['jenis_apd'] ?? 0);
    $jumlah     = (int)($_POST['jumlah'] ?? 0);
    $monitoring = $_POST['monitoring'] ?? '';
    $alasan     = $_POST['alasan'] ?? '';
    $catatan    = trim($_POST['catatan'] ?? '');

    if ($nama === '' || $tanggal === '' || $departemen === ''
        || $apd < 1 || $jumlah < 1
        || !isset(opsi_monitoring()[$monitoring]) || $alasan === '') {
        $pesan = 'Lengkapi semua field wajib.';
        $warna = 'danger';
    } else {
        $sql = 'INSERT INTO pengambilan
                (tanggal, nama_karyawan, nomor_bundy, departemen, factory, jenis_apd_id, jumlah, monitoring, alasan, catatan)
                VALUES (?,?,?,?,?,?,?,?,?,?)';
        db()->prepare($sql)->execute([
            $tanggal, $nama, $bundy, $departemen, $factory,
            $apd, $jumlah, $monitoring, $alasan, $catatan,
        ]);
        $pesan = 'Data pengambilan APD tersimpan.';
        $_POST = [];
    }
}

$apdList = daftar_apd();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Pengambilan APD - APD CII BOGOR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/adminkit/css/app.css">
    <link rel="stylesheet" href="assets/css/vue3-toastify.css">
    <link rel="stylesheet" href="style.css">
    <script>
        (function() {
            var t = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
</head>
<body class="page-form" data-theme="default" data-layout="fluid">
<nav class="navbar navbar-expand navbar-light bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img class="brand-logo" src="assets/img/logo-icon.png" alt="AMBIL APD" width="149" height="157">
            <span class="brand-text">Pengambilan APD</span>
        </a>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-primary theme-toggle-btn" title="Beralih Tema" aria-label="Beralih Tema">
                <i data-feather="moon"></i>
            </button>
            <a class="btn btn-outline-primary" href="dashboard.php">Dashboard</a>
        </div>
    </div>
</nav>

<main class="content">
    <div class="container">
        <div class="row g-4 justify-content-center">

            <!-- Form: di atas layar kecil, kolom kanan di layar besar -->
            <div class="col-lg-8 order-lg-2">
                <div class="card">
                    <div class="card-header pt-3">
                        <h5 class="card-title mb-0 d-flex align-items-center">
                            <i class="me-2" data-feather="clipboard"></i>Form Pengambilan APD
                        </h5>
                        <div class="text-muted small mt-1">Tanda <span class="req">*</span> wajib diisi.</div>
                    </div>
                    <div class="card-body">

                        <?php if ($pesan) echo flash_div($pesan, $warna); ?>

                        <form method="post" action="">
                            <fieldset class="form-section">
                                <legend class="form-section-title"><span class="step-num">1</span>Identitas</legend>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nama Karyawan <span class="req">*</span></label>
                                    <input type="text" name="nama_karyawan" class="form-control" required
                                           placeholder="Nama lengkap" value="<?= e($_POST['nama_karyawan'] ?? '') ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Nomor Bundy</label>
                                    <input type="text" name="nomor_bundy" class="form-control"
                                           placeholder="Nomor unik karyawan" value="<?= e($_POST['nomor_bundy'] ?? '') ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Tanggal Pengambilan <span class="req">*</span></label>
                                    <input type="date" name="tanggal" class="form-control" required
                                           value="<?= e($_POST['tanggal'] ?? date('Y-m-d')) ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Departemen <span class="req">*</span></label>
                                    <select name="departemen" class="form-select" required>
                                        <option value="">-- Pilih --</option>
                                        <?php foreach (opsi_departemen() as $d): ?>
                                            <option value="<?= e($d) ?>" <?= (($_POST['departemen'] ?? '') === $d) ? 'selected' : '' ?>><?= e($d) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            </fieldset>

                            <fieldset class="form-section">
                                <legend class="form-section-title"><span class="step-num">2</span>Detail Pengambilan</legend>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label d-block">Factory</label>
                                    <div class="seg-group">
                                        <?php foreach (opsi_factory() as $i => $f): ?>
                                            <input class="seg-input" type="radio" name="factory" value="<?= e($f) ?>" id="factory<?= (int)$i ?>"
                                                   <?= (($_POST['factory'] ?? '') === $f) ? 'checked' : '' ?>>
                                            <label class="seg-label" for="factory<?= (int)$i ?>"><?= e($f) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Jenis APD <span class="req">*</span></label>
                                    <select name="jenis_apd" class="form-select" required>
                                        <option value="">-- Pilih --</option>
                                        <?php foreach ($apdList as $a): ?>
                                            <option value="<?= (int)$a['id'] ?>" <?= ((int)($_POST['jenis_apd'] ?? 0) === (int)$a['id']) ? 'selected' : '' ?>><?= e($a['nama']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Jumlah APD <span class="req">*</span></label>
                                    <input type="number" name="jumlah" min="1" class="form-control" required
                                           placeholder="0" value="<?= e($_POST['jumlah'] ?? '') ?>">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Monitoring APD <span class="req">*</span></label>
                                    <select name="monitoring" class="form-select" required>
                                        <option value="">-- Pilih --</option>
                                        <?php foreach (opsi_monitoring() as $val => $label): ?>
                                            <option value="<?= e($val) ?>" <?= (($_POST['monitoring'] ?? '') === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                            </div>
                            </fieldset>

                            <fieldset class="form-section">
                                <legend class="form-section-title"><span class="step-num">3</span>Keterangan</legend>
                                <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Alasan Pengambilan <span class="req">*</span></label>
                                    <select name="alasan" class="form-select" required>
                                        <option value="">-- Pilih --</option>
                                        <?php foreach (opsi_alasan() as $a): ?>
                                            <option value="<?= e($a) ?>" <?= (($_POST['alasan'] ?? '') === $a) ? 'selected' : '' ?>><?= e($a) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Catatan (opsional)</label>
                                    <textarea name="catatan" rows="3" class="form-control"
                                              placeholder="Catatan tambahan..."><?= e($_POST['catatan'] ?? '') ?></textarea>
                                </div>
                            </div>

                            </fieldset>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary btn-lg btn-submit"><i data-feather="send"></i>Kirim Data</button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>

            <!-- Panduan singkat: kolom kiri di layar besar, turun ke bawah form di layar kecil -->
            <aside class="col-lg-4 order-lg-1">
                <div class="card guide-card">
                    <div class="card-body">
                        <h2 class="guide-title"><i data-feather="info"></i>Cara Pakai</h2>
                        <ol class="guide-steps">
                            <li><span class="step-num">1</span><div><strong>Isi identitas</strong><p>Nama, nomor bundy, tanggal, dan departemen.</p></div></li>
                            <li><span class="step-num">2</span><div><strong>Pilih APD</strong><p>Factory, jenis APD, jumlah, dan monitoring.</p></div></li>
                            <li><span class="step-num">3</span><div><strong>Kirim data</strong><p>Periksa kembali lalu tekan tombol Kirim Data.</p></div></li>
                        </ol>
                        <p class="guide-note"><i data-feather="alert-circle"></i><span>Data yang dikirim langsung tercatat dan dipakai untuk monitoring stok.</span></p>
                    </div>
                </div>
            </aside>

        </div>
    </div>
</main>

<script src="assets/adminkit/js/app.js"></script>
<script src="assets/js/toast.iife.js"></script>
<script src="assets/js/theme-toggle.js"></script>
<script>
    // Radio Factory: klik pilihan yang sudah terpilih untuk membatalkannya
    document.querySelectorAll('input[name="factory"]').forEach(function (r) {
        r.addEventListener('click', function () {
            if (r.dataset.prev === '1') { r.checked = false; }
            r.dataset.prev = r.checked ? '1' : '';
        });
    });
</script>
</body>
</html>

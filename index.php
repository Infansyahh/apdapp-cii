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

    if ($nama === '' || $bundy === '' || $tanggal === '' || $departemen === ''
        || $factory === '' || $apd < 1 || $jumlah < 1
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
</head>
<body class="page-form" data-theme="default" data-layout="fluid">
<nav class="navbar navbar-expand navbar-light bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="align-middle me-2" data-feather="shield"></i>APD CII BOGOR
        </a>
        <div class="d-flex">
            <a class="btn btn-outline-primary" href="dashboard.php">Dashboard</a>
        </div>
    </div>
</nav>

<main class="content">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9 col-xl-8">
                <div class="card">
                    <div class="card-header pt-3">
                        <h5 class="card-title mb-0 d-flex align-items-center">
                            <i class="me-2" data-feather="clipboard"></i>Form Pengambilan APD
                        </h5>
                        <div class="text-muted small mt-1">Isi data pengambilan APD sesuai identitas dan kebutuhan.</div>
                    </div>
                    <div class="card-body">

                        <?php if ($pesan) echo flash_div($pesan, $warna); ?>

                        <form method="post" action="">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nama Karyawan</label>
                                    <input type="text" name="nama_karyawan" class="form-control" required
                                           placeholder="Nama lengkap" value="<?= e($_POST['nama_karyawan'] ?? '') ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Nomor Bundy</label>
                                    <input type="text" name="nomor_bundy" class="form-control" required
                                           placeholder="Nomor unik karyawan" value="<?= e($_POST['nomor_bundy'] ?? '') ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Tanggal Pengambilan</label>
                                    <input type="date" name="tanggal" class="form-control" required
                                           value="<?= e($_POST['tanggal'] ?? date('Y-m-d')) ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Departemen</label>
                                    <select name="departemen" class="form-select" required>
                                        <option value="">-- Pilih --</option>
                                        <?php foreach (opsi_departemen() as $d): ?>
                                            <option value="<?= e($d) ?>" <?= (($_POST['departemen'] ?? '') === $d) ? 'selected' : '' ?>><?= e($d) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label d-block">Factory</label>
                                    <?php foreach (opsi_factory() as $f): ?>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="factory" value="<?= e($f) ?>" required
                                                   id="factory<?= mb_strtolower(preg_replace('/[^a-z0-9]/i', '', $f)) ?>"
                                                   <?= (($_POST['factory'] ?? '') === $f) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="factory<?= mb_strtolower(preg_replace('/[^a-z0-9]/i', '', $f)) ?>"><?= e($f) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Jenis APD</label>
                                    <select name="jenis_apd" class="form-select" required>
                                        <option value="">-- Pilih --</option>
                                        <?php foreach ($apdList as $a): ?>
                                            <option value="<?= (int)$a['id'] ?>" <?= ((int)($_POST['jenis_apd'] ?? 0) === (int)$a['id']) ? 'selected' : '' ?>><?= e($a['nama']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Jumlah APD</label>
                                    <input type="number" name="jumlah" min="1" class="form-control" required
                                           placeholder="0" value="<?= e($_POST['jumlah'] ?? '') ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Monitoring APD</label>
                                    <select name="monitoring" class="form-select" required>
                                        <option value="">-- Pilih --</option>
                                        <?php foreach (opsi_monitoring() as $val => $label): ?>
                                            <option value="<?= e($val) ?>" <?= (($_POST['monitoring'] ?? '') === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Alasan Pengambilan</label>
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

                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary btn-lg w-100">Kirim Data</button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="assets/adminkit/js/app.js"></script>
<script src="assets/js/toast.iife.js"></script>
</body>
</html>

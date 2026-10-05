<?php
// Hanya boleh di-include dari dashboard.php
if (!defined('APDCII')) { http_response_code(404); exit; }
/** @var array $stokRows */ // variabel dari dashboard.php
// Tab Monitoring: data + kartu KPI + tabel pengambilan
$qMon = db()->query('SELECT p.*, a.nama AS nama_apd FROM pengambilan p
                     JOIN apd_master a ON a.id = p.jenis_apd_id
                     ORDER BY p.created_at DESC LIMIT 200')->fetchAll();

$menipis = 0;
$totalNilai = 0;
foreach ($stokRows as $r) {
    if (status_stok((int)$r['stok_sistem'], (int)$r['minimum_stok']) !== 'OK') $menipis++;
    $totalNilai += $r['stok_sistem'] * (float)$r['harga'];
}
$trxHariIni = db()->query("SELECT
        (SELECT COUNT(*) FROM pengambilan WHERE tanggal = CURDATE()) +
        (SELECT COUNT(*) FROM apd_masuk WHERE DATE(created_at) = CURDATE())")->fetchColumn();
?>
<!-- KPI hanya di Monitoring -->
<div class="row mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 kpi-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="me-2">
                        <h4 class="mb-0"><?= count($stokRows) ?></h4>
                        <div class="text-muted text-small">Jenis APD Terdaftar</div>
                    </div>
                    <i class="align-middle flex-shrink-0" data-feather="box"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 kpi-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="me-2">
                        <h4 class="mb-0"><?= $menipis ?></h4>
                        <div class="text-muted text-small">Stok Habis / Menipis</div>
                    </div>
                    <i class="align-middle flex-shrink-0 <?= $menipis > 0 ? 'text-danger' : 'text-success' ?>" data-feather="alert-triangle"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 kpi-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="me-2">
                        <h4 class="mb-0"><?= (int)$trxHariIni ?></h4>
                        <div class="text-muted text-small">Transaksi Hari Ini</div>
                    </div>
                    <i class="align-middle flex-shrink-0" data-feather="trending-up"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 kpi-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="me-2">
                        <h4 class="mb-0">Rp <?= number_format($totalNilai, 0, ',', '.') ?></h4>
                        <div class="text-muted text-small">Total Nilai Stok</div>
                    </div>
                    <i class="align-middle flex-shrink-0" data-feather="dollar-sign"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Monitoring Pengambilan APD</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Timestamp</th><th>Tanggal</th><th>Nama Karyawan</th><th>Nomor Bundy</th>
                        <th>Departemen</th><th>Factory</th><th>Jenis APD</th><th>Jumlah</th>
                        <th>Monitoring</th><th>Alasan</th><th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$qMon): ?>
                    <tr><td colspan="11" class="text-center text-muted">Belum ada data.</td></tr>
                <?php endif; ?>
                <?php foreach ($qMon as $r): ?>
                    <tr>
                        <td><?= e($r['created_at']) ?></td>
                        <td><?= e($r['tanggal']) ?></td>
                        <td><?= e($r['nama_karyawan']) ?></td>
                        <td><?= e($r['nomor_bundy']) ?></td>
                        <td><?= e($r['departemen']) ?></td>
                        <td><?= e($r['factory']) ?></td>
                        <td><?= e($r['nama_apd']) ?></td>
                        <td><?= (int)$r['jumlah'] ?></td>
                        <td><?= e(opsi_monitoring()[$r['monitoring']] ?? $r['monitoring']) ?></td>
                        <td><?= e($r['alasan']) ?></td>
                        <td><?= e($r['catatan']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

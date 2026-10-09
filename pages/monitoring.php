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
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="card h-100 kpi-card">
            <div class="card-body">
                <div class="kpi-icon kpi-icon-green"><i data-feather="box"></i></div>
                <div class="kpi-num"><?= count($stokRows) ?></div>
                <div class="kpi-label">Jenis APD Terdaftar</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card h-100 kpi-card <?= $menipis > 0 ? 'kpi-card-danger' : '' ?>">
            <div class="card-body">
                <div class="kpi-icon <?= $menipis > 0 ? 'kpi-icon-red' : 'kpi-icon-green' ?>"><i data-feather="alert-triangle"></i></div>
                <div class="kpi-num"><?= $menipis ?></div>
                <div class="kpi-label">Stok Habis / Menipis</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card h-100 kpi-card">
            <div class="card-body">
                <div class="kpi-icon kpi-icon-amber"><i data-feather="trending-up"></i></div>
                <div class="kpi-num"><?= (int)$trxHariIni ?></div>
                <div class="kpi-label">Transaksi Hari Ini</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card h-100 kpi-card">
            <div class="card-body">
                <div class="kpi-icon kpi-icon-green"><i data-feather="dollar-sign"></i></div>
                <div class="kpi-num">Rp <?= number_format($totalNilai, 0, ',', '.') ?></div>
                <div class="kpi-label">Total Nilai Stok</div>
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
                        <th>Departemen</th><th>Factory</th><th>Jenis APD</th><th class="num">Jumlah</th>
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
                        <td class="num"><?= (int)$r['jumlah'] ?></td>
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

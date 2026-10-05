<?php
// Hanya boleh di-include dari dashboard.php
if (!defined('APDCII')) { http_response_code(404); exit; }
// Tab Statistik: data top + 4 panel chart
$periode = ($_GET['periode'] ?? 'bulan') === 'all' ? 'all' : 'bulan';
$whereBulan = "DATE_FORMAT(p.tanggal, '%Y-%m') = '" . bulan_ini() . "'";
$statTop = [
    'apdBulan' => stat_top('m.nama', $whereBulan),
    'apdAll'   => stat_top('m.nama', ''),
    'orang'    => stat_top('p.nama_karyawan', $periode === 'all' ? '' : $whereBulan),
    'dept'     => stat_top('p.departemen', $periode === 'all' ? '' : $whereBulan, 0),
    'periode'  => $periode,
];
$chartData = [];
foreach (['apdBulan', 'apdAll', 'orang', 'dept'] as $k) {
    $chartData[$k] = [
        'labels' => array_column($statTop[$k], 'label'),
        'totals' => array_map('intval', array_column($statTop[$k], 'total')),
    ];
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div class="text-muted small">
        Hanya "1 Pengambilan APD" (koreksi tidak dihitung) &middot;
        periode Orang &amp; Departemen:
        <b><?= $statTop['periode'] === 'all' ? 'All Time' : 'Bulan Ini (' . e(bulan_ini()) . ')' ?></b>
    </div>
    <form method="get" class="d-flex align-items-center gap-2">
        <input type="hidden" name="tab" value="statistik">
        <div class="btn-group btn-group-sm">
            <button type="submit" name="periode" value="bulan" class="btn btn-<?= $statTop['periode'] === 'bulan' ? 'primary' : 'outline-primary' ?>">Bulan Ini</button>
            <button type="submit" name="periode" value="all" class="btn btn-<?= $statTop['periode'] === 'all' ? 'primary' : 'outline-primary' ?>">All Time</button>
        </div>
    </form>
</div>
<div class="row g-3">
    <?php
    $lblPeriode = $statTop['periode'] === 'all' ? 'All Time' : 'Bulan Ini';
    $panel = [
        ['apdBulan', 'APD Paling Banyak Diambil - Bulan Ini', 'bar'],
        ['apdAll',   'APD Paling Banyak Diambil - All Time', 'bar'],
        ['orang',    'Orang Paling Banyak Ambil - ' . $lblPeriode, 'barh'],
        ['dept',     'Departemen Paling Banyak Ambil - ' . $lblPeriode, 'dough'],
    ];
    foreach ($panel as $pi => [$key, $title, $jenis]):
    ?>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?= e($title) ?></h5>
                </div>
                <div class="card-body">
                    <?php if (!$chartData[$key]['labels']): ?>
                        <p class="text-muted mb-0">Belum ada data.</p>
                    <?php else: ?>
                        <canvas id="chart-<?= $key ?>" height="170"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<script src="assets/js/chart.umd.min.js"></script>
<script>
(function () {
    const SD = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const PALETTE = ['#16a34a', '#14532d', '#86efac', '#166534', '#4ade80', '#052e16', '#bbf7d0', '#15803d'];
    if (typeof Chart === 'undefined') return;
    Chart.defaults.font.family = "Inter, sans-serif";
    Chart.defaults.color = '#000';

    function bar(key, horizontal) {
        const d = SD[key];
        if (!d || !d.labels.length) return;
        new Chart(document.getElementById('chart-' + key), {
            type: 'bar',
            data: {
                labels: d.labels,
                datasets: [{
                    label: 'Jumlah Diambil',
                    data: d.totals,
                    backgroundColor: 'rgba(22, 163, 74, .8)',
                    borderColor: '#16a34a',
                    borderWidth: 1,
                    borderRadius: 4,
                }],
            },
            options: {
                indexAxis: horizontal ? 'y' : 'x',
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 } },
                    y: { beginAtZero: true },
                },
            },
        });
    }
    function dough(key) {
        const d = SD[key];
        if (!d || !d.labels.length) return;
        new Chart(document.getElementById('chart-' + key), {
            type: 'doughnut',
            data: {
                labels: d.labels,
                datasets: [{
                    data: d.totals,
                    backgroundColor: PALETTE,
                    borderColor: '#fff',
                    borderWidth: 2,
                }],
            },
            options: {
                plugins: { legend: { position: 'right' } },
            },
        });
    }

    bar('apdBulan', false);
    bar('apdAll', false);
    bar('orang', true);
    dough('dept');
})();
</script>

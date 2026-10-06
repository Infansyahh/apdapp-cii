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

    const charts = {};

    function applyThemeToCharts(theme) {
        const isDark = theme === 'dark';
        const textColor = isDark ? '#cbd5e1' : '#495057';
        const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.05)';
        const doughnutBorder = isDark ? '#1b2430' : '#ffffff';

        Chart.defaults.font.family = "Inter, sans-serif";
        Chart.defaults.color = textColor;

        Object.values(charts).forEach(chart => {
            if (chart.options.scales) {
                if (chart.options.scales.x) {
                    chart.options.scales.x.ticks.color = textColor;
                    chart.options.scales.x.grid.color = gridColor;
                }
                if (chart.options.scales.y) {
                    chart.options.scales.y.ticks.color = textColor;
                    chart.options.scales.y.grid.color = gridColor;
                }
            }
            if (chart.options.plugins && chart.options.plugins.legend) {
                chart.options.plugins.legend.labels.color = textColor;
            }
            if (chart.config.type === 'doughnut' && chart.data.datasets[0]) {
                chart.data.datasets[0].borderColor = doughnutBorder;
            }
            chart.update();
        });
    }

    function currentTheme() {
        return document.documentElement.getAttribute('data-bs-theme') || 'light';
    }

    function bar(key, horizontal) {
        const d = SD[key];
        const el = document.getElementById('chart-' + key);
        if (!d || !d.labels.length || !el) return;
        
        const isDark = currentTheme() === 'dark';
        const textColor = isDark ? '#cbd5e1' : '#495057';
        const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.05)';

        charts[key] = new Chart(el, {
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
                    x: { beginAtZero: true, ticks: { precision: 0, color: textColor }, grid: { color: gridColor } },
                    y: { beginAtZero: true, ticks: { color: textColor }, grid: { color: gridColor } },
                },
            },
        });
    }

    function legendPos() {
        // Layar HP: legend di bawah, supaya chart tidak gepeng
        return window.innerWidth < 576 ? 'bottom' : 'right';
    }

    function dough(key) {
        const d = SD[key];
        const el = document.getElementById('chart-' + key);
        if (!d || !d.labels.length || !el) return;

        const isDark = currentTheme() === 'dark';
        const textColor = isDark ? '#cbd5e1' : '#495057';
        const doughnutBorder = isDark ? '#1b2430' : '#ffffff';

        charts[key] = new Chart(el, {
            type: 'doughnut',
            data: {
                labels: d.labels,
                datasets: [{
                    data: d.totals,
                    backgroundColor: PALETTE,
                    borderColor: doughnutBorder,
                    borderWidth: 2,
                }],
            },
            options: {
                plugins: {
                    legend: {
                        position: legendPos(),
                        labels: { color: textColor }
                    }
                },
            },
        });
    }

    bar('apdBulan', false);
    bar('apdAll', false);
    bar('orang', true);
    dough('dept');

    window.addEventListener('themeChanged', function (e) {
        applyThemeToCharts(e.detail.theme);
    });

    window.addEventListener('resize', function () {
        if (!charts.dept) return;
        charts.dept.options.plugins.legend.position = legendPos();
        charts.dept.update();
    });
})();
</script>

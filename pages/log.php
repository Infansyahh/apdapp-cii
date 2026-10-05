<?php
// Hanya boleh di-include dari dashboard.php
if (!defined('APDCII')) { http_response_code(404); exit; }
// Tab Log Transaksi: semua mutasi dari view v_log_transaksi
$qLog = db()->query('SELECT * FROM v_log_transaksi ORDER BY waktu DESC LIMIT 300')->fetchAll();
?>
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Log Transaksi APD</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Tanggal</th><th>Bulan</th><th>Sumber</th><th>Jenis Transaksi</th>
                        <th>Jenis APD</th><th>Quantity</th><th>Sign</th><th>Net Quantity</th>
                        <th>Catatan</th><th>STATUS</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$qLog): ?>
                    <tr><td colspan="10" class="text-center text-muted">Belum ada data.</td></tr>
                <?php endif; ?>
                <?php foreach ($qLog as $r): $w = $r['waktu']; ?>
                    <tr>
                        <td><?= e(date('Y-m-d', strtotime($w))) ?></td>
                        <td><?= e(date('Y-m', strtotime($w))) ?></td>
                        <td><?= e($r['sumber']) ?></td>
                        <td><?= e($r['jenis_transaksi']) ?></td>
                        <td><?= e($r['jenis_apd']) ?></td>
                        <td><?= (int)$r['quantity'] ?></td>
                        <td><?= (int)$r['sign'] > 0 ? '+1' : '-1' ?></td>
                        <td><?= (int)$r['net_quantity'] > 0 ? '+' : '' ?><?= (int)$r['net_quantity'] ?></td>
                        <td><?= e($r['catatan']) ?></td>
                        <td><span class="badge bg-success"><?= e($r['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

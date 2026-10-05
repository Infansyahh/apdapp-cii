<?php
// Hanya boleh di-include dari dashboard.php
if (!defined('APDCII')) { http_response_code(404); exit; }
/** @var array $stokRows */ // variabel dari dashboard.php
// Tab Stock APD: posisi stok sistem per jenis
?>
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Stok APD</h5>
        <div class="text-muted small mt-1">Stok Sistem = Stok Awal + Masuk &minus; Keluar Net + Adj Stocktake Kumulatif</div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>No</th><th>Jenis APD</th><th>Satuan Pack</th><th>Jumlah Satuan</th><th>Harga</th>
                        <th>Stok Awal (Stoktake Hari Ini)</th><th>Adjustment Otomatis Stoktake Kumulatif (Pcs)</th>
                        <th>Barang Masuk (Pcs)</th><th>Barang Keluar / Kembali (Net Pcs)</th>
                        <th>Stok Sistem (Pcs)</th><th>Total Nilai Barang</th><th>Minimum Stok</th>
                        <th>Status Stok</th><th>Actual Stoktake Bulan Aktif</th>
                        <th>Selisih Actual vs Sistem</th><th>Keterangan Stoktake</th><th>Rekomendasi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($stokRows as $i => $r): $i++; ?>
                    <?php
                        $st = status_stok((int)$r['stok_sistem'], (int)$r['minimum_stok']);
                        if ($r['selisih'] !== null && $r['selisih'] != 0) {
                            $rekom = 'Selisih opname, periksa fisik';
                        } elseif ($st === 'HABIS') {
                            $rekom = 'Restock segera';
                        } elseif ($st === 'MENIPIS') {
                            $rekom = 'Restock';
                        } else {
                            $rekom = '-';
                        }
                    ?>
                    <tr>
                        <td><?= $i ?></td>
                        <td><?= e($r['nama']) ?></td>
                        <td><?= e($r['satuan_pack']) ?></td>
                        <td><?= (int)$r['jumlah_satuan'] ?></td>
                        <td><?= number_format((float)$r['harga'], 0, ',', '.') ?></td>
                        <td><?= (int)$r['stok_awal'] ?></td>
                        <td><?= (int)$r['adj_kumulatif'] ?></td>
                        <td><?= (int)$r['barang_masuk'] ?></td>
                        <td><?= (int)$r['keluar_net'] ?></td>
                        <td class="fw-bold"><?= (int)$r['stok_sistem'] ?></td>
                        <td><?= number_format($r['stok_sistem'] * (float)$r['harga'], 0, ',', '.') ?></td>
                        <td><?= (int)$r['minimum_stok'] ?></td>
                        <td><?= badge_status($st) ?></td>
                        <td><?= $r['actual_bulan'] === null ? '-' : (int)$r['actual_bulan'] ?></td>
                        <td><?= $r['selisih'] === null ? '-' : (($r['selisih'] > 0 ? '+' : '') . (int)$r['selisih']) ?></td>
                        <td><?= e($r['keterangan_bulan'] ?? '') ?></td>
                        <td><?= e($rekom) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

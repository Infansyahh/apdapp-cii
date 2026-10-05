<?php
// Hanya boleh di-include dari dashboard.php
if (!defined('APDCII')) { http_response_code(404); exit; }
/** @var array  $stokRows */ // variabel dari dashboard.php
/** @var string $bulan */     // variabel dari dashboard.php
// Tab Stocktake Bulanan: input actual + akurasi + perbandingan antar bulan
$bulanPrev   = date('Y-m', strtotime($bulan . '-01 -1 month'));
$akurasiPrev = akurasi_bulan_tersimpan($bulanPrev);
$pairsNow    = [];
foreach ($stokRows as $r) {
    if ($r['actual_bulan'] !== null) $pairsNow[] = [(int)$r['stok_sistem'], (int)$r['actual_bulan']];
}
$akurasiNow   = akurasi_dari($pairsNow);
$selisihAkur  = ($akurasiNow !== null && $akurasiPrev !== null) ? $akurasiNow - $akurasiPrev : null;
?>
<div class="d-flex justify-content-end align-items-center mb-3">
    <form method="get" class="d-flex align-items-center gap-2">
        <input type="hidden" name="tab" value="stocktake">
        <label class="text-muted small mb-0 fw-semibold">Bulan Stoktake</label>
        <input type="month" name="bulan" value="<?= e($bulan) ?>" required
               class="form-control form-control-sm input-lebar" onchange="this.form.submit()">
        <noscript><button type="submit" class="btn btn-sm btn-primary">Terapkan</button></noscript>
    </form>
</div>
<form method="post">
    <input type="hidden" name="aksi" value="stocktake">
    <input type="hidden" name="bulan" value="<?= e($bulan) ?>">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">STOKTAKE BULANAN - <?= e($bulan) ?></h5>
            <div class="text-muted small mt-1">Kosongkan kolom Actual untuk menghapus input baris itu.</div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>No</th><th>Jenis APD</th><th>Stok Sistem Sebelum Adjustment</th>
                            <th>Minimum Stok</th><th>Input Actual Stoktake</th><th>Selisih</th>
                            <th>Keterangan</th><th>Status Stok</th><th>Adjustment Dibutuhkan</th>
                            <th>Akurasi Total Keseluruhan</th><th>Status Selisih</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                        $total = 0; $diinput = 0; $sumSel = 0; $sumMax = 0;
                        $ketCls = ['SESUAI' => 'bg-success', 'LEBIH' => 'bg-warning', 'KURANG' => 'bg-danger', 'BELUM DI CEK' => 'bg-secondary'];
                        $selCls = ['AKURAT / SESUAI' => 'bg-success', 'GAIN - MASUK ADJUSTMENT' => 'bg-warning', 'LOSE - MASUK ADJUSTMENT' => 'bg-danger', 'BELUM STOKTAKE' => 'bg-secondary'];
                        foreach ($stokRows as $i => $r): $i++;
                            $c    = (int)$r['stok_sistem'];
                            $sel  = $r['selisih'];
                            $e    = $r['actual_bulan'] === null ? null : (int)$r['actual_bulan'];
                            $st   = status_stok($e ?? $c, (int)$r['minimum_stok']);
                            $ket  = $sel === null ? 'BELUM DI CEK' : ($sel > 0 ? 'LEBIH' : ($sel < 0 ? 'KURANG' : 'SESUAI'));
                            $sts  = $sel === null ? 'BELUM STOKTAKE' : ($sel > 0 ? 'GAIN - MASUK ADJUSTMENT' : ($sel < 0 ? 'LOSE - MASUK ADJUSTMENT' : 'AKURAT / SESUAI'));
                            $acc  = null;
                            if ($sel !== null) {
                                $acc = max(0, (1 - abs($sel) / max(abs($c), abs((int)$e), 1)) * 100);
                                $diinput++;
                                $sumSel += abs($sel);
                                $sumMax += max(abs($c), abs((int)$e), 1);
                            }
                            $total++;
                    ?>
                        <tr>
                            <td><?= $i ?></td>
                            <td><?= e($r['nama']) ?></td>
                            <td class="fw-bold"><?= $c ?></td>
                            <td><?= (int)$r['minimum_stok'] ?></td>
                            <td><input type="number" name="actual[<?= (int)$r['id'] ?>]" min="0"
                               value="<?= $e ?? '' ?>" class="form-control form-control-sm input-kecil"></td>
                            <td><?= $sel === null ? '-' : (($sel > 0 ? '+' : '') . $sel) ?></td>
                            <td><span class="badge <?= $ketCls[$ket] ?>"><?= $ket ?></span></td>
                            <td><?= badge_status($st) ?></td>
                            <td><?= $sel === null ? '-' : (($sel > 0 ? '+' : '') . $sel) ?></td>
                            <td><?= $acc === null ? '-' : round($acc, 1) . '%' ?></td>
                            <td><span class="badge <?= $selCls[$sts] ?>"><?= $sts ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="11">Akurasi Total Keseluruhan:
                                <?= ($diinput && $sumMax > 0)
                                    ? round((1 - $sumSel / $sumMax) * 100, 1) . '% (' . $diinput . '/' . $total . ' jenis terinput)'
                                    : 'Belum ada data' ?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Simpan Stocktake</button>
            <span class="text-muted small ms-2">Stok Sistem = transaksi s.d. bulan terpilih + kumulatif adjustment bulan sebelumnya.</span>
        </div>
    </div>
</form>

<div class="card mt-3">
    <div class="card-header">
        <h5 class="card-title mb-0">PERBANDINGAN AKURASI KESELURUHAN</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>Bulan Sebelumnya</th>
                    <th>Akurasi Bulan Sebelumnya</th>
                    <th>Bulan Stoktake</th>
                    <th>Akurasi Bulan Ini</th>
                    <th>Selisih Akurasi</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="fw-semibold"><?= e($bulanPrev) ?></td>
                    <td><?= $akurasiPrev === null ? 'Belum ada data' : round($akurasiPrev, 1) . '%' ?></td>
                    <td class="fw-semibold"><?= e($bulan) ?></td>
                    <td><?= $akurasiNow === null ? 'Belum ada data' : round($akurasiNow, 1) . '%' ?></td>
                    <td class="fw-bold <?= $selisihAkur === null ? 'text-muted' : ($selisihAkur >= 0 ? 'text-success' : 'text-danger') ?>">
                        <?= $selisihAkur === null ? '-' : sprintf('%+.1f%%', $selisihAkur) ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

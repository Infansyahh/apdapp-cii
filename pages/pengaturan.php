<?php
// Hanya boleh di-include dari dashboard.php
if (!defined('APDCII')) { http_response_code(404); exit; }
// Tab Pengaturan Form: kelola opsi pilihan pada Form Pengambilan (index.php)

$grup = [
    'departemen' => ['Departemen', 'Pilihan dropdown Departemen', true],
    'factory'    => ['Factory', 'Pilihan radio button Factory', true],
    'alasan'     => ['Alasan Pengambilan', 'Pilihan dropdown Alasan Pengambilan', true],
    'monitoring' => ['Monitoring APD', 'Label untuk nilai 1 & 2 (nilai terkunci: dipakai hitungan +/- transaksi)', false],
];

$opsiSemua = form_opsi_semua();

// Peta id untuk keperluan edit / hapus
$idMap = [];
if (form_opsi_siap()) {
    $sql = 'SELECT id, kategori, nilai FROM form_options ORDER BY kategori, urutan, id';
    foreach (db()->query($sql) as $r) {
        $idMap[$r['kategori']][(string) $r['nilai']] = (int) $r['id'];
    }
}
?>
<div class="row g-3">
    <?php foreach ($grup as $kat => [$judulGrup, $desc, $bisaUbah]): ?>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0 d-flex align-items-center">
                        <i class="me-2" data-feather="<?= $kat === 'monitoring' ? 'shield' : 'list' ?>"></i><?= e($judulGrup) ?>
                    </h5>
                    <div class="text-muted small mt-1"><?= e($desc) ?></div>
                </div>
                <div class="card-body">

                    <?php if ($bisaUbah): ?>
                        <!-- Tambah opsi baru -->
                        <form method="post" action="?tab=pengaturan" class="d-flex gap-2 mb-3">
                            <input type="hidden" name="aksi" value="tambah_opsi">
                            <input type="hidden" name="kategori" value="<?= e($kat) ?>">
                            <input type="text" name="label" class="form-control form-control-sm"
                                   maxlength="100" required placeholder="Tambah <?= e(mb_strtolower($judulGrup)) ?> baru...">
                            <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                                <i class="align-middle me-1" data-feather="plus"></i> Tambah
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php $daftar = $opsiSemua[$kat] ?? []; ?>
                    <?php if (!$daftar): ?>
                        <p class="text-muted mb-0">Belum ada opsi.</p>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-2">
                            <?php $no = 0; foreach ($daftar as $nilai => $label): $no++; $idOpsi = $idMap[$kat][$nilai] ?? null; ?>
                                <div class="d-flex gap-2 align-items-center">
                                    <?php if ($idOpsi !== null): ?>
                                        <form method="post" action="?tab=pengaturan"
                                              class="d-flex gap-2 align-items-center flex-grow-1">
                                            <input type="hidden" name="aksi" value="edit_opsi">
                                            <input type="hidden" name="kategori" value="<?= e($kat) ?>">
                                            <input type="hidden" name="id" value="<?= $idOpsi ?>">
                                            <span class="text-muted small" style="width: 1.25rem; flex-shrink: 0"><?= $no ?></span>
                                            <input type="text" name="label" class="form-control form-control-sm"
                                                   value="<?= e($label) ?>" maxlength="100" required>
                                            <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">
                                                <i class="align-middle me-1" data-feather="check"></i> Simpan
                                            </button>
                                        </form>
                                        <?php if ($bisaUbah): ?>
                                            <form method="post" action="?tab=pengaturan" class="m-0"
                                                  onsubmit="return confirm('Hapus opsi &quot;<?= e($label) ?>&quot;? Riwayat transaksi lama tetap tersimpan.')">
                                                <input type="hidden" name="aksi" value="hapus_opsi">
                                                <input type="hidden" name="kategori" value="<?= e($kat) ?>">
                                                <input type="hidden" name="id" value="<?= $idOpsi ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus opsi">
                                                    <i class="align-middle" data-feather="trash-2"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small" style="width: 1.25rem; flex-shrink: 0"><?= $no ?></span>
                                        <span class="flex-grow-1"><?= e($label) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mt-3">
    <div class="card-body py-2">
        <div class="d-flex align-items-start small text-muted">
            <i class="align-middle me-2 flex-shrink-0 mt-1" data-feather="info" style="width:16px;height:16px;"></i>
            <span>
                Perubahan di sini langsung dipakai <strong>Form Pengambilan</strong> (index.php).
                Riwayat transaksi lama tidak ikut berubah karena disimpan sebagai teks.
                Jenis APD diatur lewat tab <a href="?tab=stok" class="text-decoration-none">Stock APD</a>.
            </span>
        </div>
    </div>
</div>

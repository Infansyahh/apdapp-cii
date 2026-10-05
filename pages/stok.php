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
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($stokRows)): ?>
                    <tr><td colspan="18" class="text-center text-muted">Belum ada data stok APD.</td></tr>
                <?php endif; ?>
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
                        <td class="fw-semibold"><?= e($r['nama']) ?></td>
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
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary btn-sm btn-edit-apd"
                                        data-id="<?= (int)$r['id'] ?>"
                                        data-nama="<?= e($r['nama']) ?>"
                                        data-satuan-pack="<?= e($r['satuan_pack']) ?>"
                                        data-jumlah-satuan="<?= (int)$r['jumlah_satuan'] ?>"
                                        data-harga="<?= (float)$r['harga'] ?>"
                                        data-stok-awal="<?= (int)$r['stok_awal'] ?>"
                                        data-minimum-stok="<?= (int)$r['minimum_stok'] ?>"
                                        title="Edit APD">
                                    <i class="align-middle" data-feather="edit-2"></i> Edit
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm btn-hapus-apd"
                                        data-id="<?= (int)$r['id'] ?>"
                                        data-nama="<?= e($r['nama']) ?>"
                                        title="Hapus APD">
                                    <i class="align-middle" data-feather="trash-2"></i> Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==========================================
     POPUP ALERT KONFIRMASI EDIT
     ========================================== -->
<div class="modal fade popup-card-modal" id="modalConfirmEdit" tabindex="-1" aria-labelledby="modalConfirmEditLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content text-center">
            <div class="modal-header border-0 pb-0 justify-content-end">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body pt-0 px-4">
                <div class="popup-icon-circle bg-primary bg-opacity-10 text-primary mx-auto">
                    <i data-feather="edit-2" style="width:26px;height:26px;"></i>
                </div>
                <h5 class="fw-bold mb-2" id="modalConfirmEditLabel">Konfirmasi Edit Data</h5>
                <p class="text-muted small mb-3">Apakah Anda yakin ingin mengubah/mengedit data untuk jenis APD berikut?</p>
                <div class="confirm-name-box fw-bold fs-5 mb-3" id="confirmEditNamaApd">-</div>
                <p class="text-muted small mb-0">Klik tombol di bawah untuk melanjutkan ke formulir edit.</p>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4 justify-content-center gap-2">
                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary px-3" id="btnProceedEdit">
                    <i class="align-middle me-1" data-feather="arrow-right"></i> Ya, Lanjutkan Edit
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
     POPUP ALERT KONFIRMASI HAPUS
     ========================================== -->
<div class="modal fade popup-card-modal" id="modalConfirmHapus" tabindex="-1" aria-labelledby="modalConfirmHapusLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content text-center">
            <div class="modal-header border-0 pb-0 justify-content-end">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body pt-0 px-4">
                <div class="popup-icon-circle bg-danger bg-opacity-10 text-danger mx-auto">
                    <i data-feather="alert-triangle" style="width:26px;height:26px;"></i>
                </div>
                <h5 class="fw-bold text-danger mb-2" id="modalConfirmHapusLabel">Konfirmasi Hapus APD</h5>
                <p class="text-muted small mb-3">Apakah Anda benar-benar yakin ingin menghapus data jenis APD ini?</p>
                <div class="confirm-name-box border-danger border-opacity-25 bg-danger bg-opacity-10 text-danger fw-bold fs-5 mb-3" id="confirmHapusNamaApd">-</div>
                <div class="alert alert-warning py-2 px-3 small text-start mb-0 d-flex align-items-start">
                    <i class="align-middle me-2 flex-shrink-0 mt-1" data-feather="info" style="width:16px;height:16px;"></i>
                    <span>Tindakan ini tidak dapat dibatalkan. APD yang memiliki riwayat transaksi (pengambilan, masuk, opname) tidak dapat dihapus.</span>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4 justify-content-center gap-2">
                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger px-3" id="btnProceedHapus">
                    <i class="align-middle me-1" data-feather="trash-2"></i> Ya, Hapus Data
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
     POPUP CARD FORM EDIT APD
     ========================================== -->
<div class="modal fade popup-card-modal" id="modalEditApd" tabindex="-1" aria-labelledby="modalEditApdLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="post" action="?tab=stok">
                <input type="hidden" name="aksi" value="edit_apd">
                <input type="hidden" name="tab" value="stok">
                <input type="hidden" name="id" id="editApdId">
                <div class="modal-header">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary p-2 rounded me-2 d-flex align-items-center justify-content-center">
                            <i class="align-middle" data-feather="edit-3" style="width:20px;height:20px;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" id="modalEditApdLabel">Form Edit Data APD</h5>
                            <div class="text-muted small">Perbarui parameter dan stok dasar untuk jenis APD ini</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama APD <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama" id="editApdNama" placeholder="Nama jenis APD" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Satuan Pack</label>
                            <input type="text" class="form-control" name="satuan_pack" id="editApdSatuanPack" placeholder="Contoh: Pack, Box, Pcs, Roll" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jumlah Satuan (Pcs/Pack)</label>
                            <input type="number" class="form-control" name="jumlah_satuan" id="editApdJumlahSatuan" min="1" required>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Harga (Rp)</label>
                            <input type="number" class="form-control" name="harga" id="editApdHarga" min="0" step="any" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Stok Awal</label>
                            <input type="number" class="form-control" name="stok_awal" id="editApdStokAwal" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Minimum Stok</label>
                            <input type="number" class="form-control" name="minimum_stok" id="editApdMinimumStok" min="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="align-middle me-1" data-feather="check"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form Tersembunyi untuk Hapus APD -->
<form id="formHapusApd" method="post" action="?tab=stok" style="display: none;">
    <input type="hidden" name="aksi" value="hapus_apd">
    <input type="hidden" name="tab" value="stok">
    <input type="hidden" name="id" id="hapusApdId">
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const confirmEditModalEl = document.getElementById('modalConfirmEdit');
    const confirmHapusModalEl = document.getElementById('modalConfirmHapus');
    const editModalEl = document.getElementById('modalEditApd');

    let pendingEditData = null;
    let pendingHapusId = null;

    // Fungsi membuka popup card modal (melayang di atas tabel/layar)
    function showPopup(el) {
        if (!el) return;
        // Sembunyikan modal lain yang sedang aktif
        document.querySelectorAll('.modal.show').forEach(function (m) {
            hidePopup(m);
        });

        el.style.display = 'flex';
        // Force reflow agar animasi transisi CSS berjalan
        void el.offsetHeight;
        el.classList.add('show');
        document.body.style.overflow = 'hidden';

        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }

    // Fungsi menutup popup card modal
    function hidePopup(el) {
        if (!el) return;
        el.classList.remove('show');
        setTimeout(function () {
            if (!el.classList.contains('show')) {
                el.style.display = 'none';
            }
            if (!document.querySelector('.modal.show')) {
                document.body.style.overflow = '';
            }
        }, 200);
    }

    // Event listener tombol tutup / batal pada semua modal
    document.querySelectorAll('.modal [data-bs-dismiss="modal"], .modal .btn-close').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const modal = this.closest('.modal');
            if (modal) hidePopup(modal);
        });
    });

    // Klik pada area backdrop hitam (di luar modal-content) untuk menutup popup
    document.querySelectorAll('.modal').forEach(function (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === this) {
                hidePopup(this);
            }
        });
    });

    // Tekan tombol ESC keyboard untuk menutup popup aktif
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal.show');
            if (activeModal) hidePopup(activeModal);
        }
    });

    function populateEditForm(data) {
        if (!data) return;
        document.getElementById('editApdId').value = data.id || '';
        document.getElementById('editApdNama').value = data.nama || '';
        document.getElementById('editApdSatuanPack').value = data.satuanPack || '';
        document.getElementById('editApdJumlahSatuan').value = data.jumlahSatuan || 1;
        document.getElementById('editApdHarga').value = data.harga || 0;
        document.getElementById('editApdStokAwal').value = data.stokAwal || 0;
        document.getElementById('editApdMinimumStok').value = data.minimumStok || 0;
    }

    // Klik tombol Edit di tabel: Buka popup konfirmasi edit yang melayang di atas tabel
    document.querySelectorAll('.btn-edit-apd').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            pendingEditData = {
                id: this.dataset.id,
                nama: this.dataset.nama,
                satuanPack: this.dataset.satuanPack,
                jumlahSatuan: this.dataset.jumlahSatuan,
                harga: this.dataset.harga,
                stokAwal: this.dataset.stokAwal,
                minimumStok: this.dataset.minimumStok
            };

            const confirmNameEl = document.getElementById('confirmEditNamaApd');
            if (confirmNameEl) {
                confirmNameEl.textContent = pendingEditData.nama;
            }

            showPopup(confirmEditModalEl);
        });
    });

    // Klik tombol "Ya, Lanjutkan Edit" pada popup konfirmasi edit: Buka popup card form edit
    const btnProceedEdit = document.getElementById('btnProceedEdit');
    if (btnProceedEdit) {
        btnProceedEdit.addEventListener('click', function (e) {
            e.preventDefault();
            hidePopup(confirmEditModalEl);
            if (pendingEditData) {
                populateEditForm(pendingEditData);
                setTimeout(function () {
                    showPopup(editModalEl);
                }, 150);
            }
        });
    }

    // Klik tombol Hapus di tabel: Buka popup konfirmasi hapus yang melayang di atas tabel
    document.querySelectorAll('.btn-hapus-apd').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            pendingHapusId = this.dataset.id;
            const nama = this.dataset.nama;

            const confirmHapusNameEl = document.getElementById('confirmHapusNamaApd');
            if (confirmHapusNameEl) {
                confirmHapusNameEl.textContent = nama;
            }

            showPopup(confirmHapusModalEl);
        });
    });

    // Klik tombol "Ya, Hapus Data" pada popup konfirmasi hapus
    const btnProceedHapus = document.getElementById('btnProceedHapus');
    if (btnProceedHapus) {
        btnProceedHapus.addEventListener('click', function (e) {
            e.preventDefault();
            if (pendingHapusId) {
                document.getElementById('hapusApdId').value = pendingHapusId;
                document.getElementById('formHapusApd').submit();
            }
        });
    }
});
</script>

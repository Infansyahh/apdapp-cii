<?php
// Hanya boleh di-include dari dashboard.php
if (!defined('APDCII')) { http_response_code(404); exit; }
// Tab Data Masuk: form input + riwayat
$apdList = daftar_apd();
$qMasuk = db()->query('SELECT m.*, a.nama AS nama_apd FROM apd_masuk m
                       JOIN apd_master a ON a.id = m.jenis_apd_id
                       ORDER BY m.created_at DESC LIMIT 200')->fetchAll();
?>
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Input Data Masuk APD</h5>
    </div>
    <div class="card-body">
        <form method="post" class="row g-3 align-items-end">
            <input type="hidden" name="aksi" value="masuk">
            <div class="col-md-5">
                <label class="form-label">Jenis APD</label>
                <select name="jenis_apd" class="form-select" required>
                    <option value="">-- Pilih --</option>
                    <?php foreach ($apdList as $a): ?>
                        <option value="<?= (int)$a['id'] ?>"><?= e($a['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Quantity</label>
                <input type="number" name="quantity" min="1" class="form-control" required>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Tabel Data Masuk</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr><th>Timestamp</th><th>Email Address</th><th>Jenis APD</th><th>Quantity</th></tr>
                </thead>
                <tbody>
                <?php if (!$qMasuk): ?>
                    <tr><td colspan="4" class="text-center text-muted">Belum ada data.</td></tr>
                <?php endif; ?>
                <?php foreach ($qMasuk as $r): ?>
                    <tr>
                        <td><?= e($r['created_at']) ?></td>
                        <td><?= e($r['email']) ?></td>
                        <td><?= e($r['nama_apd']) ?></td>
                        <td><?= (int)$r['quantity'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

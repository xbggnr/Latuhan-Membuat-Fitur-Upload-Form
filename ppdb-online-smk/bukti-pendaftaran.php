<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/student_layout.php';
require_student();

$pdo = db();
$userId = (int) $_SESSION['user']['id'];
$reg = current_registration($pdo, $userId);
if (!$reg) {
    flash('error', 'Data pendaftaran belum tersedia. Silakan lengkapi pendaftaran terlebih dahulu.');
    redirect('pendaftaran.php');
}

$stmt = $pdo->prepare('SELECT s.*, u.email FROM siswa s INNER JOIN users u ON u.id=s.user_id WHERE s.user_id=:uid LIMIT 1');
$stmt->execute(['uid' => $userId]);
$siswa = $stmt->fetch() ?: [];

$docStmt = $pdo->prepare('SELECT id, jenis_dokumen, nama_file_asli, mime_type, status_verifikasi FROM dokumen_pendaftaran WHERE pendaftaran_id=:pid');
$docStmt->execute(['pid' => $reg['id']]);
$documents = [];
foreach ($docStmt->fetchAll() as $doc) {
    $documents[$doc['jenis_dokumen']] = $doc;
}
$photo = $documents['foto'] ?? null;

$statusLabels = [
    'draft' => 'Draft / Belum dikirim',
    'submitted' => 'Berhasil dikirim – Menunggu verifikasi',
    'verified' => 'Terverifikasi',
    'accepted' => 'Diterima',
    'rejected' => 'Tidak diterima',
];
$statusLabel = $statusLabels[$reg['status']] ?? ucfirst((string)$reg['status']);
$gender = ['L' => 'Laki-laki', 'P' => 'Perempuan'];
$birthDate = !empty($siswa['tanggal_lahir']) ? date('d-m-Y', strtotime((string)$siswa['tanggal_lahir'])) : '-';
$submittedAt = !empty($reg['tanggal_kirim']) ? date('d-m-Y H:i', strtotime((string)$reg['tanggal_kirim'])) : '-';
$createdAt = !empty($reg['created_at']) ? date('d-m-Y H:i', strtotime((string)$reg['created_at'])) : '-';
$addressParts = array_filter([
    $siswa['alamat'] ?? '', $siswa['kelurahan'] ?? '', $siswa['kecamatan'] ?? '',
    $siswa['kabupaten'] ?? '', $siswa['provinsi'] ?? '', $siswa['kode_pos'] ?? ''
], static fn($v) => trim((string)$v) !== '');
$fullAddress = $addressParts ? implode(', ', $addressParts) : '-';

student_header('Bukti Pendaftaran', 'bukti');
if ($m = flash('success')) echo '<div class="alert alert-success">' . e($m) . '</div>';
if ($reg['status'] === 'draft') {
    echo '<div class="alert alert-info">Ini masih pratinjau bukti. Bukti final tersedia setelah Anda mengirim pendaftaran.</div>';
}
?>
<style>
.receipt-wrap{max-width:900px;margin:0 auto}.receipt-actions{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0 22px}.receipt{background:#fff;border:1px solid #d8e3f1;border-radius:14px;overflow:hidden;box-shadow:0 8px 24px rgba(20,55,100,.08);color:#14243b}.receipt-head{background:linear-gradient(110deg,#103d78,#0876ce);color:#fff;padding:22px 26px;display:flex;justify-content:space-between;align-items:center;gap:16px}.receipt-brand{font-size:19px;font-weight:800;letter-spacing:.2px}.receipt-sub{font-size:12px;opacity:.9;margin-top:4px}.receipt-body{padding:24px 26px}.receipt-title{text-align:center;margin:0 0 6px;color:#153d70;font-size:22px;font-weight:800}.receipt-desc{text-align:center;color:#64748b;font-size:13px;margin:0 0 20px}.receipt-number{background:#eaf3ff;border:1px solid #c9def8;border-radius:10px;padding:13px 16px;text-align:center;margin-bottom:20px}.receipt-number small{display:block;color:#526780;font-size:11px}.receipt-number strong{display:block;color:#123e78;font-size:21px;letter-spacing:1px;margin-top:4px;overflow-wrap:anywhere}.receipt-grid{display:grid;grid-template-columns:150px 1fr;gap:22px;align-items:start}.receipt-photo{width:140px;height:180px;object-fit:cover;object-position:center;border:1px solid #cbd5e1;border-radius:8px;background:#f1f5f9;display:block}.photo-placeholder{width:140px;height:180px;border:1px dashed #94a3b8;border-radius:8px;display:flex;align-items:center;justify-content:center;text-align:center;color:#64748b;font-size:12px;padding:10px;background:#f8fafc}.receipt-table{width:100%;border-collapse:collapse;font-size:13px}.receipt-table td{padding:7px 8px;border-bottom:1px solid #e8eef5;vertical-align:top}.receipt-table td:first-child{width:155px;color:#64748b}.receipt-table td:nth-child(2){width:12px;color:#64748b}.receipt-section{margin-top:22px}.receipt-section h3{font-size:14px;color:#153d70;margin:0 0 9px;padding-bottom:7px;border-bottom:1px solid #dbe7f4}.status-box{display:flex;align-items:center;gap:12px;background:#effaf3;border:1px solid #c5ebd0;border-radius:9px;padding:12px 14px;margin-top:20px}.status-dot{width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:#16a34a;color:#fff;font-weight:800;flex:none}.status-box strong{display:block;color:#166534;font-size:13px}.status-box small{display:block;color:#557361;margin-top:3px}.receipt-note{font-size:11px;color:#64748b;line-height:1.6;margin-top:18px}.receipt-foot{border-top:1px solid #e5edf6;padding:12px 26px;display:flex;justify-content:space-between;gap:12px;color:#64748b;font-size:10px}.btn-pdf{background:#fff!important;color:#16477f!important;border:1px solid #16477f!important}.btn-print{background:#145db5!important;color:#fff!important}.receipt-actions button{cursor:pointer}
@media(max-width:650px){.receipt-head{padding:17px;align-items:flex-start}.receipt-body{padding:17px}.receipt-grid{grid-template-columns:1fr}.receipt-photo,.photo-placeholder{width:120px;height:154px}.receipt-table td:first-child{width:115px}.receipt-number strong{font-size:17px}.receipt-foot{padding:12px 17px;flex-direction:column}}
@media print{
 @page{size:A4;margin:14mm}
 body{background:#fff!important;color:#111!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
 .auth-header,.student-top,.student-sidebar,.receipt-actions,.alert,.no-print{display:none!important}
 .student-shell{max-width:none!important;margin:0!important;padding:0!important}
 .student-layout{display:block!important;margin:0!important}
 .student-main-card{box-shadow:none!important;border:0!important;padding:0!important;margin:0!important}
 .receipt-wrap{max-width:none!important;margin:0!important}
 .receipt{box-shadow:none!important;border:1px solid #cbd5e1!important;border-radius:0!important;break-inside:avoid}
 .receipt-head{background:#16477f!important;color:#fff!important}
 .receipt-body{padding:18px!important}
 .receipt-table{font-size:11px!important}
 .receipt-photo,.photo-placeholder{width:115px;height:150px}
 a{color:inherit!important;text-decoration:none!important}
}
</style>
<div class="receipt-wrap">
    <div class="receipt-actions no-print">
        <button type="button" class="btn-primary btn-print" onclick="window.print()">&#128424; Cetak Bukti</button>
        <button type="button" class="btn-secondary btn-pdf" onclick="window.print()">&#8681; Unduh PDF</button>
        <a class="btn-secondary" href="dashboard.php">Kembali ke Dashboard</a>
    </div>

    <article class="receipt" id="receipt">
        <header class="receipt-head">
            <div>
                <div class="receipt-brand">SMK NUSANTARA</div>
                <div class="receipt-sub">Penerimaan Peserta Didik Baru · Tahun Ajaran <?= e((string)$reg['tahun_ajaran']) ?></div>
            </div>
            <div style="text-align:right;font-size:12px;font-weight:700">PPDB ONLINE<br><span style="font-weight:400">BUKTI PENDAFTARAN</span></div>
        </header>
        <div class="receipt-body">
            <h2 class="receipt-title">BUKTI PENDAFTARAN</h2>
            <p class="receipt-desc">Penerimaan Peserta Didik Baru (PPDB) SMK Nusantara</p>

            <div class="receipt-number">
                <small>NOMOR PENDAFTARAN</small>
                <strong><?= e((string)$reg['nomor_pendaftaran']) ?></strong>
            </div>

            <div class="receipt-grid">
                <div>
                    <?php if ($photo): ?>
                        <img class="receipt-photo" src="lihat-foto.php?id=<?= (int)$photo['id'] ?>" alt="Foto calon peserta didik">
                    <?php else: ?>
                        <div class="photo-placeholder">Foto pendaftar<br>belum diunggah</div>
                    <?php endif; ?>
                </div>
                <div>
                    <table class="receipt-table">
                        <tr><td>Nama lengkap</td><td>:</td><td><strong><?= e((string)($siswa['nama_lengkap'] ?? $_SESSION['user']['nama_lengkap'] ?? '-')) ?></strong></td></tr>
                        <tr><td>NISN</td><td>:</td><td><?= e((string)($siswa['nisn'] ?? '-')) ?></td></tr>
                        <tr><td>NIK</td><td>:</td><td><?= e((string)($siswa['nik'] ?? '-')) ?></td></tr>
                        <tr><td>Jenis kelamin</td><td>:</td><td><?= e($gender[$siswa['jenis_kelamin'] ?? ''] ?? '-') ?></td></tr>
                        <tr><td>Tempat, tanggal lahir</td><td>:</td><td><?= e((string)($siswa['tempat_lahir'] ?? '-')) ?>, <?= e($birthDate) ?></td></tr>
                        <tr><td>Asal sekolah</td><td>:</td><td><?= e((string)($siswa['asal_sekolah'] ?? '-')) ?></td></tr>
                        <tr><td>Nomor HP</td><td>:</td><td><?= e((string)($siswa['no_hp'] ?? '-')) ?></td></tr>
                        <tr><td>Email</td><td>:</td><td><?= e((string)($siswa['email'] ?? '-')) ?></td></tr>
                    </table>
                </div>
            </div>

            <section class="receipt-section">
                <h3>INFORMASI PENDAFTARAN</h3>
                <table class="receipt-table">
                    <tr><td>Tahun ajaran</td><td>:</td><td><?= e((string)$reg['tahun_ajaran']) ?></td></tr>
                    <tr><td>Pilihan jurusan</td><td>:</td><td><strong><?= e((string)($reg['nama_jurusan'] ?? '-')) ?><?= !empty($reg['kode_jurusan']) ? ' (' . e((string)$reg['kode_jurusan']) . ')' : '' ?></strong></td></tr>
                    <tr><td>Tanggal pendaftaran</td><td>:</td><td><?= e($createdAt) ?></td></tr>
                    <tr><td>Tanggal pengiriman</td><td>:</td><td><?= e($submittedAt) ?></td></tr>
                    <tr><td>Alamat</td><td>:</td><td><?= e($fullAddress) ?></td></tr>
                </table>
            </section>

            <div class="status-box">
                <div class="status-dot">&#10003;</div>
                <div><strong>STATUS: <?= e(strtoupper($statusLabel)) ?></strong><small><?= $reg['status'] === 'draft' ? 'Lengkapi dan kirim formulir untuk memperoleh bukti final.' : 'Data pendaftaran telah tercatat dalam sistem PPDB Online SMK Nusantara.' ?></small></div>
            </div>

            <p class="receipt-note"><strong>Catatan:</strong><br>
                1. Simpan bukti pendaftaran ini sebagai tanda registrasi.<br>
                2. Nomor pendaftaran digunakan untuk memantau proses seleksi.<br>
                3. Bukti ini bukan surat keputusan penerimaan siswa. Keputusan akhir mengikuti hasil verifikasi dan seleksi sekolah.
            </p>
        </div>
        <footer class="receipt-foot"><span>PPDB Online SMK Nusantara · <?= e((string)$reg['tahun_ajaran']) ?></span><span>Dicetak pada: <?= e(date('d-m-Y H:i')) ?> WIB</span></footer>
    </article>
    <p class="muted no-print" style="font-size:12px;margin-top:12px">Untuk mengunduh PDF, klik <b>Unduh PDF</b>, lalu pilih tujuan printer <b>Save as PDF / Simpan sebagai PDF</b> pada dialog cetak browser.</p>
</div>
<?php student_footer(); ?>

<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/student_layout.php';
require_student();

$pdo = db();
$userId = (int) $_SESSION['user']['id'];
$registration = current_registration($pdo, $userId);
$errors = [];
$types = [
    'foto'   => 'Pas Foto',
    'kk'     => 'Kartu Keluarga',
    'akta'   => 'Akta Kelahiran',
    'ijazah' => 'Ijazah / SKL',
];
$maxBytes = 2 * 1024 * 1024;
$uploadDir = __DIR__ . '/storage/dokumen';

if (!$registration) {
    flash('error', 'Buat draft pendaftaran dan pilih jurusan terlebih dahulu.');
    redirect('pendaftaran.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Token keamanan tidak valid. Muat ulang halaman.';
    } elseif ($registration['status'] !== 'draft') {
        $errors[] = 'Dokumen tidak dapat diubah setelah pendaftaran dikirim.';
    } else {
        $kind = (string) ($_POST['jenis_dokumen'] ?? '');
        if (!isset($types[$kind])) {
            $errors[] = 'Jenis dokumen tidak valid.';
        } elseif (!isset($_FILES['file_dokumen']) || !is_array($_FILES['file_dokumen'])) {
            $errors[] = 'Pilih file yang akan diunggah.';
        } else {
            $file = $_FILES['file_dokumen'];
            if ((int)$file['error'] !== UPLOAD_ERR_OK) {
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => 'Ukuran file melebihi batas konfigurasi PHP.',
                    UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas formulir.',
                    UPLOAD_ERR_PARTIAL => 'File hanya terunggah sebagian. Coba kembali.',
                    UPLOAD_ERR_NO_FILE => 'Pilih file terlebih dahulu.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara PHP tidak tersedia.',
                    UPLOAD_ERR_CANT_WRITE => 'Server gagal menulis file.',
                    UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP.',
                ];
                $errors[] = $uploadErrors[(int)$file['error']] ?? 'Upload file gagal.';
            } elseif (!is_uploaded_file($file['tmp_name'])) {
                $errors[] = 'Sumber file upload tidak valid.';
            } elseif ((int)$file['size'] <= 0 || (int)$file['size'] > $maxBytes) {
                $errors[] = 'Ukuran file harus lebih dari 0 dan maksimal 2 MB.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $allowed = $kind === 'foto'
                    ? ['image/jpeg' => 'jpg', 'image/png' => 'png']
                    : ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

                if (!is_string($mime) || !isset($allowed[$mime])) {
                    $errors[] = $kind === 'foto'
                        ? 'Foto harus berformat JPG, JPEG, atau PNG.'
                        : 'Dokumen harus berformat PDF, JPG, atau PNG.';
                } elseif ($kind === 'foto') {
                    $imageInfo = @getimagesize($file['tmp_name']);
                    if ($imageInfo === false || !in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
                        $errors[] = 'File tidak terdeteksi sebagai gambar JPG/PNG yang valid.';
                    }
                }

                if (!$errors) {
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
                        $errors[] = 'Folder penyimpanan tidak dapat dibuat.';
                    } elseif (!is_writable($uploadDir)) {
                        $errors[] = 'Folder storage/dokumen tidak memiliki izin tulis.';
                    } else {
                        $storedName = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
                        $destination = $uploadDir . DIRECTORY_SEPARATOR . $storedName;
                        $oldName = null;
                        $moved = false;
                        try {
                            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                                throw new RuntimeException('File gagal dipindahkan ke folder penyimpanan.');
                            }
                            $moved = true;

                            $oldStmt = $pdo->prepare(
                                'SELECT nama_file_simpan FROM dokumen_pendaftaran
                                 WHERE pendaftaran_id = :pid AND jenis_dokumen = :jenis LIMIT 1'
                            );
                            $oldStmt->execute(['pid' => $registration['id'], 'jenis' => $kind]);
                            $oldName = $oldStmt->fetchColumn() ?: null;

                            $pdo->beginTransaction();
                            $save = $pdo->prepare(
                                'INSERT INTO dokumen_pendaftaran
                                    (pendaftaran_id, jenis_dokumen, nama_file_asli, nama_file_simpan,
                                     mime_type, ukuran_file, status_verifikasi)
                                 VALUES
                                    (:pid, :jenis, :original, :stored, :mime, :size, "pending")
                                 ON DUPLICATE KEY UPDATE
                                    nama_file_asli = VALUES(nama_file_asli),
                                    nama_file_simpan = VALUES(nama_file_simpan),
                                    mime_type = VALUES(mime_type),
                                    ukuran_file = VALUES(ukuran_file),
                                    status_verifikasi = "pending",
                                    updated_at = CURRENT_TIMESTAMP'
                            );
                            $save->execute([
                                'pid' => $registration['id'],
                                'jenis' => $kind,
                                'original' => basename((string)$file['name']),
                                'stored' => $storedName,
                                'mime' => $mime,
                                'size' => (int)$file['size'],
                            ]);
                            $pdo->commit();

                            // Hapus file lama hanya setelah metadata baru berhasil tersimpan.
                            if ($oldName && $oldName !== $storedName) {
                                $oldPath = $uploadDir . DIRECTORY_SEPARATOR . basename((string)$oldName);
                                if (is_file($oldPath)) {
                                    @unlink($oldPath);
                                }
                            }
                            flash('success', $types[$kind] . ' berhasil diunggah.');
                            redirect('dokumen.php');
                        } catch (Throwable $exception) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            if ($moved && is_file($destination)) {
                                @unlink($destination);
                            }
                            error_log('PPDB upload error: ' . $exception->getMessage());
                            $errors[] = 'File gagal disimpan. Periksa konfigurasi database dan izin folder storage.';
                        }
                    }
                }
            }
        }
    }
}

$list = $pdo->prepare('SELECT * FROM dokumen_pendaftaran WHERE pendaftaran_id = :pid');
$list->execute(['pid' => $registration['id']]);
$documents = [];
foreach ($list->fetchAll() as $row) {
    $documents[$row['jenis_dokumen']] = $row;
}

student_header('Upload Dokumen', 'dokumen');
if ($message = flash('success')) {
    echo '<div class="alert alert-success">' . e($message) . '</div>';
}
if ($errors) {
    echo '<div class="alert alert-error">';
    foreach ($errors as $error) echo '<p>' . e($error) . '</p>';
    echo '</div>';
}
?>
<style>
.upload-card{border:1px solid #dce7f5;border-radius:14px;padding:18px;margin:16px 0;background:#fff}
.upload-grid{display:grid;grid-template-columns:150px 1fr;gap:20px;align-items:center}
.photo-preview{width:140px;height:180px;object-fit:cover;border:1px solid #d5e2f2;border-radius:12px;background:#f3f7fc}
.photo-placeholder{width:140px;height:180px;border:1px dashed #9bb6d8;border-radius:12px;display:grid;place-items:center;color:#64748b;background:#f5f8fc;text-align:center}
.upload-help{font-size:13px;color:#64748b;line-height:1.6}
.upload-form{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px}
.upload-form input[type=file]{max-width:100%}
@media(max-width:600px){.upload-grid{grid-template-columns:1fr}.photo-preview,.photo-placeholder{width:120px;height:155px}}
</style>
<h2>Foto dan Dokumen Persyaratan</h2>
<p class="muted">Unggah foto pendaftar serta dokumen persyaratan. Format foto JPG/PNG, sedangkan dokumen dapat berupa PDF/JPG/PNG. Maksimal 2 MB per file.</p>
<?php if ($registration['status'] !== 'draft'): ?>
    <div class="alert alert-info">Pendaftaran telah dikirim. Penggantian foto dan dokumen dinonaktifkan sampai admin membuka proses koreksi.</div>
<?php endif; ?>

<?php foreach ($types as $key => $label): $doc = $documents[$key] ?? null; ?>
<div class="upload-card">
    <h3 style="margin:0 0 12px;font-size:17px;color:#173b70"><?= e($label) ?></h3>
    <?php if ($key === 'foto'): ?>
        <div class="upload-grid">
            <div>
                <?php if ($doc): ?>
                    <img class="photo-preview" id="foto-lama" src="lihat-foto.php?id=<?= (int)$doc['id'] ?>" alt="Foto pendaftar saat ini">
                <?php else: ?>
                    <div class="photo-placeholder" id="placeholder-foto">Preview<br>Foto Pendaftar</div>
                <?php endif; ?>
                <!-- TAG IMG LIVE PREVIEW DIPINDAH KE SINI -->
                <img data-live-preview class="photo-preview" alt="Preview foto baru" style="display:none;">
            </div>
            <div>
                <p class="upload-help">Ketentuan foto: foto formal terbaru, wajah terlihat jelas, format JPG/JPEG/PNG, maksimal 2 MB. Rasio potret 3:4 disarankan.</p>
                <?php if ($doc): ?><p class="upload-help">File saat ini: <b><?= e($doc['nama_file_asli']) ?></b> · <?= number_format((int)$doc['ukuran_file']/1024, 1) ?> KB</p><?php endif; ?>
                <?php if ($registration['status'] === 'draft'): ?>
                <form class="upload-form" method="post" enctype="multipart/form-data" action="dokumen.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="jenis_dokumen" value="foto">
                    <input type="file" name="file_dokumen" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required data-preview-input>
                    <button class="btn-primary" type="submit"><?= $doc ? 'Ganti Foto' : 'Upload Foto' ?></button>
                </form>
                <p class="upload-help" data-preview-message>Preview akan muncul setelah memilih foto.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <p class="upload-help"><?= $doc ? 'File: ' . e($doc['nama_file_asli']) : 'Belum diunggah.' ?></p>
        <?php if ($registration['status'] === 'draft'): ?>
        <form class="upload-form" method="post" enctype="multipart/form-data" action="dokumen.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="jenis_dokumen" value="<?= e($key) ?>">
            <input type="file" name="file_dokumen" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
            <button class="btn-secondary" type="submit"><?= $doc ? 'Ganti Dokumen' : 'Upload Dokumen' ?></button>
        </form>
        <?php endif; ?>
    <?php endif; ?>
    <p class="upload-help">Status verifikasi: <b><?= e($doc['status_verifikasi'] ?? 'belum diunggah') ?></b></p>
</div>
<?php endforeach; ?>
<script>
    document.querySelectorAll('[data-preview-input]').forEach(function(input){
        input.addEventListener('change', function(){
            const file = this.files && this.files[0];
            
            // Cari elemen berdasarkan struktur grid
            const grid = this.closest('.upload-grid');
            const preview = grid.querySelector('[data-live-preview]');
            const placeholder = grid.querySelector('#placeholder-foto');
            const fotoLama = grid.querySelector('#foto-lama');
            const message = this.closest('form').parentElement.querySelector('[data-preview-message]');
            
            if (!file) return;
            if (!['image/jpeg','image/png'].includes(file.type)) {
                alert('Pilih foto JPG atau PNG.'); this.value=''; preview.style.display='none'; return;
            }
            if (file.size > 2 * 1024 * 1024) {
                alert('Ukuran foto maksimal 2 MB.'); this.value=''; preview.style.display='none'; return;
            }
            
            if (preview.dataset.objectUrl) URL.revokeObjectURL(preview.dataset.objectUrl);
            const url = URL.createObjectURL(file);
            
            // Sembunyikan kotak placeholder atau foto lama
            if (placeholder) placeholder.style.display = 'none';
            if (fotoLama) fotoLama.style.display = 'none';
            
            // Tampilkan preview baru
            preview.src = url; 
            preview.dataset.objectUrl = url; 
            preview.style.display = 'block';
            message.textContent = 'Preview: ' + file.name + ' (' + Math.round(file.size/1024) + ' KB). Tekan Upload/Ganti Foto untuk menyimpan.';
        });
    });
</script>
<?php student_footer(); ?>

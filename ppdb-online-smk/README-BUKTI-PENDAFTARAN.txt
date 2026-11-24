INTEGRASI FOTO, BUKTI PENDAFTARAN, CETAK, DAN PDF
PPDB ONLINE SMK NUSANTARA

FITUR BARU
- bukti-pendaftaran.php: halaman bukti pendaftaran yang mengambil data siswa, jurusan, nomor pendaftaran, status, tanggal, dan foto.
- Foto diambil melalui lihat-foto.php?id=... sehingga endpoint tetap memeriksa login dan kepemilikan pendaftaran.
- Tombol Cetak Bukti membuka dialog cetak browser.
- Tombol Unduh PDF juga membuka dialog cetak; pilih printer/tujuan "Save as PDF" atau "Simpan sebagai PDF" untuk menghasilkan file PDF. Cara ini dapat digunakan secara offline tanpa library PDF eksternal.
- Navigasi siswa dan dashboard ditambahkan tautan ke Bukti Pendaftaran.
- Setelah kirim pendaftaran berhasil, siswa diarahkan langsung ke halaman bukti.

CARA MEMASANG DI XAMPP
1. Backup folder proyek dan database terlebih dahulu.
2. Salin bukti-pendaftaran.php ke root proyek C:\xampp\htdocs\ppdb-online-smk\.
3. Salin file includes/student_layout.php dan dashboard.php versi paket ini untuk menambahkan menu bukti. Jika Anda sudah memodifikasi file tersebut, gabungkan perubahan menu secara manual.
4. Salin kirim-pendaftaran.php versi paket ini agar setelah pengiriman berhasil diarahkan ke bukti pendaftaran.
5. Pastikan file lihat-foto.php, config/database.php, includes/bootstrap.php, dan folder storage/dokumen tetap ada.
6. Pastikan tabel siswa, users, pendaftaran, jurusan, dan dokumen_pendaftaran menggunakan struktur dari proyek PPDB yang sedang dipakai.
7. Jalankan Apache dan MySQL, login sebagai siswa, unggah foto, lengkapi data dan dokumen, lalu kirim pendaftaran.
8. Buka http://localhost/ppdb-online-smk/bukti-pendaftaran.php.

CETAK DAN PDF
- Cetak Bukti: pada dialog browser pilih printer yang tersedia.
- Unduh PDF: pada dialog browser pilih "Save as PDF" (Chrome/Edge) lalu tentukan lokasi penyimpanan.
- CSS @media print menyembunyikan navigasi dan tombol, serta mengatur kertas A4.
- Tidak memerlukan internet atau library PDF tambahan.

CATATAN PENTING
- Tombol Unduh PDF menggunakan fitur cetak-ke-PDF bawaan browser, bukan file PDF yang dihasilkan langsung oleh PHP. Pengguna perlu memilih "Save as PDF" pada dialog cetak.
- QR Code tidak dibuat karena proyek belum memiliki generator QR dan belum ditentukan URL verifikasi publik. Nomor pendaftaran menjadi identitas utama pada bukti ini.
- Belum diuji terhadap server MySQL lokal pengguna; uji dengan database proyek sebenarnya.

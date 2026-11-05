# Koneksi TiDB Cloud

Laravel 12.69 memakai driver mysql pada PHP 8.2.12, pdo_mysql/mysqlnd, dan OpenSSL.
Seluruh model menggunakan koneksi default yang dipusatkan di `config/database.php`.

## Kredensial dan environment

Root proyek Laravel adalah `D:/Semester 5/PPL/PPL-KELOMPOKAC`. Kredensial yang sempat
diisi di `.env.example` telah dipindahkan ke `.env` lokal. Template dikembalikan ke
placeholder. `.env` dan `.env.backup` diabaikan Git. Password dan username disamarkan
pada laporan diagnostik. APP_DEBUG=false dipakai pada environment lokal ini.

```dotenv
DB_CONNECTION=mysql
DB_HOST=YOUR_TIDB_HOST
DB_PORT=4000
DB_DATABASE=terangdesa
DB_USERNAME=YOUR_TIDB_USERNAME
DB_PASSWORD="YOUR_TIDB_PASSWORD"
DB_COLLATION=utf8mb4_unicode_ci
DB_URL=
DATABASE_URL=
DB_SOCKET=
DB_CONNECT_TIMEOUT=10
MYSQL_ATTR_SSL_CA="D:/Semester 5/PPL/PPL-KELOMPOKAC/resources/certs/isrg-root-x1.pem"
```

DB_URL digunakan Laravel dan dapat mengubah hasil resolusi endpoint. DATABASE_URL
tidak dirujuk kode proyek. Kedua variabel kosong pada konfigurasi aktif. Tidak ada
override read/write atau koneksi khusus pada model. Sesi/cache memakai file dan
queue memakai sync.

## Sertifikat

CA publik di `resources/certs/isrg-root-x1.pem` diambil dari Let's Encrypt.
Fingerprint SHA-256 sertifikat DER telah diverifikasi:

```text
96bcec06264976f37460779acf28c5a7cfe8a3c0aae11a8ffcee05c0bddf08c6
```

PHP 8.2 memakai `PDO::MYSQL_ATTR_SSL_CA` dan
`PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true`. Pada Windows, gunakan path absolut
dengan slash `/` dan kutip bila mengandung spasi. Pada hosting, sesuaikan lokasi CA
dengan deployment atau bundle sistem yang sesuai, misalnya
`/etc/ssl/certs/ca-certificates.crt` pada Ubuntu.

- <https://docs.pingcap.com/tidbcloud/secure-connections-to-serverless-clusters/>
- <https://letsencrypt.org/certs/isrgrootx1.pem>

## Pemeriksaan

```powershell
php artisan config:clear
php artisan terangdesa:tidb-check
php artisan terangdesa:tidb-check --write-test
```

Pemeriksa menolak password placeholder, CA yang tidak dapat dibaca, dan verifikasi
sertifikat yang dinonaktifkan. Koneksi PDO aplikasi memeriksa DATABASE(), VERSION(),
Ssl_cipher, Ssl_version, tabel wajib, serta jumlah users/wilayah. Opsi write-test
membuat data uji dalam transaksi dan melakukan rollback. Error autentikasi/jaringan
dilaporkan dengan nilai rahasia disamarkan.

## Bukti koneksi 5 November 2026

- Endpoint aktif adalah gateway01.ap-southeast-1.prod.aws.tidbcloud.com, port 4000.
- Database aktif adalah terangdesa.
- Server melaporkan 8.0.11-TiDB-v8.5.3-serverless.
- Sesi PDO melaporkan TLSv1.3 dan TLS_AES_128_GCM_SHA256.
- CA dapat dibaca dan verifikasi sertifikat server aktif.
- Terdapat 10 tabel, 5.311 wilayah, dan satu akun pengguna operasional.
- Akun online yang tersedia lolos pemeriksaan autentikasi.

Database yang disediakan pengguna sudah berisi skema dan data. Integrasi ini
membaca struktur aktual tanpa DDL, migrasi, seed, atau impor ulang. Batasan skema
dan pemetaan field peta didokumentasikan pada [map-data.md](map-data.md).

Peta, dashboard, daftar/detail wilayah, serta autentikasi menggunakan koneksi yang
sama. Query wilayah selalu membaca nilai tersimpan terbaru. Berkas indikator lokal
dan kode fallback sudah dihapus. Aset geografis provinsi tetap digunakan untuk
tampilan batas. Restart server yang masih menyimpan konfigurasi lama setelah
perubahan environment. Pada proses artisan serve standar, perubahan .env dapat
memicu restart server otomatis.

## Hasil pengujian integrasi

Sebanyak 34 pengujian backend lulus dengan 303 assertions. Seluruh 20 skenario
browser lulus setelah dua pemeriksaan timing diuji ulang, dan satu tes tile eksternal
opsional dilewati pada suite normal. Pemeriksaan terpisah dengan akun online asli
berhasil login/logout, membuka daftar wilayah, dashboard, peta, dan detail. Sebanyak
20 tile OpenStreetMap asli berhasil dimuat. Tidak ada error JavaScript, permintaan
dataset lokal, atau overflow pada emulasi seluler.

Uji simpan/edit melalui halaman Pemerintah berhasil memperbarui jumlah, kondisi,
metrik dashboard, penanda, serta detail peta. Fixture dibersihkan. Checksum seluruh
baris users/wilayah asli dan struktur kolom dibandingkan sebelum/sesudah pengujian
dan tetap sama. Jumlah akhir adalah 5.311 wilayah dan satu akun, tanpa fixture tersisa.
Laporan tersamarkan tersimpan di `storage/app/tidb/verification-online.json` dan
screenshot pemeriksaan aktual tersedia pada `test-results/tidb-live-*.png`.

## Pembaruan koordinat desa

Sesudah integrasi awal, pengguna meminta titik referensi desa dikembalikan melalui
TiDB. Pada 5 November 2026, 5.227 pasangan koordinat kosong diisi setelah pencocokan
kode dan hierarki administratif dengan referensi BIG. Sebanyak 84 wilayah belum
cocok dan tetap kosong. Seluruh atribut selain koordinat tetap sama. Pemeriksaan
ulang rencana pengayaan menghasilkan nol perubahan tambahan. Lihat
[map-data.md](map-data.md) untuk metode, sumber, dan lokasi audit. Laporan hasil
terbaru tersimpan di `storage/app/map-reference/verification.json`.

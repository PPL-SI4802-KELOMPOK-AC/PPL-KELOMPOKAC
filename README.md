# TerangDesa

Aplikasi Laravel untuk pendataan wilayah, pemetaan kebutuhan listrik, serta
pengajuan dan pemantauan kebutuhan PLTS untuk kelompok warga.

## Menjalankan aplikasi

Gunakan PHP 8.3+ untuk pemasangan seluruh development tools pada composer.lock,
Composer, Node.js, dan npm. Aplikasi juga telah diuji pada PHP 8.2.12 dengan
Laravel 12.69, pdo_mysql/mysqlnd, dan OpenSSL. Pint pada lock memerlukan PHP 8.3.

```powershell
composer install
npm.cmd ci
# Hanya pada instalasi yang belum mempunyai .env:
Copy-Item .env.example .env
php artisan key:generate
```

Isi koneksi TiDB Cloud pada `.env` lokal. Setiap anggota memakai endpoint database
online yang sama. Salin nilai rahasia melalui kanal privat dan simpan pada `.env`.

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
MYSQL_ATTR_SSL_CA="D:/path/TerangDesa/resources/certs/isrg-root-x1.pem"
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Pada Windows, isi path absolut sertifikat sesuai lokasi clone, dengan slash `/`
dan kutip bila mengandung spasi. Pada hosting Linux, gunakan path absolut sertifikat
dalam deployment atau CA bundle sistem yang sesuai. Sertifikat publik ISRG Root X1
sudah tersedia dalam repository. Detail verifikasi ada di [docs/tidb.md](docs/tidb.md).

```powershell
php artisan config:clear
php artisan terangdesa:tidb-check
npm.cmd run build
php artisan serve --host=127.0.0.1 --port=8000
```

Buka <http://127.0.0.1:8000>. Gunakan `npm.cmd run dev` untuk pengembangan frontend.
Database online sudah memiliki skema dan data aplikasi. Migrasi bawaan kerangka
Laravel bukan pembentuk skema domain ini. **Hindari migrate, migrate:fresh, dan seed
pada database bersama.** Setelah mengubah environment, restart server yang masih
memegang konfigurasi lama. Sesi/cache file dan queue sync mengikuti skema proyek.

## Sumber data dan halaman

Login, identitas pengguna, pengelolaan wilayah, dashboard, dan peta memakai koneksi
default Laravel `mysql` yang diarahkan ke TiDB. Model tidak mengunci koneksi lokal.
Tidak ada pembacaan CSV atau JSON indikator pada runtime dan tidak ada fallback data
lokal ketika query gagal. Data asli CSV tetap menjadi arsip di folder dataset pemilik.

- `/login` memakai autentikasi sesi Laravel, CSRF, regenerasi sesi, dan limiter login.
- `/pemerintah/wilayah` dibatasi role `pemerintah`. Daftar 15 baris per halaman,
  pencarian nama/alamat, filter kondisi, tambah/edit, serta hapus mengikuti data online.
- `/dashboard/data` menghitung agregasi langsung dari tabel wilayah. Kondisi NULL
  ditampilkan sebagai belum diketahui. Metrik koordinat menghitung isian tersimpan.
- `/peta-wilayah/data` membaca wilayah, jumlah KK, sumber, tanggal, dan koordinat
  dari TiDB. Halaman menampilkan area kabupaten/kota dan desa dengan warna dari data ini.
  Wilayah tanpa koordinat tetap tampil pada daftar dan detail.

Pengayaan koordinat desa pada 5 November 2026 mengisi 5.227 lokasi di TiDB melalui
pencocokan kode, kabupaten, kecamatan, dan desa terhadap referensi BIG. Sebanyak 84
wilayah yang tidak cocok tetap tanpa titik. Tampilan terbaru
menggunakan 27 area kabupaten/kota berwarna dan membuka poligon desa ketika diklik.
Warna desa merah untuk KK positif, hijau untuk nol, dan abu-abu untuk data kosong
atau tidak cocok. Semua angka tetap dibaca dari TiDB. Rincian dan audit ada di
[docs/map-data.md](docs/map-data.md).

Perubahan pengelolaan langsung terbaca pada permintaan dashboard dan peta berikutnya.
Angka wilayah tidak di-cache. Cache geografis hanya menyimpan batas administratif.
Header akun, logout, sidebar, serta menu seluler menggunakan layout bersama.
Beranda mempertahankan ilustrasi surya dan efek kursor interaktif dengan dukungan
keyboard, sentuhan, serta reduced motion.

Jika perlu membuat akun Pemerintah baru dengan akses terminal pengelola:

```powershell
php artisan terangdesa:pemerintah
```

## Pemetaan field online

Skema saat ini menyimpan `jumlah_kk_terdampak`, `kondisi_listrik`, alamat lengkap,
koordinat, `sumber_data`, dan `data_per_tanggal`. Jumlah keluarga pada peta mengikuti
jumlah KK tersimpan. Nilai > 0 masuk kategori merah, tepat 0 masuk biru, dan NULL masuk
data belum tersedia. Kondisi listrik manual ditampilkan sebagai catatan tersendiri.

Kabupaten/kecamatan dibaca dari alamat online ketika komponen terakhir eksplisit
berbentuk `..., KECAMATAN, KABUPATEN/KOTA ..., JAWA BARAT`. Format lain tetap
ditampilkan utuh dengan komponen administratif yang belum tersedia. Nilai filter
kabupaten memakai kode Kemendagri kabupaten tanpa titik, yang diturunkan dari kode desa.
Tahun memakai tanggal pengamatan jika tersedia, kemudian keterangan `periode`/`tahun`
pada sumber tersimpan. Periode tidak di-hardcode. Skema belum menyediakan jumlah
pengguna PLN, sehingga detail menampilkan data tersebut sebagai belum tersedia.

## Aset peta

Tile OpenStreetMap, `public/data/jawa-barat.geojson`, Leaflet, serta pemeriksaan
geometri batas tetap dipakai. Atribusi sumber tampil pada peta. Daftar titik desa
hasil poligon BIG dan cache indikator lama sudah dilepas.

Untuk memperbarui aset batas provinsi secara sengaja:

```powershell
php artisan map:geography
```

Command ini hanya memperbarui batas dari geoBoundaries. Laporan pemetaan serta
keterbatasan data ada di [docs/map-data.md](docs/map-data.md).

## Pemeriksaan

```powershell
php artisan test
npm.cmd run build
# Opt-in fixture transaksi/akun uji pada koneksi aplikasi:
$env:PBI01_MYSQL_TESTS='1'
php artisan test
npx.cmd playwright test
Remove-Item Env:PBI01_MYSQL_TESTS
```

Pengujian backend membuat fixture dalam transaksi dan rollback. Pengujian browser
membuat akun serta wilayah khusus, lalu membersihkan hanya fixture miliknya.
Pengujian tidak menjalankan migrasi atau impor ulang. Jalankan suite database secara
berurutan. Chrome terpasang digunakan Playwright dengan tile fixture pada suite normal.
Uji tile asli bersifat opt-in melalui `MAP_LIVE_TILES=1`. Screenshot dan trace berada
di `test-results/`, diabaikan Git. Pemeriksaan mobile menggunakan emulasi Chrome.

`php artisan terangdesa:tidb-check --write-test` memeriksa koneksi, database aktif,
TLS sesi, jumlah data, serta tulis/baca dalam transaksi yang di-rollback.

# TerangDesa

Website pendataan, pengajuan, dan monitoring kebutuhan PLTS untuk kelompok warga.

## Beranda / Dashboard

Buka `/` atau pilih **Beranda**. Halaman memuat hero energi surya interaktif,
tiga metrik database, grafik **Kondisi Data Wilayah**, penjelasan alur penggunaan,
dan bagian SDG 7 dengan badge tipografi buatan aplikasi (bukan logo resmi).
Efek kursor mencakup seluruh viewport Beranda: glow mengikuti pointer di hero,
ringkasan, grafik, alur, SDG, header, dan sidebar. Ilustrasi tanpa bingkai tetap
merespons pointer di luar gambar, sementara permukaan panel mendapat cahaya halus.
Teks, tombol, dan grafik tidak digeser. Efek aktif untuk pointer presisi yang
mendukung hover, serta mati pada perangkat sentuh dan reduced motion. Panel surya
mempunyai highlight/tooltip yang dapat digunakan melalui hover, keyboard, dan tap.

Endpoint baca `/dashboard/data` menjalankan satu agregasi pada tabel `wilayah`.
Definisi metrik:

- Total: jumlah seluruh wilayah referensi tersimpan.
- Koordinat valid: kedua koordinat tersimpan, lintang -90–90 dan bujur -180–180,
  dengan pasangan 0,0 dikecualikan. Ini pemeriksaan kelengkapan/rentang koordinat,
  bukan bukti pemeriksaan lapangan dan bukan jumlah titik referensi poligon peta.
- Kondisi diisi: nilai yang dikenali pada domain aplikasi (`belum_berlistrik`,
  `akses_terbatas`, `sudah_berlistrik`). NULL, kosong, status `belum_diverifikasi`,
  dan nilai lain yang belum dikenali tidak dihitung sebagai kondisi yang lengkap.
  Isian tidak disebut sebagai data terverifikasi; skema belum mencatat bukti verifikasi.

Grafik memakai kategori aktual dari database. NULL/teks kosong digabung menjadi
**Belum diketahui**; status pending dan nilai lama yang berbeda tetap ditampilkan
sebagai kategori tersendiri dengan penjelasannya. Tidak ada persentase elektrifikasi,
data PLTS terpasang, atau jumlah keluarga terlayani yang diturunkan dari grafik ini.
Ketika semua kondisi NULL, hanya satu kategori ditampilkan. Sorotan legenda mengubah
pilihan visual dan angka di tengah grafik, sedangkan tiga metrik tetap merangkum
seluruh wilayah; tombol reset menghapus sorotan. Tabel teks selalu menyediakan angka
dan arti kategori. Grafik dibuat dengan SVG ringan tanpa pustaka grafik tambahan.

Loading menggunakan tanda “—”; kegagalan tidak diubah menjadi nol. Keadaan database
kosong menampilkan hitungan nol yang sah dan pesan belum ada data. Endpoint tidak
meng-cache angka database, tidak menulis data, dan memakai rate limiter tersendiri.
Header akun, logout, navigasi peta, serta middleware Pemerintah tetap dipertahankan.
Alur pengajuan/pemantauan pada Beranda bersifat penjelasan layanan, tanpa menambahkan
endpoint atau tombol fitur baru yang belum tersedia.

Implementasi: `DashboardController`, `DashboardSummary`, `welcome.blade.php`,
`dashboard.js`, dan `dashboard.css`, dengan perubahan kecil pada routing, provider,
entry Vite dan layout. Pengujian dashboard ada di `DashboardTest`,
`DashboardMySqlTest`, serta `tests/Browser/dashboard.spec.js`. Uji MySQL memakai
fixture transaksi yang selalu di-rollback; keadaan grafik campuran/kosong/gagal di
browser memakai fixture respons khusus pengujian tanpa mengubah database pengguna.

Jalankan pemeriksaan memakai flag MySQL pada bagian pengujian PBI-01. Browser menguji
pergerakan lapisan dari area di luar ilustrasi, batas perpindahan, glow seluruh
viewport, posisi teks/tombol, reset saat pointer keluar jendela,
tooltip hover/fokus/tap, interaksi grafik, anchor alur, reduced motion,
drawer dan lebar 390/320px. Screenshot pemeriksaan tersimpan di `test-results`.

Pemeriksaan Beranda pada 2 November 2026: **33 pengujian PHP lulus (320 assertions)**,
termasuk fixture MySQL yang membuktikan perubahan pengelolaan langsung tercermin
pada dashboard. Lima uji browser khusus dashboard membuktikan interaksi, bukan hanya
screenshot. Pengujian regresi juga mencakup identitas akun dan logout dari Beranda.
Sebanyak **18 skenario browser** lulus setelah pengujian ulang helper reset peta yang
menunggu batas/animasi dan hanya membandingkan lapisan tile aktif; satu uji penyedia
tile eksternal dilewati sesuai opt-in. Build Vite, sintaks 46 file PHP, dan
`git diff --check` lulus. Tidak ada fixture wilayah/akun yang tersisa.
Pengujian seluler memakai emulasi Chrome; belum diuji pada perangkat fisik.

## PBI-01 — Pengelolaan Data Wilayah (KF-17)

Halaman **Data wilayah** tersedia di `/pemerintah/wilayah`. Masuk melalui `/login`
dengan akun ber-role **`pemerintah`**. Role `masyarakat`, `admin`, dan pengunjung tanpa
sesi tidak boleh memanggil endpoint pengelolaan. Login memakai guard sesi Laravel,
regenerasi sesi, CSRF, serta pembatasan 5 percobaan per menit. Tidak ada registrasi
publik maupun akun Pemerintah bawaan.

Database lokal yang diperiksa adalah **`terangdesa`**, berisi 5.311 data referensi
wilayah. Konfigurasi lokal `.env` telah diarahkan ke database tersebut dengan
`DB_COLLATION=utf8mb4_unicode_ci` agar sesuai MySQL/MariaDB XAMPP. Tidak ada perubahan
skema, impor CSV, migrasi, seeder, maupun pengosongan tabel. Gunakan database yang
sudah memiliki tabel `users`, `wilayah`, `pengajuan_plts`, dan `progres_elektrifikasi`.
Migrasi kerangka Laravel pada repository bukan pembentuk skema domain yang sudah ada;
**jangan menjalankan migrate:fresh atau migrate untuk menyiapkan dataset ini**.

Tabel akun awal kosong. Pengelola yang memiliki akses terminal dapat membuat akun
Pemerintah melalui perintah berikut; kata sandi diminta secara tersembunyi dan
disimpan sebagai hash. Perintah ini menambahkan akun baru, bukan mengganti akun lain.

```powershell
php artisan terangdesa:pemerintah
php artisan serve --host=127.0.0.1 --port=8000
```

Buka <http://127.0.0.1:8000/login>, lalu pilih **Data wilayah**. Jika akun Pemerintah
sudah tersedia, langsung gunakan akun tersebut. Akun uji browser dibersihkan setelah
pengujian dan tidak dapat dipakai sebagai akun operasional. Gunakan `APP_DEBUG=false`
ketika aplikasi digunakan di luar lingkungan pengembangan.

Fitur yang tersedia:

- Daftar dari MySQL dengan pagination backend 15 baris, total hasil query aktual,
  pencarian nama/alamat lengkap dengan debounce 300 ms, serta filter kondisi termasuk NULL.
- Tambah/edit persisten, penanda baris terpilih, konfirmasi isian belum disimpan,
  status proses, pencegahan submit ganda, dan isian dipertahankan saat request gagal.
- Validasi frontend dan backend: kode unik, koordinat berpasangan dan dalam rentang
  dengan maksimal 7 desimal, masukan desimal koma, jumlah bulat nonnegatif sesuai
  batas `INT UNSIGNED`, serta tanggal pengamatan lengkap yang opsional.
- Nilai kosong tetap NULL: jumlah ditampilkan “Belum tersedia”, kondisi ditampilkan
  “Belum diverifikasi”. Domain kondisi yang disepakati tersimpan di `config/wilayah.php`:
  `belum_berlistrik`, `akses_terbatas`, dan `sudah_berlistrik`.
- `created_by` berasal dari sesi saat tambah, tetap dipertahankan saat edit. Request
  hanya boleh mengisi field formulir yang divalidasi; lokasi/kebutuhan pengajuan
  tidak diperbarui oleh fitur ini.
- Hapus dengan konfirmasi nama dan pemeriksaan referensi pengajuan/progres di backend,
  transaksi dan row lock, serta penanganan FK RESTRICT tanpa cascade.
- Sidebar navy, aksen kuning, panel putih, dua kolom pada desktop, drawer keyboard
  di seluler, dan scroll tabel di dalam panel. Halaman ini tanpa efek dekoratif.

Implementasi utama: `WilayahController`, `SaveWilayahRequest`, model `Wilayah`, middleware
`RequirePemerintah`, `LoginController`, `routes/web.php`, `config/wilayah.php`,
`resources/views/wilayah/manage.blade.php`, `region-manage.js`, `region-manage.css`, dan
layout bersama. Perintah pembuatan akun ada di `app/Console/Commands/CreatePemerintah.php`.

Perbedaan dari mockup: tidak ada gambar mockup dalam lampiran yang diterima, sehingga
tata letak mengacu pada spesifikasi tertulis dan font/SVG inline proyek. Kabupaten dan
kecamatan tidak mempunyai kolom/relasi terstruktur; alamat lengkap ditampilkan dan
dapat dicari tanpa menebak pemisahan administratif. Jarak jaringan dan catatan wilayah
tidak ditampilkan karena tidak ada kolom penyimpanan. Tahun 2024 tidak dijadikan tanggal
pengamatan. Kondisi listrik terpisah dari progres pemasangan, dan data referensi
tidak dilabeli “Data contoh”. Tidak ada perubahan fitur marker/detail/pengajuan.

### Pengujian PBI-01

Pengujian MySQL bersifat opt-in karena menggunakan koneksi lokal. Backend membuat
fixture baru di dalam transaksi dan selalu rollback; tidak memakai RefreshDatabase.
Browser membuat akun/wilayah fixture sendiri melalui skrip CLI di `tests/Support`,
lalu menghapus hanya fixture miliknya dalam `afterAll`. Tidak ada endpoint login uji
atau jalan pintas role. Jika proses browser dihentikan paksa, jalankan cleanup fixture
dengan ID dan email fixture sesuai skrip sebelum mengulang pengujian.

```powershell
$env:PBI01_MYSQL_TESTS='1'
php artisan test
npm.cmd run build
npm.cmd run test:e2e
Remove-Item Env:PBI01_MYSQL_TESTS
```

Tanpa flag, pengujian MySQL dilewati secara eksplisit. Pengujian mencakup baca ulang
setelah tambah/edit, NULL, pemilik awal, field whitelist, koordinat koma, duplikasi,
input invalid, pencarian/filter/pagination, hapus terikat/tidak terikat, FK asli,
keutuhan lokasi/kebutuhan pengajuan, login/logout, CSRF, serta role. Browser memeriksa
alur formulir, kegagalan request, penyimpanan setelah reload, drawer, dan lebar 390/320 px.
Screenshot hasil ada di `test-results/wilayah-desktop.png` dan `wilayah-mobile.png`.
Hasil pemeriksaan 2 November 2026: **15 pengujian PHP lulus (123 assertions)**,
**8 pengujian browser lulus**, 1 uji penyedia tile eksternal dilewati sesuai opt-in,
build Vite berhasil, dan sintaks 33 file PHP valid. Sesudah cleanup, tabel `wilayah`
kembali berisi 5.311 baris dengan kondisi NULL, tabel akun kembali kosong, dan tidak
ada wilayah fixture browser tersisa.
Uji peta memakai lapisan tile aktif (z-index tertinggi), karena Leaflet dapat menyisakan
container zoom lama pada DOM sesudah reset; kode fitur peta tidak diubah.

Pint bawaan memerlukan PHP 8.3, sedangkan PHP lokal adalah 8.2.12; jalankan lint Pint
pada lingkungan PHP 8.3+. Pemeriksaan sintaks PHP tetap dapat dijalankan pada PHP lokal.
Pemeriksaan seluler memakai emulasi Chrome, bukan perangkat fisik; kecocokan gambar
mockup dan tile peta eksternal tidak diverifikasi pada PBI-01.

## PBI-02 — Peta Wilayah (KF-01)

Buka **Beranda → Peta Wilayah** atau `/peta-wilayah` (route `wilayah.map`). Peta Leaflet kini terhubung ke tabel `wilayah` dan CSV 2024 yang sudah diproses di backend. Batas administratif Jawa Barat diberi outline, area di luarnya ditutupi mask, dan navigasi dibatasi di sekitar provinsi. Tersedia drag, zoom, keyboard, dan tombol **Kembali ke Jawa Barat**.

Tampilan memakai sidebar navy, aksen kuning, header putih, dan panel terang. Pada seluler sidebar menjadi drawer dengan dukungan keyboard. Peta tidak memakai parallax atau efek cahaya dekoratif. Pencarian, filter lapisan data, kabupaten/kota, ketersediaan lokasi, legenda, serta panel informasi tersedia pada halaman yang sama.

Endpoint baca publik `/peta-wilayah/data` mempertahankan akses publik peta sebelumnya; seluruh endpoint pengelolaan tetap dibatasi role Pemerintah. Rate limiter data peta dan login memakai nama terpisah agar aktivitas menjelajah tidak menghabiskan kuota login. Halaman dasar dapat dirender tanpa DB; pemuatan data memiliki status/error/retry sendiri. Permintaan koordinat viewport dan zoom menghasilkan kelompok penanda di backend, sehingga browser tidak menerima seluruh 5.311 baris. Daftar wilayah dipaginasi 15 baris.

Hasil integrasi 2 November 2026: **5.311/5.311 kode cocok**, tanpa duplikat atau nilai berbeda ketika dibandingkan dengan dua CSV asli periode 2024. Ada **1.197 angka positif** dan **4.114 nol** pada kolom berlabel belum listrik. Semua masih belum diverifikasi. **5.304 wilayah** mempunyai lokasi referensi yang sah; **7** masuk daftar lokasi belum tersedia. Tidak ada koordinat acak, geocoding massal, perubahan skema, impor ulang, atau penulisan nilai CSV ke tabel wilayah. Titik desa bukan titik rumah, kelompok KK, maupun calon pemasangan PLTS. Lihat [laporan sumber, metode, dan keterbatasan](docs/map-data.md).

Siapkan cache CSV pada instalasi baru atau setelah CSV diperbarui:

```powershell
php artisan map:prepare --dry-run
php artisan map:prepare
```

Lokasi CSV bawaan: `../Dataset/Dataset_Wilayah_2024_Bersih.csv`, relatif terhadap root proyek. Atur `MAP_DATASET_CSV` di `.env` untuk lokasi lain. Dry-run menampilkan jumlah cocok, tidak cocok, duplikat, serta perubahan database yang diusulkan (selalu nol pada implementasi ini). Cache berada di `storage/app/map/dataset-2024.json`, tidak dibaca browser dan tidak masuk Git. Isian Pemerintah selalu dibaca langsung dari MySQL; cache hanya menyimpan sumber CSV/geografi.

Artefak geografis hasil pemrosesan sudah disertakan di `resources/data/map/jawa-barat-villages.json` dan `public/data/jawa-barat.geojson`. Untuk memperbaruinya secara sengaja dengan jaringan tersedia:

```powershell
php artisan map:geography --refresh
```

Perintah mengambil metadata geoBoundaries dan poligon desa BIG dalam batch terbatas, membuat titik di dalam poligon yang kodenya cocok, serta memeriksa batas provinsi. Snapshot batch tersimpan di `storage/app/map/sources` agar proses dapat dilanjutkan dengan `map:geography` tanpa `--refresh`. Perintah ini juga tidak menulis database.

## Menjalankan lokal

Prasyarat: PHP 8.3+, Composer, Node.js, npm. Framework tetap Laravel 12.69 dan Vite sesuai proyek.

```powershell
composer install
# Hanya jika .env belum ada:
Copy-Item .env.example .env
php artisan key:generate
npm.cmd ci
npm.cmd run build
```

Gunakan konfigurasi MySQL lokal yang sudah tersedia di `.env`. Driver sesi/cache file sesuai skema lokal yang tidak memiliki tabel sessions/cache Laravel:

```dotenv
APP_NAME=TerangDesa
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=terangdesa
DB_USERNAME=root
DB_PASSWORD=
DB_COLLATION=utf8mb4_unicode_ci
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Sesuaikan kredensial dengan instalasi sendiri. Jangan menimpa `.env` yang sudah dikonfigurasi. PBI ini tidak memerlukan `php artisan migrate` atau seeder.

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Buka <http://127.0.0.1:8000/peta-wilayah>. Setelah perubahan frontend, jalankan kembali `npm.cmd run build`, atau gunakan `npm.cmd run dev` selama pengembangan. Pada PowerShell, `npm.cmd` menghindari kendala execution policy pada `npm.ps1`.

Lock Composer bawaan mengunci Pint v1.32.1 yang memerlukan PHP 8.3. Mesin pemeriksaan memakai PHP 8.2.12; dependensi dipasang secara lokal dengan `composer install --ignore-platform-req=php`, tanpa mengubah `composer.lock`. Aplikasi dan PHPUnit berhasil dijalankan, tetapi Pint tidak dijalankan. Gunakan PHP 8.3+ untuk pemasangan normal.

## Penyedia peta

Tile bawaan: `https://tile.openstreetmap.org/{z}/{x}/{y}.png`, tanpa API key. Atribusi OpenStreetMap dan Leaflet selalu terlihat di sudut peta; keterangan dataset berada terpisah di bawah peta.

`MAP_TILE_URL` dan `MAP_ATTRIBUTION` dapat dikonfigurasi melalui `.env` dan `config/map.php`. Atribusi berisi HTML tepercaya yang hanya boleh diatur pengelola. Jangan masukkan kredensial database atau kunci rahasia ke konfigurasi yang dikirim ke browser. Jika mengganti penyedia, sesuaikan pula batas zoom dan ketentuan penyedia.

Implementasi mempertahankan cache dan Referer browser, tanpa prefetch/offline download. Ikuti [kebijakan tile OSM](https://operations.osmfoundation.org/policies/tiles/); layanan bersifat best effort dan bukan layanan dengan jaminan kapasitas produksi. Dokumentasi library: [Leaflet 1.9.4](https://leafletjs.com/reference.html).

Pemuatan tile memiliki status memuat, berhasil, dan gagal. Kegagalan sebagian tile maupun timeout 15 detik menampilkan penjelasan serta tombol **Coba lagi**. Retry memuat ulang layer tanpa mengubah viewport dan tanpa menghapus cache browser. Kegagalan modul JavaScript menawarkan pemuatan ulang halaman. Jika JavaScript dinonaktifkan, halaman menampilkan instruksi mengaktifkannya.

## Pemeriksaan

```powershell
php artisan test
npm.cmd run build
npm.cmd run test:e2e
```

Playwright menggunakan **Google Chrome yang sudah terpasang**, dan menjalankan server Laravel lokal bila belum tersedia. Pengujian normal memakai tile fixture khusus pengujian agar tidak membebani server OSM. Tidak ada fixture/marker yang masuk ke aplikasi.

Cakupan pengujian browser: batas/mask Jawa Barat, pengelompokan penanda, zoom/reset, pencarian/filter/pagination, detail angka CSV terpisah dari isian Pemerintah, reload, lokasi belum tersedia, hasil kosong, kegagalan data/batas/tile dan retry, keyboard/drawer, reduced motion, serta lebar 390/320px tanpa overflow. Uji data MySQL/CSV dan regresi pengelolaan memerlukan `PBI01_MYSQL_TESTS=1` seperti perintah pengujian PBI-01. Fixture baru diisolasi dan dibersihkan; data referensi tidak dimutasi untuk pengujian.

Untuk memeriksa tile asli, jalankan pengujian opsional berikut dengan jaringan tersedia:

```powershell
$env:MAP_LIVE_TILES='1'
npm.cmd run test:e2e
Remove-Item Env:MAP_LIVE_TILES
```

Screenshot uji penyedia asli disimpan di `test-results/peta-desktop-live.png` dan `test-results/peta-mobile-live.png` (diabaikan Git). Pengujian Laravel memastikan halaman dasar tidak meminta koneksi DB, respons data aman, pencocokan kode/angka/NULL benar, titik representatif berada di poligon, dan perubahan Pemerintah langsung tercermin tanpa ditimpa CSV.

## Catatan integrasi PBI-02 sebelumnya

Pada pemeriksaan PBI-02 tanggal 1 November 2026, PBI-01 dan autentikasi belum tersedia. PBI-01 kini memiliki login dan middleware Pemerintah sendiri. Route peta tetap mengikuti akses publik sebelumnya; pembatasan pengelolaan diterapkan pada seluruh route `/pemerintah/wilayah`. Navigasi keduanya sudah terhubung melalui layout bersama.

Desain awal mengikuti arahan tertulis, lalu diperbarui berdasarkan gambar dashboard yang diberikan pengguna. Integrasi lanjutan menggunakan data MySQL dan CSV aktual tanpa mengubah data referensi. Pemeriksaan seluler memakai emulasi Chrome, bukan perangkat fisik.

Audit frontend menemukan dua temuan pada toolchain Vite 5/esbuild bawaan proyek (1 high, 1 moderate); pembaruan mayor toolchain tidak termasuk PBI-02. Pemeriksaan tampilan memakai hasil build produksi melalui server Laravel.

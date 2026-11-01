# TerangDesa

Website pendataan, pengajuan, dan monitoring kebutuhan PLTS untuk kelompok warga.

## PBI-02 — Peta Wilayah (KF-01)

Buka **Beranda → Peta Wilayah** atau `/peta-wilayah` (route `wilayah.map`). Peta Leaflet menampilkan cakupan Jawa Barat saat pertama dibuka, mendukung drag, zoom, keyboard, dan tombol **Kembali ke Jawa Barat**.

Tampilan mengikuti referensi dashboard terbaru: sidebar biru tua, aksen kuning untuk navigasi aktif, header putih dengan breadcrumb, latar abu-abu terang, dan panel peta putih. Navigasi hanya menampilkan halaman yang tersedia; nama pengguna diambil dari autentikasi jika tersedia, tanpa identitas atau statistik rekaan. Pada seluler, sidebar menjadi navigasi ringkas di atas konten.

Parallax dan cahaya pengikut kursor dipertahankan dengan intensitas lembut pada latar terang, tanpa memengaruhi tile peta. Perangkat sentuh memakai latar statis; `prefers-reduced-motion` mematikan efek gerakan dekoratif dan animasi zoom.

Peta dasar tidak mengakses tabel `wilayah` atau memerlukan koordinat desa. Tidak ada marker dummy, geocoding, perubahan skema, migrasi, atau impor data. Penanda kelompok warga merupakan PBI-03; detail wilayah merupakan PBI-04. Referensi administratif tahun 2024 tidak membuktikan status elektrifikasi desa.

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

Gunakan konfigurasi MySQL lokal yang sudah tersedia di `.env`. Untuk memeriksa peta tanpa mengakses database maupun memerlukan tabel sessions/cache, gunakan driver file:

```dotenv
APP_NAME=TerangDesa
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=terangdesa
DB_USERNAME=root
DB_PASSWORD=
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

Cakupan pengujian browser: navigasi dan viewport awal Jawa Barat, drag, zoom, reset ke posisi semula, navigasi kembali, loading/error/retry, gerakan kursor dan parallax tanpa menggeser konten, reduced motion, fokus dan kontrol keyboard, lebar 390/320px tanpa overflow, serta gestur drag sentuh dan tombol sentuh.

Untuk memeriksa tile asli, jalankan pengujian opsional berikut dengan jaringan tersedia:

```powershell
$env:MAP_LIVE_TILES='1'
npm.cmd run test:e2e
Remove-Item Env:MAP_LIVE_TILES
```

Screenshot peta asli desktop dan seluler disimpan di `test-results/peta-desktop-live.png` dan `test-results/peta-mobile-live.png` (diabaikan Git). Pengujian Laravel memastikan halaman tidak meminta koneksi DB dan tidak merender kredensial MySQL.

## Status integrasi dan batas pemeriksaan

Pada pemeriksaan 1 November 2026, branch remote `Fajri` dan `main` sama-sama menunjuk `f073517` dan hanya memuat kerangka Laravel. PBI-01, route login, middleware role, dan dokumen KF-01 terpisah belum tersedia. Implementasi mengacu pada rincian KF-01 dalam permintaan PBI-02. Route peta mengikuti akses publik route web yang tersedia; autentikasi PBI-01 belum dapat diuji. Setelah PBI-01 tersedia, tempatkan route `wilayah.map` dalam grup middleware yang sama dan integrasikan tautan ke layout aplikasi tersebut.

Desain awal mengikuti arahan tertulis, lalu diperbarui berdasarkan gambar dashboard yang diberikan pengguna. Pemeriksaan seluler memakai emulasi Chrome, bukan perangkat fisik. Data MySQL sumber tidak dibaca atau diubah.

Audit frontend menemukan dua temuan pada toolchain Vite 5/esbuild bawaan proyek (1 high, 1 moderate); pembaruan mayor toolchain tidak termasuk PBI-02. Pemeriksaan tampilan memakai hasil build produksi melalui server Laravel.

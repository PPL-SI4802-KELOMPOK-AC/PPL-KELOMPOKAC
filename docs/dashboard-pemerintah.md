# PBI-19 / KF-11: Dashboard Pemerintah

Halaman `/pemerintah/dashboard` dan endpoint JSON `/pemerintah/dashboard/data`
menggunakan middleware Pemerintah dan pemeriksaan akun aktif aplikasi. Masyarakat
dan Admin ditolak, pengunjung diarahkan ke login (JSON: 401). Login Pemerintah
sekarang menuju Dashboard, dengan sidebar yang sudah dipakai ruang Pemerintah.
Beranda publik, peta publik, pengajuan Masyarakat dan ruang Admin tetap terpisah.

## Sumber dan definisi indikator

Semua query memakai koneksi default Laravel yang sudah mengarah ke TiDB. Tidak ada
perubahan `.env`, skema online, migrasi, seed atau penulisan data operasional.
`GovernmentDashboard` melakukan agregasi status di database dan membaca atribut
wilayah yang diperlukan memakai cursor. Tidak ada cache indikator atau data
pengajuan/pengguna yang dikirim ke browser. Cache yang dipakai hanya geometri
provinsi dan validasi koordinat. Pembaruan data langsung dibaca pada request berikut.

| Indikator | Sumber dan perhitungan |
| --- | --- |
| Total pengajuan | COUNT pengajuan selain `status_terkini = draft`. Enam status layanan dipertahankan, termasuk nol. Status tidak dikenal menyebabkan pesan ketidaksesuaian, bukan total yang diam-diam berbeda. |
| Grafik status | GROUP BY `status_terkini`; jumlah enam kategori sama dengan total. Status yang ditampilkan adalah status terkini. |
| Tanggal pengiriman | MIN `riwayat_status_pengajuan.created_at` untuk `jenis_tindakan = pengajuan_baru` dan `status_baru = diajukan`. `status_diperbarui_pada` tidak digunakan sebagai tanggal pengiriman. |
| Wilayah terpetakan | Jumlah ID wilayah unik dengan koordinat numerik, dalam rentang global dan di dalam batas Jawa Barat. Kartu dan penanda berasal dari hasil validasi yang sama. |
| KK sasaran | SUM `wilayah.jumlah_kk_terdampak`, satu kali per wilayah dalam cakupan. Jika ada NULL, sasaran lengkap ditandai belum tersedia; subtotal yang diketahui tetap ada pada API. |
| KK terlayani / persentase | Belum tersedia. Skema aktual tidak memiliki realisasi pelayanan KK. Tidak dihitung dari KK terdampak, status selesai atau angka di teks bebas. Persentase ditampilkan sebagai tanda pisah. |
| PLTS selesai / wilayah terpasang | Belum tersedia. Tidak ada identitas instalasi dan kontrak penanda penyelesaian pemasangan. Status administratif `selesai` saja tidak dijadikan bukti PLTS. |
| Wilayah membutuhkan listrik | Wilayah dengan KK terdampak positif atau kondisi manual belum berlistrik/akses terbatas/ada KK belum berlistrik. |
| Antrean pemeriksaan | Jumlah pengajuan berstatus `diajukan`, karena proyek belum memiliki definisi antrean lain. |

`progres_elektrifikasi` yang diperiksa pada TiDB berisi kolom id, wilayah_id,
pengajuan_id, dicatat_oleh, tahap_progres, catatan_progres, tanggal_progres. Saat
pemeriksaan belum ada baris. Domain `tahap_progres` belum ditetapkan dan tidak ada
kolom jumlah KK realisasi atau identitas instalasi. `penginputan_plts` hanya
mencatat kebutuhan, bukan realisasi. Untuk mengaktifkan indikator pelayanan,
dibutuhkan kontrak sumber realisasi (tambahan atau kumulatif), kunci penerima/
wilayah, identitas instalasi unik, tanggal realisasi dan bukti selesai. PBI ini
tidak menambahkan skema atau mengarang kontrak tersebut.

## Periode

Pilihan default Semua periode membaca seluruh pengajuan terkirim serta master
wilayah Jawa Barat saat ini. Pilihan tahun membatasi pengajuan menurut pengiriman
awal, dan wilayah menjadi gabungan ID yang mempunyai pengajuan terkirim atau
catatan progres pada tahun tersebut. Penggabungan memakai EXISTS/IN, bukan join
baris progres, agar beberapa pengajuan/progres tidak menggandakan wilayah atau KK.
Progres ditentukan menurut `tanggal_progres`. Batas tahun awal inklusif dan awal
tahun berikutnya eksklusif. Timestamp mengikuti timezone penyimpanan aplikasi.

Status dan KK sasaran adalah nilai terkini dari cohort yang dipilih, bukan
rekonstruksi snapshot historis. Cakupan ini terlihat pada halaman. Pengajuan tanpa
riwayat pengiriman awal masuk Semua periode, dikecualikan dari filter tahun, dan
keterbatasannya disebutkan. Tahun yang tidak mempunyai data menghasilkan angka
nol dan empty state, bukan kegagalan. NULL realisasi tetap dibedakan dari nol.

## Peta dan tampilan

Memakai Leaflet yang sudah terpasang, konfigurasi tile/atribusi proyek, batas
`jawa-barat.geojson`, serta service geometri yang sama dengan peta wilayah.
Canvas menampilkan penanda lokasi referensi, popup dan pilihan wilayah melalui
daftar yang dapat dipakai dengan keyboard. Oranye menunjukkan kebutuhan listrik,
merah informasi layanan yang memerlukan pendataan. Legenda hijau tetap tersedia,
tetapi tidak menghasilkan penanda terpasang tanpa bukti pemasangan. Nol KK bukan
bukti pemasangan PLTS. Wilayah tanpa lokasi valid tetap masuk rekap pendataan.

Grafik menyediakan hover, fokus keyboard dan tabel rekap. Galat data dan galat
tile/batas mempunyai pesan serta tombol coba lagi yang terpisah. Filter membuang
indikator dan penanda lama selama loading agar data periode lama tidak terlihat
sebagai data periode baru. Layout memakai kartu navy/kuning dan panel putih,
serta drawer Pemerintah pada seluler.

## Halaman tindak lanjut yang belum tersedia

Checkout ini belum mempunyai route/controller halaman laporan Pemerintah maupun
antrean pemeriksaan. Tombol ditampilkan nonaktif dengan alasan yang terbaca.
View menyiapkan integrasi named route `pemerintah.laporan.index` dan
`pemerintah.pemeriksaan.index` serta parameter `year`. Ketika PBI halaman tujuan
diimplementasikan, route harus dilindungi akses Pemerintah dan mendukung periode
yang sama. KP-19.9 belum dapat diuji sebagai navigasi ke halaman operasional.
Tidak ada halaman palsu atau tautan 404 yang ditambahkan.

## Pengujian

```powershell
php artisan test --filter="GovernmentDashboardTest|PublicNavigationTest|DashboardTest"
php artisan dusk --filter=PBI19Test
```

Feature test membuat skema hanya pada SQLite `:memory:`. Dusk menyiapkan server,
Chrome terlihat, fixture dan SQLite sementara dari dalam
`tests/Browser/PBI19/PBI19Test.php`. Tes menolak konfigurasi fixture di luar SQLite
milik tes. Tidak menggunakan migrate:fresh atau seeding pada TiDB. Tile sintetis
lokal dipakai untuk menguji keberhasilan, gangguan dan pemulihan secara berulang.
Jeda screenshot default 2 detik dapat diatur dengan `DUSK_STEP_PAUSE`.

Skenario backend mencakup role/akun aktif, enam status dan draft, batas periode,
riwayat pengiriman ganda, duplikasi progres, koordinat rusak, pembaruan data tanpa
cache usang, nilai nol/NULL/kosong, status tidak dikenal dan redaksi kegagalan.
Catatan progres kumulatif pada teks bebas tidak dijumlahkan sebagai realisasi.
Pengujian kumulatif numerik yang sesungguhnya menunggu kontrak sumber realisasi.

## Pemeriksaan baca TiDB pada 25 November 2026

PDO aplikasi berhasil memakai database `terangdesa`, TiDB v8.5.3-serverless,
TLSv1.3 / TLS_AES_128_GCM_SHA256 dengan verifikasi sertifikat server aktif.
Hasil baca saat pemeriksaan: 22 pengajuan terkirim (semuanya Diajukan), 4 draft
dikecualikan, 5.311 wilayah dalam cakupan, 5.227 penanda, 84 lokasi belum valid,
104.518 KK sasaran, 1.197 wilayah mencatat kebutuhan listrik, dan antrean 22.
Total grafik dan jumlah penanda cocok dengan kartu. Nilai ini adalah hasil baca
saat pemeriksaan, bukan angka yang ditanam dalam implementasi.

Browser dengan koneksi TiDB aktual juga berhasil memuat dashboard, tile peta,
beranda publik dan redirect login. Tampilan seluler tidak meluber secara horizontal.
Pada filter tahun 2026 terdapat 22 pengajuan, tetapi belum ada relasi `wilayah_id`
yang terisi pada pengajuan tersebut dan belum ada progres. Karena itu wilayah
cohort tahun 2026 adalah nol, sementara Semua periode tetap menampilkan 5.227
wilayah referensi. Dashboard menyebutkan pengajuan yang belum terhubung ke master
wilayah. Tidak dilakukan pencocokan nama atau perkiraan relasi secara diam-diam.

Hasil verifikasi: 73 pengujian unit/feature lulus (549 assertions), dan 8 skenario
Dusk PBI-19 lulus. Bukti screenshot Dusk
tersimpan pada `tests/Browser/screenshots/PBI19-*.png` dan diabaikan Git mengikuti
aturan folder pengujian yang sudah ada.

## Berkas implementasi

- `app/Services/GovernmentDashboard.php`: query dan definisi indikator.
- `app/Http/Controllers/GovernmentDashboardController.php`: halaman/data dan validasi periode.
- `routes/web.php`: dua route dashboard yang dilindungi Pemerintah.
- `app/Http/Controllers/LoginController.php`: tujuan login Pemerintah dan intended URL dashboard.
- `resources/views/layouts/app.blade.php`: menu Dashboard aktif dan breadcrumb Pemerintah.
- `resources/views/pemerintah/dashboard.blade.php`: kartu, grafik, peta dan rekap.
- `resources/css/government-dashboard.css`, `resources/js/government-dashboard.js`: tampilan responsif dan interaksi.
- `resources/css/app.css`, `resources/js/app.js`: registrasi aset dashboard.
- `app/Services/Map/MapData.php`: akses ke pembaca batas provinsi agar dapat digunakan ulang.
- `bootstrap/app.php`: respons aman saat koneksi database gagal sebelum controller berjalan.
- `tests/Feature/GovernmentDashboardTest.php`, `tests/Feature/PublicNavigationTest.php`: perhitungan, akses dan regresi navigasi.
- `tests/Browser/PBI19/PBI19Test.php`: pengujian GUI terisolasi.
- `docs/dashboard-pemerintah.md`: definisi, keterbatasan dan hasil verifikasi.

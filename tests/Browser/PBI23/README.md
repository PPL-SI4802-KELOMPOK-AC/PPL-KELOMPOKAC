# GUI PBI-23, satu test case per file

Chrome ditampilkan dan setiap TC menyiapkan SQLite sementara sendiri. Data TiDB tidak diubah.
Jalankan salah satu perintah di bawah untuk menjalankan satu TC saja:

```powershell
php artisan dusk --filter=TC2301Test
php artisan dusk --filter=TC2302Test
php artisan dusk --filter=TC2303Test
php artisan dusk --filter=TC2304Test
php artisan dusk --filter=TC2305Test
php artisan dusk --filter=TC2306Test
php artisan dusk --filter=TC2307Test
php artisan dusk --filter=TC2308Test
php artisan dusk --filter=TC2309Test
php artisan dusk --filter=TC2310Test
php artisan dusk --filter=TC2311Test
php artisan dusk --filter=TC2312Test
php artisan dusk --filter=TC2313Test
php artisan dusk --filter=TC2314Test
php artisan dusk --filter=TC2315Test
php artisan dusk --filter=TC2316Test
php artisan dusk --filter=TC2317Test
```

Jeda bawaan: 3 detik setelah tindakan, pengetikan 80 ms per karakter, dan 5 detik pada hasil akhir.
Untuk memberi waktu penjelasan lebih panjang, gunakan terminal yang sama:

```powershell
$env:DUSK_STEP_PAUSE="5000"
$env:DUSK_TYPING_PAUSE="120"
$env:DUSK_RESULT_PAUSE="10000"
php artisan dusk --filter=TC2301Test
```

Hapus override untuk kembali ke jeda bawaan:

```powershell
Remove-Item Env:DUSK_STEP_PAUSE,Env:DUSK_TYPING_PAUSE,Env:DUSK_RESULT_PAUSE -ErrorAction SilentlyContinue
```

Hanya jika memang ingin menjalankan seluruh TC: `php artisan dusk tests/Browser/PBI23`.
Kode persiapan dan helper bersama berada di `PBI23TestCase.php` (kelas abstrak yang tidak dijalankan sebagai tes tersendiri).
Screenshot hasil ada di `tests/Browser/screenshots`.

## Skenario yang tersedia

| TC | Skenario |
| --- | --- |
| TC-23-01 | Buka Pengguna & peran, ringkasan, daftar dan kontrol pengelolaan |
| TC-23-02 | Tambah akun valid, hash password, dan audit |
| TC-23-03 | Cari nama/email, filter gabungan, reset, dan ringkasan global |
| TC-23-04 | Edit identitas, peran dan password; password lama ditolak; akses peran baru |
| TC-23-05 | Edit tanpa mengganti password dan login dengan password lama |
| TC-23-06 | Nonaktifkan akun dengan riwayat, sesi lama ditolak, login ulang ditolak |
| TC-23-07 | Aktifkan kembali akun dan login sesuai peran |
| TC-23-08 | Hapus akun yang memenuhi syarat — incomplete, fitur belum tersedia |
| TC-23-09 | Batal konfirmasi penghapusan — incomplete, fitur belum tersedia |
| TC-23-10 | Email duplikat/salah/kosong, nama kosong, password terlalu pendek/panjang, konfirmasi berbeda |
| TC-23-11 | Tolak hapus akun berelasi — incomplete, fitur belum tersedia |
| TC-23-12 | Tolak nonaktif/turun peran sendiri; DELETE sendiri masih incomplete |
| TC-23-13 | Tolak sesi Admin yang baru kehilangan kewenangan; SQL overlap dan DELETE masih incomplete |
| TC-23-14 | Referensi baru saat penghapusan — incomplete, fitur belum tersedia |
| TC-23-15 | Kelayakan hapus tidak diketahui — incomplete, fitur belum tersedia |
| TC-23-16 | Non-Admin ditolak pada GET/POST/PUT/nonaktif; DELETE masih incomplete |
| TC-23-17 | Audit gagal membatalkan tambah/ubah; pemulihan berhasil; bagian DELETE masih incomplete |

ID dan alur mengikuti tabel TC-23-01–17 dari deskripsi terbaru yang diberikan pengguna.
Bagian User Story, KP-19, dan Catatan dalam lampiran masih membahas dashboard PBI-19;
bagian tersebut tidak dijadikan dasar tes pengelolaan akun PBI-23.

Endpoint/tombol hapus akun belum tersedia. TC yang bergantung sepenuhnya padanya membuka
GUI daftar akun lalu ditandai **incomplete**, bukan lulus. TC gabungan menjalankan bagian
yang tersedia lebih dulu dan tetap incomplete sampai seluruh bagian dapat dibuktikan.
TC-23-13 memakai dua sesi dan formulir yang tetap terbuka saat kewenangan dicabut;
ini tidak membuktikan transaksi SQL yang benar-benar overlap. Server PHP lokal/SQLite
menserialkan request, sehingga uji konkurensi database sebenarnya tetap tertunda.
TC-23-17 memakai trigger SQLite pada database sementara untuk menggagalkan audit;
tidak menambahkan simulasi kegagalan pada kode aplikasi.
Tes dashboard yang sebelumnya keliru berada di PBI23 dikembalikan ke `tests/Browser/PBI19/PBI19Test.php`.
Fixture SQLite menggunakan ulang `Tests\Support\PBI24Fixture` yang sudah ada; tidak menambah skema atau data di TiDB.

## Status verifikasi

Pemeriksaan GUI pada 27 November 2026: **8 TC terverifikasi penuh** (01–07 dan 10),
serta **9 TC incomplete** (08, 09, 11–17). Bagian yang tersedia pada TC gabungan
tetap menjalankan assertion sebelum ditandai incomplete. Kegagalan audit pada
tambah/ubah terbukti membatalkan transaksi; setelah trigger uji dihapus, data dan
audit berhasil tersimpan. Tidak ada fitur aplikasi yang diubah untuk menutupi
bagian pengujian yang belum tersedia.

# Skenario Test PBI-01 — Mengelola data wilayah belum berlistrik (KF17)

Issue Jira: [SCRUM-6](https://pplkelac.atlassian.net/browse/SCRUM-6).

| ID | Skenario | Jenis | Hasil yang Diharapkan |
| --- | --- | --- | --- |
| **TC-17-01** | Tambah wilayah baru dengan nama desa, koordinat, dan KK sasaran yang valid | Normal | Data berhasil tersimpan ke database dan muncul pada tabel daftar wilayah |
| **TC-17-02** | Edit data wilayah yang ada (misal: ubah KK sasaran pada Mekarjaya) lalu klik Simpan | Normal | Perubahan berhasil tersimpan dan nilai terbaru langsung tampil pada tabel |
| **TC-17-03** | Hapus salah satu data wilayah dan menyetujui konfirmasi | Normal | Data dihapus dari database dan baris wilayah terhapus dari tampilan |
| **TC-17-04** | Simpan formulir dengan nama desa atau kecamatan dibiarkan kosong | Pengecualian | Sistem menolak penyimpanan dan menampilkan pesan bahwa kolom wajib diisi |
| **TC-17-05** | Masukkan format koordinat latitude/longitude di luar batas geografis valid | Pengecualian | Sistem menolak penyimpanan dan menampilkan peringatan nilai koordinat tidak valid |
| **TC-17-06** | Batalkan dialog konfirmasi hapus data | Pengecualian | Data wilayah tidak terhapus dan sistem menutup dialog konfirmasi |
| **TC-17-07** | Akses URL kelola data wilayah oleh akun dengan role User (Masyarakat) | Pengecualian | Akses ditolak dan sistem mengembalikan respons 403 Forbidden |

## Catatan pemetaan ke aplikasi

- Nama desa menggunakan `nama_wilayah`; KK sasaran menggunakan `jumlah_kk_terdampak` (label **Jumlah KK terdampak**).
- Formulir kelola wilayah saat ini tidak mempunyai kolom kecamatan tersendiri. Kecamatan merupakan bagian dari `alamat_deskriptif` (**Alamat lengkap**). TC-17-04 menguji nama dan alamat kosong; feature test juga menguji tingkat wilayah kosong. Mengosongkan hanya kata kecamatan di dalam alamat bukan aturan validasi terpisah pada aplikasi saat ini.
- Batas koordinat mengikuti validasi aplikasi: latitude −90 sampai 90, longitude −180 sampai 180. Ini batas koordinat geografis global, bukan pemeriksaan polygon Jawa Barat.
- Akun petugas ESDM memakai role internal `pemerintah`; role masyarakat adalah `masyarakat`.
- Pengujian otomatis backend berada di `tests/Feature/PBI01/KelolaWilayahTest.php`; pengujian GUI Laravel Dusk berada di `tests/Browser/PBI01/PBI01Test.php`. Nomor skenario dipertahankan pada nama metode dan screenshot.

## Menjalankan pengujian GUI (Chrome terlihat)

Dari folder proyek, jalankan di terminal:

```powershell
composer install
php artisan dusk:chrome-driver --detect
npm.cmd ci
npm.cmd run build
php tests/Support/PBI01/run-pbi01-dusk.php
```

Tutup Vite dev server sebelum menjalankan pengujian. Runner menolak `public/hot` agar GUI tidak bergantung pada dev server lain. Driver harus sesuai versi Google Chrome terpasang.

Perintah terakhir membuka Chrome, menjalankan tujuh skenario, menyimpan screenshot hasil masing-masing, lalu menutup browser setelah selesai. Runner memakai kelas Laravel Dusk melalui PHPUnit dengan `phpunit.pbi01-dusk.xml`; tidak menukar `.env` pengguna. Server uji dan port ChromeDriver disediakan otomatis sehingga tidak perlu `php artisan serve` terpisah. Jalankan tes pada desktop yang terbuka untuk melihat gerakan pengujiannya.

Untuk memberi waktu melihat tampilan atau merekam layar:

```powershell
$env:PBI01_DEMO_PAUSE = '3000'
php tests/Support/PBI01/run-pbi01-dusk.php
Remove-Item Env:PBI01_DEMO_PAUSE
```

Untuk CI tanpa jendela browser:

```powershell
php tests/Support/PBI01/run-pbi01-dusk.php --headless
```

Untuk backend saja:

```powershell
php artisan test --filter=KelolaWilayahTest
```

## Data dan pemeriksaan tambahan

Seluruh data adalah fixture sintetis. Feature test memakai SQLite dalam memori; Dusk memakai file SQLite acak di `storage/framework/testing/pbi01-dusk-*.sqlite` yang dapat diakses server dan proses test secara bersama. File dibersihkan oleh runner ketika selesai. Tidak menjalankan migrasi, truncate, atau hapus data terhadap MySQL/TiDB. `.env` tidak perlu diubah. Skema test terbatas pada tabel yang dibutuhkan skenario ini; pengujian ini tidak membuktikan konektivitas maupun kompatibilitas TiDB.

- TC-17-01 memeriksa `created_by` otomatis sama dengan ID petugas yang login. Feature test mengirim ID masyarakat palsu untuk memastikan input tersebut diabaikan.
- TC-17-02 memeriksa nilai 25 berubah menjadi 42 dan bertahan setelah muat ulang. Snapshot pengajuan mandiri, lokasi, dan riwayat status dibandingkan sebelum/sesudah pembaruan.
- TC-17-03 memakai desa uji tanpa relasi. Snapshot riwayat pengajuan juga diperiksa tetap utuh.
- TC-17-04/05 memeriksa pesan validasi GUI serta database tidak bertambah; feature test menguji penolakan backend HTTP 422 secara terpisah.
- TC-17-06 berinteraksi dengan dialog konfirmasi asli Chrome (`dismissDialog`) dan memastikan data tetap tampil setelah muat ulang. Feature test hanya memeriksa data tetap ada tanpa request DELETE; pembatalan dialog dibuktikan Dusk.
- TC-17-07 memeriksa halaman penolakan serta HTTP 403 sungguhan. Feature test juga memeriksa larangan endpoint daftar/detail/tambah/edit/hapus.
- Satu feature test tambahan memastikan wilayah yang terkait pengajuan atau progres tidak boleh dihapus (HTTP 409), agar riwayat tidak rusak.

## Bukti GUI

Setelah tes berhasil, screenshot PNG berada di `tests/Browser/screenshots/PBI01/`:

| ID | Screenshot hasil |
| --- | --- |
| TC-17-01 | `TC-17-01-tambah-berhasil.png` |
| TC-17-02 | `TC-17-02-edit-kk-berhasil.png` |
| TC-17-03 | `TC-17-03-hapus-berhasil.png` |
| TC-17-04 | `TC-17-04-validasi-wajib.png` |
| TC-17-05 | `TC-17-05-validasi-koordinat.png` |
| TC-17-06 | `TC-17-06-batal-hapus-data-utuh.png` |
| TC-17-07 | `TC-17-07-masyarakat-403.png` |

Screenshot TC-17-03/06 menunjukkan hasil setelah dialog ditutup. Dialog native benar-benar diperiksa oleh assertion Dusk; screenshot halaman tidak merekam dialog native Chrome. Hasil pass/fail tersimpan dalam `results.xml` (JUnit) di folder yang sama. Screenshot dan hasil run diabaikan Git mengikuti `.gitignore` folder Dusk, sehingga perlu dilampirkan terpisah saat pengumpulan.

Setiap gambar juga mempunyai versi `-full.png` yang merekam halaman utuh melalui Chrome DevTools dari sesi Dusk yang sama, agar tabel dan bagian bawah formulir terbaca tanpa terpotong tinggi layar.

Referensi: [Dokumentasi resmi Laravel Dusk](https://laravel.com/docs/12.x/dusk).

## Hasil verifikasi 16 November 2026

- Feature test: **8 lulus, 56 assertion**, termasuk satu regresi perlindungan relasi.
- Laravel Dusk dengan Chrome terlihat: **7 lulus, 72 assertion**, tanpa skenario yang dilewati.
- Lingkungan: PHP 8.2.12, Laravel Dusk 8.8.0, Google Chrome/ChromeDriver 157.0.8103.78.
- Bukti: 7 screenshot viewport, 7 screenshot halaman utuh, dan `results.xml`. Versi halaman utuh untuk edit KK dan validasi koordinat juga diperiksa secara visual.
- Hasil ini berasal dari database SQLite uji sintetis, bukan pengujian terhadap data TiDB online.

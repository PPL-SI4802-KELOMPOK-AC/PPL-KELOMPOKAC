# Test case PBI 5 SCRUM-10

Fitur: pencarian wilayah, saran hasil, dan pemilihan wilayah pada peta.
Implementasi Dusk: satu file untuk setiap test case di `tests/Browser/PBI05/`.
Helper bersama berada di `PBI05BrowserTestCase.php`; file ini bukan skenario tes.

| ID | File |
| --- | --- |
| TC-05-01 | `TC0501PencarianKataKunciLengkapTest.php` |
| TC-05-02 | `TC0502PencarianHurufBesarKecilTest.php` |
| TC-05-03 | `TC0503PencarianKataSebagianTest.php` |
| TC-05-04 | `TC0504PemilihanHasilDanDetailPetaTest.php` |
| TC-05-05 | `TC0505PencarianTanpaHasilTest.php` |
| TC-05-06 | `TC0506PencarianKataKunciKosongTest.php` |
| TC-05-07 | `TC0507PencarianKarakterKhususTest.php` |
| TC-05-08 | `TC0508PencarianPublikTanpaLoginTest.php` |

## Prasyarat

- Server berjalan dari folder proyek ini dan sesuai `APP_URL`.
- Dependency Laravel Dusk dan ChromeDriver sudah tersedia.
- Database dapat dibaca dan memiliki nama/alamat yang mengandung `Cingambul`.
- Seluruh filter pada awal setiap pencarian adalah default, tanpa pembatasan kabupaten.
- Tes hanya membaca data aplikasi; tidak melakukan migrasi, seed, atau perubahan wilayah.

## Skenario dan hasil yang diharapkan

| ID | Skenario | Jenis | Input | Hasil yang diharapkan |
| --- | --- | --- | --- | --- |
| TC-05-01 | Cari dengan kata kunci lengkap | Normal | `q = Cingambul` | Total hasil lebih dari nol; setiap nama/alamat pada daftar memuat kata tersebut. |
| TC-05-02 | Cari dengan huruf kecil | Normal | `q = cingambul` | Total hasil dan urutan ID pada halaman pertama sama dengan pencarian `Cingambul`. |
| TC-05-03 | Cari dengan kata sebagian | Normal | `q = Cing` | Hasil tersedia dan setiap nama/alamat yang ditampilkan memuat `Cing`, tanpa membatasi hanya awalan. |
| TC-05-04 | Memilih salah satu hasil | Normal | Klik saran pertama pada pencarian `Cingambul` | Detail menampilkan wilayah yang sama; baris terpilih dan sorotan poligon tampil pada peta. |
| TC-05-05 | Cari kata yang tidak ada | Exception | `q = xyzabc` | Total nol, tabel kosong, pesan `Tidak ada wilayah yang sesuai pencarian atau filter.` tampil, dan saran disembunyikan. |
| TC-05-06 | Cari dengan kata kunci kosong | Exception | Ketik `xyzabc`, lalu hapus dengan Ctrl+A dan Backspace | Dengan filter default, total dan ID halaman pertama kembali seperti sebelum pencarian. Semua hasil tersedia melalui paginasi, bukan harus tampil dalam satu halaman. |
| TC-05-07 | Cari dengan karakter khusus | Exception | `q = ' OR 1=1 --` | Karakter diperlakukan sebagai teks pencarian; hasil nol pada dataset ini, bukan seluruh wilayah. Halaman tetap siap dan tidak menampilkan tombol coba lagi akibat error data. |
| TC-05-08 | Cari tanpa login | Normal akses publik | Tamu membuka peta dan mencari `Cingambul` | Pengguna tetap berada pada `/peta-wilayah`, sesi tidak terautentikasi, dan hasil pencarian dapat dibaca. Tidak diarahkan ke login. |

TC-05-08 mengikuti konfirmasi bahwa peta dan pencarian tetap publik. TC-05-05
menggunakan pesan yang benar-benar ditampilkan aplikasi; teks `Wilayah tidak
ditemukan` pada rancangan awal bukan teks literal antarmuka saat ini.

## Menjalankan

Terminal pertama:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal kedua:

```powershell
php artisan dusk tests/Browser/PBI05
```

Untuk menjalankan satu skenario saja, misalnya TC-05-01:

```powershell
php artisan dusk tests/Browser/PBI05/TC0501PencarianKataKunciLengkapTest.php
```

Tes menunggu status data siap hingga 45 detik, termasuk selesainya pemuatan
hasil dan query pada URL. Screenshot setiap skenario yang berhasil disimpan
di `tests/Browser/screenshots/tc-05-*.png`. Output terminal Dusk menjadi bukti
jumlah tes dan assertion yang berhasil atau gagal.

# GUI PBI-24, satu test case per file

Chrome ditampilkan dan setiap TC menyiapkan SQLite sementara sendiri. Data TiDB tidak diubah.
Jalankan salah satu perintah di bawah untuk menjalankan satu TC saja:

```powershell
php artisan dusk --filter=TC2401Test
php artisan dusk --filter=TC2402Test
php artisan dusk --filter=TC2403Test
php artisan dusk --filter=TC2404Test
php artisan dusk --filter=TC2405Test
php artisan dusk --filter=TC2406Test
php artisan dusk --filter=TC2407Test
php artisan dusk --filter=TC2408Test
php artisan dusk --filter=TC2409Test
php artisan dusk --filter=TC2410Test
php artisan dusk --filter=TC2411Test
php artisan dusk --filter=TC2412Test
php artisan dusk --filter=TC2413Test
php artisan dusk --filter=TC2414Test
php artisan dusk --filter=TC2415Test
php artisan dusk --filter=TC2416Test
php artisan dusk --filter=KP2413Test
```

Jeda bawaan: 3 detik setelah tindakan, pengetikan 80 ms per karakter, dan 5 detik pada hasil akhir.
Untuk memberi waktu penjelasan lebih panjang, gunakan terminal yang sama:

```powershell
$env:DUSK_STEP_PAUSE="5000"
$env:DUSK_TYPING_PAUSE="120"
$env:DUSK_RESULT_PAUSE="10000"
php artisan dusk --filter=TC2401Test
```

Hapus override untuk kembali ke jeda bawaan:

```powershell
Remove-Item Env:DUSK_STEP_PAUSE,Env:DUSK_TYPING_PAUSE,Env:DUSK_RESULT_PAUSE -ErrorAction SilentlyContinue
```

Hanya jika memang ingin menjalankan seluruh TC: `php artisan dusk tests/Browser/PBI24`.
Kode persiapan dan helper bersama berada di `PBI24TestCase.php` (kelas abstrak yang tidak dijalankan sebagai tes tersendiri).
Screenshot hasil ada di `tests/Browser/screenshots`.

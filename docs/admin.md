# Administrasi pengguna

Halaman admin: `/admin/ringkasan`, `/admin/pengguna`, dan `/admin/aktivitas`. Peran admin memiliki area sendiri dan tidak memperoleh akses pengelolaan wilayah milik Pemerintah.

## Database yang sudah terisi

Database operasional menggunakan skema eksternal dengan kolom role yang sudah tersedia. Tambahkan hanya kolom is_active (default aktif untuk semua akun lama):

```powershell
php artisan migrate --path=database/migrations/2026_11_07_000001_add_is_active_to_users_table.php
php artisan migrate --path=database/migrations/2026_11_07_000002_create_admin_activity_logs_table.php
```

Jangan menjalankan migrate:fresh atau seeder pada database operasional. Migrasi awal bawaan Laravel bukan pembuat skema operasional TerangDesa.

Buat akun admin baru secara interaktif, tanpa mengubah akun yang sudah ada:

```powershell
php artisan admin:create
```

Perintah meminta nama, email unik, kata sandi, dan konfirmasi. Kata sandi tidak ditampilkan dan tidak dijadikan argumen terminal. Masuk melalui /login; akun admin diarahkan ke /admin/ringkasan.

## Perilaku

- Daftar, pencarian nama/email, filter peran/akses aktif, dan paginasi 10 akun per halaman menggunakan database.
- Tambah/edit akun, ubah peran, aktif/nonaktif, dan penggantian kata sandi melalui formulir server.
- Peran: masyarakat, pemerintah, admin. Kata sandi 8–72 karakter dan disimpan sebagai hash.
- Admin tidak dapat menonaktifkan atau menurunkan peran akunnya sendiri. Pemeriksaan dilakukan ulang di server saat menyimpan.
- Akun nonaktif ditolak saat login; sesi yang sudah terbuka keluar pada permintaan berikutnya.
- Ringkasan akun dihitung dari seluruh pengguna; filter hanya memengaruhi tabel.
- Data master dan hak akses ditampilkan sebagai informasi.
- Ringkasan operasional menghitung pengguna, wilayah, pengajuan terkirim, serta status penanganan. Draft dihitung terpisah.
- Log aktivitas menyimpan penambahan/perubahan akun, termasuk pembuatan admin lewat terminal. Perubahan akun dan log disimpan dalam satu transaksi; kegagalan pencatatan membatalkan perubahan.
- Log menyimpan identitas pelaku/pengguna dan perubahan nama, email, peran, akses. Kata sandi maupun hash tidak dicatat. Perubahan kata sandi hanya ditandai.
- Log dapat dicari, difilter berdasarkan tindakan dan tanggal WIB, serta ditampilkan 15 catatan per halaman. Tidak tersedia pengubahan/penghapusan log dari antarmuka.
- Log dimulai setelah fitur aktif; perubahan sebelumnya tidak diisi ulang.
- Tidak ada data contoh atau akun dengan kata sandi bawaan. Waktu pembaruan ditampilkan dalam WIB.

## Pemeriksaan lokal terisolasi

```powershell
php artisan test --filter=AdminUserManagementTest
php artisan test --filter=PublicNavigationTest
npm run build
```

Pengujian admin menggunakan SQLite :memory:, bukan database operasional.

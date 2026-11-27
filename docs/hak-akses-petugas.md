# PBI-24 — Kelola profil hak akses petugas

## Alur penggunaan

Admin aktif membuka **Pengguna & peran → Kelola hak akses** (`/admin/hak-akses`).
Daftar menyediakan pencarian nama/deskripsi, pagination backend, rincian profil,
petugas terkait, edit/penonaktifan dan penghapusan terkendali. Formulir diteruskan
ke halaman tinjauan sebelum konfirmasi akhir. Preview tidak menyimpan data.

Penetapan dibuka lewat **Atur profil** pada baris akun Pemerintah di daftar pengguna.
Halaman tinjauan menampilkan petugas, profil sebelumnya dan izin profil baru.
Satu petugas hanya memiliki satu profil; satu profil dapat digunakan banyak petugas.
Akun penerima wajib Pemerintah aktif dan profil wajib aktif. Identitas akun,
password, peran utama dan status akun tetap dikelola melalui PBI-23.

Petugas baru tanpa profil tidak mendapat izin operasional secara otomatis. Login
memilih Dashboard jika diizinkan, kemudian Data wilayah jika diizinkan, atau peta
publik. Menu dan tombol mengikuti keputusan akses yang sama dengan backend.

## Katalog dan pemetaan endpoint

Katalog berada di `StaffPermissionCatalog::ITEMS`, tanpa dependensi baru. Hanya
modul yang sudah mempunyai route di checkout ini yang dimasukkan. Pemeriksaan,
Verifikasi, Status pengajuan, Prioritas, Progres dan Laporan operasional belum
mempunyai route layanan Pemerintah sehingga belum ditawarkan sebagai izin palsu.
Nama profil **Petugas Verifikasi** tetap dapat dipakai, dengan izin modul yang
benar-benar tersedia.

| Kode izin | Endpoint yang dilindungi | Dependensi |
| --- | --- | --- |
| `dashboard.view` | GET `/pemerintah/dashboard`, GET `/pemerintah/dashboard/data` | Tidak ada |
| `wilayah.view` | GET `/pemerintah/wilayah`, GET `/pemerintah/wilayah/data`, GET `/pemerintah/wilayah/{id}` | Tidak ada |
| `wilayah.create` | POST `/pemerintah/wilayah` | `wilayah.view` |
| `wilayah.update` | PUT `/pemerintah/wilayah/{id}` | `wilayah.view` |
| `wilayah.delete` | DELETE `/pemerintah/wilayah/{id}` | `wilayah.view` |

Kode tidak dikenal ditolak pada backend. Dependensi tindakan diperiksa saat
validasi dan saat otorisasi, sehingga konfigurasi yang tidak lengkap tidak
memberikan akses. Profil ini tidak dapat memberikan kewenangan Admin.
Endpoint peta dan ringkasan wilayah publik tetap publik.

`RequirePemerintah` mempertahankan pemeriksaan role, kemudian
`RequireStaffPermission` memeriksa izin spesifik. Gate `staff.permission` dipakai
menu/tombol. `StaffAccess` membaca relasi akun aktif, role Pemerintah, penetapan,
profil aktif dan kode izin melalui query baru. Tidak ada cache izin dalam sesi,
relasi model yang pernah dimuat, atau cache persisten. Pencabutan izin,
penonaktifan profil, perubahan role dan status akun berlaku pada permintaan
berikutnya. Permintaan yang sudah diotorisasi sebelum pencabutan tidak dibatalkan
secara retroaktif.

## Penyimpanan dan migrasi

Migrasi khusus: `2026_11_27_000001_create_staff_access_profiles.php`.

| Tabel | Fungsi dan batasan |
| --- | --- |
| `staff_access_profiles` | Nama, kunci normalisasi unik, deskripsi, status, versi dan penanda pernah ditetapkan |
| `staff_permissions` | Katalog kode stabil dan label bahasa Indonesia |
| `staff_profile_permissions` | Relasi profil–izin, unik untuk pasangan profil/kode; foreign key RESTRICT |
| `staff_profile_assignments` | Penetapan petugas, UNIQUE `user_id`, profil, Admin penetap dan tanggal; seluruh FK RESTRICT |
| `admin_activity_logs` | Audit lama dipakai ulang, ditambah `target_type`, `target_id` snapshot tanpa FK profil dan `result` |

Nama dirapikan dengan `Str::squish`; kunci unik adalah SHA-256 dari nama yang
dirapikan dan diubah ke huruf kecil. Kapitalisasi dan spasi berlebih tidak membuat
profil berbeda. Batas nama 150 karakter, deskripsi 2.000 karakter. Hash ini adalah
kunci nama profil, bukan password.

DDL bersifat aditif. Guard tabel/kolom dan transaksi inisialisasi memungkinkan
proses dilanjutkan jika DDL terputus. Migrasi awal membuat profil **Petugas
Pemerintah** dengan lima izin untuk akun Pemerintah aktif yang sudah ada, agar
kemampuan sebelum PBI-24 tetap terjaga. Bootstrap ini tercatat dalam audit dan
bukan pengecualian otorisasi saat aplikasi berjalan. Petugas yang dibuat setelah
migrasi tetap harus ditetapkan profilnya oleh Admin.

Migrasi dipasang hanya dengan path khusus berikut, tanpa menjalankan migrasi
domain lain, reset database, impor atau seed ulang:

```powershell
php artisan migrate --path=database/migrations/2026_11_27_000001_create_staff_access_profiles.php --force
```

TiDB online diperiksa sebagai v8.5.3, dengan `foreign_key_checks = 1`.
[Dokumentasi TiDB](https://docs.pingcap.com/tidb/stable/foreign-key/) mendukung FK
dan RESTRICT pada versi ini. Struktur FK dan indeks unik penetapan juga diperiksa
langsung setelah migrasi. Rollback skema diblokir jika profil/audit telah dipakai;
perubahan lanjutan harus memakai migrasi aditif agar riwayat tidak hilang.

## Transaksi, audit dan penghapusan

`StaffProfileManager` mengunci Admin dalam urutan yang sama dengan PBI-23,
memeriksa kembali Admin aktif, kemudian mengunci profil dan petugas. Penetapan
mengunci profil lama/baru menurut ID dan memeriksa ulang role/status penerima.
Indeks unik memastikan satu profil per petugas, termasuk untuk permintaan bersamaan.

Versi profil dan jumlah penetapan pada konfirmasi dibandingkan lagi dengan kondisi
terbaru. Perubahan antara tampilan tinjauan dan penyimpanan ditolak agar Admin
meninjau dampak ulang. Pada hapus, profil serta penetapan diperiksa dengan locking
read dalam transaksi. Jika penetapan baru sudah commit, hapus ditolak dan relasi
tetap utuh. FK RESTRICT menjadi pertahanan terakhir terhadap referensi lain.

Profil yang sedang digunakan tetap ditolak untuk dihapus, termasuk bila profil
atau akun terkait nonaktif. Penanda `ever_assigned` membuat profil yang pernah
ditetapkan tetap ditolak walaupun seluruh petugas kemudian pindah profil. Gunakan
penonaktifan; akun, penetapan dan riwayat tetap dipertahankan. Profil belum pernah
ditetapkan boleh dihapus setelah konfirmasi. Relasi konfigurasi izinnya dibersihkan
dalam transaksi yang sama.

Audit menyimpan pelaku, identitas target, jenis tindakan, perubahan sebelum–sesudah,
waktu dan hasil sukses. ID serta nama profil merupakan snapshot sehingga audit
tetap terbaca setelah profil dihapus. Penetapan juga menyimpan akun penerima.
Tidak ada password atau hash password dalam profil/audit. Kegagalan audit
membatalkan perubahan utama, relasi, versi dan penanda riwayat dalam transaksi.
Percobaan yang gagal tidak menghasilkan audit keberhasilan. Formulir mempertahankan
input ketika penyimpanan atau validasi gagal.

Relasi profil tetap disimpan ketika PBI-23 mengubah role/status akun. Query izin
langsung menghentikan akses yang tidak lagi sah. FK ke pengguna/penetap adalah
referensi yang harus diperhitungkan bila fitur hapus akun PBI-23 dikembangkan.
PBI-24 tidak menambahkan endpoint hapus akun atau mengubah perlindungan Admin sendiri.

## Hasil pengujian

```powershell
npm.cmd run build
php artisan test
php artisan dusk tests/Browser/PBI24
```

Untuk menjalankan hanya berkas PBI-24 tanpa penemuan berkas tes browser lain:

```powershell
php artisan dusk --filter=TC2401Test
```

Pengujian unit/feature: **100 lulus, 772 assertions** pada pemeriksaan akhir.
Termasuk seluruh TC-24-01–16, validasi/dependensi, pembacaan izin terbaru pada
sesi aktif, penolakan langsung endpoint, perlindungan PBI-23, audit gagal,
penetapan unik, referensi baru sebelum hapus, serta migrasi yang dapat dilanjutkan.

GUI Chrome terlihat: **17 lulus, 111 assertions**, mencakup 16 TC dan satu
skenario tambahan untuk responsivitas serta keadaan kosong. Screenshot ada di
`tests/Browser/screenshots/PBI24-*.png`.
Tes menyiapkan server dan SQLite sementara sendiri; tidak membuat fixture di TiDB.
Audit gagal pada GUI disimulasikan memakai trigger SQLite di database tes, tanpa
menambahkan pintu khusus pada kode aplikasi. TC-24-06 memakai dua browser dengan
sesi Admin dan petugas yang berbeda; petugas menerima 403 setelah izin dicabut.
TC-24-15 memakai dua sesi Admin dan penetapan yang selesai sebelum konfirmasi
hapus. Ini memeriksa kondisi balapan antarsesi; bukan uji beban transaksi SQL
overlap pada cluster TiDB produksi.

Regresi GUI PBI-01 TC-17-02 (edit wilayah dan mempertahankan riwayat pengajuan):
**1 lulus, 12 assertions**.

## Verifikasi TiDB dan browser online — 27 November 2026

- TLSv1.3, cipher TLS_AES_128_GCM_SHA256 dan verifikasi sertifikat aktif.
- Sebelum/sesudah migrasi: 9 akun, 5.311 wilayah, 26 pengajuan, 0 progres dan
  22 riwayat status. Seluruh isi baris akun terverifikasi tidak berubah.
- Terbentuk satu profil, lima izin, tiga penetapan untuk tiga Pemerintah aktif,
  serta empat catatan audit bootstrap.
- Indeks unik `user_id`, foreign key RESTRICT dan `foreign_key_checks` terverifikasi.
- Browser dengan TiDB aktual berhasil membuka tombol Kelola hak akses, daftar,
  rincian lima izin dan tiga petugas, serta formulir pada layar seluler tanpa
  luapan horizontal. Akun Pemerintah lama tetap dapat membuka Data wilayah dan
  ditolak dengan 403 pada pengelolaan profil Admin.
- Pemeriksaan online menggunakan sesi Dusk lokal untuk akun yang tersedia dan
  hanya membaca halaman. Tambah/ubah/hapus serta rollback diuji pada database
  terisolasi, bukan memakai akun atau profil uji di database operasional.

## Berkas yang berubah

- `app/Services/StaffPermissionCatalog.php`, `StaffAccess.php`, `StaffProfileManager.php`, `StaffAccessAudit.php`.
- `app/Models/StaffAccessProfile.php`, `StaffProfileAssignment.php`, `User.php`, `AdminActivityLog.php`.
- `app/Http/Controllers/StaffAccessController.php`, `LoginController.php`.
- `app/Http/Requests/SaveStaffProfileRequest.php`, `AssignStaffProfileRequest.php`.
- `app/Http/Middleware/RequireStaffPermission.php`, `app/Providers/AppServiceProvider.php`, `routes/web.php`.
- Migrasi `database/migrations/2026_11_27_000001_create_staff_access_profiles.php`.
- `resources/views/admin/access/*.blade.php`, halaman daftar pengguna dan partial audit.
- Layout Admin/Pemerintah, navigasi atas, dan halaman kelola wilayah.
- `resources/css/staff-access.css`, `resources/js/staff-access.js`, registrasi aset dan `region-manage.js`.
- `tests/Feature/StaffAccessTest.php`, fixture pada `tests/Support`, `tests/Browser/PBI24/PBI24TestCase.php` dan satu kelas per TC di folder PBI24.
- Fixture tes PBI-01 serta tes feature navigasi/dashboard disesuaikan agar memakai
  profil nyata pada SQLite, tanpa mematikan middleware otorisasi.

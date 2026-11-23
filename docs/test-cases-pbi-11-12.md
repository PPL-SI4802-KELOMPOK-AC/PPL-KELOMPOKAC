# Test case PBI-11 dan PBI-12

## Skenario untuk review Jira

Gunakan tabel di bagian ini untuk demo dan pengujian saat ini. Bagian audit setelahnya merekam hasil eksekusi terdahulu.

### PBI 11 — Melihat status pengajuan

Sebagai Masyarakat, saya ingin membuka detail pengajuan milik saya dari halaman Riwayat agar dapat melihat status terkini, waktu pembaruan, dan catatan perubahan status, termasuk alasan penolakan bila tersedia.

| TC | Prasyarat | Langkah | Hasil yang diperiksa |
|---|---|---|---|
| TC01 | Pemilik memiliki pengajuan berstatus Diperiksa | Login → buka Riwayat → klik **Lihat detail** pada tiket miliknya | Nomor tiket, status Diperiksa, dan catatan pemeriksaan tampil |
| TC02 | Pengajuan memiliki tiga catatan hingga Diverifikasi | Login pemilik → buka bukti pengiriman | Status dan waktu terakhir tampil. Tiga catatan tampil dari yang paling awal |
| TC03 | Bukti pengajuan sedang terbuka dan status sudah berubah dari Diperiksa menjadi Diverifikasi di data uji | Muat ulang halaman | Ringkasan menampilkan Diverifikasi. Catatan lama Diperiksa tetap tampil di riwayat |
| TC04 | Pengajuan berstatus Ditolak dan ada catatan alasan | Login pemilik → buka bukti pengiriman | Status Ditolak dan alasan `Dokumen persyaratan tidak lengkap.` tampil |
| TC05 | Masyarakat lain mengetahui URL detail pengajuan | Login sebagai Masyarakat lain → buka URL tersebut | HTTP 404 |
| TC06 | ID pengajuan tidak ada | Login sebagai Masyarakat → buka URL detail ID tersebut | HTTP 404 |
| TC07 | Pengunjung belum login | Buka URL detail pengajuan | Dialihkan ke `/login` |
| TC08 | Akun Pemerintah sudah login | Buka URL detail pengajuan Masyarakat | HTTP 403 |

Laravel memeriksa role dan kepemilikan sebelum mengambil catatan yang terkait dengan `pengajuan_id`. Detail menampilkan `pengajuan_plts.status_terkini` dan `status_diperbarui_pada`, lalu menampilkan `riwayat_status_pengajuan` berdasarkan waktu dari yang paling awal. Pengiriman pengajuan menetapkan status awal Diajukan. Pada TC02–TC04, perubahan status disiapkan di SQLite sebagai prasyarat. Cakupan PBI ini adalah melihat status yang tersimpan. Persetujuan, penolakan, dan aturan kelayakan berdasarkan kondisi listrik belum menjadi alur keputusan di kode yang diuji.

Untuk demo manual, login dengan akun Masyarakat aktif dan pilih **Riwayat → Lihat detail** pada tiket milik akun tersebut. Jika belum ada tiket, kirim pengajuan lengkap di lingkungan demo yang memiliki data uji. Gunakan tiket yang sudah memiliki status Ditolak dan catatan alasan untuk memperagakan TC04. Jangan mengubah status pengajuan warga sungguhan untuk demo.

### PBI 12 — Menyiapkan autentikasi dan akses pengguna

Sebagai pengguna TerangDesa, saya ingin masuk dengan akun sesuai peran agar hanya bisa menggunakan fitur yang menjadi tanggung jawab saya. Pembagian tiga peran berikut mengikuti hasil wawancara yang disebut di Jira dan route yang tersedia di aplikasi.

| Peran | Tujuan login default | Akses khusus | Akses yang ditolak |
|---|---|---|---|
| Admin | `/admin/ringkasan` | Ringkasan admin, pengelolaan akun dan status aktifnya, serta log aktivitas | Pengelolaan wilayah Pemerintah dan pengajuan privat Masyarakat |
| Pemerintah | `/pemerintah/wilayah` | Melihat, menambah, mengubah, dan menghapus data wilayah | Panel Admin dan pengajuan privat Masyarakat |
| Masyarakat | `/peta-wilayah` | Membuat dan mengirim pengajuan serta membuka riwayat dan detail pengajuan sendiri | Panel Admin, pengelolaan wilayah Pemerintah, dan detail pengajuan warga lain |

Beranda dan peta publik bisa dibuka semua peran maupun pengunjung tanpa sesi. Pada halaman terlindungi, pengunjung tanpa sesi diarahkan ke login dan pengguna dengan role yang salah menerima HTTP 403. Laravel memvalidasi email dan password terhadap data akun, memeriksa status aktif, lalu membuat ulang ID sesi setelah login. Jika pengguna sebelumnya meminta halaman lokal yang diizinkan bagi perannya, login dapat mengembalikannya ke halaman tersebut. Logout mengakhiri sesi dan mengarahkan ke `/login`.

| TC | Langkah inti | Hasil yang diperiksa |
|---|---|---|
| TC01–TC03 | Login dengan satu akun aktif per role | Masing-masing tiba di tujuan login untuk perannya |
| TC04–TC06 | Kirim password salah, email tidak terdaftar, atau form kosong | Form menampilkan pesan kesalahan dan pengguna tetap di login |
| TC07–TC10 | Buka URL Pemerintah atau Admin dengan role yang salah atau tanpa sesi, sesuai kasus | Role yang salah mendapat HTTP 403. Pengunjung diarahkan ke `/login` |
| TC11–TC12 | Logout lalu coba akses terlindungi kembali. Periksa hash akun fixture lewat server | Akses ulang diarahkan ke login. Password uji cocok dengan hash yang tersimpan |
| TC13 | Ulangi validasi login serta alur buka halaman yang diizinkan, logout, dan akses ulang untuk ketiga peran | Pesan validasi sesuai. Halaman yang diizinkan terbuka, lalu sesi berakhir. Pengunjung juga diarahkan ke login |
| TC14.1–TC14.9 | Kirim field kosong ke endpoint login dan uji enam kombinasi lintas role melalui HTTP Laravel | Validasi JSON mengembalikan HTTP 422. Akses lintas role mengembalikan HTTP 403 |

Untuk demo manual, login dengan masing-masing akun aktif di lingkungan demo. Buka halaman sesuai role, lalu ketik URL yang ditolak di tabel. Klik **Keluar** dan buka kembali halaman terlindungi untuk menunjukkan bahwa sesi berakhir. TC13 Pemerintah di Chrome headless mengaktifkan tombol via DOM. Untuk menunjukkan klik biasa, gunakan demo manual di jendela Chrome.

### Cara menjalankan dan cakupan database

Untuk demo manual terpisah, gunakan konfigurasi TiDB Cloud lingkungan demo dan jalankan `php artisan serve`. Pastikan database demo sudah punya akun aktif untuk ketiga peran dan tiket milik akun Masyarakat. `php artisan terangdesa:tidb-check` bisa memeriksa koneksi, tetapi hasilnya tidak menggantikan demo alur pengguna.

Untuk Dusk, hentikan Vite dev server bila aktif dan jalankan `npm run build` bila manifest belum tersedia. Dari root repo, jalankan:

```sh
php artisan dusk --browse --without-tty --do-not-cache-result tests/Browser/PBI11
php artisan dusk --browse --without-tty --do-not-cache-result tests/Browser/PBI12
```

Suite menjalankan route dan tampilan Laravel dengan database SQLite sementara dan data fixture, termasuk akun dan pengajuan contoh. Koneksi serta jalur database diperiksa sebelum menulis fixture. Hasil Dusk membuktikan perilaku aplikasi pada data uji tersebut. Kesesuaian skema, koneksi, dan alur pada TiDB harus diperiksa terpisah di database demo. TC14.10 tentang seeder default sudah dihapus dari suite aktif.

Hasil verifikasi suite saat ini dalam mode headless: PBI 11 **8 lulus, 49 assertion** dan PBI 12 **30 lulus, 132 assertion**.

## Arsip audit sebelumnya

Audit dan eksekusi individual dengan filter method/dataset: 17 November 2026 (WIB). PHP 8.5.7, Laravel 12.72, Dusk 8.8, PHPUnit 11.5.57. Hasil di bawah berasal dari eksekusi lokal, bukan perkiraan.

## Isolasi lanjutan TC13 Pemerintah — mode browser

**Tidak ada perubahan source test atau aplikasi pada putaran ini.** Selector, klik asli, assertion, timeout 5 detik, delay 2000 ms per transisi dan 3000 ms pada finally tetap sama. Hasil dibedakan menurut mode; PASS pada Chrome terlihat tidak dianggap perbaikan atau PASS pada headless.

| Eksekusi test asli | Mode | Hasil assertion | Assertions | Deprecation |
|---|---|---|---:|---|
| TC-AUTH-13.5, individual Pemerintah | Headless default | ERROR: timeout menunggu /login | 4 | 3 lokasi PDO |
| TC-AUTH-13.5, individual Pemerintah | Chrome terlihat | PASS | 6 | 3 lokasi PDO |
| Seluruh TC13 (9 seleksi) | Chrome terlihat | 9 PASS, tanpa FAIL/ERROR/skip | 42 | 3 lokasi PDO |

Case individual memakai method asli `test_allowed_route_and_logout_for_each_role`, dataset `pemerintah`. Setelah individual Chrome terlihat berhasil, seluruh class TC13 dijalankan untuk regresi dalam mode yang sama, termasuk dataset Pemerintah sekali lagi. Semua tetap memakai SQLite unik/terisolasi dari base class. Tidak menjalankan ulang PBI11, seeder, atau suite PBI lain pada putaran ini; temuan mereka tetap tercatat di bagian audit sebelumnya.

### Perbandingan interaksi ketiga role

Pembanding diagnostik mewarisi test asli dan hanya merekam posisi, event native dan jaringan. Masyarakat dan Admin masing-masing melewati assertion 6; Pemerintah pada headless tetap ERROR 4. Hasil pembanding tidak dijumlahkan sebagai case/requirement baru atau dicampur dengan 9 seleksi regresi utama.

| Pemeriksaan desktop (1920 × 937 viewport) | Masyarakat | Pemerintah | Admin |
|---|---|---|---|
| Selector menemukan tombol Keluar | 1, terlihat dan enabled | 1, terlihat dan enabled | 1, terlihat dan enabled |
| Posisi tombol (x, y) | 1548.44, 116.5 | 1820.44, 116.5 | 1831.17, 120.5 |
| Elemen teratas pada pusat tombol | BUTTON | BUTTON | BUTTON |
| Pointer-events | auto | auto | auto |
| Menu yang harus dibuka | Tidak ada menu logout tersembunyi | Tidak ada; toggle drawer hidden, drawer tidak terbuka, backdrop hidden | Tidak ada; toggle drawer hidden, drawer tidak terbuka, backdrop hidden |
| Event pointerdown/mousedown/pointerup/mouseup/click/submit | Teramati | Tidak teramati pada reproduksi headless | Teramati |
| Request browser POST /logout | Teramati | Tidak teramati pada reproduksi headless | Teramati |
| Respons sesudah POST logout | 302 ke /login | Tidak ada POST sehingga tidak ada respons logout | 302 ke /login |
| Hasil test diagnostik | PASS | ERROR | PASS |

Pada Pemerintah, node hasil resolusi WebDriver sama dengan node DOM yang diinspeksi. Seluruh ancestor tidak inert; tombol tidak disabled; tidak ada dialog JavaScript aktif; document.hasFocus=true dan visibilityState=visible. Visual viewport berskala 1, offset 0 dan scroll 0. Tidak ditemukan bukti overlay, menu yang belum dibuka, selector mengenai tombol lain, atau form owner yang salah. Form mengarah ke /logout dengan method POST. Percobaan tambahan memakai hover + pointer click native juga masih timeout pada headless; ini hanya diagnosis, bukan pengganti langkah/assertion test asli.

### Handler JavaScript dan batas kesimpulan

- Layout Pemerintah `resources/views/layouts/app.blade.php:51` dan masyarakat pada `resources/views/layouts/partials/top-navigation.blade.php:25` memiliki form logout `data-leave-form`. Layout Admin `resources/views/layouts/admin.blade.php:36` memakai form POST biasa.
- `resources/js/region-manage.js` mengelola drawer dan memasang handler submit `data-leave-form` pada halaman manager Pemerintah. `canDiscard()` dapat mencegah submit saat busy/editPending atau bila konfirmasi perubahan dibatalkan. Handler drawer juga mengatur workspace.inert saat menu terbuka.
- Pada snapshot yang diperiksa, drawer tertutup, backdrop hidden, ancestor tidak inert, serta isian form wilayah kosong. Tidak ada event pointer/click/submit yang terekam pada headless Pemerintah. **Tidak terbukti handler submit canDiscard membatalkan logout pada reproduksi ini**, sehingga handler tidak dimatikan/diubah dari test atau aplikasi.
- Peringatan PDO menyebabkan HTML BackCompat; hal itu juga diamati pada pembanding yang berhasil. Hubungan kausal warning/BackCompat dengan kegagalan tidak ditetapkan dan konfigurasi tidak diubah.
- Dengan test dan selector yang sama, mode terlihat berhasil secara individual dan dalam regresi TC13, sedangkan headless tetap gagal. **Batas isolasi yang terbukti adalah perbedaan hasil menurut mode browser pada lingkungan ini.** Belum ada bukti yang menentukan penyebab internal Chrome/ChromeDriver atau perbedaan runtime halaman, sehingga tidak menyatakan bug browser/aplikasi tertentu sudah ditemukan atau diperbaiki.

Tidak mengganti klik dengan HTTP, pemanggilan logout langsung, JS form.submit/requestSubmit, atau manipulasi sesi. Diagnosis baru memakai klik mouse biasa dan pointer action native saja. Pemeriksaan HTTP dari audit terdahulu tetap bukti terpisah, bukan pengganti pengujian browser ini.

### Verifikasi sesi dan requirement

Jira mewajibkan logout mengakhiri sesi dan akses berikutnya ke route terlindungi ditolak/diarahkan ke login. URL segera setelah logout tidak ditentukan Jira. Redirect /login merupakan kontrak implementasi `LoginController::destroy` yang tetap diuji.

Pada hasil PASS mode terlihat, test asli menunggu /login, memeriksa path, kemudian membuka kembali `wilayah.index` melalui navigasi browser dan kembali diarahkan ke /login. Tidak ada penghapusan cookie/manipulasi sesi setelah klik; penolakan akses ulang menjadi bukti browser tidak lagi terautentikasi. Pada headless, sesi belum berakhir karena tidak ada request logout, sebagaimana bukti audit sebelumnya. Assertion akses ulang tidak dicabut, dan timeout tetap 5 detik.

### Bukti dan perintah reproduksi

- [Pemerintah headless: JUnit](../tests/Browser/PBI12/.runtime/logout-isolation-headless.xml), [output](../tests/Browser/PBI12/.runtime/logout-isolation-headless.log).
- [Pemerintah Chrome terlihat: JUnit](../tests/Browser/PBI12/.runtime/logout-isolation-headed.xml), [output](../tests/Browser/PBI12/.runtime/logout-isolation-headed.log).
- [Regresi seluruh TC13 Chrome terlihat: JUnit](../tests/Browser/PBI12/.runtime/logout-isolation-tc13-headed-regression.xml), [output](../tests/Browser/PBI12/.runtime/logout-isolation-tc13-headed-regression.log), [ringkasan per dataset](../tests/Browser/PBI12/.runtime/logout-isolation-regression-results.json).
- [Perbandingan masyarakat](../tests/Browser/PBI12/.runtime/interaction-summary-masyarakat.json), [Pemerintah](../tests/Browser/PBI12/.runtime/interaction-summary-pemerintah.json), [Admin](../tests/Browser/PBI12/.runtime/interaction-summary-admin.json). Ringkasan memuat URL/event/request tanpa menyalin kredensial atau header cookie.
- [Detail target Pemerintah](../tests/Browser/PBI12/.runtime/interaction-before-pemerintah.json), [DOM](../tests/Browser/PBI12/.runtime/interaction-pemerintah.txt). Source helper diagnostik diarsipkan `.php.txt` agar tidak ikut discovery Dusk normal. Tidak ada penambahan case produksi atau perubahan method/provider.

Headless (masih ERROR pada lingkungan ini):

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter 'test_allowed_route_and_logout_for_each_role@pemerintah' --log-junit tests/Browser/PBI12/.runtime/logout-isolation-headless.xml tests/Browser/PBI12/TC13AutentikasiLengkapTest.php
```

Chrome terlihat, individual Pemerintah (PASS):

```sh
DUSK_HEADLESS_DISABLED=1 php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter 'test_allowed_route_and_logout_for_each_role@pemerintah' --log-junit tests/Browser/PBI12/.runtime/logout-isolation-headed.xml tests/Browser/PBI12/TC13AutentikasiLengkapTest.php
```

Chrome terlihat, seluruh TC13 (9 PASS):

```sh
DUSK_HEADLESS_DISABLED=1 php vendor/bin/phpunit --do-not-cache-result --display-deprecations --log-junit tests/Browser/PBI12/.runtime/logout-isolation-tc13-headed-regression.xml tests/Browser/PBI12/TC13AutentikasiLengkapTest.php
```

Flag tersebut hanya memilih mode proses pengujian, tidak menulis .env/config atau mengubah default headless. Pemanggilan PHPUnit langsung menghindari pembuatan konfigurasi sementara/pembersihan artefak global oleh Artisan Dusk. Tidak mengubah timeout atau delay untuk memperoleh PASS. Default headless **belum diperbaiki**.

### Hasil regresi TC13 per case/dataset — Chrome terlihat

| ID | Method/dataset | Status | Assertions |
|---|---|---|---:|
| TC-AUTH-13.1 | test_single_empty_field_is_rejected with data set "email kosong" | PASS | 4 |
| TC-AUTH-13.2 | test_single_empty_field_is_rejected with data set "password kosong" | PASS | 4 |
| TC-AUTH-13.3 | test_invalid_credentials_show_exactly_the_same_generic_message | PASS | 7 |
| TC-AUTH-13.4 | test_allowed_route_and_logout_for_each_role with data set "masyarakat" | PASS | 6 |
| TC-AUTH-13.5 | test_allowed_route_and_logout_for_each_role with data set "pemerintah" | PASS | 6 |
| TC-AUTH-13.6 | test_allowed_route_and_logout_for_each_role with data set "admin" | PASS | 6 |
| TC-AUTH-13.7 | test_guest_redirect_for_each_protected_role_route with data set "masyarakat" | PASS | 3 |
| TC-AUTH-13.8 | test_guest_redirect_for_each_protected_role_route with data set "pemerintah" | PASS | 3 |
| TC-AUTH-13.9 | test_guest_redirect_for_each_protected_role_route with data set "admin" | PASS | 3 |

Total regresi utama: **9 PASS, 42 assertions, tiga lokasi deprecation PDO**. Deprecation saja tidak berarti assertion gagal. Angka ini tidak digabung dengan putaran headless, pembanding diagnostik, temuan seeder atau kegagalan legacy.

Dokumen ini adalah satu-satunya file sumber yang diperbarui pada tindak lanjut isolasi ini. Tidak ada bukti langkah interaksi test keliru yang cukup untuk membenarkan perubahan test; semua assertion, timeout, delay dan fixture dipertahankan. Temuan seeder dan kegagalan legacy tidak diubah.

## Audit kegagalan sebelumnya — PBI11 dan PBI12

Laporan pengguna: PBI11 tiga FAIL (TC01/02/04) dan lima DEPR; PBI12 29 deprecated, dua failed, 131 assertions. Angka tersebut dicatat sebagai laporan pengguna. Audit ini menjalankan ulang **lima case terkait secara individual**, bukan seluruh suite. JUnit memisahkan assertion failure (FAIL) dan exception (ERROR), meskipun tampilan runner dapat merangkum keduanya sebagai failed.

| Case | Hasil aktual terbaru | Assertions | Deprecation |
|---|---|---:|---|
| TC-11-01 | FAIL | 6 | 3 lokasi PDO |
| TC-11-02 | FAIL | 4 | 3 lokasi PDO |
| TC-11-04 | FAIL | 4 | 3 lokasi PDO |
| TC-AUTH-13.5 | ERROR | 4 | 3 lokasi PDO |
| TC-AUTH-14.10 | ERROR | 2 | 3 lokasi PDO |

Total putaran ini: **5 case, 20 assertions, 3 FAIL legacy dan 2 ERROR**. Peringatan PDO adalah tiga lokasi unik (database.php:58/59/80), berulang pada lima proses; tidak disembunyikan dan bukan alasan assertion gagal. Pemeriksaan HTTP diagnostik tambahan di bawah lulus 12 assertions dan tidak dicampur ke total lima case.

### PBI11 — membedakan fixture, selector, pemuatan dan fitur

| Pemeriksaan | Bukti | Kesimpulan |
|---|---|---|
| Fixture | Dump SQLite memperlihatkan tiket PLTS-PBI11-001, pemilik benar dan catatan yang gagal dicari. TC02 memiliki tiga catatan dalam urutan waktu; TC04 memiliki catatan penolakan yang tepat | Catatan tidak hilang dari fixture |
| Konsistensi TC02 | Sebelum perbaikan, catatan ketiga status_baru=diverifikasi tetapi pengajuan_plts.status_terkini masih diperiksa | Kekeliruan fixture kecil diperbaiki: ringkasan menjadi diverifikasi dan timestamp 2026-11-17 10:00:00; tidak mengubah assertion |
| Selector/teks | TC01/04 mencari teks terlihat pada body; TC02 membaca document.body.innerText. Screenshot memperlihatkan tiket dan status yang benar | Bukan selector linimasa yang gagal menunjuk elemen; teks yang dicari memang tidak ada di DOM |
| Pemuatan | Bukti browser pada ketiga case: HTTP 200 dan document.readyState=complete. View receipt dirender server; tidak ada fetch/modul linimasa | Tidak ada bukti catatan hanya terlambat dimuat; menambah delay tidak menyelesaikan jalur render yang tidak ada |
| Aplikasi read-only | PengajuanController.php:84 hanya mengambil pengajuan milik user; view pengajuan/terkirim.blade.php:11 menampilkan status terkini. Tidak ada query/render catatan riwayat atau alasan penolakan | Halaman bukti pengiriman saat ini memang tidak menyediakan keluaran yang diminta test legacy |
| Requirement | Jira autentikasi terbaru identik untuk PBI11/PBI12; tidak memuat linimasa/alasan penolakan. Repository tidak menyediakan sumber requirement mandiri untuk memvalidasi pemetaan legacy tersebut | Tiga FAIL dipertahankan sebagai ketidaksesuaian pemetaan legacy, bukan bug acceptance autentikasi PBI11/PBI12 |

Bukti screenshot terbaru asli pada `.runtime/failure-Tests_Browser_PBI11_TC01...png`, TC02 dan TC04 telah dibaca. Sebelumnya source Dusk untuk kegagalan ini belum tersedia; audit membuat reproduksi dengan test yang mewarisi case asli dan hanya merekam DOM/fixture secara read-only. Bukti terperinci:

- [TC01 DOM](../tests/Browser/PBI11/.runtime/TC01LihatStatusPengajuanEvidenceTest.txt), [fixture/URL/status](../tests/Browser/PBI11/.runtime/TC01LihatStatusPengajuanEvidenceTest.json).
- [TC02 DOM dan fixture sebelum sinkronisasi](../tests/Browser/PBI11/.runtime/TC02WaktuPembaruanStatusEvidenceTest.txt), [metadata](../tests/Browser/PBI11/.runtime/TC02WaktuPembaruanStatusEvidenceTest.json). Perbaikan fixture kemudian diverifikasi melalui eksekusi ulang case asli: tetap FAIL pada catatan UI, bukan pada urutan data.
- [TC04 DOM](../tests/Browser/PBI11/.runtime/TC04StatusDitolakEvidenceTest.txt), [fixture/URL/status](../tests/Browser/PBI11/.runtime/TC04StatusDitolakEvidenceTest.json).

Tidak ada perubahan controller, model, view, route, middleware, PDO atau fitur PBI lain. Tidak menambah delay dan tidak menghapus assertion untuk tiga FAIL ini.

### TC13 Pemerintah — bukti logout browser dan server

1. Screenshot dan [DOM aktual](../tests/Browser/PBI12/.runtime/logout-diagnostic.txt) tetap memperlihatkan Pemerintah Uji pada halaman Kelola data wilayah. URL aktual masih `/pemerintah/wilayah`, readyState complete.
2. Selector `form[action$="/logout"] button[type="submit"]` menemukan tombol Keluar yang aktif. Form owner tombol benar; action adalah `/logout`, method POST, form valid, token CSRF tersedia. Tombol tidak disabled/inert, seluruh ancestor tidak inert, dan elementFromPoint pada pusat tombol menunjuk tombol yang sama. Tidak ada bukti selector salah atau elemen tertutup.
3. [Rekaman jaringan dan sesi](../tests/Browser/PBI12/.runtime/logout-diagnostic.json) tidak memuat POST `/logout` dalam reproduksi yang timeout. **Respons logout tidak tersedia karena request tidak terkirim**; HTTP 200 yang teramati berasal dari halaman/probe route Pemerintah, bukan respons logout.
4. Probe GET dengan cookie browser yang sama ke `/pemerintah/wilayah` mendapat HTTP 200, tanpa redirect, dan masih memuat Pemerintah Uji. Jadi sesi pada reproduksi browser belum berakhir. Pemeriksaan aktivasi keyboard diagnostik juga belum mencapai login; form/target/event diperiksa pada [metadata target](../tests/Browser/PBI12/.runtime/logout-click-target.json) dan [probe keyboard](../tests/Browser/PBI12/.runtime/logout-keyboard-probe.json). Aktivasi keyboard ini bukan pengganti aksi/hasil pada test asli.
5. Diagnosis terpisah melalui HTTP Laravel terisolasi berhasil: login Pemerintah; POST logout menghasilkan **302 ke /login**; assertGuest lulus; GET ulang route Pemerintah **302 ke /login**. [Bukti HTTP](../tests/Browser/PBI12/.runtime/logout-http-evidence.json), [JUnit 12 assertions](../tests/Browser/PBI12/.runtime/logout-http-evidence.xml). Pemeriksaan server ini tidak mengklaim alur browser lulus.
6. Jira mewajibkan logout mengakhiri sesi; tidak menentukan URL segera sesudah logout. Redirect `/login` adalah kontrak implementasi yang eksplisit pada LoginController::destroy. Assertion redirect yang sudah ada dipertahankan, serta akses ulang route terlindungi tetap harus menuju login. Menghapus penantian redirect tidak menyelesaikan fakta bahwa request logout tidak terkirim dan sesi browser masih aktif.

**Kesimpulan terbatas:** server logout berhasil saat POST benar-benar diterima. Error reproduksi terjadi pada alur aktivasi/logout browser sebelum ada request tersebut. Belum ada bukti yang mengisolasi penyebab terakhir ke WebDriver, handler UI, atau komponen halaman; tidak menyatakan controller logout rusak dan tidak mengganti selector/aksi tanpa bukti perbaikan. Perubahan timeout, penghapusan assertion, submit form paksa, atau respons 200 sebagai ekspektasi logout tidak digunakan. Timeout tetap 5 detik dan delay pengamatan tetap 2000/3000 ms.

Bukti juga menunjukkan BackCompat dan peringatan PDO di awal HTML serta error daftar wilayah. Hubungan sebab akibatnya dengan aktivasi logout **belum dibuktikan**, sehingga tidak dijadikan alasan mengubah konfigurasi, JS atau assertion.

### TC14 seeder — penyebab dan batas bukti

`database/seeders/DatabaseSeeder.php:18` hanya membuat satu Test User, tanpa role. `database/factories/UserFactory.php:24` menyediakan name/email/email_verified_at/password/remember_token, juga tanpa role. Seeder tidak menyediakan tiga akun masyarakat, pemerintah, admin sesuai Jira.

Pada fixture SQLite dengan role wajib, INSERT menghasilkan QueryException `NOT NULL constraint failed: users.role`. Pemeriksaan role/hash/login tiga akun yang berada setelah seeder belum tercapai. Akun masyarakat/Pemerintah/Admin yang dibuat base fixture tidak dipakai sebagai pengganti bukti seeder; akun fixture dikosongkan sebelum seed. Tidak memberi default role dari test dan tidak mengubah seeder/factory. Default role pada skema deployment yang belum diaudit tidak diasumsikan; walaupun ada default, satu akun Test User tetap tidak membuktikan tiga role.

### Perintah dua case PBI12 yang benar-benar dijalankan

Pemanggilan PHPUnit langsung menghindari pembuatan konfigurasi sementara/pembersihan artefak global oleh Artisan Dusk. Setiap base test membuat server, ChromeDriver dan SQLite uniknya sendiri. Perintah ini tidak memakai database utama.

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter test_allowed_route_and_logout_for_each_role@pemerintah --log-junit tests/Browser/PBI12/.runtime/failure-review/TC-AUTH-13.5.xml tests/Browser/PBI12/TC13AutentikasiLengkapTest.php
```

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '^.*::test_database_seeder_provides_login_accounts_for_all_roles$' --log-junit tests/Browser/PBI12/.runtime/failure-review/TC-AUTH-14.10.xml tests/Browser/PBI12/TC14KontrakServerTest.php
```

Semua JUnit/output terbaru untuk lima case berada di `.runtime/failure-review/` pada folder PBI masing-masing; [manifest hasil](../tests/Browser/PBI12/.runtime/failure-review-results.json). Helper diagnostik hanya menyimpan bukti lokal dan diarsipkan sebagai `.php.txt` agar tidak menambah case pada discovery Dusk normal.

Perubahan source pada audit ini hanya `tests/Browser/PBI11/TC02WaktuPembaruanStatusTest.php` (sinkronisasi fixture) dan dokumen TC ini. Assertion, timeout, delay dan data seeder tidak dilemahkan. Tidak ada perubahan aplikasi atau perubahan awal pengguna yang dibatalkan. Sintaks TC02, Pint untuk TC02, serta git diff --check lulus.

## Lingkup dan traceability

Deskripsi Jira terbaru yang diberikan untuk PBI-11 dan PBI-12 identik: autentikasi dan otorisasi. Repository belum memuat dokumen Jira atau pemisahan requirement yang dapat membuktikan pembagian berbeda. Karena itu seluruh TC-AUTH menelusuri **kedua PBI**, memakai satu kumpulan test di folder PBI12. Nomor dan file lama dipertahankan agar pekerjaan sebelumnya tetap dapat ditelusuri.

Folder PBI11 yang sudah ada menguji status pengajuan PLTS. **Linimasa dan alasan penolakan tidak tercantum dalam Jira autentikasi PBI-11/PBI-12 yang diberikan.** Test status pengajuan dipertahankan sebagai cakupan legacy dengan assertion yang sudah ada. Kegagalannya merupakan ketidaksesuaian pemetaan test terhadap requirement terbaru, bukan bukti kegagalan acceptance autentikasi PBI-11/PBI-12. Tidak ada upaya memperbaiki fitur status pengajuan atau PBI lain. Delapan file TC sesuai struktur sebelumnya; tiga file duplikat yang disebutkan pada percakapan sebelumnya sudah tidak ditemukan. Tidak ada test yang dihapus atau diganti nama.

`PBI11TestCase` benar merupakan abstract class turunan DuskTestCase. Fixture, server PHP, port ChromeDriver dinamis, penjagaan koneksi SQLite, cleanup dan login tetap dipertahankan. SQLite, kompilasi Blade dan artefak Dusk sekarang berada di `.runtime/` pada masing-masing folder test. Perubahan variabel environment hanya berlaku pada proses test dan dipulihkan sesudahnya; tidak ada perubahan .env/config atau migrasi database utama. Dua .gitignore mengabaikan artefak runtime.

Perubahan pengguna yang sudah ada pada `PBI12/TC08MasyarakatAksesAdminTest.php` (menghapus teks perintah shell sebelum `<?php`) dipertahankan.

## Kebijakan dan tujuan route yang ditemukan

| Aktor | Role aplikasi | Tujuan login implementasi | Route sah yang dilindungi |
|---|---|---|---|
| User | masyarakat | wilayah.map: /peta-wilayah | pengajuan.riwayat: /pengajuan/riwayat |
| Pemerintah | pemerintah | wilayah.index: /pemerintah/wilayah | wilayah.index |
| Admin | admin | admin.dashboard: /admin/ringkasan | admin.dashboard |

Ketiga middleware Require* secara eksplisit membatasi role masing-masing; kombinasi silang lainnya diuji berdasarkan kode ini. `docs/admin.md` juga menyatakan Admin tidak berhak mengelola wilayah Pemerintah. Peta dan beranda adalah route publik menurut routes/web.php dan PublicNavigationTest; guest tidak diharapkan dialihkan dari peta. Karena itu pemeriksaan guest User memakai route pengajuan yang memang dilindungi.

**Ketidaksesuaian yang perlu keputusan:** Jira menghendaki Pemerintah menuju dashboard, tetapi implementasi dan test lama menuju Kelola data wilayah. Tidak ada route dashboard Pemerintah yang dinyatakan oleh Jira. TC login Pemerintah yang lulus membuktikan kontrak implementasi, belum membuktikan acceptance requirement dashboard. Pemetaan istilah dashboard ke halaman Kelola data wilayah dicatat sebagai belum terkonfirmasi; laporan ini tidak mengarang URL dashboard atau menganggap acceptance tersebut telah lulus. Tujuan Admin /admin/ringkasan mengikuti dokumentasi pengelolaan operasional proyek; tidak dibuat URL baru.

## Data dan prasyarat

Semua akun dibuat pada SQLite unik milik test. PBI11 memakai pemohon-pbi11@example.test, pemohon-lain-pbi11@example.test, pemerintah-pbi11@example.test, password `1234567890`, tiket PLTS-PBI11-001/002. PBI12 mempertahankan identitas fixture lama: masyarakat.uji@example.test, sep@mail.com, tom@mail.com; semuanya dibuat lokal, tidak membaca akun operasional. Password masyarakat/Pemerintah `1234567890`, Admin `123456780123456780`.

Test seeder menghapus fixture akun sebelum menjalankan DatabaseSeeder asli, sehingga akun buatan test tidak menutupi kekurangan seeder. Factory proyek menetapkan password awal `password`; seeder saat ini gagal sebelum akun terbentuk. Validitas login akun seeder belum dapat dibuktikan.

## TC dan hasil aktual

Status **PASS** di tabel berarti assertion otomatis selesai tanpa failure/error, **FAIL** berarti assertion gagal, dan **ERROR** berarti eksekusi terhenti oleh exception. **Deprecation dilaporkan terpisah** dan dapat terjadi pada ketiga status tersebut. Semua 39 test memicu peringatan PDO, sehingga PASS assertion tidak berarti eksekusi PHPUnit bersih dari peringatan. Ada tiga lokasi deprecation unik: config/database.php baris 58, 59 dan 80. Status assertion juga tidak otomatis membuktikan acceptance requirement yang pemetaannya belum terkonfirmasi, khususnya tujuan dashboard Pemerintah.

| ID TC | PBI/requirement terkait | Skenario | Jenis pengujian | Prasyarat | Data input | Langkah pengujian | Hasil yang diharapkan | Hasil aktual | Status | Deprecation |
|---|---|---|---|---|---|---|---|---|---|---|
| TC-11-01 | Legacy status pengajuan — di luar Jira autentikasi PBI-11/PBI-12 terbaru | Status pengajuan sendiri dan linimasa | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | pemohon-pbi11@example.test; PLTS-PBI11-001 | Login; buka pengajuan.terkirim milik sendiri | Nomor, status Diperiksa dan catatan linimasa tampil | HTTP 200, readyState complete; tiket dan Diperiksa tampil; catatan Pengajuan sedang diperiksa. tidak ada dalam DOM; 6 assertions (audit kegagalan terbaru) | FAIL | 3 lokasi PDO (terpisah) |
| TC-11-02 | Legacy status pengajuan — di luar Jira autentikasi PBI-11/PBI-12 terbaru | Tiga catatan riwayat kronologis | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | Tiga catatan 15–17 November 2026 | Sisipkan catatan ketiga; periksa data; login dan buka bukti pengajuan | Tiga catatan tampil berurutan menurut waktu | Fixture ringkasan diselaraskan menjadi diverifikasi; urutan tiga catatan tetap benar; assertion UI tetap gagal karena catatan pertama tidak tampil; 4 assertions (audit kegagalan terbaru) | FAIL | 3 lokasi PDO (terpisah) |
| TC-11-03 | Legacy status pengajuan — di luar Jira autentikasi PBI-11/PBI-12 terbaru | Perubahan status terbaru | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | Diperiksa → Diverifikasi | Login; lihat status awal; perbarui fixture; refresh halaman | Diverifikasi tampil dan Diperiksa hilang | Status awal dan status setelah refresh sesuai; 6 assertions (individual) | PASS | 3 lokasi PDO |
| TC-11-04 | Legacy status pengajuan — di luar Jira autentikasi PBI-11/PBI-12 terbaru | Status Ditolak dan alasan | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | Dokumen persyaratan tidak lengkap. | Ubah status dan tambahkan alasan; login; buka pengajuan | Ditolak dan catatan penolakan tampil | HTTP 200, readyState complete; Ditolak tampil; catatan Dokumen persyaratan tidak lengkap. tersimpan pada fixture tetapi tidak ada dalam DOM; 4 assertions (audit kegagalan terbaru) | FAIL | 3 lokasi PDO (terpisah) |
| TC-11-05 | Legacy status pengajuan — di luar Jira autentikasi PBI-11/PBI-12 terbaru | Pengajuan pengguna lain | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | pemohon-lain-pbi11@example.test; pengajuan pemohon | Login sebagai pemohon lain; akses URL milik pemohon | HTTP 403 atau 404 | Akses ditolak sesuai assertion HTTP 403/404; 5 assertions (individual) | PASS | 3 lokasi PDO |
| TC-11-06 | Legacy status pengajuan — di luar Jira autentikasi PBI-11/PBI-12 terbaru | Pengajuan tidak ditemukan | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | ID 999999 | Login; buka URL ID tidak ada | HTTP 404 | HTTP 404; 5 assertions (individual) | PASS | 3 lokasi PDO |
| TC-11-07 | Legacy status pengajuan — di luar Jira autentikasi PBI-11/PBI-12 terbaru | Guest mengakses pengajuan | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | Tanpa cookie sesi | Bersihkan sesi; buka URL pengajuan | Redirect login | Redirect /login; 3 assertions (individual) | PASS | 3 lokasi PDO |
| TC-11-08 | Legacy status pengajuan — di luar Jira autentikasi PBI-11/PBI-12 terbaru | Pemerintah mengakses pengajuan | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | pemerintah-pbi11@example.test; role pemerintah | Buat akun pada SQLite PBI11; periksa role/hash; login; periksa nama; buka URL pengajuan | HTTP 403 pada navigasi URL pengajuan | Nama Pemerintah tampil setelah login; URL pengajuan menghasilkan HTTP 403; 8 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-01 (lama TC-PBI12-01) | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Login User | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | masyarakat.uji@example.test / 1234567890 | Login melalui formulir | Redirect peta wilayah | /peta-wilayah; 3 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-02 (lama TC-PBI12-02) | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Login Pemerintah | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | sep@mail.com / 1234567890 (fixture lokal) | Login melalui formulir | Requirement: dashboard; kontrak implementasi: wilayah.index | /pemerintah/wilayah; halaman Kelola data wilayah, bukan dashboard tersendiri; acceptance dashboard belum terverifikasi; 3 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-03 (lama TC-PBI12-03) | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Login Admin | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | tom@mail.com / password fixture ADMIN | Login melalui formulir | Kelola data operasional sesuai docs/admin.md | /admin/ringkasan; Ringkasan operasional tampil; 4 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-04 (lama TC-PBI12-04) | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Password salah | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | Email masyarakat; password-salah | Kirim form; tunggu #email-error | Email atau kata sandi salah; tetap login | Pesan tampil; tetap /login; 4 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-05 (lama TC-PBI12-05) | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Email tidak terdaftar | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | tidak.terdaftar@example.test / 1234567890 | Kirim form; tunggu #email-error | Email atau kata sandi salah; tetap login | Pesan tampil; tetap /login; 4 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-06 (lama TC-PBI12-06) | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Dua isian kosong | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | Email dan password kosong | Periksa required; tekan Masuk; periksa validity | Browser menolak pengiriman | Kedua valueMissing=true; tetap /login; 9 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-07 (lama TC-PBI12-07) | PBI-11/PBI-12 — autentikasi (Jira duplikat) | User ke route Pemerintah | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | Akun masyarakat; /pemerintah/wilayah | Login; akses URL langsung | HTTP 403 | HTTP 403; 3 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-08 (lama TC-PBI12-08) | PBI-11/PBI-12 — autentikasi (Jira duplikat) | User ke route Admin | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | Akun masyarakat; /admin/ringkasan | Login; akses URL langsung | HTTP 403 | HTTP 403; 3 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-09 (lama TC-PBI12-09) | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Pemerintah ke route Admin | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | Akun pemerintah; /admin/ringkasan | Login; akses URL langsung | HTTP 403 | HTTP 403; 3 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-10 (lama TC-PBI12-10) | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Guest ke Admin | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | Tanpa cookie sesi | Buka URL Admin langsung | Redirect login | /login; 3 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-11 (lama TC-PBI12-11) | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Logout User | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | Akun masyarakat | Login; klik Keluar; akses /pengajuan/riwayat lagi | Sesi berakhir; akses ulang redirect login | Logout dan akses ulang berakhir di /login; 5 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-12 (lama TC-PBI12-12) | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Hash password ketiga role | Integrasi DB / Hash Laravel | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | Tiga akun fixture: masyarakat.uji@example.test / 1234567890; sep@mail.com / 1234567890; tom@mail.com / 123456780123456780; password mentah dibaca dari SQLite | Baca nilai mentah tabel users; bandingkan plaintext; Hash::check | Bukan plaintext; hash dapat diverifikasi | Ketiga nilai berbeda dari plaintext dan lolos Hash::check; 8 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-13.1 | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Satu isian login kosong | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | email=""; password="1234567890"; field kosong=email | Isi satu field; tekan Masuk; periksa validity.valueMissing | Tetap login; field kosong ditolak | Tetap /login; valueMissing=true; 4 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-13.2 | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Satu isian login kosong | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | email="masyarakat.uji@example.test"; password=""; field kosong=password | Isi satu field; tekan Masuk; periksa validity.valueMissing | Tetap login; field kosong ditolak | Tetap /login; valueMissing=true; 4 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-13.3 | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Pesan umum identik | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | email masyarakat.uji@example.test lalu unknown@example.test; keduanya password wrong-password | Kirim dua login; baca teks #email-error; akses Admin sesudah setiap kegagalan | Kedua pesan persis Email atau kata sandi salah; tidak terautentikasi | Dua pesan identik; akses Admin redirect login; 7 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-13.4 | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Akses sah dan logout masyarakat | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | role=masyarakat; email=masyarakat.uji@example.test; password=1234567890; route=pengajuan.riwayat (/pengajuan/riwayat); destination=/peta-wilayah | Login; periksa redirect; buka route sah; klik Keluar; akses ulang route | HTTP 200; logout dan akses ulang redirect login | HTTP 200; dua redirect /login; 6 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-13.5 | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Akses sah dan logout pemerintah | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | role=pemerintah; email=sep@mail.com; password=1234567890; route=wilayah.index (/pemerintah/wilayah); destination=/pemerintah/wilayah | Login; periksa redirect; buka route sah; klik Keluar; akses ulang route | HTTP 200; logout dan akses ulang redirect login | Headless: ERROR timeout, tanpa POST logout; 4 assertions. Chrome terlihat: PASS 6 assertions pada individual dan regresi TC13; penolakan akses ulang terbukti. Status kolom ini memakai mode default headless | ERROR | 3 lokasi PDO (terpisah) |
| TC-AUTH-13.6 | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Akses sah dan logout admin | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | role=admin; email=tom@mail.com; password=123456780123456780; route=admin.dashboard (/admin/ringkasan); destination=/admin/ringkasan | Login; periksa redirect; buka route sah; klik Keluar; akses ulang route | HTTP 200; logout dan akses ulang redirect login | HTTP 200; dua redirect /login; 6 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-13.7 | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Guest ke route terlindungi masyarakat | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | Tanpa sesi; route=pengajuan.riwayat (/pengajuan/riwayat); role provider=masyarakat; argumen kredensial provider tidak dipakai untuk login | Bersihkan cookie; buka route role | Redirect login | /login; 3 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-13.8 | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Guest ke route terlindungi pemerintah | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | Tanpa sesi; route=wilayah.index (/pemerintah/wilayah); role provider=pemerintah; argumen kredensial provider tidak dipakai untuk login | Bersihkan cookie; buka route role | Redirect login | /login; 3 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-13.9 | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Guest ke route terlindungi admin | Browser / Dusk | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | Tanpa sesi; route=admin.dashboard (/admin/ringkasan); role provider=admin; argumen kredensial provider tidak dipakai untuk login | Bersihkan cookie; buka route role | Redirect login | /login; 3 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-14.1 | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Validasi server keduanya kosong | Integrasi HTTP Laravel / SQLite | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | POST JSON {"email":"","password":""}; error fields email dan password | POST JSON login.store tanpa validasi browser | HTTP 422 dan error field yang kosong; guest | HTTP 422; error sesuai; assertGuest lulus; 7 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-14.2 | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Validasi server email kosong | Integrasi HTTP Laravel / SQLite | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | POST JSON {"email":"","password":"1234567890"}; error field email | POST JSON login.store tanpa validasi browser | HTTP 422 dan error field yang kosong; guest | HTTP 422; error sesuai; assertGuest lulus; 6 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-14.3 | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Validasi server password kosong | Integrasi HTTP Laravel / SQLite | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | POST JSON {"email":"masyarakat.uji@example.test","password":""}; error field password | POST JSON login.store tanpa validasi browser | HTTP 422 dan error field yang kosong; guest | HTTP 422; error sesuai; assertGuest lulus; 6 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-14.4 | PBI-11/PBI-12 — autentikasi (Jira duplikat) | URL langsung: masyarakat ke pemerintah | Integrasi HTTP Laravel / SQLite | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | actor=masyarakat (masyarakat.uji@example.test); target=wilayah.index (/pemerintah/wilayah); actingAs fixture lalu GET langsung | Autentikasi role fixture; GET route target | HTTP 403 sesuai RequireAdmin/RequirePemerintah/RequireMasyarakat | HTTP 403; 3 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-14.5 | PBI-11/PBI-12 — autentikasi (Jira duplikat) | URL langsung: masyarakat ke admin | Integrasi HTTP Laravel / SQLite | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | actor=masyarakat (masyarakat.uji@example.test); target=admin.dashboard (/admin/ringkasan); actingAs fixture lalu GET langsung | Autentikasi role fixture; GET route target | HTTP 403 sesuai RequireAdmin/RequirePemerintah/RequireMasyarakat | HTTP 403; 3 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-14.6 | PBI-11/PBI-12 — autentikasi (Jira duplikat) | URL langsung: pemerintah ke admin | Integrasi HTTP Laravel / SQLite | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | actor=pemerintah (sep@mail.com); target=admin.dashboard (/admin/ringkasan); actingAs fixture lalu GET langsung | Autentikasi role fixture; GET route target | HTTP 403 sesuai RequireAdmin/RequirePemerintah/RequireMasyarakat | HTTP 403; 3 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-14.7 | PBI-11/PBI-12 — autentikasi (Jira duplikat) | URL langsung: pemerintah ke masyarakat | Integrasi HTTP Laravel / SQLite | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | actor=pemerintah (sep@mail.com); target=pengajuan.riwayat (/pengajuan/riwayat); actingAs fixture lalu GET langsung | Autentikasi role fixture; GET route target | HTTP 403 sesuai RequireAdmin/RequirePemerintah/RequireMasyarakat | HTTP 403; 3 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-14.8 | PBI-11/PBI-12 — autentikasi (Jira duplikat) | URL langsung: admin ke pemerintah | Integrasi HTTP Laravel / SQLite | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | actor=admin (tom@mail.com); target=wilayah.index (/pemerintah/wilayah); actingAs fixture lalu GET langsung | Autentikasi role fixture; GET route target | HTTP 403 sesuai RequireAdmin/RequirePemerintah/RequireMasyarakat | HTTP 403; 3 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-14.9 | PBI-11/PBI-12 — autentikasi (Jira duplikat) | URL langsung: admin ke masyarakat | Integrasi HTTP Laravel / SQLite | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | actor=admin (tom@mail.com); target=pengajuan.riwayat (/pengajuan/riwayat); actingAs fixture lalu GET langsung | Autentikasi role fixture; GET route target | HTTP 403 sesuai RequireAdmin/RequirePemerintah/RequireMasyarakat | HTTP 403; 3 assertions (individual) | PASS | 3 lokasi PDO |
| TC-AUTH-14.10 | PBI-11/PBI-12 — autentikasi (Jira duplikat) | Seeder menyediakan tiga akun login | Integrasi HTTP Laravel / SQLite | Server dan Chrome lokal; SQLite terisolasi; aset build tersedia | DatabaseSeeder asli; users fixture dikosongkan; role wajib; password factory password; target role masyarakat, pemerintah, admin | Hapus akun fixture; jalankan seeder hanya pada SQLite uji; periksa role/hash/login/logout | Akun masyarakat, pemerintah dan admin valid serta dapat login | ERROR QueryException: NOT NULL constraint failed: users.role pada SQLite fixture. Seeder berhenti sebelum pemeriksaan role/hash/login akun hasil seed; 2 assertions (audit kegagalan terbaru) | ERROR | 3 lokasi PDO (terpisah) |

## Ringkasan eksekusi individual awal (historis) dan pemetaan

**39 ID eksekusi dokumentasi dipetakan secara eksplisit ke method/dataset lalu dijalankan satu per satu dalam 39 proses PHPUnit.** Discovery `--list-tests-xml` memeriksa class, method, dan nama dataset asli. Setiap JUnit hasil eksekusi diverifikasi berisi tepat satu testcase dengan class serta nama method/dataset yang dituju. Tidak ada seleksi nol testcase atau beberapa dataset sekaligus. Setiap proses memakai SQLite fixture unik yang dibersihkan sesudahnya.

Jumlah ini bukan 39 requirement atau skenario bisnis independen. Delapan ID merupakan legacy status pengajuan; 31 seleksi autentikasi memiliki cakupan yang beririsan atau menguji lapisan berbeda. Dua case manual tambahan di bawah menjelaskan celah bukti acceptance dan tidak ikut dihitung sebagai eksekusi otomatis.

| ID yang beririsan/komposit | Penjelasan |
|---|---|
| TC-AUTH-01 / 13.4; 02 / 13.5; 03 / 13.6 | Login role yang sama; case 13 menambahkan akses sah, logout dan akses ulang |
| TC-AUTH-04 / 05 / 13.3 | Kredensial gagal beririsan; 13.3 menambahkan perbandingan dua pesan persis identik |
| TC-AUTH-06 / 14.1 | Dua field kosong: validasi browser dan HTTP server |
| TC-AUTH-13.1 / 14.2; 13.2 / 14.3 | Satu field kosong: validasi browser dan HTTP server |
| TC-AUTH-07 / 14.4; 08 / 14.5; 09 / 14.6 | Larangan role yang sama pada browser dan GET HTTP Laravel |
| TC-AUTH-10 / 13.9 | Guest ke Admin yang sama |
| TC-AUTH-11 / 13.4 | Logout User dan akses ulang yang sama |
| TC-AUTH-12 | Satu method tanpa dataset memeriksa hash tiga role dalam foreach; bukan tiga test independen |
| TC-AUTH-13.3 | Satu case membandingkan dua percobaan login; bukan dua dataset yang bisa difilter terpisah |
| TC-AUTH-13.4–13.6 | Masing-masing satu case komposit login, route sah, logout dan akses ulang |
| TC-AUTH-14.10 | Satu case seeder dilanjut pemeriksaan tiga role; pemeriksaan setelah seed tidak tercapai saat seeder ERROR |

Hasil putaran individual awal, sebelum audit terbaru: **35 PASS assertion, 3 FAIL legacy, 1 ERROR seeder, 174 assertions**. Ini hasil historis; lihat audit terbaru untuk ERROR logout Pemerintah. Kelima case yang diaudit ulang tidak lagi memakai status putaran awal pada tabel. Tidak ada skip. Test autentikasi lama: 12 PASS assertion, 52 assertions. PASS redirect implementasi Pemerintah bukan bukti acceptance dashboard yang pemetaannya belum ditentukan.

Deprecation terpisah: **39 proses terdampak, tiga lokasi PDO unik, 117 laporan berulang** (39 × 3), bukan 117 bug berbeda atau assertion gagal. Peringatan juga muncul pada proses yang FAIL/ERROR.

Bukti baru per ID: `tests/Browser/PBI11/.runtime/individual/<ID>.xml`, `<ID>.log`, `<ID>.command.txt` dan padanannya pada PBI12. Manifest/hasil lengkap disimpan di `tests/Browser/PBI12/.runtime/individual/results.json`; XML discovery berada pada folder yang sama. Artefak runtime diabaikan Git. Perintah dan ringkasan hasilnya tetap disimpan di dokumen ini. Hasil gabungan per file dari putaran sebelumnya bukan sumber hasil individual ini.

## Bukti eksekusi tiap case

Setiap perintah berikut benar-benar dijalankan dan diverifikasi memilih satu testcase. Dataset `tidak ada` berarti method tidak memakai data provider; loop internal tidak dihitung sebagai dataset terpisah.

### TC-11-01 — Status pengajuan sendiri dan linimasa

- Pemetaan: legacy status pengajuan, di luar Jira autentikasi PBI-11/PBI-12 terbaru.
- File: [TC01LihatStatusPengajuanTest.php](../tests/Browser/PBI11/TC01LihatStatusPengajuanTest.php).
- Method: `Tests\Browser\PBI11\TC01LihatStatusPengajuanTest::test_owner_can_view_their_own_submission_status`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI11 dengan dua pemohon dan tiket PLTS-PBI11-001/002.
- Input: pemohon-pbi11@example.test; PLTS-PBI11-001.
- Langkah: Login; buka pengajuan.terkirim milik sendiri.
- Expected: Nomor, status Diperiksa dan catatan linimasa tampil.
- Actual: HTTP 200, readyState complete; tiket dan Diperiksa tampil; catatan Pengajuan sedang diperiksa. tidak ada dalam DOM.
- Status: **FAIL**; assertions **6**; exit proses **1**; putaran audit kegagalan terbaru.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: Catatan ada pada fixture tetapi halaman receipt tidak mengambil/merender riwayat. Tidak ada dasar requirement autentikasi terbaru untuk linimasa/alasan penolakan. Tidak menambah delay atau melemahkan assertion.
- Bukti terbaru: [JUnit](../tests/Browser/PBI11/.runtime/failure-review/TC-11-01.xml), [output lengkap](../tests/Browser/PBI11/.runtime/failure-review/TC-11-01.log); bukti DOM/fixture/network dirinci pada bagian audit kegagalan di awal dokumen.

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '^.*::test_owner_can_view_their_own_submission_status$' --log-junit tests/Browser/PBI11/.runtime/failure-review/TC-11-01.xml tests/Browser/PBI11/TC01LihatStatusPengajuanTest.php
```

### TC-11-02 — Tiga catatan riwayat kronologis

- Pemetaan: legacy status pengajuan, di luar Jira autentikasi PBI-11/PBI-12 terbaru.
- File: [TC02WaktuPembaruanStatusTest.php](../tests/Browser/PBI11/TC02WaktuPembaruanStatusTest.php).
- Method: `Tests\Browser\PBI11\TC02WaktuPembaruanStatusTest::test_three_status_history_notes_are_ordered_by_created_at`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI11 dengan dua pemohon dan tiket PLTS-PBI11-001/002.
- Input: Tiga catatan 15–17 November 2026.
- Langkah: Sisipkan catatan ketiga; periksa data; login dan buka bukti pengajuan.
- Expected: Tiga catatan tampil berurutan menurut waktu.
- Actual: Fixture ringkasan diselaraskan menjadi diverifikasi; urutan tiga catatan tetap benar; assertion UI tetap gagal karena catatan pertama tidak tampil.
- Status: **FAIL**; assertions **4**; exit proses **1**; putaran audit kegagalan terbaru.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: Catatan ada pada fixture tetapi halaman receipt tidak mengambil/merender riwayat. Tidak ada dasar requirement autentikasi terbaru untuk linimasa/alasan penolakan. Tidak menambah delay atau melemahkan assertion.
- Bukti terbaru: [JUnit](../tests/Browser/PBI11/.runtime/failure-review/TC-11-02.xml), [output lengkap](../tests/Browser/PBI11/.runtime/failure-review/TC-11-02.log); bukti DOM/fixture/network dirinci pada bagian audit kegagalan di awal dokumen.

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '^.*::test_three_status_history_notes_are_ordered_by_created_at$' --log-junit tests/Browser/PBI11/.runtime/failure-review/TC-11-02.xml tests/Browser/PBI11/TC02WaktuPembaruanStatusTest.php
```

### TC-11-03 — Perubahan status terbaru

- Pemetaan: legacy status pengajuan, di luar Jira autentikasi PBI-11/PBI-12 terbaru.
- File: [TC03TimelineStatusTest.php](../tests/Browser/PBI11/TC03TimelineStatusTest.php).
- Method: `Tests\Browser\PBI11\TC03TimelineStatusTest::test_latest_submission_status_is_displayed_after_update`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI11 dengan dua pemohon dan tiket PLTS-PBI11-001/002.
- Input: Diperiksa → Diverifikasi.
- Langkah: Login; lihat status awal; perbarui fixture; refresh halaman.
- Expected: Diverifikasi tampil dan Diperiksa hilang.
- Actual: Status awal dan status setelah refresh sesuai.
- Status: **PASS**; assertions **6**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI11/.runtime/individual/TC-11-03.xml), [output lengkap](../tests/Browser/PBI11/.runtime/individual/TC-11-03.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI11\\TC03TimelineStatusTest::test_latest_submission_status_is_displayed_after_update$/' --log-junit tests/Browser/PBI11/.runtime/individual/TC-11-03.xml tests/Browser/PBI11/TC03TimelineStatusTest.php
```

### TC-11-04 — Status Ditolak dan alasan

- Pemetaan: legacy status pengajuan, di luar Jira autentikasi PBI-11/PBI-12 terbaru.
- File: [TC04StatusDitolakTest.php](../tests/Browser/PBI11/TC04StatusDitolakTest.php).
- Method: `Tests\Browser\PBI11\TC04StatusDitolakTest::test_rejected_status_and_reason_are_displayed`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI11 dengan dua pemohon dan tiket PLTS-PBI11-001/002.
- Input: Dokumen persyaratan tidak lengkap..
- Langkah: Ubah status dan tambahkan alasan; login; buka pengajuan.
- Expected: Ditolak dan catatan penolakan tampil.
- Actual: HTTP 200, readyState complete; Ditolak tampil; catatan Dokumen persyaratan tidak lengkap. tersimpan pada fixture tetapi tidak ada dalam DOM.
- Status: **FAIL**; assertions **4**; exit proses **1**; putaran audit kegagalan terbaru.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: Catatan ada pada fixture tetapi halaman receipt tidak mengambil/merender riwayat. Tidak ada dasar requirement autentikasi terbaru untuk linimasa/alasan penolakan. Tidak menambah delay atau melemahkan assertion.
- Bukti terbaru: [JUnit](../tests/Browser/PBI11/.runtime/failure-review/TC-11-04.xml), [output lengkap](../tests/Browser/PBI11/.runtime/failure-review/TC-11-04.log); bukti DOM/fixture/network dirinci pada bagian audit kegagalan di awal dokumen.

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '^.*::test_rejected_status_and_reason_are_displayed$' --log-junit tests/Browser/PBI11/.runtime/failure-review/TC-11-04.xml tests/Browser/PBI11/TC04StatusDitolakTest.php
```

### TC-11-05 — Pengajuan pengguna lain

- Pemetaan: legacy status pengajuan, di luar Jira autentikasi PBI-11/PBI-12 terbaru.
- File: [TC05AksesPengajuanPenggunaLainTest.php](../tests/Browser/PBI11/TC05AksesPengajuanPenggunaLainTest.php).
- Method: `Tests\Browser\PBI11\TC05AksesPengajuanPenggunaLainTest::test_user_cannot_access_another_users_submission`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI11 dengan dua pemohon dan tiket PLTS-PBI11-001/002.
- Input: pemohon-lain-pbi11@example.test; pengajuan pemohon.
- Langkah: Login sebagai pemohon lain; akses URL milik pemohon.
- Expected: HTTP 403 atau 404.
- Actual: Akses ditolak sesuai assertion HTTP 403/404.
- Status: **PASS**; assertions **5**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI11/.runtime/individual/TC-11-05.xml), [output lengkap](../tests/Browser/PBI11/.runtime/individual/TC-11-05.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI11\\TC05AksesPengajuanPenggunaLainTest::test_user_cannot_access_another_users_submission$/' --log-junit tests/Browser/PBI11/.runtime/individual/TC-11-05.xml tests/Browser/PBI11/TC05AksesPengajuanPenggunaLainTest.php
```

### TC-11-06 — Pengajuan tidak ditemukan

- Pemetaan: legacy status pengajuan, di luar Jira autentikasi PBI-11/PBI-12 terbaru.
- File: [TC06PengajuanTidakDitemukanTest.php](../tests/Browser/PBI11/TC06PengajuanTidakDitemukanTest.php).
- Method: `Tests\Browser\PBI11\TC06PengajuanTidakDitemukanTest::test_nonexistent_submission_returns_404`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI11 dengan dua pemohon dan tiket PLTS-PBI11-001/002.
- Input: ID 999999.
- Langkah: Login; buka URL ID tidak ada.
- Expected: HTTP 404.
- Actual: HTTP 404.
- Status: **PASS**; assertions **5**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI11/.runtime/individual/TC-11-06.xml), [output lengkap](../tests/Browser/PBI11/.runtime/individual/TC-11-06.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI11\\TC06PengajuanTidakDitemukanTest::test_nonexistent_submission_returns_404$/' --log-junit tests/Browser/PBI11/.runtime/individual/TC-11-06.xml tests/Browser/PBI11/TC06PengajuanTidakDitemukanTest.php
```

### TC-11-07 — Guest mengakses pengajuan

- Pemetaan: legacy status pengajuan, di luar Jira autentikasi PBI-11/PBI-12 terbaru.
- File: [TC07GuestRedirectLoginTest.php](../tests/Browser/PBI11/TC07GuestRedirectLoginTest.php).
- Method: `Tests\Browser\PBI11\TC07GuestRedirectLoginTest::test_guest_is_redirected_to_login`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI11 dengan dua pemohon dan tiket PLTS-PBI11-001/002.
- Input: Tanpa cookie sesi.
- Langkah: Bersihkan sesi; buka URL pengajuan.
- Expected: Redirect login.
- Actual: Redirect /login.
- Status: **PASS**; assertions **3**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI11/.runtime/individual/TC-11-07.xml), [output lengkap](../tests/Browser/PBI11/.runtime/individual/TC-11-07.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI11\\TC07GuestRedirectLoginTest::test_guest_is_redirected_to_login$/' --log-junit tests/Browser/PBI11/.runtime/individual/TC-11-07.xml tests/Browser/PBI11/TC07GuestRedirectLoginTest.php
```

### TC-11-08 — Pemerintah mengakses pengajuan

- Pemetaan: legacy status pengajuan, di luar Jira autentikasi PBI-11/PBI-12 terbaru.
- File: [TC08PemerintahAksesPengajuanTest.php](../tests/Browser/PBI11/TC08PemerintahAksesPengajuanTest.php).
- Method: `Tests\Browser\PBI11\TC08PemerintahAksesPengajuanTest::test_government_user_cannot_access_submission_status`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI11 dengan dua pemohon dan tiket PLTS-PBI11-001/002.
- Input: pemerintah-pbi11@example.test; role pemerintah.
- Langkah: Buat akun pada SQLite PBI11; periksa role/hash; login; periksa nama; buka URL pengajuan.
- Expected: HTTP 403 pada navigasi URL pengajuan.
- Actual: Nama Pemerintah tampil setelah login; URL pengajuan menghasilkan HTTP 403.
- Status: **PASS**; assertions **8**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI11/.runtime/individual/TC-11-08.xml), [output lengkap](../tests/Browser/PBI11/.runtime/individual/TC-11-08.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI11\\TC08PemerintahAksesPengajuanTest::test_government_user_cannot_access_submission_status$/' --log-junit tests/Browser/PBI11/.runtime/individual/TC-11-08.xml tests/Browser/PBI11/TC08PemerintahAksesPengajuanTest.php
```

### TC-AUTH-01 — Login User

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC01LoginMasyarakatTest.php](../tests/Browser/PBI12/TC01LoginMasyarakatTest.php).
- Method: `Tests\Browser\PBI12\TC01LoginMasyarakatTest::test_tc_pbi12_01_login_masyarakat`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: masyarakat.uji@example.test / 1234567890.
- Langkah: Login melalui formulir.
- Expected: Redirect peta wilayah.
- Actual: /peta-wilayah.
- Status: **PASS**; assertions **3**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-01.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-01.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC01LoginMasyarakatTest::test_tc_pbi12_01_login_masyarakat$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-01.xml tests/Browser/PBI12/TC01LoginMasyarakatTest.php
```

### TC-AUTH-02 — Login Pemerintah

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC02LoginPemerintahTest.php](../tests/Browser/PBI12/TC02LoginPemerintahTest.php).
- Method: `Tests\Browser\PBI12\TC02LoginPemerintahTest::test_tc_pbi12_02_login_pemerintah`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: sep@mail.com / 1234567890 (fixture lokal).
- Langkah: Login melalui formulir.
- Expected: Requirement: dashboard; kontrak implementasi: wilayah.index.
- Actual: /pemerintah/wilayah; halaman Kelola data wilayah, bukan dashboard tersendiri; acceptance dashboard belum terverifikasi.
- Status: **PASS**; assertions **3**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-02.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-02.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC02LoginPemerintahTest::test_tc_pbi12_02_login_pemerintah$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-02.xml tests/Browser/PBI12/TC02LoginPemerintahTest.php
```

### TC-AUTH-03 — Login Admin

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC03LoginAdminTest.php](../tests/Browser/PBI12/TC03LoginAdminTest.php).
- Method: `Tests\Browser\PBI12\TC03LoginAdminTest::test_tc_pbi12_03_login_admin`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: tom@mail.com / password fixture ADMIN.
- Langkah: Login melalui formulir.
- Expected: Kelola data operasional sesuai docs/admin.md.
- Actual: /admin/ringkasan; Ringkasan operasional tampil.
- Status: **PASS**; assertions **4**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-03.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-03.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC03LoginAdminTest::test_tc_pbi12_03_login_admin$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-03.xml tests/Browser/PBI12/TC03LoginAdminTest.php
```

### TC-AUTH-04 — Password salah

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC04PasswordSalahTest.php](../tests/Browser/PBI12/TC04PasswordSalahTest.php).
- Method: `Tests\Browser\PBI12\TC04PasswordSalahTest::test_tc_pbi12_04_password_salah`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: Email masyarakat; password-salah.
- Langkah: Kirim form; tunggu #email-error.
- Expected: Email atau kata sandi salah; tetap login.
- Actual: Pesan tampil; tetap /login.
- Status: **PASS**; assertions **4**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-04.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-04.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC04PasswordSalahTest::test_tc_pbi12_04_password_salah$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-04.xml tests/Browser/PBI12/TC04PasswordSalahTest.php
```

### TC-AUTH-05 — Email tidak terdaftar

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC05EmailTidakTerdaftarTest.php](../tests/Browser/PBI12/TC05EmailTidakTerdaftarTest.php).
- Method: `Tests\Browser\PBI12\TC05EmailTidakTerdaftarTest::test_tc_pbi12_05_email_tidak_terdaftar`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: tidak.terdaftar@example.test / 1234567890.
- Langkah: Kirim form; tunggu #email-error.
- Expected: Email atau kata sandi salah; tetap login.
- Actual: Pesan tampil; tetap /login.
- Status: **PASS**; assertions **4**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-05.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-05.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC05EmailTidakTerdaftarTest::test_tc_pbi12_05_email_tidak_terdaftar$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-05.xml tests/Browser/PBI12/TC05EmailTidakTerdaftarTest.php
```

### TC-AUTH-06 — Dua isian kosong

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC06FormLoginKosongTest.php](../tests/Browser/PBI12/TC06FormLoginKosongTest.php).
- Method: `Tests\Browser\PBI12\TC06FormLoginKosongTest::test_tc_pbi12_06_form_login_kosong`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: Email dan password kosong.
- Langkah: Periksa required; tekan Masuk; periksa validity.
- Expected: Browser menolak pengiriman.
- Actual: Kedua valueMissing=true; tetap /login.
- Status: **PASS**; assertions **9**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-06.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-06.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC06FormLoginKosongTest::test_tc_pbi12_06_form_login_kosong$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-06.xml tests/Browser/PBI12/TC06FormLoginKosongTest.php
```

### TC-AUTH-07 — User ke route Pemerintah

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC07MasyarakatAksesPemerintahTest.php](../tests/Browser/PBI12/TC07MasyarakatAksesPemerintahTest.php).
- Method: `Tests\Browser\PBI12\TC07MasyarakatAksesPemerintahTest::test_tc_pbi12_07_masyarakat_dilarang_mengakses_pemerintah`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: Akun masyarakat; /pemerintah/wilayah.
- Langkah: Login; akses URL langsung.
- Expected: HTTP 403.
- Actual: HTTP 403.
- Status: **PASS**; assertions **3**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-07.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-07.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC07MasyarakatAksesPemerintahTest::test_tc_pbi12_07_masyarakat_dilarang_mengakses_pemerintah$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-07.xml tests/Browser/PBI12/TC07MasyarakatAksesPemerintahTest.php
```

### TC-AUTH-08 — User ke route Admin

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC08MasyarakatAksesAdminTest.php](../tests/Browser/PBI12/TC08MasyarakatAksesAdminTest.php).
- Method: `Tests\Browser\PBI12\TC08MasyarakatAksesAdminTest::test_tc_pbi12_08_masyarakat_dilarang_mengakses_admin`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: Akun masyarakat; /admin/ringkasan.
- Langkah: Login; akses URL langsung.
- Expected: HTTP 403.
- Actual: HTTP 403.
- Status: **PASS**; assertions **3**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-08.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-08.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC08MasyarakatAksesAdminTest::test_tc_pbi12_08_masyarakat_dilarang_mengakses_admin$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-08.xml tests/Browser/PBI12/TC08MasyarakatAksesAdminTest.php
```

### TC-AUTH-09 — Pemerintah ke route Admin

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC09PemerintahAksesAdminTest.php](../tests/Browser/PBI12/TC09PemerintahAksesAdminTest.php).
- Method: `Tests\Browser\PBI12\TC09PemerintahAksesAdminTest::test_tc_pbi12_09_pemerintah_dilarang_mengakses_admin`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: Akun pemerintah; /admin/ringkasan.
- Langkah: Login; akses URL langsung.
- Expected: HTTP 403.
- Actual: HTTP 403.
- Status: **PASS**; assertions **3**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-09.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-09.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC09PemerintahAksesAdminTest::test_tc_pbi12_09_pemerintah_dilarang_mengakses_admin$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-09.xml tests/Browser/PBI12/TC09PemerintahAksesAdminTest.php
```

### TC-AUTH-10 — Guest ke Admin

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC10GuestAksesAdminTest.php](../tests/Browser/PBI12/TC10GuestAksesAdminTest.php).
- Method: `Tests\Browser\PBI12\TC10GuestAksesAdminTest::test_tc_pbi12_10_pengunjung_diarahkan_ke_login`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: Tanpa cookie sesi.
- Langkah: Buka URL Admin langsung.
- Expected: Redirect login.
- Actual: /login.
- Status: **PASS**; assertions **3**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-10.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-10.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC10GuestAksesAdminTest::test_tc_pbi12_10_pengunjung_diarahkan_ke_login$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-10.xml tests/Browser/PBI12/TC10GuestAksesAdminTest.php
```

### TC-AUTH-11 — Logout User

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC11LogoutTest.php](../tests/Browser/PBI12/TC11LogoutTest.php).
- Method: `Tests\Browser\PBI12\TC11LogoutTest::test_tc_pbi12_11_logout`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: Akun masyarakat.
- Langkah: Login; klik Keluar; akses /pengajuan/riwayat lagi.
- Expected: Sesi berakhir; akses ulang redirect login.
- Actual: Logout dan akses ulang berakhir di /login.
- Status: **PASS**; assertions **5**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-11.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-11.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC11LogoutTest::test_tc_pbi12_11_logout$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-11.xml tests/Browser/PBI12/TC11LogoutTest.php
```

### TC-AUTH-12 — Hash password ketiga role

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC12PasswordHashTest.php](../tests/Browser/PBI12/TC12PasswordHashTest.php).
- Method: `Tests\Browser\PBI12\TC12PasswordHashTest::test_tc_pbi12_12_password_tersimpan_dalam_bentuk_hash`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: Tiga akun fixture: masyarakat.uji@example.test / 1234567890; sep@mail.com / 1234567890; tom@mail.com / 123456780123456780; password mentah dibaca dari SQLite.
- Langkah: Baca nilai mentah tabel users; bandingkan plaintext; Hash::check.
- Expected: Bukan plaintext; hash dapat diverifikasi.
- Actual: Ketiga nilai berbeda dari plaintext dan lolos Hash::check.
- Status: **PASS**; assertions **8**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-12.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-12.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC12PasswordHashTest::test_tc_pbi12_12_password_tersimpan_dalam_bentuk_hash$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-12.xml tests/Browser/PBI12/TC12PasswordHashTest.php
```

### TC-AUTH-13.1 — Satu isian login kosong

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC13AutentikasiLengkapTest.php](../tests/Browser/PBI12/TC13AutentikasiLengkapTest.php).
- Method: `Tests\Browser\PBI12\TC13AutentikasiLengkapTest::test_single_empty_field_is_rejected`.
- Dataset: `email kosong`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: email=""; password="1234567890"; field kosong=email.
- Langkah: Isi satu field; tekan Masuk; periksa validity.valueMissing.
- Expected: Tetap login; field kosong ditolak.
- Actual: Tetap /login; valueMissing=true.
- Status: **PASS**; assertions **4**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.1.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.1.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC13AutentikasiLengkapTest::test_single_empty_field_is_rejected\ with\ data\ set\ "email\ kosong"$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.1.xml tests/Browser/PBI12/TC13AutentikasiLengkapTest.php
```

### TC-AUTH-13.2 — Satu isian login kosong

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC13AutentikasiLengkapTest.php](../tests/Browser/PBI12/TC13AutentikasiLengkapTest.php).
- Method: `Tests\Browser\PBI12\TC13AutentikasiLengkapTest::test_single_empty_field_is_rejected`.
- Dataset: `password kosong`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: email="masyarakat.uji@example.test"; password=""; field kosong=password.
- Langkah: Isi satu field; tekan Masuk; periksa validity.valueMissing.
- Expected: Tetap login; field kosong ditolak.
- Actual: Tetap /login; valueMissing=true.
- Status: **PASS**; assertions **4**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.2.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.2.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC13AutentikasiLengkapTest::test_single_empty_field_is_rejected\ with\ data\ set\ "password\ kosong"$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.2.xml tests/Browser/PBI12/TC13AutentikasiLengkapTest.php
```

### TC-AUTH-13.3 — Pesan umum identik

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC13AutentikasiLengkapTest.php](../tests/Browser/PBI12/TC13AutentikasiLengkapTest.php).
- Method: `Tests\Browser\PBI12\TC13AutentikasiLengkapTest::test_invalid_credentials_show_exactly_the_same_generic_message`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: email masyarakat.uji@example.test lalu unknown@example.test; keduanya password wrong-password.
- Langkah: Kirim dua login; baca teks #email-error; akses Admin sesudah setiap kegagalan.
- Expected: Kedua pesan persis Email atau kata sandi salah; tidak terautentikasi.
- Actual: Dua pesan identik; akses Admin redirect login.
- Status: **PASS**; assertions **7**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.3.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.3.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC13AutentikasiLengkapTest::test_invalid_credentials_show_exactly_the_same_generic_message$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.3.xml tests/Browser/PBI12/TC13AutentikasiLengkapTest.php
```

### TC-AUTH-13.4 — Akses sah dan logout masyarakat

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC13AutentikasiLengkapTest.php](../tests/Browser/PBI12/TC13AutentikasiLengkapTest.php).
- Method: `Tests\Browser\PBI12\TC13AutentikasiLengkapTest::test_allowed_route_and_logout_for_each_role`.
- Dataset: `masyarakat`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: role=masyarakat; email=masyarakat.uji@example.test; password=1234567890; route=pengajuan.riwayat (/pengajuan/riwayat); destination=/peta-wilayah.
- Langkah: Login; periksa redirect; buka route sah; klik Keluar; akses ulang route.
- Expected: HTTP 200; logout dan akses ulang redirect login.
- Actual: HTTP 200; dua redirect /login.
- Status: **PASS**; assertions **6**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.4.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.4.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC13AutentikasiLengkapTest::test_allowed_route_and_logout_for_each_role\ with\ data\ set\ "masyarakat"$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.4.xml tests/Browser/PBI12/TC13AutentikasiLengkapTest.php
```

### TC-AUTH-13.5 — Akses sah dan logout pemerintah

- Hasil mode terbaru: **headed PASS 6 assertions**, juga PASS dalam regresi TC13; **headless tetap ERROR 4**. Detail di bagian isolasi lanjutan. Status/error dan perintah headless di bawah tetap dicatat sebagai hasil default; tidak mengklaim headless diperbaiki.
- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC13AutentikasiLengkapTest.php](../tests/Browser/PBI12/TC13AutentikasiLengkapTest.php).
- Method: `Tests\Browser\PBI12\TC13AutentikasiLengkapTest::test_allowed_route_and_logout_for_each_role`.
- Dataset: `pemerintah`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: role=pemerintah; email=sep@mail.com; password=1234567890; route=wilayah.index (/pemerintah/wilayah); destination=/pemerintah/wilayah.
- Langkah: Login; periksa redirect; buka route sah; klik Keluar; akses ulang route.
- Expected: HTTP 200; logout dan akses ulang redirect login.
- Actual: ERROR timeout 5 detik menunggu /login; URL tetap /pemerintah/wilayah. Diagnostik browser tidak melihat POST /logout dan probe route terlindungi tetap HTTP 200 dengan akun Pemerintah. Tidak ada respons logout untuk dinilai.
- Status: **ERROR**; assertions **4**; exit proses **2**; putaran audit kegagalan terbaru.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: Timeout bukan bukti respons logout salah: browser tidak mengirim POST /logout. Selector, form owner/action/method dan tombol aktif sudah diperiksa. GET ulang route Pemerintah masih terautentikasi. Akar masalah aktivasi native browser belum terisolasi; assertion/timeout/delay dipertahankan.
- Bukti terbaru: [JUnit](../tests/Browser/PBI12/.runtime/failure-review/TC-AUTH-13.5.xml), [output lengkap](../tests/Browser/PBI12/.runtime/failure-review/TC-AUTH-13.5.log); bukti DOM/fixture/network dirinci pada bagian audit kegagalan di awal dokumen.

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter test_allowed_route_and_logout_for_each_role@pemerintah --log-junit tests/Browser/PBI12/.runtime/failure-review/TC-AUTH-13.5.xml tests/Browser/PBI12/TC13AutentikasiLengkapTest.php
```

### TC-AUTH-13.6 — Akses sah dan logout admin

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC13AutentikasiLengkapTest.php](../tests/Browser/PBI12/TC13AutentikasiLengkapTest.php).
- Method: `Tests\Browser\PBI12\TC13AutentikasiLengkapTest::test_allowed_route_and_logout_for_each_role`.
- Dataset: `admin`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: role=admin; email=tom@mail.com; password=123456780123456780; route=admin.dashboard (/admin/ringkasan); destination=/admin/ringkasan.
- Langkah: Login; periksa redirect; buka route sah; klik Keluar; akses ulang route.
- Expected: HTTP 200; logout dan akses ulang redirect login.
- Actual: HTTP 200; dua redirect /login.
- Status: **PASS**; assertions **6**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.6.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.6.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC13AutentikasiLengkapTest::test_allowed_route_and_logout_for_each_role\ with\ data\ set\ "admin"$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.6.xml tests/Browser/PBI12/TC13AutentikasiLengkapTest.php
```

### TC-AUTH-13.7 — Guest ke route terlindungi masyarakat

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC13AutentikasiLengkapTest.php](../tests/Browser/PBI12/TC13AutentikasiLengkapTest.php).
- Method: `Tests\Browser\PBI12\TC13AutentikasiLengkapTest::test_guest_redirect_for_each_protected_role_route`.
- Dataset: `masyarakat`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: Tanpa sesi; route=pengajuan.riwayat (/pengajuan/riwayat); role provider=masyarakat; argumen kredensial provider tidak dipakai untuk login.
- Langkah: Bersihkan cookie; buka route role.
- Expected: Redirect login.
- Actual: /login.
- Status: **PASS**; assertions **3**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.7.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.7.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC13AutentikasiLengkapTest::test_guest_redirect_for_each_protected_role_route\ with\ data\ set\ "masyarakat"$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.7.xml tests/Browser/PBI12/TC13AutentikasiLengkapTest.php
```

### TC-AUTH-13.8 — Guest ke route terlindungi pemerintah

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC13AutentikasiLengkapTest.php](../tests/Browser/PBI12/TC13AutentikasiLengkapTest.php).
- Method: `Tests\Browser\PBI12\TC13AutentikasiLengkapTest::test_guest_redirect_for_each_protected_role_route`.
- Dataset: `pemerintah`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: Tanpa sesi; route=wilayah.index (/pemerintah/wilayah); role provider=pemerintah; argumen kredensial provider tidak dipakai untuk login.
- Langkah: Bersihkan cookie; buka route role.
- Expected: Redirect login.
- Actual: /login.
- Status: **PASS**; assertions **3**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.8.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.8.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC13AutentikasiLengkapTest::test_guest_redirect_for_each_protected_role_route\ with\ data\ set\ "pemerintah"$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.8.xml tests/Browser/PBI12/TC13AutentikasiLengkapTest.php
```

### TC-AUTH-13.9 — Guest ke route terlindungi admin

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC13AutentikasiLengkapTest.php](../tests/Browser/PBI12/TC13AutentikasiLengkapTest.php).
- Method: `Tests\Browser\PBI12\TC13AutentikasiLengkapTest::test_guest_redirect_for_each_protected_role_route`.
- Dataset: `admin`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: Tanpa sesi; route=admin.dashboard (/admin/ringkasan); role provider=admin; argumen kredensial provider tidak dipakai untuk login.
- Langkah: Bersihkan cookie; buka route role.
- Expected: Redirect login.
- Actual: /login.
- Status: **PASS**; assertions **3**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.9.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.9.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC13AutentikasiLengkapTest::test_guest_redirect_for_each_protected_role_route\ with\ data\ set\ "admin"$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-13.9.xml tests/Browser/PBI12/TC13AutentikasiLengkapTest.php
```

### TC-AUTH-14.1 — Validasi server keduanya kosong

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC14KontrakServerTest.php](../tests/Browser/PBI12/TC14KontrakServerTest.php).
- Method: `Tests\Browser\PBI12\TC14KontrakServerTest::test_server_rejects_blank_credentials`.
- Dataset: `keduanya kosong`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: POST JSON {"email":"","password":""}; error fields email dan password.
- Langkah: POST JSON login.store tanpa validasi browser.
- Expected: HTTP 422 dan error field yang kosong; guest.
- Actual: HTTP 422; error sesuai; assertGuest lulus.
- Status: **PASS**; assertions **7**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.1.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.1.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC14KontrakServerTest::test_server_rejects_blank_credentials\ with\ data\ set\ "keduanya\ kosong"$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.1.xml tests/Browser/PBI12/TC14KontrakServerTest.php
```

### TC-AUTH-14.2 — Validasi server email kosong

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC14KontrakServerTest.php](../tests/Browser/PBI12/TC14KontrakServerTest.php).
- Method: `Tests\Browser\PBI12\TC14KontrakServerTest::test_server_rejects_blank_credentials`.
- Dataset: `email kosong`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: POST JSON {"email":"","password":"1234567890"}; error field email.
- Langkah: POST JSON login.store tanpa validasi browser.
- Expected: HTTP 422 dan error field yang kosong; guest.
- Actual: HTTP 422; error sesuai; assertGuest lulus.
- Status: **PASS**; assertions **6**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.2.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.2.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC14KontrakServerTest::test_server_rejects_blank_credentials\ with\ data\ set\ "email\ kosong"$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.2.xml tests/Browser/PBI12/TC14KontrakServerTest.php
```

### TC-AUTH-14.3 — Validasi server password kosong

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC14KontrakServerTest.php](../tests/Browser/PBI12/TC14KontrakServerTest.php).
- Method: `Tests\Browser\PBI12\TC14KontrakServerTest::test_server_rejects_blank_credentials`.
- Dataset: `password kosong`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: POST JSON {"email":"masyarakat.uji@example.test","password":""}; error field password.
- Langkah: POST JSON login.store tanpa validasi browser.
- Expected: HTTP 422 dan error field yang kosong; guest.
- Actual: HTTP 422; error sesuai; assertGuest lulus.
- Status: **PASS**; assertions **6**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.3.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.3.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC14KontrakServerTest::test_server_rejects_blank_credentials\ with\ data\ set\ "password\ kosong"$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.3.xml tests/Browser/PBI12/TC14KontrakServerTest.php
```

### TC-AUTH-14.4 — URL langsung: masyarakat ke pemerintah

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC14KontrakServerTest.php](../tests/Browser/PBI12/TC14KontrakServerTest.php).
- Method: `Tests\Browser\PBI12\TC14KontrakServerTest::test_role_middleware_rejects_direct_url`.
- Dataset: `masyarakat ke pemerintah`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: actor=masyarakat (masyarakat.uji@example.test); target=wilayah.index (/pemerintah/wilayah); actingAs fixture lalu GET langsung.
- Langkah: Autentikasi role fixture; GET route target.
- Expected: HTTP 403 sesuai RequireAdmin/RequirePemerintah/RequireMasyarakat.
- Actual: HTTP 403.
- Status: **PASS**; assertions **3**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.4.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.4.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC14KontrakServerTest::test_role_middleware_rejects_direct_url\ with\ data\ set\ "masyarakat\ ke\ pemerintah"$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.4.xml tests/Browser/PBI12/TC14KontrakServerTest.php
```

### TC-AUTH-14.5 — URL langsung: masyarakat ke admin

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC14KontrakServerTest.php](../tests/Browser/PBI12/TC14KontrakServerTest.php).
- Method: `Tests\Browser\PBI12\TC14KontrakServerTest::test_role_middleware_rejects_direct_url`.
- Dataset: `masyarakat ke admin`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: actor=masyarakat (masyarakat.uji@example.test); target=admin.dashboard (/admin/ringkasan); actingAs fixture lalu GET langsung.
- Langkah: Autentikasi role fixture; GET route target.
- Expected: HTTP 403 sesuai RequireAdmin/RequirePemerintah/RequireMasyarakat.
- Actual: HTTP 403.
- Status: **PASS**; assertions **3**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.5.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.5.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC14KontrakServerTest::test_role_middleware_rejects_direct_url\ with\ data\ set\ "masyarakat\ ke\ admin"$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.5.xml tests/Browser/PBI12/TC14KontrakServerTest.php
```

### TC-AUTH-14.6 — URL langsung: pemerintah ke admin

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC14KontrakServerTest.php](../tests/Browser/PBI12/TC14KontrakServerTest.php).
- Method: `Tests\Browser\PBI12\TC14KontrakServerTest::test_role_middleware_rejects_direct_url`.
- Dataset: `pemerintah ke admin`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: actor=pemerintah (sep@mail.com); target=admin.dashboard (/admin/ringkasan); actingAs fixture lalu GET langsung.
- Langkah: Autentikasi role fixture; GET route target.
- Expected: HTTP 403 sesuai RequireAdmin/RequirePemerintah/RequireMasyarakat.
- Actual: HTTP 403.
- Status: **PASS**; assertions **3**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.6.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.6.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC14KontrakServerTest::test_role_middleware_rejects_direct_url\ with\ data\ set\ "pemerintah\ ke\ admin"$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.6.xml tests/Browser/PBI12/TC14KontrakServerTest.php
```

### TC-AUTH-14.7 — URL langsung: pemerintah ke masyarakat

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC14KontrakServerTest.php](../tests/Browser/PBI12/TC14KontrakServerTest.php).
- Method: `Tests\Browser\PBI12\TC14KontrakServerTest::test_role_middleware_rejects_direct_url`.
- Dataset: `pemerintah ke masyarakat`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: actor=pemerintah (sep@mail.com); target=pengajuan.riwayat (/pengajuan/riwayat); actingAs fixture lalu GET langsung.
- Langkah: Autentikasi role fixture; GET route target.
- Expected: HTTP 403 sesuai RequireAdmin/RequirePemerintah/RequireMasyarakat.
- Actual: HTTP 403.
- Status: **PASS**; assertions **3**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.7.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.7.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC14KontrakServerTest::test_role_middleware_rejects_direct_url\ with\ data\ set\ "pemerintah\ ke\ masyarakat"$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.7.xml tests/Browser/PBI12/TC14KontrakServerTest.php
```

### TC-AUTH-14.8 — URL langsung: admin ke pemerintah

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC14KontrakServerTest.php](../tests/Browser/PBI12/TC14KontrakServerTest.php).
- Method: `Tests\Browser\PBI12\TC14KontrakServerTest::test_role_middleware_rejects_direct_url`.
- Dataset: `admin ke pemerintah`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: actor=admin (tom@mail.com); target=wilayah.index (/pemerintah/wilayah); actingAs fixture lalu GET langsung.
- Langkah: Autentikasi role fixture; GET route target.
- Expected: HTTP 403 sesuai RequireAdmin/RequirePemerintah/RequireMasyarakat.
- Actual: HTTP 403.
- Status: **PASS**; assertions **3**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.8.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.8.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC14KontrakServerTest::test_role_middleware_rejects_direct_url\ with\ data\ set\ "admin\ ke\ pemerintah"$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.8.xml tests/Browser/PBI12/TC14KontrakServerTest.php
```

### TC-AUTH-14.9 — URL langsung: admin ke masyarakat

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC14KontrakServerTest.php](../tests/Browser/PBI12/TC14KontrakServerTest.php).
- Method: `Tests\Browser\PBI12\TC14KontrakServerTest::test_role_middleware_rejects_direct_url`.
- Dataset: `admin ke masyarakat`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: actor=admin (tom@mail.com); target=pengajuan.riwayat (/pengajuan/riwayat); actingAs fixture lalu GET langsung.
- Langkah: Autentikasi role fixture; GET route target.
- Expected: HTTP 403 sesuai RequireAdmin/RequirePemerintah/RequireMasyarakat.
- Actual: HTTP 403.
- Status: **PASS**; assertions **3**; exit proses **0**.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: tidak ada failure/error pada JUnit individual.
- Bukti: [JUnit](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.9.xml), [output lengkap](../tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.9.log).

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '/^Tests\\Browser\\PBI12\\TC14KontrakServerTest::test_role_middleware_rejects_direct_url\ with\ data\ set\ "admin\ ke\ masyarakat"$/' --log-junit tests/Browser/PBI12/.runtime/individual/TC-AUTH-14.9.xml tests/Browser/PBI12/TC14KontrakServerTest.php
```

### TC-AUTH-14.10 — Seeder menyediakan tiga akun login

- Pemetaan: autentikasi bersama PBI-11/PBI-12; Jira duplikat.
- File: [TC14KontrakServerTest.php](../tests/Browser/PBI12/TC14KontrakServerTest.php).
- Method: `Tests\Browser\PBI12\TC14KontrakServerTest::test_database_seeder_provides_login_accounts_for_all_roles`.
- Dataset: `tidak ada`.
- Prasyarat: Server/ChromeDriver lokal; aset build tersedia; setUp memastikan koneksi dan path SQLite unik sebelum fixture dibuat. Fixture PBI12 dengan akun aktif masyarakat, pemerintah dan admin.
- Input: DatabaseSeeder asli; users fixture dikosongkan; role wajib; password factory password; target role masyarakat, pemerintah, admin.
- Langkah: Hapus akun fixture; jalankan seeder hanya pada SQLite uji; periksa role/hash/login/logout.
- Expected: Akun masyarakat, pemerintah dan admin valid serta dapat login.
- Actual: ERROR QueryException: NOT NULL constraint failed: users.role pada SQLite fixture. Seeder berhenti sebelum pemeriksaan role/hash/login akun hasil seed.
- Status: **ERROR**; assertions **2**; exit proses **2**; putaran audit kegagalan terbaru.
- Deprecation terpisah: **3** lokasi (config/database.php:58, :59, :80).
- Detail kegagalan: DatabaseSeeder.php:18 hanya membuat Test User tanpa role; UserFactory juga tidak menentukan role. Tidak ada default role yang ditambahkan dari test; validitas login tiga akun seed belum dapat diuji.
- Bukti terbaru: [JUnit](../tests/Browser/PBI12/.runtime/failure-review/TC-AUTH-14.10.xml), [output lengkap](../tests/Browser/PBI12/.runtime/failure-review/TC-AUTH-14.10.log); bukti DOM/fixture/network dirinci pada bagian audit kegagalan di awal dokumen.

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '^.*::test_database_seeder_provides_login_accounts_for_all_roles$' --log-junit tests/Browser/PBI12/.runtime/failure-review/TC-AUTH-14.10.xml tests/Browser/PBI12/TC14KontrakServerTest.php
```

## FAIL — ketidaksesuaian pemetaan legacy

| TC | Hasil aktual | Penyebab berdasarkan audit source | File aplikasi terkait (hanya dibaca) |
|---|---|---|---|
| TC-11-01 | Nomor/status tampil, catatan linimasa tidak tampil | Halaman bukti pengiriman menampilkan status terkini; tidak merender catatan riwayat | app/Http/Controllers/PengajuanController.php:84; resources/views/pengajuan/terkirim.blade.php:11 |
| TC-11-02 | Data fixture terurut; catatan pertama tidak ditemukan pada UI | Assertion UI mengharapkan linimasa pada halaman bukti yang tidak menyediakannya | app/Http/Controllers/PengajuanController.php:84; resources/views/pengajuan/terkirim.blade.php:7 |
| TC-11-04 | Ditolak tampil, alasan penolakan tidak tampil | Halaman bukti tidak mengambil/merender catatan penolakan | app/Http/Controllers/PengajuanController.php:84; resources/views/pengajuan/terkirim.blade.php:11 |

Ketiga FAIL tetap dilaporkan sebagai hasil eksekusi nyata. Linimasa, urutan catatan status, dan alasan penolakan berasal dari skenario legacy sebelumnya; ketiganya tidak tercantum dalam deskripsi Jira autentikasi PBI-11/PBI-12 terbaru. Karena itu tidak dinyatakan sebagai bug acceptance kedua PBI. Assertion tetap dipertahankan, tanpa skip, tanpa perubahan fitur aplikasi, dan tanpa perbaikan PBI lain. Komentar pada ketiga file test kini menjelaskan pemetaan legacy ini.

## ERROR — requirement seeder autentikasi

`TC14KontrakServerTest::test_database_seeder_provides_login_accounts_for_all_roles` berhenti dengan **NOT NULL constraint failed: users.role** setelah dua assertion penjagaan SQLite. `database/seeders/DatabaseSeeder.php:18` hanya membuat satu Test User dan tidak mengisi role; `database/factories/UserFactory.php:24` juga tidak menyediakan role. Pada skema fixture dengan kolom role wajib, INSERT seeder gagal. Seeder belum menyediakan akun awal masyarakat, pemerintah, dan admin sesuai Jira.

Seeder diuji hanya pada SQLite unik yang telah diverifikasi oleh setUp. Akun fixture dihapus dahulu supaya tidak menutupi kekurangan seeder. Pemeriksaan role/hash/login/logout akun hasil seeder yang terletak sesudah pemanggilan seeder **belum dapat dijalankan** karena exception tersebut; tidak diklaim PASS. Implementasi seeder/factory tidak diubah. `docs/admin.md` menyatakan tidak ada akun contoh bawaan: konflik kebijakan dengan Jira dicatat tanpa mengubah dokumentasi aplikasi tersebut.

## Deprecation — terpisah dari FAIL/ERROR

| Lokasi | Peringatan aktual |
|---|---|
| config/database.php:58 | PDO::MYSQL_ATTR_SSL_CA deprecated sejak PHP 8.5 |
| config/database.php:59 | PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT deprecated sejak PHP 8.5 |
| config/database.php:80 | PDO::MYSQL_ATTR_SSL_CA deprecated sejak PHP 8.5 |

Output `--display-deprecations` membuktikan ketiga sumber ini. Config tidak diubah dan peringatannya tidak disembunyikan untuk membuat hasil terlihat bersih.

## Perbaikan test dan batas bukti

- Fixture PBI11 dilengkapi tabel wilayah karena log sebelumnya membuktikan `no such table: wilayah` saat membuka peta/Pemerintah. Fixture PBI12 dilengkapi riwayat_status_pengajuan untuk menguji route User yang dilindungi. Semua skema tetap hanya berada di SQLite test.
- Login menunggu navigasi selesai; login gagal menunggu #email-error. Browser ditutup setiap tearDown agar dataset yang membangun ulang fixture tidak berbagi browser. Test logout Pemerintah menunggu permintaan daftar wilayah selesai. Pada putaran awal dataset TC13 melewati assertion; setelah delay pengamatan, dataset Pemerintah kembali ERROR. Audit terbaru membuktikan tidak ada POST logout pada reproduksi browser, sementara kontrak HTTP server lulus; penyebab akhir interaksi UI/browser belum terisolasi.
- TC-11-03 memeriksa Diperiksa sebelum perubahan lalu Diverifikasi sesudah refresh. TC-11-08 memeriksa role dan hash akun Pemerintah, nama pada browser, URL pengajuan fixture, serta responseStatus navigasi tersebut: hasil 403, bukan 200.
- TC13 melengkapi validasi browser, pesan error persis identik, akses sah, guest dan logout setiap role. TC14 melengkapi validasi server dan matriks HTTP 403 berdasarkan middleware proyek, serta mengungkap ERROR seeder. TC12 membaca hash mentah dari database dan memverifikasinya menggunakan Hash::check.
- Struktur database operasional tidak diakses. Migrasi awal repository tidak memuat role dan dokumentasi menjelaskan skema eksternal. Fixture dengan kolom role dan email unik bukan bukti skema deployment. TC manual pemeriksaan skema database operasional tetap **Belum dijalankan**.
- Tujuan login Pemerintah `/pemerintah/wilayah` berasal dari `app/Http/Controllers/LoginController.php:35`. Assertion lama memverifikasi tujuan implementasi itu; requirement Jira menyebut dashboard tanpa URL. Acceptance istilah dashboard tetap **belum terverifikasi**, tanpa mengarang tujuan baru. Ini dicatat terpisah dari hasil assertion dan tidak menghambat penyelesaian audit test/dokumen sesuai koreksi pengguna.
- Error daftar wilayah yang sempat terlihat pada screenshot terdahulu hanya dicatat sebagai observasi di luar assertion autentikasi. Tidak diperbaiki dan tidak dimasukkan sebagai FAIL PBI-11/PBI-12.

## Perintah validasi dan reproduksi

Runner pendukung test membaca ID pada tabel dokumen, memetakan provider secara eksplisit, memeriksa discovery dan menjalankan seleksi satu per satu. Regex filter memakai ^ dan $ serta nama dataset lengkap. Setiap hasil JUnit harus memuat tepat satu class/method/dataset yang dimaksud. Runner menyimpan hasil tiap ID dan melanjutkan case independen setelah FAIL/ERROR.

```sh
php vendor/bin/phpunit --do-not-cache-result --display-deprecations --filter '^.*::test_database_seeder_provides_login_accounts_for_all_roles$' --log-junit tests/Browser/PBI12/.runtime/failure-review/TC-AUTH-14.10.xml tests/Browser/PBI12/TC14KontrakServerTest.php
```

Untuk menguji satu ID saja, salin perintah persis pada bagian ID itu. Regex class berisi backslash yang di-escape untuk PCRE dan sudah dikutip untuk shell. `--do-not-cache-result` mencegah perubahan cache PHPUnit root. `--display-deprecations` mempertahankan bukti peringatan. Database/server/ChromeDriver dibuat oleh base class dan dibersihkan setelah proses. Kegagalan seeder tidak menghambat case lain karena fixture masing-masing terpisah. Tidak menjalankan seluruh suite proyek atau mengganti .env.

Semua testcase dijalankan individual; tidak ada assertion yang diubah pada putaran ini. Sintaks runner, git diff --check, dan konsistensi seluruh command/dataset/status/assertions pada dokumen terhadap JUnit individual telah diperiksa dan lulus. Tabel akhir memuat 39 ID otomatis dan dua ID manual; keduanya tidak dicampur sebagai jumlah test yang dieksekusi.

## Daftar perubahan

Diubah: PBI11/PBI11TestCase.php, TC01LihatStatusPengajuanTest.php, TC02WaktuPembaruanStatusTest.php, TC03TimelineStatusTest.php, TC04StatusDitolakTest.php, TC08PemerintahAksesPengajuanTest.php; PBI12/PBI12TestCase.php, TC04PasswordSalahTest.php, TC05EmailTidakTerdaftarTest.php, TC06FormLoginKosongTest.php, TC12PasswordHashTest.php.

Ditambahkan: kedua folder PBI memiliki .gitignore untuk .runtime; PBI12/TC13AutentikasiLengkapTest.php, PBI12/TC14KontrakServerTest.php, PBI12/run_individual_cases.py; dokumen ini pada lokasi yang diminta dalam instruksi terbaru. Semua perubahan source test berada di dua folder yang diizinkan. Tidak ada perubahan controller, model, middleware, route, migration, seeder, config, .env atau phpunit.xml. File dihapus/diganti nama: **tidak ada**. Perubahan awal pengguna pada PBI12/TC08 tetap dipertahankan.

Pada putaran individual ini ditambahkan runner pendukung test dan diperbarui dokumen ini. Assertion, fixture dan file aplikasi tidak diubah. Perubahan awal pengguna pada PBI12/TC08 tetap dipertahankan. Tidak ada file yang dihapus atau diganti nama.

## Case tanpa bukti otomatis acceptance

Kedua ID manual berikut memperjelas celah yang sebelumnya hanya berupa catatan. Keduanya tidak termasuk 39 seleksi PHPUnit dan belum dijalankan manual.

### TC-MAN-01 — Struktur users pada skema aplikasi sebenarnya

- Requirement: PBI-11/PBI-12; id, name, email unik, password, role, created_at, updated_at.
- Method/dataset: tidak ada test otomatis terhadap skema deployment; skema yang dibuat sendiri oleh base test bukan bukti skema aplikasi.
- Prasyarat: salinan skema aplikasi yang disanitasi dan disetujui sebagai acuan; database uji disposable di folder runtime yang diizinkan, tanpa koneksi produksi. Acuan tersebut belum tersedia pada audit ini.
- Input: daftar kolom wajib; dua akun sintetis ber-email sama `duplicate@example.test`, role valid dan password hash Laravel.
- Langkah manual: (1) siapkan database kosong dari salinan skema acuan, bukan pembentuk fixture Dusk; (2) buka metadata users, pada SQLite gunakan `PRAGMA table_info(users)`; (3) periksa semua kolom wajib, primary key id dan nullability; (4) periksa `PRAGMA index_list(users)` dan `PRAGMA index_info(<nama_index>)` untuk unique email; (5) hanya pada database disposable, coba simpan dua akun dengan email sama; (6) catat error constraint aktual dan rollback/hapus data uji.
- Perintah yang dijalankan: tidak ada untuk case manual ini.
- Expected: semua kolom tersedia; id primary key; email unik dan duplikat ditolak.
- Actual/status: **BELUM DIJALANKAN**. Audit migrasi awal tidak menemukan role, tetapi skema eksternal sebenarnya belum diperiksa; audit source bukan hasil pengujian deployment.
- Hambatan: salinan skema acuan belum tersedia; database utama tidak diakses. Deprecation: belum diamati karena belum dieksekusi.

### TC-MAN-02 — Acceptance dashboard Pemerintah

- Requirement: PBI-11/PBI-12; Pemerintah diarahkan ke dashboard setelah login.
- Method/dataset: TC-AUTH-02 dan TC-AUTH-13.5 memeriksa redirect implementasi wilayah.index; belum ada assertion yang membuktikan halaman itu adalah dashboard yang dimaksud Jira. Tidak mengarang URL baru.
- Prasyarat: pemetaan dashboard disepakati berdasarkan acceptance/kebijakan proyek; server manual lokal memakai SQLite fixture terisolasi dan akun Pemerintah aktif; browser tanpa cookie/intended URL. Server Dusk individual sudah dibersihkan, bukan sesi manual yang masih aktif.
- Input: fixture `sep@mail.com`, password `1234567890`, role pemerintah; base URL server uji manual.
- Langkah manual: (1) catat route dan konten dashboard dari acuan acceptance yang disepakati; (2) siapkan server fixture lokal untuk sesi manual; (3) buka /login tanpa sesi, isi akun Pemerintah dan tekan Masuk; (4) catat URL akhir, HTTP status, judul dan konten; (5) cocokkan halaman dengan acuan dashboard, bukan hanya menu atau role; (6) simpan bukti dan logout.
- Perintah yang dijalankan: tidak ada pengujian manual acceptance ini; bukti otomatis redirect ada pada TC-AUTH-02 dan TC-AUTH-13.5.
- Expected: dashboard yang disepakati terbuka setelah login; URL tidak diasumsikan dari deskripsi Jira yang tidak memberi URL.
- Actual/status: **BELUM DIJALANKAN** untuk acceptance dashboard. Fakta otomatis terpisah: login menuju /pemerintah/wilayah dan halaman Kelola data wilayah dapat diakses.
- Hambatan: pemetaan istilah dashboard belum tersedia. Deprecation manual belum diamati; peringatan otomatis tercatat pada case terkait.

## Tabel ringkas seluruh hasil individual

Baris adalah ID dokumentasi/eksekusi, bukan klaim requirement bisnis unik. Status memakai hasil default headless terakhir per ID: lima case dari audit kegagalan dan 34 case dari putaran sebelumnya; hasil headed TC13 disajikan terpisah pada bagian isolasi lanjutan; tabel bukan satu eksekusi ulang seluruh suite. PASS hanya menyatakan assertion lulus; batas acceptance dan cakupan beririsan dijelaskan di atas.

| ID | Skenario | Status | Assertions | Deprecation terpisah |
|---|---|---|---:|---|
| TC-11-01 | Status pengajuan sendiri dan linimasa | FAIL | 6 | 3 lokasi PDO |
| TC-11-02 | Tiga catatan riwayat kronologis | FAIL | 4 | 3 lokasi PDO |
| TC-11-03 | Perubahan status terbaru | PASS | 6 | 3 lokasi PDO |
| TC-11-04 | Status Ditolak dan alasan | FAIL | 4 | 3 lokasi PDO |
| TC-11-05 | Pengajuan pengguna lain | PASS | 5 | 3 lokasi PDO |
| TC-11-06 | Pengajuan tidak ditemukan | PASS | 5 | 3 lokasi PDO |
| TC-11-07 | Guest mengakses pengajuan | PASS | 3 | 3 lokasi PDO |
| TC-11-08 | Pemerintah mengakses pengajuan | PASS | 8 | 3 lokasi PDO |
| TC-AUTH-01 | Login User | PASS | 3 | 3 lokasi PDO |
| TC-AUTH-02 | Login Pemerintah | PASS | 3 | 3 lokasi PDO |
| TC-AUTH-03 | Login Admin | PASS | 4 | 3 lokasi PDO |
| TC-AUTH-04 | Password salah | PASS | 4 | 3 lokasi PDO |
| TC-AUTH-05 | Email tidak terdaftar | PASS | 4 | 3 lokasi PDO |
| TC-AUTH-06 | Dua isian kosong | PASS | 9 | 3 lokasi PDO |
| TC-AUTH-07 | User ke route Pemerintah | PASS | 3 | 3 lokasi PDO |
| TC-AUTH-08 | User ke route Admin | PASS | 3 | 3 lokasi PDO |
| TC-AUTH-09 | Pemerintah ke route Admin | PASS | 3 | 3 lokasi PDO |
| TC-AUTH-10 | Guest ke Admin | PASS | 3 | 3 lokasi PDO |
| TC-AUTH-11 | Logout User | PASS | 5 | 3 lokasi PDO |
| TC-AUTH-12 | Hash password ketiga role | PASS | 8 | 3 lokasi PDO |
| TC-AUTH-13.1 | Satu isian login kosong | PASS | 4 | 3 lokasi PDO |
| TC-AUTH-13.2 | Satu isian login kosong | PASS | 4 | 3 lokasi PDO |
| TC-AUTH-13.3 | Pesan umum identik | PASS | 7 | 3 lokasi PDO |
| TC-AUTH-13.4 | Akses sah dan logout masyarakat | PASS | 6 | 3 lokasi PDO |
| TC-AUTH-13.5 | Akses sah/logout Pemerintah — headless (headed PASS 6) | ERROR | 4 | 3 lokasi PDO |
| TC-AUTH-13.6 | Akses sah dan logout admin | PASS | 6 | 3 lokasi PDO |
| TC-AUTH-13.7 | Guest ke route terlindungi masyarakat | PASS | 3 | 3 lokasi PDO |
| TC-AUTH-13.8 | Guest ke route terlindungi pemerintah | PASS | 3 | 3 lokasi PDO |
| TC-AUTH-13.9 | Guest ke route terlindungi admin | PASS | 3 | 3 lokasi PDO |
| TC-AUTH-14.1 | Validasi server keduanya kosong | PASS | 7 | 3 lokasi PDO |
| TC-AUTH-14.2 | Validasi server email kosong | PASS | 6 | 3 lokasi PDO |
| TC-AUTH-14.3 | Validasi server password kosong | PASS | 6 | 3 lokasi PDO |
| TC-AUTH-14.4 | URL langsung: masyarakat ke pemerintah | PASS | 3 | 3 lokasi PDO |
| TC-AUTH-14.5 | URL langsung: masyarakat ke admin | PASS | 3 | 3 lokasi PDO |
| TC-AUTH-14.6 | URL langsung: pemerintah ke admin | PASS | 3 | 3 lokasi PDO |
| TC-AUTH-14.7 | URL langsung: pemerintah ke masyarakat | PASS | 3 | 3 lokasi PDO |
| TC-AUTH-14.8 | URL langsung: admin ke pemerintah | PASS | 3 | 3 lokasi PDO |
| TC-AUTH-14.9 | URL langsung: admin ke masyarakat | PASS | 3 | 3 lokasi PDO |
| TC-AUTH-14.10 | Seeder menyediakan tiga akun login | ERROR | 2 | 3 lokasi PDO |
| TC-MAN-01 | Struktur users pada skema acuan | BELUM DIJALANKAN | — | Belum diamati |
| TC-MAN-02 | Acceptance dashboard Pemerintah | BELUM DIJALANKAN | — | Belum diamati |

Gabungan hasil terakhir per ID dari putaran berbeda: **39 ID otomatis, 172 assertions: 34 PASS, 3 FAIL legacy, 2 ERROR (logout Pemerintah dan seeder)** dalam mode default/headless. Regresi TC13 dengan browser terlihat adalah hasil terpisah: 9 PASS, 42 assertions; bukan perbaikan hasil default headless. Putaran audit kegagalan sebelumnya menjalankan **5 case, 20 assertions: 3 FAIL dan 2 ERROR**; tidak menjalankan ulang 39 case. Tambahan manual: **2 BELUM DIJALANKAN**. Deprecation: **3 sumber unik**; lima proses audit kegagalan sebelumnya serta eksekusi isolasi/regresi TC13 melaporkannya secara terpisah. Tidak ada skip, pelemahan assertion, perubahan aplikasi atau perbaikan PBI lain. Pengujian dan dokumentasi selesai dalam lingkup yang diizinkan; bug dan pemetaan acceptance yang belum terverifikasi tetap dicatat.

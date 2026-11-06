# PBI 08 / SCRUM-13: ringkasan dan pengiriman pengajuan

Branch pekerjaan: `Rafi-PBI-07,-PBI-08`. Dasar pekerjaan sudah diselaraskan dengan
`origin/main` yang memisahkan halaman lokasi dan bukti foto.

## Alur

1. Masyarakat menyimpan data lokasi, lalu mengunggah bukti foto pada draft miliknya.
2. Tombol Lanjut ke ringkasan aktif setelah minimal satu foto tersimpan.
3. `/pengajuan/lokasi/ringkasan` membaca lokasi, metadata foto, dan identitas akun.
   Tautan Ubah data lokasi dan Kembali ke bukti foto mempertahankan draft yang sama.
4. Pengusul menyetujui pernyataan kebenaran data, kemudian memilih Kirim pengajuan.
5. POST `/pengajuan/lokasi/kirim` mengunci akun dan pengajuan, memeriksa pemilik,
   lokasi tersimpan, jumlah foto (1–5), serta persetujuan. Satu transaksi mengganti
   tiket `DRAFT-...` menjadi `PLTS-YYYYMMDD-ULID`, menetapkan status `diajukan`,
   memperbarui waktu status, dan menulis catatan status awal.
6. `/pengajuan/lokasi/terkirim/{id}` menampilkan nomor pengajuan dan status hanya
   kepada pemilik. Refresh atau pengiriman ulang POST yang sama tidak membuat
   nomor baru atau mencatat status dua kali. Pengajuan terkirim tidak dapat diedit
   melalui form draft; foto privat tetap dapat dibuka pemilik melalui endpoint foto.

Pencatatan awal pada `riwayat_status_pengajuan` memakai `status_sebelumnya = NULL`
karena enum tabel tersebut tidak memiliki nilai draft. `status_baru = diajukan`,
`jenis_tindakan = pengajuan_baru`, dan `diubah_oleh` adalah akun pengusul. Persetujuan
dicatat dalam catatan awal itu. Nomor tiket dilindungi indeks unik yang sudah ada.
Kegagalan salah satu penulisan membatalkan seluruh transaksi.

## Batas cakupan dan data

Tidak ada perubahan skema atau migrasi pada TiDB bersama. Implementasi menampilkan
data nyata yang sudah didukung aplikasi. Jumlah rumah tangga, jarak jaringan,
uraian kondisi tambahan, dan nomor telepon pada mockup tidak ditambahkan karena
belum dikumpulkan oleh form dan belum ada pada skema pengguna/lokasi. Koordinat
tetap opsional; ringkasan menyebutkan jika tidak diisi. Aturan minimal satu foto
dipertahankan dari PBI-07. Pratinjau foto memakai URL sementara disk R2 privat.

Pengiriman memeriksa metadata foto yang telah tersimpan, tidak mengirim ulang
gambar atau meminta pemeriksaan file ke R2. File R2 yang dihapus di luar aplikasi
masih dapat menyebabkan pratinjau gambar gagal. Tidak ditambahkan fitur hapus foto,
riwayat pengguna, notifikasi, verifikasi pemerintah, atau perubahan status lanjutan.

## Verifikasi

```powershell
php artisan test --filter='PengajuanSubmissionTest|LokasiPengajuanTest|R2StorageTest'
npm run build
npx playwright test --config=playwright.location.config.js
```

Tes backend memakai SQLite di memori dan R2 palsu. Tes browser memakai database
SQLite sementara, akun dan draft fixture, serta gambar yang dipenuhi secara lokal
di browser. Tes tidak menulis ke TiDB atau Cloudflare R2 asli.

Pemeriksaan manual: lanjutkan draft yang memiliki foto, periksa data ringkasan,
uji tombol kirim sebelum/sesudah mencentang pernyataan, kirim satu pengajuan uji,
lalu refresh halaman hasil dan pastikan nomor tetap sama. Pengiriman manual pada
environment biasa menulis pengajuan ke database yang dikonfigurasi di `.env`.

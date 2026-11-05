# PBI 06 Data lokasi pengajuan

Halaman `/pengajuan/lokasi` tersedia bagi akun dengan role `masyarakat` melalui
autentikasi yang sudah ada. Menu Pengajuan PLTS membuka draft lokasi terakhir milik
akun tersebut. Pembuatan akun tetap mengikuti pengelolaan akun proyek.

Form memuat provinsi Jawa Barat, kabupaten/kota, kecamatan, desa/kelurahan, alamat
rinci, lintang dan bujur. Pilihan administratif menggunakan indeks referensi BIG
yang sudah tersimpan di `public/data/map-boundaries`. Titik dapat dipilih dengan
klik peta, menggeser penanda, tombol titik tengah, atau mengetik koordinat. Peta
dasar memakai konfigurasi tile aplikasi. Koordinat opsional; kedua kolom boleh
kosong dan disimpan sebagai NULL. Jika diisi, lintang dan bujur wajib berpasangan
dan berada di batas referensi Jawa Barat. Tombol Hapus titik mengosongkan keduanya.
Batas desa ditampilkan sebagai panduan, bukan validasi survei.

Simpan draft lokasi menulis `pengajuan_plts` berstatus `draft` dan satu
`lokasi_pengajuan` dalam transaksi. Kode internal draft memakai awalan `DRAFT-`.
Alamat rinci dan empat komponen administratif digabung pada `alamat_deskriptif`
sesuai skema yang sudah ada, lalu dipisahkan kembali saat form dibuka. Draft tidak
langsung ditautkan ke data `wilayah`; wilayah yang belum terdata tetap dapat
dilaporkan. Pengiriman ulang memperbarui draft aktif yang sama. Hanya pemilik yang
dapat mengubahnya dan hanya selama statusnya masih `draft`.

Prasyarat database bersama: enum `pengajuan_plts.status_terkini` telah diperluas
dengan nilai `draft`, dengan seluruh nilai lama dan default `diajukan` dipertahankan.
Kolom `lokasi_pengajuan.latitude` dan `longitude` perlu mengizinkan NULL, dengan
presisi tetap DECIMAL(10,7) dan DECIMAL(11,7). Tidak ada migrasi Laravel yang
dijalankan pada database bersama.

Cakupan fitur hanya data lokasi dan penyimpanan draft. Halaman tidak menyediakan
unggah bukti, pengisian kebutuhan/identitas tambahan, pengiriman, riwayat,
notifikasi, atau perubahan status penanganan.

Pengujian backend terisolasi menggunakan SQLite dalam memori:

```powershell
php artisan test --filter=LokasiPengajuanTest
npm run build
npx playwright test --config=playwright.location.config.js
```

Suite ini membuat skema uji sendiri tanpa migrasi atau penulisan ke TiDB bersama.
Suite browser menjalankan server terpisah pada port 8016 dengan database SQLite
sementara dan akun uji, kemudian menghapus database uji setelah selesai.

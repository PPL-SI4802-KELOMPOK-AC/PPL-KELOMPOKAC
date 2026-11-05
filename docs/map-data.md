# Peta wilayah berbasis TiDB

Pembaruan 5 November 2026 memakai tabel online `terangdesa.wilayah` sebagai sumber
seluruh data wilayah pada peta. Endpoint mempertahankan akses publik yang sudah ada.
Pengelolaan tetap memerlukan akun Pemerintah.

## Data wilayah

`MapData` membaca model Wilayah melalui koneksi default Laravel. Pencarian dan
filter diterapkan pada snapshot baris database yang sama untuk daftar, ringkasan,
dan penanda. Daftar dipaginasi 15 baris. Koordinat viewport hanya membatasi penanda,
sehingga wilayah lain tetap dapat dicari pada daftar.

| Nilai API | Kolom atau sumber TiDB |
| --- | --- |
| Identitas, kode, nama, alamat | id, kode_referensi, nama_wilayah, alamat_deskriptif |
| Keluarga belum berlistrik | jumlah_kk_terdampak |
| Kondisi manual | kondisi_listrik |
| Jumlah pengguna PLN | NULL karena kolom belum tersedia |
| Sumber | sumber_data |
| Periode | Tahun data_per_tanggal, kemudian keterangan periode/tahun pada sumber |
| Koordinat | latitude dan longitude |
| Kabupaten/kecamatan | Komponen eksplisit pada alamat lengkap tersimpan |

Kode kabupaten berasal dari awalan kode desa Kemendagri. Nama administratif hanya
dipisahkan bila alamat berakhir dengan komponen kabupaten/kota dan `JAWA BARAT` yang
jelas. Alamat lain tetap ditampilkan utuh. Data yang tidak tersedia tetap NULL.
Tidak ada pencocokan ke CSV, layanan desa, JSON indikator, atau titik lokal lain
saat halaman dibuka. Pengayaan koordinat sekali jalan dijelaskan di bawah.

Kategori desa ditentukan jumlah KK. Lebih dari nol memakai merah, nol
memakai hijau, dan NULL memakai abu-abu. Pengguna PLN tidak dipakai
untuk menyimpulkan seluruh warga sudah berlistrik. Kondisi manual terpisah dari
kategori jumlah keluarga. Tahun dan sumber ditampilkan sesuai setiap catatan.

## Koordinat dan geografi

Kedua koordinat harus tersedia, numerik, sesuai rentang global, dan berada pada
geometri batas referensi Jawa Barat. Pasangan tidak valid tidak menghasilkan titik.
Wilayah tanpa lokasi tetap dapat dibaca melalui daftar. Penanda merupakan lokasi
referensi desa, bukan rumah warga atau titik calon pemasangan PLTS.

Tampilan terbaru menggunakan poligon administratif, tanpa bulatan penanda.
Koordinat lama tetap tersimpan untuk kebutuhan aplikasi, tetapi tidak menentukan
keberadaan atau warna poligon. Field API `features` tetap tersedia demi kompatibilitas;
halaman peta menggunakan field `areas`.

Geografi yang tetap diperlukan:

- `public/data/jawa-barat.geojson`, batas provinsi dari geoBoundaries/OSM/Wambacher,
  representasi 2017, ODbL 1.0. Digunakan untuk outline, mask, dan validasi cakupan.
- Tile OpenStreetMap dan atribusinya, serta Leaflet untuk navigasi/zoom/keyboard.
- `map:geography`, khusus pembaruan aset batas provinsi.

Sumber dan lisensi:

- <https://www.geoboundaries.org/>
- <https://opendatacommons.org/licenses/odbl/1-0/>
- <https://www.openstreetmap.org/copyright>

## Pembaruan dan error

Hasil query wilayah tidak di-cache. CRUD Pemerintah, dashboard, dan peta membaca
database yang sama. Hanya geometri batas statis yang di-cache berdasarkan hash file.
Request lama dibatalkan ketika filter berubah. Loading berakhir pada sukses, error,
atau timeout. Error data tidak diubah menjadi hasil kosong. Retry data, batas, dan
tile ditangani masing-masing. Reset membersihkan filter dan pilihan detail.

Pembaca CSV, JSON indikator, command persiapan/impor, serta fallback titik BIG sudah
dihapus bersama pengujian khusus alur tersebut. Cache indikator dan snapshot titik
lama dibersihkan. CSV sumber asli di folder dataset pengguna dipertahankan sebagai
arsip. Tidak ada fallback database lokal, pembuatan tabel, migrasi, seed, atau impor
data indikator dalam pekerjaan ini. Pengayaan koordinat yang kemudian diminta
pengguna merupakan proses terpisah, tanpa mengaktifkan kembali fallback lokal.

## Pemeriksaan aktual

Koneksi PDO mengarah ke TiDB Cloud v8.5.3-serverless dan database `terangdesa`, memakai
TLS 1.3 / TLS_AES_128_GCM_SHA256 dengan verifikasi sertifikat server aktif. Database
memiliki 5.311 wilayah dan satu akun operasional saat pemeriksaan awal.

Jumlah KK memuat 1.197 desa bernilai positif dan 4.114 bernilai nol. Pada pemeriksaan
awal seluruh koordinat, kondisi manual, serta tanggal pengamatan masih NULL.
Periode 2024 terbaca dari
keterangan sumber yang tersimpan. Kolom PLN belum tersedia.

Uji backend memakai fixture transaksi untuk koordinat, NULL/0, filter gabungan,
pagination, pengelompokan, pembaruan Pemerintah, dan kegagalan query. Uji browser
memeriksa sumber online dan simpan/baca lintas daftar, dashboard, serta penanda peta.
Data uji dibersihkan setelah pemeriksaan dan checksum data asli dibandingkan kembali.

## Pengayaan titik referensi desa — 5 November 2026

Atas permintaan pengguna, 5.227 pasangan koordinat kosong di TiDB diisi dari titik
di dalam poligon desa BIG skala 1:10.000 (Oktober 2020), snapshot 2 November 2026.
Atribut administratif diverifikasi kembali melalui layanan resmi BIG pada hari
pengayaan. Kode KDEPUM, FID, dan nama sumber snapshot diperiksa sebelum pencocokan
kode desa, nama desa, kecamatan, dan kabupaten/kota dengan catatan TiDB. Kabupaten
tanpa awalan pada sumber diperlakukan sebagai kabupaten; Kota tetap dibedakan.
Tidak ada pencocokan samar, pengacakan, atau penggunaan titik pusat kabupaten.

Sebanyak 77 wilayah memiliki perbedaan nama administratif dan tujuh tidak mempunyai
kode referensi unik yang cocok. Sebanyak 84 wilayah ini tetap tanpa koordinat.
Jumlah KK, kondisi, sumber indikator, tanggal pengamatan, ID, relasi, dan skema
tidak diubah. Koordinat yang sudah terisi, termasuk pasangan parsial, tidak ditimpa.

Perintah pemeliharaan menerima berkas referensi terverifikasi secara eksplisit:

```sh
php artisan map:fill-village-locations storage/app/map-reference/verified-villages.json
php artisan map:fill-village-locations storage/app/map-reference/verified-villages.json --apply
```

Default adalah pratinjau. Penerapan menggunakan transaksi, penguncian, pemeriksaan
nilai sebelum/sesudah, serta pencadangan baris dan rencana pada
`storage/app/map-reference/<timestamp>/`. Berkas ini merupakan audit pemeliharaan,
bukan sumber runtime peta. Perintah dapat diulang tanpa menimpa koordinat.
Teman yang terhubung ke TiDB yang sama langsung membaca titik hasil pengayaan.

Sumber: [BIG — Batas Wilayah Kelurahan/Desa](https://geoservices.big.go.id/rbi/rest/services/BATASWILAYAH/Administrasi_AR_KelDesa_10K/MapServer/0).

## Peta area kabupaten → desa

Sesuai koreksi pengguna, peta sekarang mengisi bentuk wilayah administratif.
Tampilan awal memiliki 27 poligon kabupaten/kota. Klik area atau pilih kabupaten
untuk memuat poligon desa/kelurahan kabupaten itu; tombol Kembali ke Jawa Barat
mengembalikan tingkat kabupaten. Hover memperlihatkan ringkasan, klik desa
memperlihatkan detail, dan Enter/Spasi pada area mendukung navigasi keyboard.

Warna kabupaten mengikuti proporsi desa dengan jumlah KK belum berlistrik > 0
dibagi jumlah desa dengan angka tersedia, berdasarkan hasil filter: hijau 0%,
hijau muda >0–10%, kuning >10–25%, oranye >25–50%, merah >50%. Angka ini bukan
rasio elektrifikasi maupun persentase keluarga. Catatan NULL dikecualikan dari
penyebut dan tetap dihitung sebagai data belum tersedia. Jika semua NULL,
persentase dan total KK tetap NULL serta area abu-abu.

Pada tingkat desa, merah berarti ada KK belum berlistrik, hijau berarti angka
tersimpan nol, dan abu-abu berarti NULL atau tidak ada kecocokan data dalam hasil
filter. Kode dan nama desa, kecamatan, serta kabupaten diperiksa bersama agar
desa bernama sama tidak tertukar. Catatan tanpa titik dapat memiliki poligon;
catatan tanpa poligon tetap dapat dibaca pada daftar.

`public/data/map-boundaries/` menyimpan geografi BIG saja: 27 batas kabupaten/kota
skala 1:50.000, 5.957 batas desa/kelurahan skala 1:10.000 yang dipisahkan per
kabupaten, dan indeks administratif. Atribut KK, tahun indikator, dan kondisi
tidak disimpan dalam aset ini. Semua angka, warna, dan detail berasal dari TiDB
melalui `MapAreas`; query data tidak di-cache. Hanya bentuk geografis statis
yang di-cache dan tiap kabupaten dimuat sesuai kebutuhan. Pan/zoom tidak perlu
mengulang query database.

Pembaharuan geografi: `php artisan map:administrative-boundaries`. Perintah ini
hanya membaca layanan BIG dan menulis aset geografis, tanpa mutasi database.
Data disederhanakan untuk tampilan dan bukan penetapan batas hukum.

Sumber kabupaten: [BIG — Batas Wilayah Kabupaten/Kota](https://geoservices.big.go.id/rbi/rest/services/BATASWILAYAH/Administrasi_AR_KabKota_50K/MapServer/0).

Verifikasi pembaruan area: 44 tes backend lulus; 21 skenario browser lulus setelah
dua pemeriksaan lama disesuaikan dan diuji ulang, satu uji tile eksternal opsional
dilewati. Hover diuji pada posisi di dalam poligon (pusat bounding box Kabupaten
Bogor terletak di Kota Bogor). Navigasi keyboard, data NULL, filter, kabupaten tanpa
catatan, detail desa, simpan/edit lintas halaman, serta tampilan 390/320px diperiksa.
Jumlah akhir tetap 5.311 wilayah dan satu pengguna, tanpa fixture tersisa. Laporan:
`storage/app/map-reference/area-verification.json`.

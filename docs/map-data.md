# Peta Jawa Barat: sumber dan pengolahan

Finalisasi 3 November 2026 menghubungkan peta dengan MySQL `terangdesa` dan dataset
2024. Pada 4 November, pengguna meminta data yang sama ditampilkan di pengelolaan:
perintah sinkronisasi terpisah mengisi jumlah keluarga dan kondisi yang masih NULL.
Tidak ada migrasi, tabel/kolom baru, pengosongan tabel, atau perubahan koordinat.

## Sinkronisasi pengelolaan wilayah — 4 November 2026

`php artisan wilayah:sync-csv` menghasilkan preview per kode; `--apply` menerapkan
ke referensi yang jumlah dan kondisinya masih NULL. Periode hanya 2024, kode hanya
Kemendagri yang cocok, tanpa pencocokan nama. Sebanyak **5.311 baris diperbarui**:
`jumlah_kk_terdampak` memakai jumlah keluarga belum berlistrik, kondisi bernilai
`ada_kk_belum_berlistrik` (1.197) atau `tidak_tercatat_kk_belum_berlistrik` (4.114).
PLN tetap terpisah; jumlah warga, koordinat, `created_by`, dan tanggal tidak diisi.
Tanggal lengkap tidak ditebak dari tahun sumber. NULL angka sumber tidak menjadi nol.

Nilai sebelum/sesudah tersimpan pada audit lokal
`storage/app/imports/wilayah-2024-apply-20261104-064051-08b525f3.json`.
Audit disimpan sebelum commit; kegagalan penulisan audit menggagalkan transaksi.
Sumber lama yang persis dikenali sebagai impor 2024 diperbarui menjadi keterangan
dataset acuan proyek. Sumber lain, catatan bertanggal, pemilik manual, dan isian
parsial tidak diubah. Impor bersifat idempoten, termasuk ketika data hasil impor
kemudian sengaja dikosongkan oleh pengelola. Tidak ada perubahan akun/autentikasi.
Verifikasi akhir: 0 perbedaan jumlah/kondisi terhadap CSV, 5.311 jumlah terisi,
5.311 kondisi terisi; jumlah warga, koordinat database, dan tanggal tetap NULL.
Preview ulang menghasilkan 0 perubahan. Pengujian: 37 tes PHP dan 20 tes browser
lulus, satu uji tile eksternal opsional dilewati. Tidak ada fixture yang tertinggal.

## Audit CSV dan pencocokan

Sumber lokal: `D:/Semester 5/PPL/Dataset/Dataset_Wilayah_2024_Bersih.csv`.
Kolom `kemendagri_kode_desa_kelurahan` dicocokkan secara persis dengan
`wilayah.kode_referensi` berbentuk `32.xx.xx.xxxx`. Kode BPS tetap merupakan atribut
berbeda; tidak dicocokkan sebagai kode Kemendagri. Data tahun selain 2024 dan provinsi
selain kode 32 dilewati. Kode ganda dilaporkan dan tidak dipilih/ditambahkan sembarang;
cache aktif tidak diganti bila input mengandung kode ganda atau tidak ada baris valid.

| Pemeriksaan | Hasil lokal |
| --- | ---: |
| Baris CSV periode 2024, kode provinsi 32 | 5.311 |
| Cocok dengan kode pada MySQL | 5.311 |
| CSV tidak cocok / kode duplikat | 0 / 0 |
| Kolom berlabel belum listrik bernilai positif | 1.197 |
| Kolom berlabel belum listrik bernilai nol | 4.114 |
| Status sumber belum diverifikasi | 5.311 |
| Kabupaten/kota yang tercakup dalam CSV | 19 |
| Perubahan database dari persiapan/pemuatan peta | 0 |
| Pengisian referensi kosong melalui sinkronisasi 4 November | 5.311 |

Kedua CSV asli (`Data Desa Belum Teraliri Listrik.csv` dan `Data Desa Sumber Listrik
PLN.csv`) juga dibaca dan dibandingkan berdasarkan kode Kemendagri + tahun 2024.
Masing-masing memiliki 5.311 kode unik tahun 2024, tanpa baris hilang atau perbedaan
angka terhadap kolom terkait pada CSV bersih. Tahun lain serta rekap kabupaten pada
CSV lain tidak digabung atau dijumlahkan.

Pengguna menyetujui dataset sebagai acuan proyek untuk kondisi tahun 2024.
Antarmuka menampilkan **Sumber: dataset acuan proyek • Tahun 2024**. Persetujuan
tersebut bukan survei lapangan atau verifikasi resmi PLN/ESDM. Status asal sumber
dipertahankan pada artefak untuk audit; identitas verifikator tidak dibuat.
Jumlah keluarga belum berlistrik > 0 menjadi kategori `has_unserved`, tepat 0 menjadi
`no_unserved`, dan NULL/tidak cocok menjadi `unavailable`. Data Pemerintah yang lebih
baru tidak menentukan kategori dataset 2024 dan tetap ditampilkan terpisah.
PLN bukan KK terdampak; tidak ada pengurangan angka PLN untuk memperoleh jumlah
keluarga tanpa listrik. Desa dapat mempunyai pengguna PLN dan keluarga belum
berlistrik sekaligus. Angka nol bukan bukti seluruh desa sudah berlistrik.
Dataset tidak diklaim mencakup seluruh desa atau seluruh kabupaten/kota di provinsi.

## Sumber geografis

1. [BIG: Batas Wilayah Kelurahan/Desa 1:10.000](https://geoservices.big.go.id/rbi/rest/services/BATASWILAYAH/Administrasi_AR_KelDesa_10K/MapServer)
   dan [metadata layer](https://geoservices.big.go.id/rbi/rest/services/BATASWILAYAH/Administrasi_AR_KelDesa_10K/MapServer/0):
   edisi Oktober 2020, kode `KDEPUM` (Kemendagri), sistem koordinat WGS84. Metadata
   layanan mencantumkan kredit Badan Informasi Geospasial dan kemampuan publik
   `Map,Query,Data`. Keterangan layanan mengakui batas yang belum definitif, desa yang
   belum terwakili, serta kemungkinan perbedaan segmen batas. Tidak ada lisensi terbuka
   spesifik yang dinyatakan pada metadata layer yang diperiksa; implementasi memakai
   layanan publik untuk referensi kartografis dengan kredit BIG, tanpa mengklaim batas
   resmi atau lisensi CC untuk data BIG. Metadata asli disimpan bersama snapshot lokal.
2. [geoBoundaries](https://www.geoboundaries.org/), API
   `https://www.geoboundaries.org/api/current/gbOpen/IDN/ADM1/`: batas provinsi dari
   OpenStreetMap/Wambacher, tahun representasi 2017, boundary ID `IDN-ADM1-65028918`.
   File disederhanakan pada snapshot commit `9469f09`; metadata menyatakan
   [ODbL 1.0](https://opendatacommons.org/licenses/odbl/1-0/). Nama Jawa Barat pada
   sumber adalah `West Java`. Hanya fitur tersebut disertakan, dengan metadata sumber
   dan atribusi. Batas referensi lama/disederhanakan dipakai untuk tampilan dan validasi
   cakupan; tidak dipakai untuk keputusan atau penetapan batas hukum.
3. Peta dasar [OpenStreetMap](https://www.openstreetmap.org/copyright), melalui tile
   publik standar, tanpa bulk download atau prefetch. Atribusi selalu terlihat dan
   pengujian otomatis normal memakai tile fixture untuk mengurangi beban layanan.

Urutan lokasi: koordinat referensi yang tersimpan di database, lalu titik dari poligon
BIG berkode sama. Untuk data manual yang tidak lengkap/tidak valid/di luar provinsi,
sistem menampilkan alasannya dan tidak diam-diam menggantinya dengan sumber lain.
Skema belum mempunyai atribut verifikasi/jenis lokasi khusus rumah; seluruh titik
dilabeli **Lokasi referensi desa**, tanpa klaim lokasi rumah atau lokasi pemasangan.

Titik BIG dibuat menggunakan irisan horizontal di bagian dalam poligon. Lubang dan
MultiPolygon diperhitungkan, kemudian titik diuji terhadap poligon desa dan batas
provinsi. Titik rata-rata yang mungkin jatuh di laut tidak dipakai. Penyederhanaan
poligon layanan maksimum 0,0001 derajat ditujukan untuk referensi tampilan, bukan survei.
Koordinat BIG hanya disimpan pada artefak pendukung, tidak ditulis ke `wilayah`.

Layanan BIG mengembalikan 5.957 fitur Jawa Barat. Sebanyak 5.951 titik lolos pemeriksaan
geometri/batas referensi; 6 tidak dipakai. Untuk 5.311 wilayah dalam database,
**5.304 berhasil dipetakan** dan **7 tidak memiliki pasangan lokasi valid**:

| Kode Kemendagri | Wilayah |
| --- | --- |
| 32.07.17.2010 | CINTAJAYA |
| 32.07.17.2011 | CINTARATU |
| 32.07.17.2013 | TAMBAKREJA |
| 32.09.39.2001 | SAMBENG |
| 32.09.39.2002 | SIRNABAYA |
| 32.79.04.2001 | LANGENSARI |
| 32.79.04.2003 | WARINGINSARI |

Kode yang belum cocok tidak ditebak menggunakan nama desa, BPS, atau koordinat
kabupaten. Perbedaan versi administrasi merupakan keterbatasan yang belum diselesaikan.
Wilayah tersebut tetap dapat dicari dan dibaca melalui filter “Lokasi belum tersedia”.

## Alur runtime dan pembaruan

- `map:prepare --dry-run` membaca CSV dan MySQL, melaporkan audit tanpa menulis apa pun.
  `map:prepare` menyimpan artefak CSV ke `storage/app/map/dataset-2024.json` secara
  atomik. Ulangi perintah ini setelah memperbarui CSV; data browser tidak membaca CSV.
- `map:geography --refresh` mengambil metadata provinsi serta 15 batch poligon BIG (400 ID per
  batch, bukan 5.311 permintaan geocoding). Tanpa `--refresh`, snapshot batch lokal memungkinkan resume.
  Artefak hasil menyimpan URL sumber, waktu pengambilan, edisi, kode, dan feature ID.
- `MapData` meng-cache artefak berdasarkan hash konten. Data Pemerintah dibaca langsung
  dari MySQL pada setiap permintaan, sehingga edit/hapus terlihat pada peta berikutnya.
- Endpoint mengelompokkan titik dalam grid 54 piksel menurut zoom dan viewport.
  Penanda kelompok memakai lokasi salah satu anggota terdekat pusat kelompok yang
  valid, bukan titik rata-rata di luar daratan. Di zoom awal sekitar 50 kelompok/satuan
  mewakili ribuan desa. Jumlah keluarga tidak dijadikan jumlah penanda.
- Daftar dipaginasi 15 baris dan tidak dibatasi viewport. Pencarian/filter berlaku
  bersama untuk daftar, ringkasan, dan marker; perubahan filter mengembalikan halaman
  ke 1, membatalkan request lama, dan menghapus penanda kedaluwarsa.
- Respons membedakan kondisi Pemerintah yang diisi melalui pengelolaan, kondisi dataset,
  NULL, serta jenis/sumber lokasi. Atribut `created_by`, identitas akun, dan konfigurasi
  MySQL tidak disertakan. Error data, error batas, error tile, serta hasil kosong berbeda.
- Filter `condition=all|has_unserved|no_unserved`, pencarian, kabupaten, dan lokasi
  diterapkan bersama. Daftar kabupaten berasal dari data yang cocok dengan wilayah.
  NULL/tidak cocok hanya muncul pada Semua kondisi. Reset menghapus seluruh filter,
  pilihan detail, serta pagination; tombol Kembali ke Jawa Barat mengatur viewport.
- Merah/simbol ! dan biru/simbol 0 digunakan pada penanda desa; NULL memakai abu-abu/?.
  Kelompok berwarna kategori jika semua anggotanya sama; kelompok campuran navy
  merinci jumlah desa setiap kategori pada popup. Simbol dan teks membedakan kategori
  selain warna. Jumlah kelompok adalah jumlah lokasi desa, bukan keluarga.
- Error screenshot berasal dari MySQL yang berhenti (koneksi 3306 ditolak).
  Menyalakan MySQL lokal memulihkan endpoint tanpa migrasi atau perubahan data.

## Pemeriksaan

Jalankan pengujian dengan flag `PBI01_MYSQL_TESTS=1` sesuai README. Pengujian CSV dan
geometri tidak memerlukan MySQL; pengujian integrasi memakai transaksi/fixture baru,
tanpa memigrasi atau mengubah data referensi. Pengujian mencakup kecocokan angka asli,
kode BPS/Kemendagri, periode, duplikasi, NULL/0, geometri berlubang/multipoligon,
clustering/viewport, prioritas koordinat, CRUD Pemerintah yang langsung tercermin,
otorisasi/CSRF/FK, rate limiter terpisah, filter/detail/pagination/reload dan seluler.

Chrome juga dipakai untuk pemeriksaan dengan tile OpenStreetMap asli. Pemeriksaan
seluler merupakan emulasi, bukan perangkat fisik. Belum ada verifikasi lapangan atas
indikator listrik maupun titik referensi, dan tidak ada lokasi rumah/PLTS yang dibuat.

Hasil finalisasi 3 November 2026: **34 pengujian PHP lulus (354 assertions),
19 pengujian browser lulus**; satu uji tile eksternal pada suite normal dilewati
sesuai opt-in. Pengujian mencakup kondisi > 0 / = 0 / NULL / tidak cocok, filter
gabungan kabupaten dan pencarian, reset, detail, loading/error/kosong, mobile
390/320px, serta regresi CRUD dan autentikasi. Fixture NULL pada pengujian browser
hanya mengganti respons pengujian; data referensi tidak diubah. Build Vite berhasil.
Pemeriksaan visual terpisah memakai tile OpenStreetMap asli: 28 respons tile berhasil,
tidak ada error JavaScript, dan tidak ada overflow pada viewport seluler baru.
Screenshot lokal: `test-results/peta-final-desktop.png`, `peta-final-positive.png`,
dan `peta-final-mobile.png` (diabaikan Git). Akhir pengujian: tabel wilayah tetap
5.311 baris; tidak ada akun `@example.test` atau wilayah fixture yang tertinggal.

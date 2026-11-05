@extends('layouts.app')
@section('title', 'Data wilayah | TerangDesa')
@section('body-class', 'admin-page')
@section('breadcrumb', 'Data wilayah')
@section('content')
<div data-region-manager data-base-url="{{ route('wilayah.index') }}" data-list-url="{{ route('wilayah.data') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <section class="page-intro manage-intro">
        <div><h1>Kelola data wilayah</h1><p>Perbarui informasi lokasi dan kebutuhan listrik secara terstruktur.</p></div>
        <button class="button button--primary" type="button" data-add><span aria-hidden="true">＋</span> Tambah wilayah</button>
    </section>
    <div class="notice" role="status" aria-live="polite" data-notice hidden></div>
    <div class="manage-grid">
        <section class="panel list-panel" aria-labelledby="list-title">
            <div class="panel-heading"><div><h2 id="list-title">Daftar wilayah</h2><p>Data tersimpan dan pembaruan wilayah</p></div><span class="panel-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M9 10v10"/></svg></span></div>
            <form class="list-filters" data-filters role="search">
                <div class="field"><label for="search">Cari wilayah atau alamat</label><input id="search" name="q" type="search" placeholder="Nama desa, kecamatan, atau kabupaten…" maxlength="150" autocomplete="off"></div>
                <div class="field"><label for="condition-filter">Kondisi listrik</label><select id="condition-filter" name="kondisi"><option value="">Semua kondisi</option><option value="unknown">Data belum tersedia</option>@foreach($conditions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                <button class="button filter-submit" type="submit">Cari</button>
            </form>
            <div class="list-message" role="status" aria-live="polite"><span data-list-status>Memuat data wilayah…</span><button class="button" type="button" data-retry hidden>Coba lagi</button></div>
            <div class="table-scroll" tabindex="0" role="region" aria-label="Tabel daftar wilayah" data-table-wrap aria-busy="true">
                <table class="region-table"><thead><tr><th scope="col">Nama wilayah / alamat</th><th scope="col">Keluarga / KK terdampak</th><th scope="col">Kondisi listrik</th><th scope="col">Tindakan</th></tr></thead><tbody data-rows></tbody></table>
            </div>
            <div class="pagination"><span data-result-count>Menunggu data</span><div><button class="button" type="button" data-prev disabled aria-label="Halaman sebelumnya">‹</button><span data-page>1</span><button class="button" type="button" data-next disabled aria-label="Halaman berikutnya">›</button></div></div>
        </section>
        <section class="panel form-panel" aria-labelledby="form-title">
            <div class="panel-heading"><div><h2 id="form-title" tabindex="-1">Tambah wilayah</h2><p data-form-caption>Lengkapi informasi wilayah yang akan dicatat.</p></div><span class="panel-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m14 5 5 5M4 20l4-1 12-12a3 3 0 0 0-4-4L4 15Z"/></svg></span></div>
            <form class="region-form" data-region-form novalidate>
                <p class="form-help">Tanda <span aria-hidden="true">*</span> wajib diisi. Kosongkan data yang belum diketahui.</p>
                <div class="form-alert" role="alert" data-form-error hidden></div>
                <div class="field"><label for="nama_wilayah">Nama wilayah <span aria-hidden="true">*</span></label><input id="nama_wilayah" name="nama_wilayah" required maxlength="150" placeholder="Nama desa atau wilayah"><span class="field-error" id="error-nama_wilayah"></span></div>
                <div class="form-pair">
                    <div class="field"><label for="kode_referensi">Kode referensi</label><input id="kode_referensi" name="kode_referensi" maxlength="32" placeholder="Kode administratif"><span class="field-error" id="error-kode_referensi"></span></div>
                    <div class="field"><label for="tingkat_wilayah">Tingkat wilayah <span aria-hidden="true">*</span></label><input id="tingkat_wilayah" name="tingkat_wilayah" list="region-levels" required maxlength="50" placeholder="Contoh: desa_kelurahan"><datalist id="region-levels"><option value="desa_kelurahan"><option value="dusun"><option value="rw"><option value="rt"></datalist><span class="field-error" id="error-tingkat_wilayah"></span></div>
                </div>
                <div class="field"><label for="alamat_deskriptif">Alamat lengkap <span aria-hidden="true">*</span></label><textarea id="alamat_deskriptif" name="alamat_deskriptif" required maxlength="10000" rows="2" placeholder="Desa, kecamatan, kabupaten, dan keterangan lokasi"></textarea><span class="field-error" id="error-alamat_deskriptif"></span></div>
                <div class="form-pair">
                    <div class="field"><label for="latitude">Lintang</label><input id="latitude" name="latitude" inputmode="decimal" placeholder="Contoh: -6,9175000"><span class="field-error" id="error-latitude"></span></div>
                    <div class="field"><label for="longitude">Bujur</label><input id="longitude" name="longitude" inputmode="decimal" placeholder="Contoh: 107,6191000"><span class="field-error" id="error-longitude"></span></div>
                </div>
                <p class="form-help coordinate-help">Isi keduanya atau kosongkan keduanya. Maksimal 7 angka desimal; koma atau titik diterima.</p>
                <div class="form-pair">
                    <div class="field"><label for="jumlah_kk_terdampak">Jumlah KK terdampak</label><input id="jumlah_kk_terdampak" name="jumlah_kk_terdampak" type="number" min="0" max="4294967295" step="1" inputmode="numeric" placeholder="Belum tersedia"><span class="field-error" id="error-jumlah_kk_terdampak"></span></div>
                    <div class="field"><label for="jumlah_warga_terdampak">Jumlah warga terdampak</label><input id="jumlah_warga_terdampak" name="jumlah_warga_terdampak" type="number" min="0" max="4294967295" step="1" inputmode="numeric" placeholder="Belum tersedia"><span class="field-error" id="error-jumlah_warga_terdampak"></span></div>
                </div>
                <p class="form-help">Jumlah KK terdampak menggunakan angka keluarga belum berlistrik. Nilai 0 berarti tidak tercatat pada data tersebut.</p>
                <div class="field"><label for="kondisi_listrik">Kondisi elektrifikasi</label><select id="kondisi_listrik" name="kondisi_listrik"><option value="">Belum diisi</option>@foreach($conditions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select><span class="field-error" id="error-kondisi_listrik"></span></div>
                <details class="supporting-info"><summary>Informasi pendukung</summary><div class="supporting-fields">
                    <div class="field"><label for="sumber_data">Sumber data</label><input id="sumber_data" name="sumber_data" maxlength="255" placeholder="Sumber informasi atau hasil survei"><span class="field-error" id="error-sumber_data"></span></div>
                    <div class="field"><label for="data_per_tanggal">Tanggal pengamatan</label><input id="data_per_tanggal" name="data_per_tanggal" type="date"><span class="field-error" id="error-data_per_tanggal"></span></div>
                </div></details>
                <div class="form-actions"><button class="button button--primary" type="submit" data-save>Simpan data</button><button class="button" type="button" data-cancel>Batal</button></div>
            </form>
        </section>
    </div>
    <p class="reference-note">Sumber dan tanggal data mengikuti informasi pada setiap wilayah. Satu desa dapat memiliki pengguna PLN sekaligus keluarga belum berlistrik.</p>
    <noscript><p class="notice">Aktifkan JavaScript untuk mencari dan mengelola data wilayah.</p></noscript>
</div>
@endsection

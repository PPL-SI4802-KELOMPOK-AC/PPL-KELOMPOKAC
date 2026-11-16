@extends('layouts.app')
@section('title', 'Lokasi Pengajuan | TerangDesa')
@section('body-class', 'admin-page location-page')
@section('breadcrumb', 'Lokasi pengajuan')
@section('content')
    <section class="page-intro evidence-page-intro" aria-labelledby="location-title">
        <div>
            <h1 id="location-title">Tentukan lokasi pengajuan</h1>
            <p>Lengkapi alamat wilayah dan pilih titik calon lokasi PLTS.</p>
        </div>
        @if($draft)<span class="draft-badge">Draft tersimpan</span>@endif
    </section>
    @if(session('location_saved'))<div class="notice" role="status">{{ session('location_saved') }}</div>@endif
    @if(session('location_required'))<div class="notice" role="status">{{ session('location_required') }}</div>@endif
    @include('pengajuan.partials.validation-summary')
    @include('pengajuan.partials.steps', ['step' => 'lokasi'])
    <form class="location-grid" action="{{ route('pengajuan.lokasi.store') }}" method="POST" data-location-form
        data-administration-url="{{ route('pengajuan.lokasi.administrasi') }}" data-areas-url="{{ asset('data/map-boundaries') }}">
        @csrf
        <input type="hidden" name="draft_id" value="{{ $draft?->id }}">
        <section class="panel location-fields" aria-labelledby="location-fields-title">
            <h2 id="location-fields-title">Data wilayah</h2>
            <div class="form-pair">
                <div class="field"><label for="provinsi">Provinsi <span aria-hidden="true">*</span></label><input id="provinsi" name="provinsi" value="Jawa Barat" readonly required></div>
                <div class="field"><label for="kabupaten">Kabupaten/kota <span aria-hidden="true">*</span></label><select id="kabupaten" name="kabupaten" required disabled @error('kabupaten') aria-invalid="true" aria-describedby="kabupaten-error" @enderror>
                    <option value="">Pilih kabupaten/kota</option>
                    @foreach($districts as $code => $name)<option value="{{ $code }}" @selected($values['kabupaten'] === (string) $code)>{{ $name }}</option>@endforeach
                </select>@error('kabupaten')<span class="field-error" id="kabupaten-error">{{ $message }}</span>@enderror</div>
            </div>
            <div class="form-pair">
                <div class="field"><label for="kecamatan">Kecamatan <span aria-hidden="true">*</span></label><select id="kecamatan" name="kecamatan" required @disabled(!$villages) @error('kecamatan') aria-invalid="true" aria-describedby="kecamatan-error" @enderror>
                    <option value="">Pilih kecamatan</option>
                    @foreach(collect($villages)->pluck('subdistrict')->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE) as $name)<option @selected($values['kecamatan'] === $name)>{{ $name }}</option>@endforeach
                </select>@error('kecamatan')<span class="field-error" id="kecamatan-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="desa">Desa/kelurahan <span aria-hidden="true">*</span></label><select id="desa" name="desa" required @disabled(!$values['kecamatan']) @error('desa') aria-invalid="true" aria-describedby="desa-error" @enderror>
                    <option value="">Pilih desa/kelurahan</option>
                    @foreach($villages as $village) @if($village['subdistrict'] === $values['kecamatan'])<option value="{{ $village['code'] }}" @selected($values['desa'] === $village['code'])>{{ $village['name'] }}</option>@endif @endforeach
                </select>@error('desa')<span class="field-error" id="desa-error">{{ $message }}</span>@enderror</div>
            </div>
            <p class="form-help" data-administration-status role="status">Pilih wilayah secara berurutan untuk menentukan alamat lokasi.</p>
            <button class="button" type="button" data-administration-retry hidden>Coba lagi pilihan wilayah</button>
            <div class="field"><label for="alamat">Alamat/kampung <span aria-hidden="true">*</span></label><textarea id="alamat" name="alamat" rows="4" maxlength="2000" required placeholder="Nama kampung, jalan, RT/RW, atau patokan lokasi" @error('alamat') aria-invalid="true" aria-describedby="alamat-error" @enderror>{{ $values['alamat'] }}</textarea>@error('alamat')<span class="field-error" id="alamat-error">{{ $message }}</span>@enderror</div>
            <div class="location-hint"><strong>Alamat yang mudah ditemukan</strong><p>Tulis alamat rinci dan patokan terdekat. Titik peta mewakili lokasi yang Anda ajukan.</p></div>
        </section>
        <section class="location-position" aria-label="Titik lokasi pengajuan">
            <div class="panel location-map-panel">
                <div class="location-map-heading"><strong>Jawa Barat</strong><span>Pilih titik calon lokasi PLTS</span></div>
                <div id="location-map" data-location-map data-tile-url="{{ config('map.tile_url') }}" data-attribution="{{ config('map.attribution') }}" data-boundary-url="{{ asset('data/jawa-barat.geojson') }}" role="region" aria-label="Peta pemilihan lokasi" aria-describedby="location-map-help"></div>
                <div class="location-map-actions"><button class="button" type="button" data-location-center disabled>Pilih titik di tengah peta</button><button class="button" type="button" data-location-clear disabled>Hapus titik</button></div>
                <p id="location-map-help">Klik peta atau pilih titik di tengah peta, lalu geser penanda untuk menyesuaikan lokasi. Koordinat juga dapat diketik di bawah.</p>
                <p class="form-help location-map-status" data-location-map-status role="status">Memuat peta…</p>
                <button class="button location-map-retry" type="button" data-location-map-retry hidden>Coba lagi peta</button>
            </div>
            <div class="form-pair location-coordinates">
                @foreach(['latitude' => ['Lintang', '-7.5812000'], 'longitude' => ['Bujur', '107.6984000']] as $field => [$label, $example])
                    <div class="field"><label for="{{ $field }}">{{ $label }} (opsional)</label><input id="{{ $field }}" name="{{ $field }}" value="{{ $values[$field] }}" inputmode="decimal" maxlength="20" placeholder="Contoh: {{ $example }}" aria-describedby="location-coordinate-help{{ $errors->has($field) ? ' '.$field.'-error' : '' }}" @error($field) aria-invalid="true" @enderror>@error($field)<span class="field-error" id="{{ $field }}-error">{{ $message }}</span>@enderror</div>
                @endforeach
            </div>
            <p id="location-coordinate-help" class="form-help">Koordinat boleh dikosongkan. Jika diisi, lengkapi lintang dan bujur.</p>
            <p class="location-point-status" data-location-point-status role="status">Belum ada titik dipilih. Draft dapat disimpan berdasarkan alamat.</p>
            <div class="location-save">
                <button class="button" type="submit" name="action" value="save">Simpan draft</button>
                <button class="button button--primary" type="submit" name="action" value="continue" data-location-next disabled aria-describedby="location-next-help"><span aria-hidden="true">&rarr;</span> Lanjut ke bukti foto</button>
            </div>
            <p id="location-next-help" class="form-help" data-location-next-help role="status">Lengkapi kabupaten/kota, kecamatan, desa/kelurahan, dan alamat untuk melanjutkan.</p>
            <p class="form-help">Draft tersimpan di akun Anda. Lanjutkan untuk menambahkan foto bukti kondisi wilayah.</p>
            <p class="form-help">* Wajib diisi. Batas wilayah pada peta adalah referensi administratif.</p>
        </section>
    </form>
    <script type="application/json" data-location-villages>@json($villages)</script>
    <noscript><p class="notice is-error">Aktifkan JavaScript untuk memilih kecamatan, desa, dan titik pada peta.</p></noscript>
@endsection

@extends('layouts.app')
@section('title', 'Bukti Foto Pengajuan | TerangDesa')
@section('body-class', 'admin-page location-page')
@section('breadcrumb', 'Lokasi pengajuan')
@section('content')
    <section class="page-intro evidence-page-intro" aria-labelledby="location-title">
        <div>
            <h1 id="location-title">Tunjukkan kondisi di lapangan</h1>
            <p>Pengajuan kebutuhan PLTS · Dokumentasikan kondisi rumah dan akses lokasi.</p>
        </div>
        @if($draft)<span class="draft-badge">Draft tersimpan</span>@endif
    </section>
    @if(session('location_saved'))<div class="notice" role="status">{{ session('location_saved') }}</div>@endif
    @if(session('evidence_saved'))<div class="notice" role="status">{{ session('evidence_saved') }}</div>@endif
    @if($errors->any())
        <div class="notice is-error" role="alert"><strong>Periksa data berikut:</strong><ul>
            @foreach($errors->messages() as $field => $messages)<li><a href="#{{ $field }}">{{ $messages[0] }}</a></li>@endforeach
        </ul></div>
    @endif
    <ol class="location-stepper" aria-label="Tahapan pengajuan">
        <li class="{{ $draft ? 'is-complete' : 'is-current' }}">
            @if($draft)<a href="#location-editor" data-open-location-editor aria-label="Buka data lokasi tersimpan"><span aria-hidden="true">✓</span>Data lokasi</a>
            @else<span><span aria-hidden="true">1</span>Data lokasi</span>@endif
        </li>
        <li class="{{ $draft ? 'is-current' : 'is-pending' }}" @if($draft) aria-current="step" @endif><span><span aria-hidden="true">2</span>Bukti foto</span></li>
        <li class="is-pending"><span><span aria-hidden="true">3</span>Ringkasan &amp; kirim</span></li>
    </ol>

    @if($draft)
        @include('pengajuan.partials.bukti')
        <details class="location-editor" id="location-editor" data-location-editor>
            <summary><span><strong>Data lokasi</strong><small>Draft tersimpan · Ubah jika perlu</small></span><span aria-hidden="true">⌄</span></summary>
    @endif
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
                <div class="location-map-actions"><button class="button" type="button" data-location-center disabled>Pilih titik di tengah peta</button><button class="button" type="button" data-location-home disabled>Lihat Jawa Barat</button><button class="button" type="button" data-location-clear disabled>Hapus titik</button></div>
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
            <div class="location-save"><button class="button button--primary" type="submit">Simpan draft lokasi</button><p class="form-help">Draft tersimpan di akun Anda dan dapat dibuka kembali melalui menu Pengajuan PLTS.</p></div>
            <p class="form-help">* Wajib diisi. Batas wilayah pada peta adalah referensi administratif.</p>
        </section>
    </form>
    @if($draft)</details>
    @else
        <section class="panel evidence-not-ready"><strong>Bukti foto</strong><p>Simpan draft lokasi terlebih dahulu untuk mengunggah foto bukti.</p></section>
    @endif
    <script type="application/json" data-location-villages>@json($villages)</script>
    <noscript><p class="notice is-error">Aktifkan JavaScript untuk memilih kecamatan, desa, dan titik pada peta.</p></noscript>
@endsection

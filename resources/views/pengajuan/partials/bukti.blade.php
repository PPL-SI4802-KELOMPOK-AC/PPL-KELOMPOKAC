<section class="evidence-layout" aria-label="Unggah bukti foto">
    <div class="evidence-main">
        <form class="evidence-form" action="{{ route('pengajuan.bukti.store') }}" method="POST" enctype="multipart/form-data" data-evidence-upload data-existing-count="{{ $draft->bukti->count() }}">
            @csrf
            <input type="hidden" name="draft_id" value="{{ $draft->id }}">
            @if($draft->bukti->count() < 5)
                <label class="evidence-dropzone" for="photos" data-evidence-dropzone>
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 18a4.5 4.5 0 0 1-.6-8.96A5.7 5.7 0 0 1 17.4 8a4.3 4.3 0 0 1 1.1 8.16M12 21V11m-4 4 4-4 4 4"/></svg>
                    <strong>Seret foto ke sini atau pilih dari perangkat</strong>
                    <span id="photos-help">JPG / PNG / WebP · Maksimal 5 MB per foto · Hingga 5 foto</span>
                    <span class="button button--primary evidence-pick-button">Pilih foto</span>
                    <input id="photos" name="photos[]" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple required aria-describedby="photos-help evidence-status">
                </label>
                @if($errors->has('photos') || $errors->has('photos.0'))<p class="field-error" id="photos-error" role="alert">{{ $errors->first('photos') ?: $errors->first('photos.0') }}</p>@endif
            @else
                <p class="evidence-limit-note" role="status">Batas lima foto untuk draft ini sudah tercapai.</p>
            @endif
            <p class="form-help evidence-status" id="evidence-status" data-evidence-status role="status">{{ $draft->bukti->count() }} foto tersimpan. {{ 5 - $draft->bukti->count() }} slot tersisa.</p>
            <div class="evidence-preview" data-evidence-preview aria-live="polite"></div>
            @if($draft->bukti->count() < 5)<button class="button button--primary evidence-submit" type="submit" data-evidence-submit disabled>Unggah foto</button>@endif
        </form>

        @if($draft->bukti->isNotEmpty())
            <div class="evidence-grid" aria-label="Foto bukti yang sudah tersimpan">
                @foreach($draft->bukti as $bukti)
                    <figure class="evidence-card evidence-card--saved">
                        <img src="{{ route('pengajuan.bukti.show', $bukti->id) }}" alt="Bukti kondisi wilayah: {{ $bukti->nama_file_asli }}" loading="lazy">
                        <figcaption>
                            <strong>{{ $bukti->nama_file_asli }}</strong>
                            <span>{{ number_format($bukti->ukuran_byte / 1048576, 1, ',', '.') }} MB · {{ strtoupper(str_replace('image/', '', $bukti->mime_type)) }} · Foto bukti</span>
                            <span class="evidence-uploaded"><span aria-hidden="true">✓</span> Unggahan selesai</span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @endif
    </div>

    <aside class="evidence-sidebar" aria-label="Kelengkapan bukti">
        <section class="panel evidence-side-card">
            <h2>Kelengkapan bukti</h2>
            <span class="evidence-side-label" data-evidence-count-label>Foto tersimpan</span>
            <strong class="evidence-count"><span data-evidence-count>{{ $draft->bukti->count() }}</span> dari 5 foto</strong>
            <div class="evidence-check {{ $draft->bukti->isNotEmpty() ? 'is-complete' : '' }}" data-evidence-check>
                <span class="evidence-check-icon" aria-hidden="true">{{ $draft->bukti->isNotEmpty() ? '✓' : '○' }}</span>
                <span data-evidence-check-label>{{ $draft->bukti->isNotEmpty() ? 'Foto kondisi wilayah tersedia' : 'Pilih minimal satu foto bukti' }}</span>
            </div>
            <div class="evidence-check is-complete">
                <span class="evidence-check-icon" aria-hidden="true">✓</span>
                <span>Draft lokasi tersimpan</span>
            </div>
            <p>Unggah foto yang jelas dan menunjukkan kondisi rumah atau akses menuju lokasi. Hindari menyertakan data pribadi.</p>
        </section>
        <section class="panel evidence-side-card evidence-next-card">
            <h2>Siap melanjutkan</h2>
            <p>Foto tersimpan pada draft pengajuan Anda. Pastikan lokasi yang diajukan sudah benar.</p>
            <a class="button button--primary" href="#location-editor" data-open-location-editor>Kembali ke data lokasi</a>
        </section>
    </aside>
</section>

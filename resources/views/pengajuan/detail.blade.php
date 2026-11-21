@extends('layouts.app')
@section('title', 'Detail Pengajuan PLTS | TerangDesa')
@section('body-class', 'admin-page location-page submission-detail-page')
@section('breadcrumb', 'Detail pengajuan')
@section('content')
    @php
        $status = $pengajuan->status_terkini;
        $formatDate = fn ($value) => \Illuminate\Support\Carbon::parse($value)->locale('id')->translatedFormat('d M Y, H:i');
        $latitude = $pengajuan->lokasi?->latitude;
        $longitude = $pengajuan->lokasi?->longitude;
    @endphp

    <svg class="submission-detail__symbols" aria-hidden="true" width="0" height="0">
        <symbol id="detail-arrow" viewBox="0 0 24 24">
            <path d="M20 12H4m7-7-7 7 7 7"/>
        </symbol>
        <symbol id="detail-ticket" viewBox="0 0 24 24">
            <path d="M6 2h8l5 5v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1Zm7 0v6h6M8 13h8m-8 4h8"/>
        </symbol>
        <symbol id="detail-calendar" viewBox="0 0 24 24">
            <rect x="3" y="5" width="18" height="16" rx="2"/>
            <path d="M7 3v4m10-4v4M3 10h18m-14 5h2"/>
        </symbol>
        <symbol id="detail-info" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/>
        </symbol>
        <symbol id="detail-photo" viewBox="0 0 24 24">
            <rect x="3" y="4" width="18" height="16" rx="2"/>
            <circle cx="8" cy="9" r="1"/><path d="m3 17 5-4 4 3 4-5 5 5"/>
        </symbol>
        <symbol id="detail-clock" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
        </symbol>
        <symbol id="detail-zoom" viewBox="0 0 24 24">
            <circle cx="10.5" cy="10.5" r="5.5"/>
            <path d="m15 15 5 5m-12-9.5h5M10.5 8v5"/>
        </symbol>
    </svg>

    <header class="submission-detail__hero" aria-labelledby="detail-title">
        <a class="button submission-detail__back" href="{{ route('pengajuan.riwayat') }}">
            <svg aria-hidden="true"><use href="#detail-arrow"/></svg>
            Kembali ke Riwayat
        </a>
        <div class="submission-detail__title">
            <svg aria-hidden="true"><use href="#detail-ticket"/></svg>
            <h1 id="detail-title">Detail Pengajuan PLTS</h1>
        </div>
    </header>

    <dl class="panel submission-detail__summary" aria-label="Ringkasan tiket">
        <div class="submission-detail__ticket">
            <span class="submission-detail__icon-box" aria-hidden="true">
                <svg><use href="#detail-ticket"/></svg>
            </span>
            <div>
                <dt>Nomor Pengajuan</dt>
                <dd>{{ filled($pengajuan->nomor_tiket) ? $pengajuan->nomor_tiket : 'Tidak tersedia' }}</dd>
            </div>
        </div>
        <div class="submission-detail__date">
            <svg aria-hidden="true"><use href="#detail-calendar"/></svg>
            <div>
                <dt>Tanggal Pengajuan</dt>
                <dd>{{ $submittedAt ? $formatDate($submittedAt) : 'Tidak tersedia' }}</dd>
            </div>
        </div>
        <div>
            <dt class="submission-detail__sr-only">Status Terkini</dt>
            <dd><span class="submission-detail__badge" data-detail-status="{{ $status }}">{{ $statusLabels[$status] ?? 'Tidak tersedia' }}</span></dd>
        </div>
    </dl>

    <div class="submission-detail__layout">
        <div class="submission-detail__main">
            <section class="panel submission-detail__card" aria-labelledby="detail-info-title">
                <h2 id="detail-info-title">
                    <svg aria-hidden="true"><use href="#detail-info"/></svg>
                    Informasi Pengajuan
                </h2>
                <div class="submission-detail__information">
                    <dl class="submission-detail__fields">
                        <div><dt>Nama Pengusul</dt><dd>{{ filled($ownerName) ? $ownerName : 'Tidak tersedia' }}</dd></div>
                        <div><dt>Desa/Kelurahan</dt><dd>{{ $village ?? 'Tidak tersedia' }}</dd></div>
                        <div><dt>Kecamatan</dt><dd>{{ $subdistrict ?? 'Tidak tersedia' }}</dd></div>
                        <div><dt>Kabupaten/Kota</dt><dd>{{ $district ?? 'Tidak tersedia' }}</dd></div>
                        <div><dt>Provinsi</dt><dd>{{ $province ?? 'Tidak tersedia' }}</dd></div>
                    </dl>
                    <dl class="submission-detail__location">
                        <div><dt>Alamat Lokasi</dt><dd>{{ filled($address) ? $address : 'Tidak tersedia' }}</dd></div>
                        <div>
                            <dt>Koordinat</dt>
                            <dd>
                                @if($latitude !== null && $longitude !== null)
                                    {{ number_format($latitude, 7, '.', '') }}, {{ number_format($longitude, 7, '.', '') }}
                                @else
                                    Tidak tersedia
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Ringkasan Pengajuan</dt>
                            <dd>{{ filled($pengajuan->ringkasan_pengajuan) ? $pengajuan->ringkasan_pengajuan : 'Tidak tersedia' }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="panel submission-detail__card" aria-labelledby="detail-evidence-title">
                <h2 id="detail-evidence-title">
                    <svg aria-hidden="true"><use href="#detail-photo"/></svg>
                    Foto Bukti Kondisi Wilayah
                </h2>
                @if($pengajuan->bukti->isEmpty())
                    <p class="submission-detail__empty">Foto bukti tidak tersedia.</p>
                @else
                    <div class="submission-detail__gallery">
                        @foreach($pengajuan->bukti as $bukti)
                            <figure class="submission-detail__photo" data-detail-photo>
                                <button type="button" class="detail-photo-trigger" aria-haspopup="dialog"
                                    aria-label="Perbesar {{ $bukti->nama_file_asli }}">
                                    <img src="{{ route('pengajuan.bukti.show', $bukti->id) }}"
                                        alt="Bukti kondisi wilayah: {{ $bukti->nama_file_asli }}" loading="lazy">
                                    <span class="submission-detail__photo-error" hidden>Foto tidak dapat dimuat.</span>
                                    <span class="detail-photo-trigger__zoom" aria-hidden="true"><svg><use href="#detail-zoom"/></svg></span>
                                </button>
                                <figcaption>{{ $bukti->nama_file_asli }}</figcaption>
                            </figure>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <section class="panel submission-detail__card" aria-labelledby="detail-status-title">
            <h2 id="detail-status-title">
                <svg aria-hidden="true"><use href="#detail-clock"/></svg>
                Status Pengajuan
            </h2>
            <p class="submission-detail__updated">
                Pembaruan terakhir
                <strong>{{ $pengajuan->status_diperbarui_pada ? $formatDate($pengajuan->status_diperbarui_pada) : 'Tidak tersedia' }}</strong>
            </p>
            @if($history->isEmpty())
                <p class="submission-detail__empty">Riwayat perubahan status belum tersedia.</p>
            @else
                <ol class="submission-detail__timeline" aria-label="Riwayat perubahan status">
                    @foreach($history as $event)
                        <li data-detail-status="{{ $event->status_baru }}" @class(['is-current' => $loop->last && $event->status_baru === $status])>
                            <strong>{{ $statusLabels[$event->status_baru] ?? 'Tidak tersedia' }}</strong>
                            <span>{{ $event->created_at ? $formatDate($event->created_at) : 'Tidak tersedia' }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>
    @if($pengajuan->bukti->isNotEmpty())
        <dialog class="detail-lightbox" aria-labelledby="detail-lightbox-title">
            <div class="detail-lightbox__content">
                <header class="detail-lightbox__header">
                    <h2 id="detail-lightbox-title">Pratinjau Foto Bukti</h2>
                    <button type="button" class="button detail-lightbox__close" aria-label="Tutup pratinjau foto" autofocus>
                        <span aria-hidden="true">&times;</span>
                    </button>
                </header>
                <div class="detail-lightbox__image"></div>
                <p class="detail-lightbox__message" role="status"></p>
                <div class="detail-lightbox__navigation">
                    <button type="button" class="button" data-photo-step="-1" @disabled($pengajuan->bukti->count() === 1)>Sebelumnya</button>
                    <span class="detail-lightbox__position" aria-live="polite" aria-atomic="true"></span>
                    <button type="button" class="button" data-photo-step="1" @disabled($pengajuan->bukti->count() === 1)>Berikutnya</button>
                </div>
            </div>
        </dialog>

        <style>
            .detail-photo-trigger {
                position: relative;
                display: block;
                width: 100%;
                padding: 0;
                border: 0;
                border-radius: 10px;
                background: transparent;
                font: inherit;
                cursor: zoom-in;
            }
            .detail-photo-trigger__zoom {
                position: absolute;
                right: 7px;
                bottom: 7px;
                display: grid;
                width: 34px;
                height: 34px;
                place-items: center;
                border-radius: 8px;
                color: var(--detail-ink);
                background: #fff;
                box-shadow: 0 1px 5px #10294b33;
            }
            .submission-detail-page #konten .detail-photo-trigger__zoom svg { width: 20px; height: 20px; }
            .detail-photo-trigger:focus-visible { outline: 3px solid #5aabff; outline-offset: 3px; }
            .detail-lightbox button:focus-visible { outline: 3px solid #8a5b00; outline-offset: 3px; }

            .detail-lightbox {
                width: min(64rem, calc(100% - 24px));
                max-width: none;
                max-height: calc(100vh - 24px);
                max-height: calc(100dvh - 24px);
                padding: 0;
                border: 1px solid var(--detail-border);
                border-radius: 14px;
                color: var(--detail-ink);
                background: rgb(255 255 255 / 94%);
                box-shadow: 0 20px 48px rgb(5 15 27 / 24%);
            }
            .detail-lightbox::backdrop {
                background: rgb(5 15 27 / 78%);
                backdrop-filter: blur(3px);
            }
            .submission-detail-page:has(.detail-lightbox[open]) { overflow: hidden; }
            .detail-lightbox__content { padding: clamp(12px, 2vw, 20px); }
            .detail-lightbox__header {
                display: flex;
                gap: 12px;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 12px;
            }
            .detail-lightbox__header h2 { margin: 0; color: inherit; font-size: clamp(16px, 3vw, 20px); }
            .detail-lightbox .button { min-height: 44px; color: var(--detail-ink); background: #fff; }
            .detail-lightbox .button:disabled { opacity: .55; }
            .detail-lightbox .detail-lightbox__close {
                flex: none;
                width: 44px;
                padding: 0;
                border-color: var(--detail-accent-fill);
                background: var(--detail-accent-fill);
                font-size: 24px;
            }
            .detail-lightbox__image img {
                display: block;
                max-width: 100%;
                max-height: 66vh;
                max-height: 66dvh;
                margin-inline: auto;
                border-radius: 8px;
                object-fit: contain;
            }
            .detail-lightbox__message {
                display: grid;
                min-height: 10rem;
                margin: 0;
                place-items: center;
                color: inherit;
                text-align: center;
            }
            .detail-lightbox [hidden] { display: none; }
            .detail-lightbox__navigation {
                display: grid;
                grid-template-columns: 1fr auto 1fr;
                align-items: center;
                gap: 8px;
                margin-top: 12px;
            }
            .detail-lightbox__navigation button { justify-self: start; padding-inline: clamp(8px, 2vw, 16px); }
            .detail-lightbox__navigation button:last-child { justify-self: end; }
            .detail-lightbox__position {
                padding: 5px 8px;
                border-radius: 999px;
                color: inherit;
                background: var(--detail-accent-fill);
                text-align: center;
                font-size: 13px;
            }
        </style>

        <script>
            (() => {
                const photos = Array.from(document.querySelectorAll('[data-detail-photo]'));
                const lightbox = document.querySelector('.detail-lightbox');
                const frame = lightbox.querySelector('.detail-lightbox__image');
                const message = lightbox.querySelector('.detail-lightbox__message');
                const position = lightbox.querySelector('.detail-lightbox__position');
                let activeIndex = 0;
                let opener;

                const showPhoto = (index) => {
                    activeIndex = (index + photos.length) % photos.length;
                    const thumbnail = photos[activeIndex].querySelector('img');
                    const preview = document.createElement('img');
                    preview.alt = thumbnail.alt;
                    preview.hidden = true;
                    position.textContent = `Foto ${activeIndex + 1} dari ${photos.length}`;
                    message.textContent = 'Memuat foto…';
                    message.hidden = false;

                    preview.addEventListener('load', () => {
                        if (frame.firstElementChild === preview) {
                            preview.hidden = false;
                            message.hidden = true;
                        }
                    }, { once: true });
                    preview.addEventListener('error', () => {
                        if (frame.firstElementChild === preview) {
                            message.textContent = 'Foto tidak dapat dimuat.';
                        }
                    }, { once: true });

                    frame.replaceChildren(preview);
                    preview.src = thumbnail.src;
                };

                photos.forEach((photo, index) => {
                    const image = photo.querySelector('img');
                    const showFallback = () => {
                        image.hidden = true;
                        photo.querySelector('.submission-detail__photo-error').hidden = false;
                    };

                    image.addEventListener('error', showFallback, { once: true });
                    if (image.complete && image.naturalWidth === 0) {
                        showFallback();
                    }

                    const trigger = photo.querySelector('.detail-photo-trigger');
                    trigger.addEventListener('click', () => {
                        opener = trigger;
                        showPhoto(index);
                        lightbox.showModal();
                    });
                });

                lightbox.querySelector('.detail-lightbox__close').addEventListener('click', () => lightbox.close());
                lightbox.querySelectorAll('[data-photo-step]').forEach((button) => {
                    button.addEventListener('click', () => showPhoto(activeIndex + Number(button.dataset.photoStep)));
                });
                lightbox.addEventListener('click', (event) => {
                    if (event.target === lightbox) {
                        lightbox.close();
                    }
                });
                lightbox.addEventListener('keydown', (event) => {
                    const steps = { ArrowLeft: -1, ArrowRight: 1 };
                    if (photos.length > 1 && steps[event.key]) {
                        event.preventDefault();
                        showPhoto(activeIndex + steps[event.key]);
                    }
                });
                lightbox.addEventListener('close', () => {
                    if (!lightbox.open) {
                        frame.replaceChildren();
                        opener?.focus();
                    }
                });
            })();
        </script>
    @endif
@endsection

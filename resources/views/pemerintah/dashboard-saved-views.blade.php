<section class="panel gov-saved-views" data-gov-saved-views data-url="{{ route('pemerintah.dashboard.tampilan.index') }}" aria-labelledby="gov-saved-title">
    <div class="gov-saved-heading">
        <h2 id="gov-saved-title">Tampilan tersimpan</h2>
    </div>
    <p data-saved-status role="status"></p>
    <details data-saved-list>
        <summary>Lihat daftar tampilan</summary>
        <ul data-saved-items></ul>
        <div class="gov-saved-actions" data-saved-pages hidden>
            <button class="button" type="button" data-saved-prev>Sebelumnya</button>
            <span data-saved-page></span>
            <button class="button" type="button" data-saved-next>Berikutnya</button>
        </div>
    </details>

    <dialog id="gov-saved-dialog" class="gov-saved-dialog" aria-labelledby="gov-saved-dialog-title">
        <form data-saved-form>
            @csrf
            <h2 id="gov-saved-dialog-title">Simpan Tampilan</h2>
            <p data-saved-scope></p>
            <p data-saved-warning role="alert" hidden></p>
            <div class="gov-filter-field">
                <label for="gov-saved-name">Nama tampilan</label>
                <input id="gov-saved-name" name="name" required maxlength="100" autofocus>
            </div>
            <div class="gov-filter-fields" data-saved-filters hidden>
                <div class="gov-filter-field">
                    <label for="gov-saved-year">Tahun</label>
                    <input id="gov-saved-year" name="year" type="number" min="1900" max="2100" placeholder="Semua periode">
                </div>
                <div class="gov-filter-field">
                    <label for="gov-saved-district">Kabupaten/kota</label>
                    <select id="gov-saved-district" name="kabupaten">
                        <option value="">Semua kabupaten/kota</option>
                        @foreach($districts as $code => $name)
                            <option value="{{ $code }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="gov-filter-field">
                    <label for="gov-saved-subdistrict">Kecamatan</label>
                    <select id="gov-saved-subdistrict" name="kecamatan"><option value="">Semua kecamatan</option></select>
                </div>
                <div class="gov-filter-field">
                    <label for="gov-saved-village">Desa/kelurahan</label>
                    <select id="gov-saved-village" name="desa"><option value="">Semua desa/kelurahan</option></select>
                </div>
                <div class="gov-filter-field">
                    <label for="gov-saved-condition">Kondisi listrik</label>
                    <select id="gov-saved-condition" name="kondisi">
                        <option value="">Semua kondisi listrik</option>
                        <option value="unknown">Data belum tersedia</option>
                        @foreach($conditions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p data-saved-error role="alert" hidden></p>
            <div class="gov-saved-actions">
                <button class="button button--primary" type="submit">Simpan</button>
                <button class="button" type="button" data-saved-cancel>Batal</button>
            </div>
        </form>
    </dialog>
    <dialog class="gov-saved-dialog gov-saved-confirm" data-saved-confirm aria-labelledby="gov-saved-confirm-title" aria-describedby="gov-saved-confirm-message">
        <form method="dialog">
            <h2 id="gov-saved-confirm-title">Hapus tampilan?</h2>
            <p id="gov-saved-confirm-message"></p>
            <div class="gov-saved-actions">
                <button class="button" value="cancel" autofocus>Batal</button>
                <button class="button gov-saved-danger" value="delete">Hapus tampilan</button>
            </div>
        </form>
    </dialog>
</section>

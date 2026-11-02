// Drawer hanya dipakai halaman administrasi; navigasi peta tetap mengikuti perilaku semula.
const drawer = document.querySelector('#sidebar');
const toggle = document.querySelector('[data-drawer-open]');
const backdrop = document.querySelector('.drawer-backdrop');
const workspace = document.querySelector('.workspace');
const mobile = window.matchMedia('(max-width: 760px)');
function closeDrawer(restoreFocus = true) {
    document.body.classList.remove('drawer-is-open');
    backdrop.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
    workspace.inert = false;
    drawer.inert = mobile.matches;
    toggle.hidden = !mobile.matches;
    drawer.querySelector('[data-drawer-close]').hidden = !mobile.matches;
    document.body.style.overflow = '';
    if (restoreFocus && mobile.matches) toggle.focus();
}
toggle.addEventListener('click', () => {
    drawer.inert = false;
    document.body.classList.add('drawer-is-open');
    backdrop.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    workspace.inert = true;
    document.body.style.overflow = 'hidden';
    requestAnimationFrame(() => drawer.querySelector('button').focus());
});
document.querySelectorAll('[data-drawer-close]').forEach(button => button.addEventListener('click', () => closeDrawer()));
document.addEventListener('keydown', event => {
    if (!document.body.classList.contains('drawer-is-open')) return;
    if (event.key === 'Escape') closeDrawer();
    if (event.key === 'Tab') {
        const focusable = [...drawer.querySelectorAll('a, button')].filter(el => el.getClientRects().length);
        const first = focusable[0], last = focusable.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
});
mobile.addEventListener('change', () => closeDrawer(false));
closeDrawer(false);

const manager = document.querySelector('[data-region-manager]');
if (manager) initializeManager(manager);

function initializeManager(root) {
    const find = selector => root.querySelector(selector);
    const form = find('[data-region-form]');
    const filters = find('[data-filters]');
    const base = root.dataset.baseUrl;
    const csrf = find('meta[name="csrf-token"]').content;
    const rows = find('[data-rows]');
    const fields = [...form.querySelectorAll('[name]')];
    const conditions = Object.fromEntries([...form.elements.kondisi_listrik.options].map(option => [option.value, option.textContent]));
    let selected = null, page = 1, lastPage = 1, total = 0, busy = false, editPending = false;
    let baseline = '', listAbort, debounce, noticeTimer;
    const snapshot = () => JSON.stringify(fields.map(field => [field.name, field.value]));
    const dirty = () => snapshot() !== baseline;
    const canDiscard = () => !busy && !editPending && (!dirty() || window.confirm('Ada perubahan yang belum disimpan. Buang perubahan tersebut?'));
    const announce = (message, error = false) => {
        clearTimeout(noticeTimer);
        const notice = find('[data-notice]');
        notice.textContent = message;
        notice.classList.toggle('is-error', error);
        notice.hidden = false;
        noticeTimer = setTimeout(() => { notice.hidden = true; }, error ? 12000 : 6000);
    };
    function clearErrors() {
        form.querySelectorAll('.field-error').forEach(el => { el.textContent = ''; });
        fields.forEach(field => { field.removeAttribute('aria-invalid'); field.removeAttribute('aria-describedby'); });
        find('[data-form-error]').hidden = true;
    }
    function showErrors(errors, message = 'Periksa isian yang ditandai, lalu simpan kembali.') {
        clearErrors();
        for (const [name, messages] of Object.entries(errors)) {
            const field = form.elements.namedItem(name);
            const error = form.querySelector(`#error-${name}`);
            if (!field || !error) continue;
            error.textContent = Array.isArray(messages) ? messages.join(' ') : messages;
            field.setAttribute('aria-invalid', 'true');
            field.setAttribute('aria-describedby', error.id);
            const details = field.closest('details');
            if (details) details.open = true;
        }
        const alert = find('[data-form-error]');
        alert.textContent = message;
        alert.hidden = false;
        form.querySelector('[aria-invalid="true"]')?.focus();
    }
    function highlight() {
        rows.querySelectorAll('tr').forEach(row => row.classList.toggle('is-selected', Number(row.dataset.id) === selected));
    }
    function fill(data = null, focus = true) {
        selected = data?.id ?? null;
        fields.forEach(field => { field.value = data?.[field.name] ?? ''; });
        find('#form-title').textContent = data ? `Edit wilayah • ${data.nama_wilayah}` : 'Tambah wilayah';
        find('[data-form-caption]').textContent = data ? 'Perbarui data referensi wilayah yang dipilih.' : 'Lengkapi informasi wilayah yang akan dicatat.';
        clearErrors();
        baseline = snapshot();
        highlight();
        if (focus) { find('#form-title').focus(); find('.form-panel').scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
    }
    function setBusy(value) {
        busy = value;
        form.setAttribute('aria-busy', String(value));
        disableEditor(value);
        find('[data-save]').textContent = value ? 'Menyimpan…' : 'Simpan data';
    }
    function disableEditor(value) {
        form.querySelectorAll('input, select, textarea, button').forEach(el => { el.disabled = value; });
        find('[data-add]').disabled = value;
    }
    async function api(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...(options.body ? { 'Content-Type': 'application/json' } : {}) },
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
            let message = body.message;
            if (response.status === 401) message = 'Sesi berakhir. Buka halaman Masuk di tab baru, lalu coba simpan kembali. Isian tetap dipertahankan.';
            if (response.status === 419) message = 'Sesi keamanan kedaluwarsa. Salin isian yang belum tersimpan, lalu muat ulang dan masuk kembali.';
            if (response.status === 403) message = 'Akun ini tidak memiliki izin Pemerintah untuk mengelola wilayah.';
            if (response.status >= 500) message = 'Data belum dapat diproses. Periksa koneksi database atau hubungi pengelola, lalu coba kembali.';
            throw Object.assign(new Error(message || 'Permintaan gagal. Silakan coba kembali.'), { errors: body.errors, status: response.status });
        }
        return body;
    }
    function render(data) {
        rows.replaceChildren();
        data.forEach(region => {
            const row = document.createElement('tr');
            row.dataset.id = region.id;
            const name = document.createElement('td');
            const title = document.createElement('strong');
            title.textContent = region.nama_wilayah;
            const address = document.createElement('span');
            address.className = 'row-address';
            address.textContent = region.alamat_deskriptif;
            name.append(title, address);
            const count = document.createElement('td');
            count.textContent = region.jumlah_kk_terdampak === null ? 'Belum tersedia' : new Intl.NumberFormat('id-ID').format(region.jumlah_kk_terdampak);
            const condition = document.createElement('td');
            const badge = document.createElement('span');
            badge.className = 'condition-badge';
            badge.dataset.condition = region.kondisi_listrik ?? 'unknown';
            badge.textContent = region.kondisi_listrik === null ? 'Belum diverifikasi' : (conditions[region.kondisi_listrik] ?? region.kondisi_listrik);
            condition.append(badge);
            const actionCell = document.createElement('td');
            const actions = document.createElement('div');
            actions.className = 'row-actions';
            const edit = document.createElement('button');
            edit.type = 'button'; edit.className = 'button'; edit.textContent = 'Edit';
            edit.setAttribute('aria-label', `Edit ${region.nama_wilayah}`);
            edit.addEventListener('click', () => editRegion(region.id));
            const remove = document.createElement('button');
            remove.type = 'button'; remove.className = 'button delete-button';
            remove.setAttribute('aria-label', `Hapus ${region.nama_wilayah}`);
            remove.title = `Hapus ${region.nama_wilayah}`;
            // Ikon mengikuti SVG inline yang sudah dipakai proyek.
            remove.innerHTML = '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/></svg>';
            remove.addEventListener('click', () => deleteRegion(region, remove));
            actions.append(edit, remove); actionCell.append(actions);
            row.append(name, count, condition, actionCell); rows.append(row);
        });
        highlight();
    }
    async function loadList() {
        listAbort?.abort();
        const abort = new AbortController(); listAbort = abort;
        const params = new URLSearchParams({ q: filters.elements.q.value.trim(), kondisi: filters.elements.kondisi.value, page });
        const status = find('[data-list-status]');
        status.textContent = 'Memuat data wilayah…';
        status.parentElement.classList.remove('is-error');
        find('[data-retry]').hidden = true;
        find('[data-table-wrap]').setAttribute('aria-busy', 'true');
        find('[data-prev]').disabled = true; find('[data-next]').disabled = true;
        try {
            const result = await api(`${root.dataset.listUrl}?${params}`, { signal: abort.signal });
            if (abort.signal.aborted) return;
            lastPage = result.last_page; total = result.total;
            if (page > lastPage) { page = lastPage; return loadList(); }
            render(result.data);
            const filtered = !!(params.get('q') || params.get('kondisi'));
            status.textContent = total ? `${new Intl.NumberFormat('id-ID').format(total)} wilayah ditemukan` : (filtered ? 'Tidak ada wilayah yang sesuai pencarian atau filter.' : 'Belum ada data wilayah. Tambahkan wilayah pertama.');
            find('[data-result-count]').textContent = total ? `${result.from}–${result.to} dari ${new Intl.NumberFormat('id-ID').format(total)} wilayah` : '0 wilayah';
            find('[data-page]').textContent = `${page} / ${lastPage}`;
            find('[data-prev]').disabled = page <= 1; find('[data-next]').disabled = page >= lastPage;
            history.replaceState(null, '', `${base}?${params}`);
        } catch (error) {
            if (error.name === 'AbortError') return;
            rows.replaceChildren();
            status.textContent = error.message === 'Failed to fetch' ? 'Gagal mengambil data. Periksa koneksi lalu coba lagi.' : error.message;
            status.parentElement.classList.add('is-error');
            find('[data-result-count]').textContent = 'Jumlah hasil belum tersedia';
            find('[data-page]').textContent = '—';
            find('[data-retry]').hidden = false;
        } finally {
            if (!abort.signal.aborted) find('[data-table-wrap]').setAttribute('aria-busy', 'false');
        }
    }
    async function editRegion(id) {
        if (!canDiscard()) return;
        editPending = true;
        disableEditor(true);
        form.setAttribute('aria-busy', 'true');
        find('[data-form-caption]').textContent = 'Memuat data wilayah…';
        try { fill(await api(`${base}/${id}`)); }
        catch (error) { announce(`Wilayah gagal dibuka. ${error.message}`, true); }
        finally { editPending = false; disableEditor(false); form.setAttribute('aria-busy', 'false'); }
    }
    async function deleteRegion(region, button) {
        if (busy || editPending) return;
        if (selected === region.id && !canDiscard()) return;
        if (!window.confirm(`Hapus wilayah “${region.nama_wilayah}”? Tindakan ini tidak dapat dibatalkan.`)) return;
        setBusy(true); button.disabled = true;
        try {
            const result = await api(`${base}/${region.id}`, { method: 'DELETE' });
            if (selected === region.id) fill(null, false);
            announce(result.message); await loadList();
        } catch (error) { announce(error.message, true); }
        finally { setBusy(false); button.disabled = false; }
    }
    function validate(payload) {
        const errors = {};
        const required = { nama_wilayah: 'Nama wilayah', tingkat_wilayah: 'Tingkat wilayah', alamat_deskriptif: 'Alamat lengkap' };
        for (const [field, label] of Object.entries(required)) if (!payload[field]) errors[field] = `${label} wajib diisi.`;
        for (const field of fields) {
            if (field.maxLength > 0 && (payload[field.name]?.length ?? 0) > field.maxLength) errors[field.name] = `Maksimal ${field.maxLength} karakter.`;
        }
        if (!!payload.latitude !== !!payload.longitude) {
            errors.latitude = errors.longitude = 'Lintang dan bujur harus diisi berpasangan.';
        }
        for (const [name, limit] of [['latitude', 90], ['longitude', 180]]) {
            if (payload[name] && (!/^[+-]?\d+(?:\.\d{1,7})?$/.test(payload[name]) || Math.abs(Number(payload[name])) > limit)) errors[name] = `Masukkan angka antara -${limit} dan ${limit}, maksimal 7 desimal.`;
        }
        for (const name of ['jumlah_kk_terdampak', 'jumlah_warga_terdampak']) {
            const field = form.elements.namedItem(name);
            if (field.validity.badInput || (payload[name] !== null && (!/^\d+$/.test(payload[name]) || Number(payload[name]) > 4294967295))) errors[name] = 'Masukkan bilangan bulat 0 sampai 4294967295, atau kosongkan.';
        }
        if (payload.kondisi_listrik !== null && !Object.hasOwn(conditions, payload.kondisi_listrik)) errors.kondisi_listrik = 'Pilih kondisi listrik yang tersedia.';
        if (form.elements.data_per_tanggal.validity.badInput) errors.data_per_tanggal = 'Masukkan tanggal pengamatan yang lengkap dan valid.';
        return errors;
    }
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (busy || editPending) return;
        clearErrors();
        const payload = Object.fromEntries(fields.map(field => [field.name, field.value.trim() || null]));
        for (const name of ['latitude', 'longitude']) if (payload[name]) payload[name] = payload[name].replaceAll(',', '.');
        const errors = validate(payload);
        if (Object.keys(errors).length) return showErrors(errors);
        setBusy(true);
        try {
            const result = await api(selected ? `${base}/${selected}` : base, { method: selected ? 'PUT' : 'POST', body: JSON.stringify(payload) });
            fill(result.wilayah, false); announce(result.message); await loadList();
        } catch (error) {
            showErrors(error.errors ?? {}, error.errors ? 'Periksa isian yang ditandai, lalu simpan kembali.' : `${error.message} Isian belum disimpan.`);
        } finally {
            setBusy(false);
            if (!find('[data-form-error]').hidden) form.querySelector('[aria-invalid="true"]')?.focus();
        }
    });
    find('[data-add]').addEventListener('click', () => { if (canDiscard()) fill(); });
    find('[data-cancel]').addEventListener('click', () => { if (canDiscard()) fill(); });
    find('[data-prev]').addEventListener('click', () => { if (page > 1) { page--; loadList(); } });
    find('[data-next]').addEventListener('click', () => { if (page < lastPage) { page++; loadList(); } });
    find('[data-retry]').addEventListener('click', loadList);
    filters.addEventListener('submit', event => { event.preventDefault(); clearTimeout(debounce); page = 1; loadList(); });
    filters.elements.q.addEventListener('input', () => { clearTimeout(debounce); listAbort?.abort(); page = 1; debounce = setTimeout(loadList, 300); });
    filters.elements.kondisi.addEventListener('change', () => { clearTimeout(debounce); page = 1; loadList(); });
    window.addEventListener('beforeunload', event => { if (dirty() || busy) { event.preventDefault(); event.returnValue = ''; } });
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || link.target === '_blank' || link.getAttribute('href').startsWith('#')) return;
        if (!canDiscard()) event.preventDefault();
        else baseline = snapshot();
    });
    document.querySelectorAll('[data-leave-form]').forEach(el => el.addEventListener('submit', event => {
        if (!canDiscard()) event.preventDefault(); else baseline = snapshot();
    }));
    const initial = new URLSearchParams(location.search);
    filters.elements.q.value = initial.get('q')?.slice(0, 150) ?? '';
    filters.elements.kondisi.value = initial.get('kondisi') ?? '';
    page = Math.max(1, Math.min(1000000, Number.parseInt(initial.get('page'), 10) || 1));
    fill(null, false); loadList();
}

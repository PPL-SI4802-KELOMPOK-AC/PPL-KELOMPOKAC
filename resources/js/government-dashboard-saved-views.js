export const savedViewFocusHash = '#fokus-tampilan';

export function setupSavedDashboardViews(dashboard, getAppliedFilters, applySavedView) {
    const root = dashboard.querySelector('[data-gov-saved-views]');
    const find = selector => root.querySelector(selector);
    const create = dashboard.querySelector('[data-saved-create]');
    const status = find('[data-saved-status]');
    const list = find('[data-saved-list]');
    const items = find('[data-saved-items]');
    const pages = find('[data-saved-pages]');
    const previous = find('[data-saved-prev]');
    const next = find('[data-saved-next]');
    const dialog = find('dialog');
    const confirmation = find('[data-saved-confirm]');
    const form = find('[data-saved-form]');
    const { year, kabupaten, kecamatan, desa, kondisi } = form.elements;
    const name = form.elements.namedItem('name');
    const submit = form.querySelector('[type=submit]');
    const cancel = find('[data-saved-cancel]');
    const warning = find('[data-saved-warning]');
    const feedback = find('[data-saved-error]');
    const controls = [year, kabupaten, kecamatan, desa, kondisi];
    const base = root.dataset.url;
    let page = 1;
    let listRevision = 0;
    let selectedId = null;
    let snapshot;
    let villages = [];
    let lookup;
    let busy = false;
    let saving = false;

    async function request(url, { method = 'GET', body, signal } = {}) {
        const headers = { Accept: 'application/json' };
        if (method !== 'GET') headers['X-CSRF-TOKEN'] = form.elements._token.value;
        if (body) headers['Content-Type'] = 'application/json';
        const response = await fetch(url, { method, headers, body: body && JSON.stringify(body), signal }).catch(error => {
            if (error.name === 'AbortError') throw error;
            throw new Error('Koneksi terputus. Coba lagi.');
        });
        if (response.status === 204) return;
        const data = await response.json().catch(() => null);
        if (response.ok && data) return data;
        const messages = Object.values(data?.errors ?? {}).flat();
        if (response.status === 503 && data?.code === 'saved_views_storage_unavailable' && typeof data.message === 'string') {
            throw new Error(data.message);
        }
        const message = {
            401: 'Sesi berakhir. Silakan masuk kembali.',
            403: 'Anda tidak memiliki akses ke tampilan ini.',
            404: 'Tampilan tersimpan tidak ditemukan.',
            419: 'Sesi berakhir. Muat ulang halaman dan coba lagi.',
        }[response.status];
        throw new Error(messages.join(' ') || message || 'Tampilan tersimpan belum dapat diproses. Coba lagi.');
    }

    function message(element, text = '') {
        element.textContent = text;
        element.hidden = !text;
    }

    function row(view) {
        const item = document.createElement('li');
        const label = document.createElement('strong');
        label.textContent = view.name;
        const description = document.createElement('div');
        description.className = 'gov-saved-description';
        const summary = document.createElement('p');
        summary.className = 'gov-saved-filter-summary';
        summary.textContent = ['year', 'kabupaten', 'kecamatan', 'desa', 'kondisi']
            .map(key => view.filter_labels[key]).join(' · ');
        description.append(label, summary);
        const actions = document.createElement('div');
        actions.className = 'gov-saved-actions';
        for (const action of ['Buka', 'Hanya Terapkan', 'Ubah', 'Hapus']) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'button';
            button.textContent = action;
            button.setAttribute('aria-label', `${action} ${view.name}`);
            button.addEventListener('click', () => handleAction(action, view, button));
            actions.append(button);
        }
        item.append(description, actions);
        return item;
    }

    async function loadPage(target = page) {
        const revision = ++listRevision;
        list.setAttribute('aria-busy', 'true');
        pages.hidden = true;
        items.replaceChildren();
        const notice = (text, state) => {
            const item = document.createElement('li');
            item.textContent = text;
            item.dataset.savedListState = state;
            items.replaceChildren(item);
        };
        notice('Memuat tampilan tersimpan…', 'loading');
        const url = new URL(base, location.origin);
        url.searchParams.set('page', target);
        try {
            const result = await request(url);
            if (revision !== listRevision) return;
            if (target > result.last_page) {
                await loadPage(result.last_page);
                return;
            }
            page = result.current_page;
            if (result.data.length) items.replaceChildren(...result.data.map(row));
            else notice('Belum ada tampilan tersimpan.', 'empty');
            previous.disabled = page <= 1;
            next.disabled = page >= result.last_page;
            find('[data-saved-page]').textContent = `Halaman ${page} dari ${result.last_page}`;
            pages.hidden = result.last_page <= 1;
        } catch (error) {
            if (revision !== listRevision) return;
            notice(error.message, 'error');
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'button';
            retry.textContent = 'Coba lagi';
            retry.addEventListener('click', () => loadPage(target));
            items.firstElementChild.append(' ', retry);
        } finally {
            if (revision === listRevision) list.removeAttribute('aria-busy');
        }
    }

    function selectValue(control, value) {
        control.querySelector('[data-stale-option]')?.remove();
        if (value && !Array.from(control.options).some(option => option.value === value)) {
            const option = new Option(`${value} (tidak tersedia)`, value);
            option.dataset.staleOption = '';
            control.add(option);
        }
        control.value = value ?? '';
    }

    function setVillages(value = '') {
        desa.replaceChildren(new Option('Semua desa/kelurahan', ''), ...villages
            .filter(item => item.subdistrict === kecamatan.value)
            .map(item => new Option(item.name, item.code)));
        selectValue(desa, value);
        desa.disabled = !kecamatan.value && !value;
    }

    async function loadAdministration(subdistrict = '', village = '') {
        lookup?.abort();
        lookup = null;
        submit.disabled = false;
        message(feedback);
        villages = [];
        kecamatan.replaceChildren(new Option('Semua kecamatan', ''));
        kecamatan.disabled = !kabupaten.value && !subdistrict;
        selectValue(kecamatan, subdistrict);
        setVillages(village);
        if (!kabupaten.value || kabupaten.selectedOptions[0]?.hasAttribute('data-stale-option')) return;

        const current = new AbortController();
        let lookupFailed = false;
        lookup = current;
        submit.disabled = kecamatan.disabled = desa.disabled = true;
        const url = new URL(dashboard.dataset.administrationUrl, location.origin);
        url.searchParams.set('kabupaten', kabupaten.value);
        try {
            const options = await request(url, { signal: current.signal });
            if (current.signal.aborted) return;
            villages = options.villages;
            kecamatan.append(...Object.entries(options.subdistricts).map(([code, label]) => new Option(label, code)));
            selectValue(kecamatan, subdistrict);
        } catch {
            if (current.signal.aborted) return;
            lookupFailed = true;
            message(feedback, 'Pilihan wilayah gagal dimuat. Tutup dan buka kembali editor, atau pilih kabupaten/kota lain.');
        } finally {
            if (!current.signal.aborted) {
                submit.disabled = lookupFailed;
                kecamatan.disabled = false;
                setVillages(village);
            }
        }
    }

    function openDialog(view = null, errors = {}) {
        form.reset();
        selectedId = view?.id ?? null;
        find('#gov-saved-dialog-title').textContent = view ? 'Ubah tampilan' : 'Simpan Tampilan';
        find('[data-saved-filters]').hidden = !view;
        controls.forEach(control => { control.disabled = !view; });
        name.value = view?.name ?? '';
        submit.disabled = false;
        message(feedback);
        const problems = Object.values(errors).flat();
        message(warning, problems.length ? `Konfigurasi tersimpan sudah kedaluwarsa. ${problems.join(' ')}` : '');
        if (view) {
            message(find('[data-saved-scope]'));
            year.value = view.year ?? '';
            selectValue(kabupaten, view.kabupaten);
            selectValue(kondisi, view.kondisi);
            loadAdministration(view.kecamatan, view.desa);
        } else {
            const applied = getAppliedFilters();
            snapshot = Object.fromEntries(Object.entries(applied.values).map(([key, value]) => [key, value || null]));
            message(find('[data-saved-scope]'), `Filter aktif: ${Object.values(applied.labels).join(' • ')}`);
        }
        dialog.showModal();
    }

    async function handleAction(action, view, button) {
        if (busy || dialog.open) return;
        busy = true;
        const url = `${base}/${encodeURIComponent(view.id)}`;
        try {
            if (action === 'Hapus') {
                find('#gov-saved-confirm-message').textContent = `Tampilan “${view.name}” akan dihapus.`;
                confirmation.returnValue = '';
                confirmation.showModal();
                const confirmed = await new Promise(resolve => {
                    confirmation.addEventListener('close', () => resolve(confirmation.returnValue === 'delete'), { once: true });
                });
                if (!confirmed) return;
            }
            button.disabled = true;
            message(status);
            if (action === 'Hapus') {
                await request(url, { method: 'DELETE' });
                message(status, 'Tampilan dihapus.');
                await loadPage();
            } else {
                const saved = await request(url);
                if (!saved.dashboard_url || action === 'Ubah') {
                    openDialog(saved.data, saved.filter_errors);
                } else if (action === 'Hanya Terapkan') {
                    await applySavedView(saved.data);
                    message(status, 'Filter tampilan diterapkan.');
                } else {
                    const target = new URL(saved.dashboard_url);
                    target.hash = savedViewFocusHash;
                    if (target.pathname === location.pathname && target.search === location.search) {
                        history.replaceState(history.state, '', target);
                        location.reload();
                    } else {
                        location.assign(target);
                    }
                }
            }
        } catch (error) {
            message(status, error.message);
        } finally {
            busy = button.disabled = false;
        }
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (submit.disabled || saving) return;
        const filters = selectedId === null ? snapshot : Object.fromEntries(controls.map(control => [control.name, control.value || null]));
        const url = selectedId === null ? base : `${base}/${encodeURIComponent(selectedId)}`;
        saving = submit.disabled = cancel.disabled = true;
        name.readOnly = true;
        const enabledFilters = controls.filter(control => !control.disabled);
        enabledFilters.forEach(control => { control.disabled = true; });
        message(feedback);
        try {
            await request(url, { method: selectedId === null ? 'POST' : 'PUT', body: { name: name.value, ...filters } });
            message(status, selectedId === null ? 'Tampilan disimpan.' : 'Tampilan diperbarui.');
            dialog.close();
            if (list.open) loadPage(selectedId === null ? 1 : page);
            else list.open = true;
        } catch (error) {
            message(feedback, error.message);
        } finally {
            enabledFilters.forEach(control => { control.disabled = false; });
            name.readOnly = saving = submit.disabled = cancel.disabled = false;
        }
    });
    dialog.addEventListener('cancel', event => { if (cancel.disabled) event.preventDefault(); });
    dialog.addEventListener('close', () => lookup?.abort());
    cancel.addEventListener('click', () => dialog.close());
    kabupaten.addEventListener('change', () => loadAdministration());
    kecamatan.addEventListener('change', () => setVillages());
    create.addEventListener('click', () => { if (!busy && getAppliedFilters()) openDialog(); });
    list.addEventListener('toggle', () => { if (list.open) loadPage(); });
    previous.addEventListener('click', () => loadPage(page - 1));
    next.addEventListener('click', () => loadPage(page + 1));

    return { updateAvailability() { create.disabled = !getAppliedFilters(); } };
}

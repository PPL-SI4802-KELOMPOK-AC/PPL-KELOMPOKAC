import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const element = document.querySelector('[data-region-map]');
const panel = document.querySelector('[data-map-panel]');
const status = document.querySelector('[data-map-status]');
const retry = document.querySelector('[data-map-retry]');
const reset = document.querySelector('[data-map-reset]');
const filters = document.querySelector('[data-map-filters]');
const dataStatus = document.querySelector('[data-map-data-status]');
const dataState = document.querySelector('[data-map-data-state]');
const dataRetry = document.querySelector('[data-map-data-retry]');
const rows = document.querySelector('[data-map-rows]');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const events = new AbortController();
const number = value => value === null || value === undefined ? 'Belum tersedia' : new Intl.NumberFormat('id-ID').format(value);
const categories = {
    has_unserved: { label: 'Ada KK belum berlistrik', symbol: '!' },
    no_unserved: { label: 'Tidak tercatat KK belum berlistrik', symbol: '0' },
    unavailable: { label: 'Data belum tersedia', symbol: '?' },
};
const familyCount = value => value === null || value === undefined ? 'Data belum tersedia' : `${number(value)} keluarga`;
let homeBounds = L.latLngBounds([[-7.85, 106.35], [-5.9, 108.85]]);
let tileLayer, tileTimeout, tileFailed = false, disposed = false, zooming = false, resetAfterZoom = false;
let provinceLayer, maskLayer, boundaryReady = false;
let dataAbort, debounce, page = 1, lastPage = 1, selectedId = null;
let districtOptionsLoaded = false;
const initial = new URLSearchParams(location.search);
filters.elements.q.value = (initial.get('q') ?? '').slice(0, 150);
for (const name of ['condition', 'location']) {
    const value = initial.get(name) ?? 'all';
    if ([...filters.elements[name].options].some(option => option.value === value)) filters.elements[name].value = value;
}
page = Math.max(1, Number.parseInt(initial.get('page'), 10) || 1);
if (filters.elements.location.value !== 'all') filters.querySelector('details').open = true;
const map = L.map(element, { zoomControl: false, minZoom: 5, maxZoom: 19, maxBoundsViscosity: 1, zoomAnimation: !reducedMotion.matches, fadeAnimation: !reducedMotion.matches });
map.on('zoomstart', () => { zooming = true; });
map.on('zoomend', () => {
    zooming = false;
    if (resetAfterZoom) { resetAfterZoom = false; requestAnimationFrame(() => home(true)); }
});
L.control.zoom({ position: 'topright', zoomInTitle: 'Perbesar peta', zoomOutTitle: 'Perkecil peta' }).addTo(map);
L.control.scale({ position: 'bottomleft', imperial: false }).addTo(map);
const markers = L.layerGroup().addTo(map);
const selection = L.layerGroup().addTo(map);
function home(animate = false) {
    if (disposed) return;
    if (zooming) { resetAfterZoom = true; return; }
    map.fitBounds(homeBounds, { padding: [24, 24], animate: animate && !reducedMotion.matches });
}
function setTileStatus(state, message) {
    if (disposed) return;
    panel.dataset.state = state;
    status.textContent = message;
    element.setAttribute('aria-busy', String(state === 'loading'));
    retry.hidden = state !== 'error';
}
function tileFailure() {
    tileFailed = true; clearTimeout(tileTimeout);
    setTileStatus('error', 'Sebagian atau seluruh peta dasar gagal dimuat. Periksa koneksi internet, lalu coba lagi.');
}
function tileLoading() {
    if (tileFailed) return;
    setTileStatus('loading', 'Memuat peta dasar…');
    clearTimeout(tileTimeout); tileTimeout = setTimeout(tileFailure, 15000);
}
function loadTiles() {
    clearTimeout(tileTimeout);
    if (tileLayer) { tileLayer.off(); tileLayer.remove(); }
    tileFailed = false; tileLoading();
    tileLayer = L.tileLayer(element.dataset.tileUrl, { attribution: element.dataset.attribution, maxZoom: 19, keepBuffer: 2 });
    tileLayer.on('loading', tileLoading);
    tileLayer.on('tileerror', tileFailure);
    tileLayer.on('load', () => { clearTimeout(tileTimeout); if (!tileFailed) setTileStatus('ready', 'Peta dasar siap dijelajahi'); });
    tileLayer.addTo(map);
}
async function json(url, signal) {
    const response = await fetch(url, { signal, headers: { Accept: 'application/json' } });
    const body = await response.json();
    if (!response.ok) throw new Error(response.status === 429 ? 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.' : (body.message || 'Permintaan data gagal.'));
    return body;
}
async function loadBoundary() {
    const state = document.querySelector('[data-boundary-state]');
    const label = document.querySelector('[data-boundary-status]');
    const retryBoundary = document.querySelector('[data-boundary-retry]');
    state.dataset.boundaryState = 'loading'; retryBoundary.hidden = true;
    try {
        const boundary = await json(element.dataset.boundaryUrl, events.signal);
        if (!['Polygon', 'MultiPolygon'].includes(boundary.geometry?.type)) throw new Error('Batas tidak valid.');
        provinceLayer?.remove(); maskLayer?.remove();
        provinceLayer = L.geoJSON(boundary, { interactive: false, style: { color: '#0d5d76', weight: 2.5, fillColor: '#48a3b5', fillOpacity: .035 } }).addTo(map);
        const polygons = boundary.geometry.type === 'Polygon' ? [boundary.geometry.coordinates] : boundary.geometry.coordinates;
        const maskRings = [[[-85, -180], [85, -180], [85, 180], [-85, 180]]];
        polygons.forEach(polygon => polygon.forEach(ring => maskRings.push(ring.map(([lng, lat]) => [lat, lng]))));
        if (!map.getPane('province-mask')) { map.createPane('province-mask'); map.getPane('province-mask').style.zIndex = 350; map.getPane('province-mask').style.pointerEvents = 'none'; }
        maskLayer = L.polygon(maskRings, { pane: 'province-mask', interactive: false, stroke: false, fillColor: '#e5edf2', fillOpacity: .97, fillRule: 'evenodd', className: 'province-mask' }).addTo(map);
        homeBounds = provinceLayer.getBounds();
        map.setMaxBounds(homeBounds.pad(.13));
        map.setMinZoom(Math.max(5, map.getBoundsZoom(homeBounds, false, L.point(48, 48))));
        home(); boundaryReady = true;
        state.dataset.boundaryState = 'ready';
        label.textContent = 'Batas referensi Jawa Barat ditampilkan';
    } catch (error) {
        if (error.name === 'AbortError') return;
        state.dataset.boundaryState = 'error';
        label.textContent = 'Batas provinsi gagal dimuat. Pembatasan administratif belum dapat ditampilkan.';
        retryBoundary.hidden = false;
    }
}
function textNode(tag, text, className = '') {
    const node = document.createElement(tag); node.textContent = text;
    if (className) node.className = className;
    return node;
}
function detailBlock(title, values, notes = []) {
    const block = document.createElement('section'); block.className = 'detail-block';
    block.append(textNode('h3', title));
    const dl = document.createElement('dl');
    values.forEach(([label, value]) => dl.append(textNode('dt', label), textNode('dd', value)));
    block.append(dl);
    notes.forEach(([text, className]) => block.append(textNode('p', text, className)));
    return block;
}
function selectRegion(record, pan = false) {
    selectedId = record.id;
    document.querySelector('#map-detail-title').textContent = record.name;
    const detail = document.querySelector('[data-map-detail]');
    detail.replaceChildren(textNode('p', record.address), textNode('p', `Kode Kemendagri: ${record.code ?? 'Belum tersedia'}`, 'source-text'));
    const csv = record.csv;
    detail.append(textNode('p', categories[record.category].label, `condition-label ${record.category}`));
    detail.append(detailBlock('Data wilayah · 2024', [
        ['Kecamatan', csv?.kecamatan_name ?? 'Belum tersedia'],
        ['Kabupaten/kota', csv?.kabupaten_name ?? 'Belum tersedia'],
        ['Keluarga belum berlistrik', familyCount(csv?.belum_listrik)],
        ['Keluarga pengguna PLN', familyCount(csv?.pln)],
        ['Tahun data', csv ? String(csv.year) : 'Belum tersedia'],
    ], [['Sumber: dataset acuan proyek • Tahun 2024', 'source-text']]));
    if (record.government.condition || record.government.affected_kk !== null || record.government.observation_date || record.government.source) detail.append(detailBlock('Catatan pada aplikasi', [
        ['Kondisi listrik', record.government.condition_label],
        ['KK terdampak', number(record.government.affected_kk)],
        ['Tanggal pengamatan', record.government.observation_date ?? 'Belum tersedia'],
    ], [[`Sumber: ${record.government.source ?? 'Belum dicatat'}`, 'source-text']]));
    detail.append(detailBlock(record.location ? 'Lokasi referensi desa' : 'Lokasi belum tersedia', [], [
        [record.location ? record.location.source_label : record.location_unavailable_reason, 'source-text'],
        ['Titik bukan lokasi rumah, kelompok keluarga, atau calon pemasangan PLTS.', 'detail-hint'],
    ]));
    selection.clearLayers();
    if (record.location) {
        const [lng, lat] = record.location.coordinates;
        L.circleMarker([lat, lng], { radius: 15, color: '#f5ac00', weight: 3, fill: false, interactive: false }).addTo(selection);
        if (pan) map.setView([lat, lng], Math.max(map.getZoom(), 13), { animate: !reducedMotion.matches });
    }
    rows.querySelectorAll('tr').forEach(row => row.classList.toggle('is-selected', Number(row.dataset.id) === selectedId));
    if (pan) { document.querySelector('#map-detail-title').focus({ preventScroll: true }); document.querySelector('.map-workspace').scrollIntoView({ block: 'nearest', behavior: reducedMotion.matches ? 'auto' : 'smooth' }); }
}
function villagePopup(record) {
    const popup = document.createElement('div'); popup.className = 'map-popup';
    popup.append(textNode('h3', record.name), textNode('p', 'Lokasi referensi desa'), textNode('p', categories[record.category].label));
    popup.append(textNode('p', `Keluarga belum berlistrik: ${familyCount(record.csv?.belum_listrik)}`));
    popup.append(textNode('p', 'Sumber: dataset acuan proyek • Tahun 2024'));
    const button = textNode('button', 'Baca informasi', 'button'); button.type = 'button';
    button.addEventListener('click', () => selectRegion(record, false)); popup.append(button);
    return popup;
}
function renderMarkers(features) {
    markers.clearLayers();
    let count = 0;
    features.forEach(feature => {
        if (feature.type === 'cluster') {
            const [lng, lat] = feature.coordinates;
            const categories = Object.keys(feature.categories);
            const category = categories.length === 1 && ['has_unserved', 'no_unserved', 'unavailable'].includes(categories[0]) ? categories[0] : '';
            const marker = L.marker([lat, lng], { icon: L.divIcon({ className: 'cluster-icon', html: `<span class="cluster-bubble ${category}">${number(feature.count)}</span>`, iconSize: [42, 42], iconAnchor: [21, 21] }), title: `${feature.count} lokasi referensi desa`, alt: `${feature.count} lokasi referensi desa` });
            const popup = document.createElement('div'); popup.className = 'map-popup';
            popup.append(textNode('h3', `${number(feature.count)} lokasi desa`));
            popup.append(textNode('p', `Ada KK belum berlistrik: ${number(feature.categories.has_unserved ?? 0)} desa · Tidak tercatat KK belum berlistrik: ${number(feature.categories.no_unserved ?? 0)} desa · Data belum tersedia: ${number(feature.categories.unavailable ?? 0)} desa`));
            popup.append(textNode('p', 'Kelompok penanda desa, bukan jumlah keluarga. Gunakan pencarian/daftar untuk memilih desa.'));
            const button = textNode('button', 'Perbesar kelompok', 'button'); button.type = 'button';
            button.addEventListener('click', () => { map.closePopup(); map.fitBounds(feature.bounds, { padding: [40, 40], maxZoom: Math.min(19, map.getZoom() + 3), animate: !reducedMotion.matches }); });
            popup.append(button); marker.bindPopup(popup, { autoPan: false }).addTo(markers); count += feature.count;
        } else {
            const record = feature.record;
            const [lng, lat] = record.location.coordinates;
            const label = `Lokasi referensi desa ${record.name} — ${categories[record.category].label}`;
            const marker = L.marker([lat, lng], { icon: L.divIcon({ className: 'village-icon', html: `<span class="village-dot ${record.category}" aria-hidden="true">${categories[record.category].symbol}</span>`, iconSize: [30, 30], iconAnchor: [15, 15] }), title: label, alt: label });
            marker.bindPopup(villagePopup(record), { autoPan: false }).on('click', () => selectRegion(record)).addTo(markers); count++;
        }
    });
    element.dataset.visibleLocations = String(count);
    element.dataset.markerCount = String(features.length);
}
function renderList(list) {
    rows.replaceChildren();
    list.data.forEach(record => {
        const row = document.createElement('tr'); row.dataset.id = record.id;
        row.classList.toggle('is-selected', record.id === selectedId);
        const name = document.createElement('td'); name.append(textNode('strong', record.name), textNode('small', record.address));
        const csv = document.createElement('td');
        csv.append(textNode('span', familyCount(record.csv?.belum_listrik)));
        const condition = document.createElement('td');
        condition.append(textNode('span', categories[record.category].label, `condition-label ${record.category}`));
        const location = textNode('td', record.location ? 'Referensi desa' : 'Belum tersedia');
        const action = document.createElement('td');
        const button = textNode('button', 'Lihat', 'button'); button.type = 'button'; button.setAttribute('aria-label', `Lihat ${record.name}`);
        button.addEventListener('click', () => selectRegion(record, true)); action.append(button);
        row.append(name, csv, condition, location, action); rows.append(row);
    });
    page = list.current_page; lastPage = list.last_page;
    document.querySelector('[data-map-list-count]').textContent = list.total ? `${(page - 1) * 15 + 1}–${Math.min(page * 15, list.total)} dari ${number(list.total)} wilayah` : '0 wilayah';
    document.querySelector('[data-map-page]').textContent = `${page} / ${lastPage}`;
    document.querySelector('[data-map-prev]').disabled = page <= 1;
    document.querySelector('[data-map-next]').disabled = page >= lastPage;
    const empty = document.querySelector('[data-map-empty]'); empty.hidden = list.total !== 0;
    empty.textContent = 'Tidak ada wilayah yang sesuai pencarian atau filter. Peta dasar tetap dapat digunakan.';
}
async function loadData() {
    if (disposed) return;
    dataAbort?.abort(); const abort = new AbortController(); dataAbort = abort;
    let timedOut = false;
    const timeout = setTimeout(() => { timedOut = true; abort.abort(); }, 15000);
    dataState.dataset.mapDataState = 'loading'; dataStatus.textContent = 'Memuat data wilayah…'; dataRetry.hidden = true;
    rows.setAttribute('aria-busy', 'true');
    document.querySelector('[data-map-prev]').disabled = true; document.querySelector('[data-map-next]').disabled = true;
    const bounds = map.getBounds();
    const params = new URLSearchParams({ q: filters.elements.q.value.trim(), condition: filters.elements.condition.value, kabupaten: filters.elements.kabupaten.value, location: filters.elements.location.value, page, zoom: map.getZoom(), bbox: [bounds.getWest(), bounds.getSouth(), bounds.getEast(), bounds.getNorth()].map(value => value.toFixed(7)).join(',') });
    try {
        const result = await json(`${element.dataset.dataUrl}?${params}`, abort.signal);
        if (abort.signal.aborted) return;
        if (!result.summary || !Array.isArray(result.features) || !result.list) throw new Error('Respons data wilayah tidak lengkap.');
        if (!districtOptionsLoaded) {
            for (const district of result.kabupaten) { const option = textNode('option', district.name); option.value = district.code; filters.elements.kabupaten.append(option); }
            districtOptionsLoaded = true;
            if (initial.get('kabupaten') && !filters.elements.kabupaten.value) {
                const value = initial.get('kabupaten');
                if ([...filters.elements.kabupaten.options].some(option => option.value === value)) { filters.elements.kabupaten.value = value; return loadData(); }
            }
        }
        renderMarkers(result.features); renderList(result.list);
        const s = result.summary;
        dataStatus.textContent = s.total ? `${number(s.total)} wilayah sesuai filter · ${number(s.located)} lokasi tersedia · ${number(s.unlocated)} lokasi belum tersedia` : 'Tidak ada wilayah yang sesuai pencarian atau filter. Peta dasar tetap tampil.';
        dataState.dataset.mapDataState = 'ready';
        document.querySelector('[data-map-list-caption]').textContent = filters.elements.location.value === 'missing' ? 'Wilayah tetap tercantum dalam daftar; lokasi pada peta belum tersedia.' : 'Daftar mengikuti filter. Perbesar peta untuk melihat lokasi desa.';
        const url = new URLSearchParams({ q: filters.elements.q.value.trim(), condition: filters.elements.condition.value, kabupaten: filters.elements.kabupaten.value, location: filters.elements.location.value, page });
        history.replaceState(null, '', `${location.pathname}?${url}`);
    } catch (error) {
        if (dataAbort !== abort || disposed || (error.name === 'AbortError' && !timedOut)) return;
        renderMarkers([]); rows.replaceChildren();
        document.querySelector('[data-map-empty]').hidden = true;
        document.querySelector('[data-map-list-count]').textContent = 'Jumlah hasil belum tersedia';
        document.querySelector('[data-map-page]').textContent = '—';
        dataStatus.textContent = `Gagal mengambil data wilayah. ${timedOut ? 'Waktu tunggu habis. Silakan coba lagi.' : error.message === 'Failed to fetch' ? 'Periksa koneksi lalu coba lagi.' : error.message}`;
        dataState.dataset.mapDataState = 'error'; dataRetry.hidden = false;
    } finally {
        clearTimeout(timeout);
        if (dataAbort === abort) rows.setAttribute('aria-busy', 'false');
    }
}
function filterChanged() {
    clearTimeout(debounce); dataAbort?.abort(); page = 1;
    selectedId = null; selection.clearLayers(); map.closePopup();
    renderMarkers([]); rows.replaceChildren();
    dataState.dataset.mapDataState = 'loading'; dataStatus.textContent = 'Memuat data wilayah…';
    dataRetry.hidden = true;
    document.querySelector('[data-map-empty]').hidden = true;
    document.querySelector('[data-map-prev]').disabled = true;
    document.querySelector('[data-map-next]').disabled = true;
    document.querySelector('#map-detail-title').textContent = 'Pilih wilayah';
    document.querySelector('[data-map-detail]').replaceChildren(textNode('p', 'Pilih penanda atau tombol “Lihat” pada daftar untuk membaca informasi wilayah.'));
    debounce = setTimeout(loadData, 300);
}
filters.addEventListener('submit', event => { event.preventDefault(); filterChanged(); clearTimeout(debounce); loadData(); });
filters.addEventListener('reset', event => {
    event.preventDefault();
    filters.elements.q.value = ''; filters.elements.kabupaten.value = '';
    filters.elements.condition.value = 'all'; filters.elements.location.value = 'all';
    initial.delete('kabupaten'); filters.querySelector('details').open = false;
    filterChanged(); clearTimeout(debounce); loadData();
});
filters.elements.q.addEventListener('input', filterChanged);
for (const name of ['condition', 'location', 'kabupaten']) filters.elements[name].addEventListener('change', filterChanged);
document.querySelector('[data-map-prev]').addEventListener('click', () => { if (page > 1) { page--; loadData(); } });
document.querySelector('[data-map-next]').addEventListener('click', () => { if (page < lastPage) { page++; loadData(); } });
dataRetry.addEventListener('click', loadData);
document.querySelector('[data-boundary-retry]').addEventListener('click', async () => { await loadBoundary(); loadData(); });
reset.addEventListener('click', () => home(true), { signal: events.signal });
retry.addEventListener('click', loadTiles, { signal: events.signal });
map.on('moveend', () => { if (boundaryReady) { clearTimeout(debounce); debounce = setTimeout(loadData, 180); } });
function motionChanged() { map.stop(); map.options.zoomAnimation = !reducedMotion.matches; map.options.fadeAnimation = !reducedMotion.matches; }
reducedMotion.addEventListener('change', motionChanged);
const resize = new ResizeObserver(() => { map.invalidateSize({ animate: false, pan: false }); if (boundaryReady) map.setMinZoom(Math.max(5, map.getBoundsZoom(homeBounds, false, L.point(48, 48)))); });
resize.observe(element);
home(); loadTiles(); reset.disabled = false;
loadBoundary().finally(() => loadData());
window.addEventListener('pagehide', () => {
    disposed = true; events.abort(); dataAbort?.abort(); clearTimeout(debounce); clearTimeout(tileTimeout);
    resize.disconnect(); reducedMotion.removeEventListener('change', motionChanged);
    tileLayer?.off(); map.remove();
}, { once: true });
window.addEventListener('pageshow', event => { if (event.persisted && disposed) location.reload(); });

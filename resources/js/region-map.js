import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const element = document.querySelector('[data-region-map]');
const panel = document.querySelector('[data-map-panel]');
const status = document.querySelector('[data-map-status]');
const retry = document.querySelector('[data-map-retry]');
const reset = document.querySelector('[data-map-reset]');
const detailPanel = document.querySelector('[data-map-detail-panel]');
const closeDetail = document.querySelector('[data-map-close-detail]');
const showDetail = document.querySelector('[data-map-show-detail]');
const filters = document.querySelector('[data-map-filters]');
const searchSuggestions = document.querySelector('#map-search-suggestions');
const dataStatus = document.querySelector('[data-map-data-status]');
const dataState = document.querySelector('[data-map-data-state]');
const dataRetry = document.querySelector('[data-map-data-retry]');
const rows = document.querySelector('[data-map-rows]');
const preview = document.querySelector('[data-map-preview]');
const searchResults = document.querySelector('[data-map-search-results]');
const searchGlobal = document.querySelector('[data-map-search-global]');
const expand = document.querySelector('[data-map-expand]');
const districtLayers = new Map();
let pinnedPreview = null, previousOverflow = '', expandReturnFocus = null;
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
let dataAbort, debounce, page = 1, lastPage = 1, selectedId = null, searchRecords = [];
let districtOptionsLoaded = false;
const initial = new URLSearchParams(location.search);
let focusSearch = Boolean(initial.get('q'));
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
const areas = L.layerGroup().addTo(map);
const geometryCache = new Map();
let areaLayers = new Map(), renderedDistrict = null, activeVillages = {}, scopeBounds = null;
const areaColors = { has_unserved: '#c53d48', no_unserved: '#2f8b63', unavailable: '#b8c4ce' };
const areaStatus = document.querySelector('[data-map-area-status]');
const legend = document.querySelector('[data-area-legend]');
const selection = L.layerGroup().addTo(map);
function closeAreaTooltips() { areas.eachLayer(group => group.eachLayer(shape => shape.closeTooltip())); }
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
function setPreview(name, value, hint, level = 'RINGKASAN WILAYAH', category = '') {
    preview.querySelector('[data-preview-name]').textContent = name;
    preview.querySelector('[data-preview-value]').textContent = value;
    preview.querySelector('[data-preview-hint]').textContent = hint;
    preview.querySelector('[data-preview-level]').textContent = level;
    preview.dataset.category = category;
}
function resetPreview() {
    if (pinnedPreview) setPreview(...pinnedPreview);
    else setPreview('Mulai dari kabupaten', 'Arahkan kursor ke area berwarna', 'Klik untuk membuka desa dan jumlah KK', 'JELAJAHI WILAYAH');
}
function syncQuickFilters() {
    document.querySelectorAll('[data-map-quick]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.mapQuick === filters.elements.condition.value)));
}
function setContext() {
    const district = filters.elements.kabupaten.value;
    document.querySelector('[data-map-context]').textContent = district ? filters.elements.kabupaten.selectedOptions[0]?.textContent : 'Jawa Barat';
    document.querySelector('[data-map-context-hint]').textContent = district ? 'Jawa Barat / Desa dan kelurahan' : 'Pilih kabupaten untuk menjelajahi desa';
}
function renderSearchResults(result) {
    searchResults.replaceChildren();
    searchResults.hidden = !filters.elements.q.value.trim() || result.list.total === 0;
    if (searchResults.hidden) return;
    searchResults.append(textNode('p', 'Pilih hasil untuk menyorot area desa', 'map-search-caption'));
    const choices = document.createElement('div'); choices.className = 'map-search-choices';
    for (const record of result.list.data.slice(0, 5)) {
        const button = textNode('button', '', 'map-search-choice'); button.type = 'button';
        button.setAttribute('aria-label', 'Sorot ' + record.name + ', ' + (record.administration.subdistrict_name ?? record.address));
        button.append(textNode('strong', record.name), textNode('small', [record.administration.subdistrict_name, record.administration.district_name].filter(Boolean).join(' · ') || record.address), textNode('span', number(record.electricity.unserved_families) + ' KK belum berlistrik'));
        button.addEventListener('click', () => selectRegion(record, true), { signal: events.signal }); choices.append(button);
    }
    searchResults.append(choices);
}
function focusSearchResult(result) {
    if (!focusSearch || !filters.elements.q.value.trim()) return;
    const bounds = L.latLngBounds([]);
    const shapes = result.areas.level === 'village' ? areaLayers.values()
        : Object.keys(result.areas.districts).map(code => districtLayers.get(code)).filter(Boolean);
    for (const shape of shapes) bounds.extend(shape.getBounds());
    if (bounds.isValid()) map.fitBounds(bounds, { padding: [35, 35], maxZoom: 13, animate: !reducedMotion.matches });
    focusSearch = false;
}
function selectRegion(record, pan = false) {
    closeAreaTooltips();
    selectedId = record.id;
    pinnedPreview = [record.name, number(record.electricity.unserved_families) + ' KK belum berlistrik', record.administration.subdistrict_name ?? record.address, 'DESA TERPILIH', record.category];
    resetPreview();
    const shape = areaLayers.get(record.code);
    document.querySelector('#map-detail-title').textContent = record.name;
    const detail = document.querySelector('[data-map-detail]');
    detailPanel.hidden = false; showDetail.hidden = true;
    document.querySelector('.map-workspace').classList.remove('detail-closed');
    detail.replaceChildren(textNode('p', record.address));
    const electricity = record.electricity;
    detail.append(textNode('p', categories[record.category].label, `condition-label ${record.category}`));
    detail.append(detailBlock('Ringkasan', [
        ['Kecamatan', record.administration.subdistrict_name ?? 'Belum tersedia'],
        ['Kabupaten/kota', record.administration.district_name ?? 'Belum tersedia'],
        ['KK belum berlistrik', familyCount(electricity.unserved_families)],
        ['Tahun data', electricity.year === null ? 'Belum tersedia' : String(electricity.year)],
        ['Kode wilayah', record.code ?? 'Belum tersedia'],
    ]));
    selection.clearLayers();
    if (shape) {
        const selectedGeometry = shape.toGeoJSON();
        L.geoJSON(selectedGeometry, { interactive: false, style: { color: '#fff', weight: 7, opacity: .98, fillOpacity: 0 } }).addTo(selection);
        L.geoJSON(selectedGeometry, { interactive: false, style: { color: '#123b52', weight: 4, opacity: 1, fillColor: '#ffd54a', fillOpacity: .42, className: 'selected-area' } }).addTo(selection);
        if (pan) {
            const subdistrict = record.administration.subdistrict_name;
            const bounds = L.latLngBounds([]);
            for (const [code, villageShape] of areaLayers) {
                if ((activeVillages[code]?.administration.subdistrict_name ?? null) === subdistrict) bounds.extend(villageShape.getBounds());
            }
            map.fitBounds(bounds.isValid() ? bounds : shape.getBounds(), { padding: [35, 35], maxZoom: 12, animate: !reducedMotion.matches });
        }
    } else if (pan && record.administration.district_code && filters.elements.kabupaten.value !== record.administration.district_code) {
        filters.elements.kabupaten.value = record.administration.district_code;
        const id = record.id;
        loadData().then(() => { if (selectedId === id) selectRegion(record, true); });
    }
    rows.querySelectorAll('tr').forEach(row => row.classList.toggle('is-selected', Number(row.dataset.id) === selectedId));
    if (pan) { requestAnimationFrame(() => map.invalidateSize({ animate: false, pan: true })); document.querySelector('#map-detail-title').focus({ preventScroll: true }); document.querySelector('.map-workspace').scrollIntoView({ block: 'nearest', behavior: reducedMotion.matches ? 'auto' : 'smooth' }); }
}
function shareColor(share) {
    return share === null ? '#b8c4ce' : share === 0 ? '#2f8b63'
        : share <= 10 ? '#8fbd71' : share <= 25 ? '#f0c75b' : share <= 50 ? '#e88a4c' : '#c53d48';
}
function clearAreas() {
    areas.clearLayers(); areaLayers.clear(); districtLayers.clear(); activeVillages = {}; scopeBounds = null;
    element.dataset.areaCount = '0';
}
async function geometry(district, signal) {
    const key = district || 'districts';
    if (!geometryCache.has(key)) {
        const name = district ? 'villages-' + district : 'districts';
        const data = await json(element.dataset.areasUrl + '/' + name + '.geojson', signal);
        if (data.type !== 'FeatureCollection' || !Array.isArray(data.features)) throw new Error('Batas wilayah tidak valid.');
        geometryCache.set(key, data);
    }
    return geometryCache.get(key);
}
function areaTooltip(name, lines) {
    const box = document.createElement('div'); box.className = 'area-tooltip';
    box.append(textNode('strong', name));
    for (const [index, line] of lines.entries()) box.append(textNode('div', line, index === 1 ? 'area-tooltip__status' : 'area-tooltip__meta'));
    return box;
}
async function renderAreas(data, signal) {
    if (!data) throw new Error('Ringkasan area belum tersedia.');
    areaStatus.textContent = 'Memuat batas wilayah…';
    const collection = await geometry(data.district, signal);
    if (signal.aborted) return;
    clearAreas(); selection.clearLayers();
    activeVillages = data.villages;
    const isVillage = data.level === 'village';
    const layer = L.geoJSON(collection, {
        style: feature => {
            const value = isVillage ? data.villages[feature.properties.code] : data.districts[feature.properties.code];
            const color = isVillage ? areaColors[value?.category ?? 'unavailable'] : shareColor(value?.share ?? null);
            return { color: '#fff', weight: isVillage ? .9 : 1.5, fillColor: color,
                fillOpacity: value ? .78 : .5, className: 'map-area ' + (isVillage ? 'village-area' : 'district-area') };
        },
        onEachFeature: (feature, shape) => {
            const code = feature.properties.code;
            const record = isVillage ? data.villages[code] : null;
            const summary = data.districts[code];
            const name = isVillage ? feature.properties.name : summary?.name ?? feature.properties.name;
            const lines = isVillage ? [feature.properties.subdistrict + ' · ' + feature.properties.district,
                record ? categories[record.category].label : 'Data belum tersedia',
                'KK belum berlistrik: ' + familyCount(record?.electricity.unserved_families)]
                : [summary ? summary.has_unserved + ' dari ' + summary.known + ' desa memiliki KK belum berlistrik' : 'Data aplikasi belum tersedia',
                    summary?.unavailable ? summary.unavailable + ' desa tanpa angka' : 'Klik untuk melihat desa/kelurahan'];
            shape.bindTooltip(areaTooltip(name, lines), { sticky: true, className: 'area-tooltip-shell' });
            const previewArea = () => setPreview(name,
                number(isVillage ? record?.electricity.unserved_families : summary?.families) + ' KK belum berlistrik',
                isVillage ? feature.properties.subdistrict + ' · ' + feature.properties.district : 'Klik untuk membuka desa/kelurahan',
                isVillage ? 'DESA / KELURAHAN' : 'KABUPATEN / KOTA', isVillage ? record?.category ?? 'unavailable' : '');
            shape.on('mouseover', () => { previewArea(); shape.setStyle({ weight: 2.5, color: '#234a5f', fillOpacity: .94 }); shape.bringToFront(); });
            shape.on('mouseout', () => {
                // Hover only affects the feature temporarily. Persistent selection is
                // drawn in `selection`, so never leave a selected feature's base style mutated.
                layer.resetStyle(shape); resetPreview();
                selection.eachLayer(group => group.eachLayer(selectedLayer => selectedLayer.bringToFront?.()));
            });
            shape.on('click', () => {
                shape.closeTooltip();
                if (isVillage) {
                    if (record) selectRegion(record, true);
                    else {
                        selectedId = null; selection.clearLayers();
                        pinnedPreview = [name, 'Data KK belum tersedia', feature.properties.subdistrict + ' · ' + feature.properties.district, 'DESA TERPILIH', 'unavailable'];
                        resetPreview();
                        detailPanel.hidden = false; showDetail.hidden = true;
                        document.querySelector('.map-workspace').classList.remove('detail-closed');
                        document.querySelector('#map-detail-title').textContent = name;
                        document.querySelector('[data-map-detail]').replaceChildren(textNode('p', feature.properties.subdistrict + ', ' + feature.properties.district), textNode('p', 'Batas desa tersedia, tetapi belum ada data aplikasi yang cocok. Warna abu-abu tidak berarti nol KK.'));
                    }
                } else {
                    map.fitBounds(shape.getBounds(), { padding: [28, 28], maxZoom: 10, animate: !reducedMotion.matches });
                    filters.elements.kabupaten.value = code;
                    if (filters.elements.kabupaten.value === code) { filterChanged(); clearTimeout(debounce); loadData(); }
                    else {
                        document.querySelector('#map-detail-title').textContent = name;
                        document.querySelector('[data-map-detail]').replaceChildren(textNode('p', 'Batas kabupaten tersedia; data aplikasi untuk kabupaten ini belum tersedia.'));
                    }
                }
            });
            shape.on('add', () => {
                const path = shape.getElement?.();
                if (path) {
                    path.setAttribute('tabindex', '0'); path.setAttribute('role', 'button');
                    path.setAttribute('aria-label', name + (isVillage ? ' — ' + (record ? categories[record.category].label : 'Data belum tersedia') : ' — Lihat desa'));
                    path.dataset.areaCode = code;
                    L.DomEvent.on(path, 'focus', previewArea);
                    L.DomEvent.on(path, 'blur', resetPreview);
                    L.DomEvent.on(path, 'keydown', event => { if (event.key === 'Enter' || event.key === ' ') { L.DomEvent.preventDefault(event); shape.fire('click'); } });
                }
            });
            if (isVillage && record) areaLayers.set(code, shape);
            if (!isVillage) districtLayers.set(code, shape);
        },
    }).addTo(areas);
    scopeBounds = layer.getBounds();
    element.dataset.areaLevel = data.level;
    element.dataset.areaCount = String(collection.features.length);
    const matched = isVillage ? Object.keys(data.villages).length : Object.keys(data.districts).length;
    areaStatus.textContent = isVillage ? collection.features.length + ' batas desa/kelurahan · ' + matched + ' cocok dengan hasil filter' : '27 kabupaten/kota · Klik area untuk membuka desa/kelurahan';
    legend.replaceChildren();
    const items = isVillage ? [['#c53d48', 'Ada KK belum berlistrik'], ['#2f8b63', 'Tidak tercatat KK belum berlistrik'], ['#b8c4ce', 'Data belum tersedia / tidak cocok']]
        : [['#2f8b63', '0%'], ['#8fbd71', '>0–10%'], ['#f0c75b', '>10–25%'], ['#e88a4c', '>25–50%'], ['#c53d48', '>50%'], ['#b8c4ce', 'Belum ada angka']];
    legend.append(textNode('strong', isVillage ? 'Kondisi desa:' : 'Desa dengan KK belum berlistrik:'));
    for (const [color, label] of items) { const item = textNode('span', ''); const swatch = document.createElement('i'); swatch.className = 'area-swatch'; swatch.style.backgroundColor = color; item.append(swatch, document.createTextNode(label)); legend.append(item); }
    if (!isVillage) legend.append(textNode('small', 'Persentase dari desa yang angkanya tersedia dalam hasil filter; bukan rasio elektrifikasi.', 'area-legend-note'));
    if (isVillage && renderedDistrict !== data.district) {
        renderedDistrict = data.district;
        map.fitBounds(layer.getBounds(), { padding: [20, 20], animate: !reducedMotion.matches });
        document.querySelector('#map-detail-title').textContent = data.districts[data.district]?.name ?? filters.elements.kabupaten.selectedOptions[0]?.textContent ?? 'Desa/kelurahan';
        document.querySelector('[data-map-detail]').replaceChildren(textNode('p', 'Klik atau arahkan kursor ke area desa untuk membaca kondisi listriknya. Area abu-abu belum mempunyai data yang cocok dengan hasil filter.'));
    } else if (!isVillage) renderedDistrict = null;
    setContext();
    if (selectedId) { const record = Object.values(data.villages).find(r => r.id === selectedId); if (record) selectRegion(record); }
}
function renderList(list) {
    rows.replaceChildren();
    list.data.forEach(record => {
        const row = document.createElement('tr'); row.dataset.id = record.id;
        row.classList.toggle('is-selected', record.id === selectedId);
        const name = document.createElement('td'); name.append(textNode('strong', record.name), textNode('small', record.address));
        const families = document.createElement('td');
        families.append(textNode('span', familyCount(record.electricity.unserved_families)));
        const condition = document.createElement('td');
        condition.append(textNode('span', categories[record.category].label, `condition-label ${record.category}`));
        const location = textNode('td', record.location ? 'Referensi desa' : 'Belum tersedia');
        const action = document.createElement('td');
        const button = textNode('button', 'Lihat', 'button'); button.type = 'button'; button.setAttribute('aria-label', `Lihat ${record.name}`);
        button.addEventListener('click', () => selectRegion(record, true)); action.append(button);
        row.append(name, families, condition, location, action); rows.append(row);
    });
    page = list.current_page; lastPage = list.last_page;
    document.querySelector('[data-map-list-count]').textContent = list.total ? `${(page - 1) * 15 + 1}–${Math.min(page * 15, list.total)} dari ${number(list.total)} wilayah` : '0 wilayah';
    document.querySelector('[data-map-page]').textContent = `${page} / ${lastPage}`;
    document.querySelector('[data-map-prev]').disabled = page <= 1;
    document.querySelector('[data-map-next]').disabled = page >= lastPage;
    const empty = document.querySelector('[data-map-empty]'); empty.hidden = list.total !== 0;
    empty.textContent = 'Tidak ada wilayah yang sesuai pencarian atau filter. Peta dasar tetap dapat digunakan.';
    searchRecords = list.data;
    renderSearchSuggestions(searchRecords);
}
function hideSearchSuggestions() {
    if (searchSuggestions) searchSuggestions.hidden = true;
    filters.elements.q.setAttribute('aria-expanded', 'false');
}
function renderSearchSuggestions(records) {
    // Suggestions are optional: older/cached templates must not break map rendering.
    if (!searchSuggestions) return;
    const query = filters.elements.q.value.trim();
    searchSuggestions.replaceChildren();
    if (!query || document.activeElement !== filters.elements.q || !records.length) {
        hideSearchSuggestions();
        return;
    }
    for (const record of records.slice(0, 6)) {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'map-search-suggestion';
        option.setAttribute('role', 'option');
        option.setAttribute('aria-label', `${record.name}, ${record.address || 'alamat belum tersedia'}`);
        option.append(textNode('strong', record.name), textNode('small', record.address || 'Alamat belum tersedia'));
        option.addEventListener('click', () => {
            hideSearchSuggestions();
            selectRegion(record, true);
        });
        searchSuggestions.append(option);
    }
    searchSuggestions.hidden = false;
    filters.elements.q.setAttribute('aria-expanded', 'true');
}
async function loadData() {
    if (disposed) return;
    dataAbort?.abort(); const abort = new AbortController(); dataAbort = abort;
    let timedOut = false;
    const timeout = setTimeout(() => { timedOut = true; abort.abort(); }, 30000);
    dataState.dataset.mapDataState = 'loading'; dataStatus.textContent = 'Memuat data wilayah…'; dataRetry.hidden = true;
    rows.setAttribute('aria-busy', 'true'); searchGlobal.hidden = true;
    syncQuickFilters();
    document.querySelectorAll('[data-map-stat]').forEach(element => element.textContent = '—');
    document.querySelector('[data-map-prev]').disabled = true; document.querySelector('[data-map-next]').disabled = true;
    const bounds = map.getBounds();
    const params = new URLSearchParams({ q: filters.elements.q.value.trim(), condition: filters.elements.condition.value, kabupaten: filters.elements.kabupaten.value, location: filters.elements.location.value, page, zoom: map.getZoom(), bbox: [bounds.getWest(), bounds.getSouth(), bounds.getEast(), bounds.getNorth()].map(value => value.toFixed(7)).join(',') });
    try {
        const result = await json(`${element.dataset.dataUrl}?${params}`, abort.signal);
        if (abort.signal.aborted) return;
        if (!result.summary || !Array.isArray(result.features) || !result.list) throw new Error('Respons data wilayah tidak lengkap.');
        if (!districtOptionsLoaded) {
            const boundaries = await geometry(null, abort.signal);
            if (abort.signal.aborted) return;
            const names = new Map(result.kabupaten.map(district => [district.code, district.name]));
            const options = boundaries.features.map(feature => ({ code: feature.properties.code,
                name: names.get(feature.properties.code) ?? (/^kota\s/i.test(feature.properties.name) ? feature.properties.name : 'Kabupaten ' + feature.properties.name) }));
            options.sort((a, b) => a.name.localeCompare(b.name, 'id'));
            for (const district of options) { const option = textNode('option', district.name); option.value = district.code; filters.elements.kabupaten.append(option); }
            districtOptionsLoaded = true;
            if (initial.get('kabupaten') && !filters.elements.kabupaten.value) {
                const value = initial.get('kabupaten');
                if ([...filters.elements.kabupaten.options].some(option => option.value === value)) { filters.elements.kabupaten.value = value; return loadData(); }
            }
        }
        const matchingDistricts = Object.keys(result.areas?.districts ?? {});
        if (focusSearch && filters.elements.q.value.trim() && !filters.elements.kabupaten.value && matchingDistricts.length === 1) {
            filters.elements.kabupaten.value = matchingDistricts[0]; initial.delete('kabupaten');
            if (filters.elements.kabupaten.value) return loadData();
        }
        await renderAreas(result.areas, abort.signal);
        if (abort.signal.aborted) return;
        renderList(result.list); renderSearchResults(result); focusSearchResult(result);
        searchGlobal.hidden = !(result.summary.total === 0 && filters.elements.q.value.trim() && filters.elements.kabupaten.value);
        document.querySelectorAll('[data-map-stat]').forEach(element => element.textContent = number(result.summary[element.dataset.mapStat]));
        document.querySelector('[data-map-period]').textContent = result.data.periods.length ? `Periode ${result.data.periods.join(', ')}` : 'Periode belum tersedia';
        const s = result.summary;
        dataStatus.textContent = s.total ? `${number(s.total)} wilayah sesuai filter · ${number(s.located)} lokasi tersedia · ${number(s.unlocated)} lokasi belum tersedia` : 'Tidak ada wilayah yang sesuai pencarian atau filter. Peta dasar tetap tampil.';
        dataState.dataset.mapDataState = 'ready';
        document.querySelector('[data-map-list-caption]').textContent = filters.elements.location.value === 'missing' ? 'Wilayah tetap tercantum dalam daftar; lokasi pada peta belum tersedia.' : 'Daftar mengikuti filter. Klik kabupaten pada peta untuk melihat area desa.';
        const url = new URLSearchParams({ q: filters.elements.q.value.trim(), condition: filters.elements.condition.value, kabupaten: filters.elements.kabupaten.value, location: filters.elements.location.value, page });
        history.replaceState(null, '', `${location.pathname}?${url}`);
    } catch (error) {
        if (dataAbort !== abort || disposed || (error.name === 'AbortError' && !timedOut)) return;
        clearAreas(); rows.replaceChildren(); searchResults.hidden = true;
        document.querySelector('[data-map-empty]').hidden = true;
        document.querySelector('[data-map-list-count]').textContent = 'Jumlah hasil belum tersedia';
        document.querySelector('[data-map-page]').textContent = '—';
        dataStatus.textContent = `Gagal mengambil data wilayah. ${timedOut ? 'Waktu tunggu habis. Silakan coba lagi.' : error.message === 'Failed to fetch' ? 'Periksa koneksi lalu coba lagi.' : error.message}`;
        dataState.dataset.mapDataState = 'error'; dataRetry.hidden = false; areaStatus.textContent = 'Lapisan wilayah belum tersedia. Coba lagi data.';
        document.querySelector('[data-map-period]').textContent = 'Periode belum tersedia';
    } finally {
        clearTimeout(timeout);
        if (dataAbort === abort) rows.setAttribute('aria-busy', 'false');
    }
}
function filterChanged() {
    searchRecords = []; hideSearchSuggestions();
    clearTimeout(debounce); dataAbort?.abort(); page = 1;
    selectedId = null; pinnedPreview = null; resetPreview(); selection.clearLayers(); map.closePopup();
    focusSearch = Boolean(filters.elements.q.value.trim()); searchResults.hidden = true; searchGlobal.hidden = true; syncQuickFilters();
    clearAreas(); rows.replaceChildren();
    dataState.dataset.mapDataState = 'loading'; dataStatus.textContent = 'Memuat data wilayah…';
    dataRetry.hidden = true;
    document.querySelector('[data-map-empty]').hidden = true;
    document.querySelector('[data-map-prev]').disabled = true;
    document.querySelector('[data-map-next]').disabled = true;
    document.querySelector('#map-detail-title').textContent = 'Pilih wilayah';
    document.querySelector('[data-map-detail]').replaceChildren(textNode('p', 'Klik area atau tombol “Lihat” pada daftar untuk membaca informasi wilayah.'));
    debounce = setTimeout(loadData, 300);
}
closeDetail.addEventListener('click', () => {
    detailPanel.hidden = true; showDetail.hidden = false; document.querySelector('.map-workspace').classList.add('detail-closed');
    selectedId = null; pinnedPreview = null; resetPreview(); selection.clearLayers(); rows.querySelectorAll('tr').forEach(row => row.classList.remove('is-selected'));
    map.closePopup();
    document.querySelector('#map-detail-title').textContent = 'Pilih wilayah';
    document.querySelector('[data-map-detail]').replaceChildren(textNode('p', 'Pilih area pada peta atau tombol “Lihat” pada daftar untuk membuka detail wilayah.'));
    map.invalidateSize({ animate: false, pan: true });
    showDetail.focus({ preventScroll: true });
}, { signal: events.signal });
showDetail.addEventListener('click', () => {
    detailPanel.hidden = false; showDetail.hidden = true; document.querySelector('.map-workspace').classList.remove('detail-closed');
    map.invalidateSize({ animate: false, pan: true });
}, { signal: events.signal });
for (const button of document.querySelectorAll('[data-map-quick]')) button.addEventListener('click', () => {
    filters.elements.condition.value = button.dataset.mapQuick; filterChanged(); clearTimeout(debounce); loadData();
}, { signal: events.signal });
searchGlobal.addEventListener('click', () => {
    filters.elements.kabupaten.value = ''; initial.delete('kabupaten');
    filterChanged(); clearTimeout(debounce); loadData();
}, { signal: events.signal });
function setExpanded(value) {
    const wasExpanded = panel.classList.contains('is-expanded');
    if (value === wasExpanded) return;
    if (value) { previousOverflow = document.body.style.overflow; expandReturnFocus = document.activeElement; document.body.style.overflow = 'hidden'; }
    else document.body.style.overflow = previousOverflow;
    panel.setAttribute('role', value ? 'dialog' : 'region');
    if (value) panel.setAttribute('aria-modal', 'true'); else panel.removeAttribute('aria-modal');
    panel.classList.toggle('is-expanded', value); expand.setAttribute('aria-expanded', String(value));
    expand.textContent = value ? 'Kecilkan peta ⛶' : 'Perluas peta ⛶';
    requestAnimationFrame(() => {
        map.invalidateSize({ animate: false, pan: true });
        if (!value) { panel.scrollIntoView({ block: 'start', behavior: 'instant' }); expandReturnFocus?.focus({ preventScroll: true }); }
    });
}
document.addEventListener('focusin', event => { if (panel.classList.contains('is-expanded') && !panel.contains(event.target)) expand.focus(); }, { signal: events.signal });
expand.addEventListener('click', () => setExpanded(!panel.classList.contains('is-expanded')), { signal: events.signal });
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && panel.classList.contains('is-expanded')) { event.preventDefault(); setExpanded(false); }
}, { signal: events.signal });
filters.addEventListener('submit', event => { event.preventDefault(); filterChanged(); clearTimeout(debounce); loadData(); });
filters.addEventListener('reset', event => {
    event.preventDefault();
    filters.elements.q.value = ''; filters.elements.kabupaten.value = '';
    filters.elements.condition.value = 'all'; filters.elements.location.value = 'all';
    initial.delete('kabupaten'); filters.querySelector('details').open = false;
    filterChanged(); clearTimeout(debounce); loadData();
});
filters.elements.q.addEventListener('input', filterChanged);
filters.elements.q.addEventListener('focus', () => renderSearchSuggestions(searchRecords));
filters.elements.q.addEventListener('blur', () => setTimeout(() => {
    if (!searchSuggestions?.contains(document.activeElement)) hideSearchSuggestions();
}, 150));
filters.elements.q.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        hideSearchSuggestions();
    }
    if (event.key === 'ArrowDown' && searchSuggestions && !searchSuggestions.hidden) {
        event.preventDefault();
        searchSuggestions.querySelector('button')?.focus();
    }
});
searchSuggestions?.addEventListener('focusout', event => {
    if (!searchSuggestions.contains(event.relatedTarget) && event.relatedTarget !== filters.elements.q) hideSearchSuggestions();
});
searchSuggestions?.addEventListener('keydown', event => {
    const options = [...searchSuggestions.querySelectorAll('button')];
    const index = options.indexOf(document.activeElement);
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault(); options[(index + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length]?.focus();
    }
    if (event.key === 'Escape') { event.preventDefault(); filters.elements.q.focus(); hideSearchSuggestions(); }
});
for (const name of ['condition', 'location', 'kabupaten']) filters.elements[name].addEventListener('change', filterChanged);
document.querySelector('[data-map-prev]').addEventListener('click', () => { if (page > 1) { page--; loadData(); } });
document.querySelector('[data-map-next]').addEventListener('click', () => { if (page < lastPage) { page++; loadData(); } });
dataRetry.addEventListener('click', loadData);
document.querySelector('[data-boundary-retry]').addEventListener('click', async () => { await loadBoundary(); loadData(); });
reset.addEventListener('click', () => {
    detailPanel.hidden = false; showDetail.hidden = true; document.querySelector('.map-workspace').classList.remove('detail-closed');
    filters.elements.q.value = ''; filters.elements.kabupaten.value = ''; initial.delete('kabupaten');
    filterChanged(); clearTimeout(debounce); home(true); loadData();
}, { signal: events.signal });
retry.addEventListener('click', loadTiles, { signal: events.signal });
// Geometry covers the selected administrative area; panning does not refetch TiDB.
function motionChanged() { map.stop(); map.options.zoomAnimation = !reducedMotion.matches; map.options.fadeAnimation = !reducedMotion.matches; }
reducedMotion.addEventListener('change', motionChanged);
function fitResponsiveScope() {
    closeAreaTooltips(); map.invalidateSize({ animate: false, pan: true });
    if (!boundaryReady) return;
    // Leaflet clamps getBoundsZoom to the existing minimum; release the desktop
    // minimum before calculating a smaller fit for a narrow screen.
    map.setMinZoom(5);
    map.setMinZoom(Math.max(5, map.getBoundsZoom(homeBounds, false, L.point(48, 48))));
    let bounds = scopeBounds?.isValid() ? scopeBounds : homeBounds;
    const selected = Object.values(activeVillages).find(record => record.id === selectedId);
    if (selected || filters.elements.q.value.trim()) {
        const matches = L.latLngBounds([]);
        for (const [code, shape] of areaLayers) {
            if (!selected || activeVillages[code]?.administration.subdistrict_name === selected.administration.subdistrict_name) matches.extend(shape.getBounds());
        }
        if (matches.isValid()) bounds = matches;
    }
    map.fitBounds(bounds, { padding: [24, 24], maxZoom: selected ? 12 : 13, animate: false });
}
const resize = new ResizeObserver(fitResponsiveScope);
resize.observe(element);
syncQuickFilters(); home(); loadTiles(); reset.disabled = false;
loadBoundary().finally(() => loadData());
window.addEventListener('pagehide', () => {
    if (panel.classList.contains('is-expanded')) document.body.style.overflow = previousOverflow;
    disposed = true; events.abort(); dataAbort?.abort(); clearTimeout(debounce); clearTimeout(tileTimeout);
    resize.disconnect(); reducedMotion.removeEventListener('change', motionChanged);
    tileLayer?.off(); map.remove();
}, { once: true });
window.addEventListener('pageshow', event => { if (event.persisted && disposed) location.reload(); });

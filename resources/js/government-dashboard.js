import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { dashboardAreas } from './government-dashboard-areas';
import { savedViewFocusHash, setupSavedDashboardViews } from './government-dashboard-saved-views';

const root = document.querySelector('[data-government-dashboard]');
const find = selector => root.querySelector(selector);
const format = value => value == null ? 'Belum tersedia' : new Intl.NumberFormat('id-ID').format(value);
const status = find('[data-gov-status]');
const retry = find('[data-gov-retry]');
const form = find('[data-gov-period-form]');
const { year, kabupaten, kecamatan, desa } = form.elements;
const filterControls = [year, kabupaten, kecamatan, desa, form.elements.kondisi];
const draftStatus = find('[data-gov-draft-status]');
const resultPanels = root.querySelectorAll('[data-gov-results]');
const administrationStatus = find('#gov-administration-status');
const administrationRetry = find('[data-gov-administration-retry]');
const mapElement = find('[data-gov-map]');
const tileStatus = find('[data-gov-tile-status]');
const tileRetry = find('[data-gov-tile-retry]');
const life = new AbortController();
const refreshInterval = 30_000;
let refreshTimer, pageSuspended = false;
let tabVisible = document.visibilityState === 'visible';
let request, tiles, tileFailed = false, tilesReady = false, boundaryReady = false, loadingBoundary;
let administrationRequest;
let administration = JSON.parse(find('[data-gov-administration-options]').textContent);
let appliedFilters = root.dataset.state === 'error' ? null : readFilters();
let boundaryLayer, markerLayer, selectedMarker;
const zoomStep = .25;
let pendingZoomSteps = 0;
let zooming = false;
const provincePadding = L.point(24, 24);
// The extent of the bundled province asset is also used while it is loading.
let homeBounds = L.latLngBounds([[-7.8204266, 106.3708302], [-5.9147353, 108.8867652]]);
const map = L.map(mapElement, {
    preferCanvas: true, minZoom: 4, maxZoom: 18, maxBounds: homeBounds.pad(.05), maxBoundsViscosity: 1,
    zoomSnap: zoomStep, zoomDelta: zoomStep, wheelPxPerZoomLevel: 480, wheelDebounceTime: 75,
    scrollWheelZoom: false, zoomControl: false,
    zoomAnimation: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
});
const tilePane = map.createPane('government-tiles');
tilePane.style.zIndex = '200';
tilePane.style.visibility = 'hidden';
const maskPane = map.createPane('government-province-mask');
maskPane.style.zIndex = '350';
maskPane.style.pointerEvents = 'none';
const zoomControl = L.control.zoom({ position: 'topright', zoomInTitle: 'Perbesar peta', zoomOutTitle: 'Perkecil peta' }).addTo(map);
L.control.scale({ imperial: false }).addTo(map);
updateZoomFloor();
home();
let viewportRevision = 0;
map.on('movestart', () => { viewportRevision++; });
map.on('zoomend zoomlevelschange moveend', publishViewport);
publishViewport();
// Leaflet ignores an additional animated setZoom while a transition is running.
function applyZoomStep() {
    if (zooming || !pendingZoomSteps) return;
    const direction = Math.sign(pendingZoomSteps);
    const next = map.getZoom() + direction * zoomStep;
    if (next < map.getMinZoom() || next > map.getMaxZoom()) {
        pendingZoomSteps = 0;
        return;
    }
    pendingZoomSteps -= direction;
    zooming = true;
    map.setZoom(next);
}
map.on('zoomstart', () => { zooming = true; });
map.on('zoomend', () => {
    zooming = false;
    applyZoomStep();
});
zoomControl.getContainer().addEventListener('click', event => {
    const button = event.target.closest('.leaflet-control-zoom-in, .leaflet-control-zoom-out');
    if (!button) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const step = button.classList.contains('leaflet-control-zoom-in') ? 1 : -1;
    pendingZoomSteps = Math.max(-4, Math.min(4, pendingZoomSteps + step));
    applyZoomStep();
}, true);
// Keep browser-level pinch/Ctrl-wheel zoom out of the map interaction.
mapElement.addEventListener('wheel', event => {
    if (event.ctrlKey || event.metaKey) event.preventDefault();
}, { passive: false, capture: true });
mapElement.addEventListener('click', () => map.scrollWheelZoom.enable());
mapElement.addEventListener('focusin', () => map.scrollWheelZoom.enable());
mapElement.addEventListener('mouseleave', () => map.scrollWheelZoom.disable());
mapElement.addEventListener('focusout', event => {
    if (!mapElement.contains(event.relatedTarget)) map.scrollWheelZoom.disable();
});
const colors = { unserved: '#ec9d06', installed: '#2a9d60', needs_data: '#db4347' };
const labels = { unserved: 'Belum berlistrik', installed: 'PLTS terpasang', needs_data: 'Perlu pendataan' };
const markers = new Map();
const areas = dashboardAreas({ map, L, root, mapElement, json, node, signal: life.signal, home, format,
    viewportRevision: () => viewportRevision });
const points = find('[data-gov-area-points]');
points.addEventListener('change', () => {
    if (points.checked) markerLayer?.addTo(map);
    else markerLayer?.remove();
});

async function json(url, signal) {
    const response = await fetch(url, { signal, cache: 'no-store', headers: { Accept: 'application/json' } });
    if (!response.ok) {
        const body = await response.json().catch(() => ({}));
        const errors = response.status === 422 ? body.errors ?? {} : {};
        const validationMessage = Object.values(errors).flat().join(' ');
        const message = response.status === 401 ? 'Sesi berakhir. Silakan masuk kembali.'
            : response.status === 403 ? 'Akun ini tidak memiliki akses dashboard Pemerintah.'
                : response.status === 422 ? validationMessage || 'Periksa pilihan filter dashboard.'
                    : 'Data dashboard belum dapat dimuat. Periksa koneksi lalu coba kembali.';
        // Only server-controlled public messages are rendered, always as text.
        throw Object.assign(new Error(response.status === 503 && typeof body.message === 'string' ? body.message : message),
            { status: response.status, errors });
    }
    return response.json();
}
function readFilters() {
    const values = Object.fromEntries(filterControls.map(control => [control.name, control.value]));
    const labels = Object.fromEntries(filterControls.map(control => [control.name, control.selectedOptions[0].textContent]));
    if (values.year) labels.year = `Tahun ${values.year}`;
    if (values.kecamatan) labels.kecamatan = `Kecamatan ${labels.kecamatan}`;
    return { values, labels };
}
function writeFilters(url, { values }) {
    for (const [name, value] of Object.entries(values)) {
        if (value) url.searchParams.set(name, value);
        else url.searchParams.delete(name);
    }
}
function updateDraftStatus() {
    const pending = filterControls.some(control => control.value !== (appliedFilters?.values[control.name] ?? ''));
    draftStatus.toggleAttribute('data-pending', pending);
    draftStatus.textContent = pending
        ? 'Pilihan berubah. Tekan Terapkan untuk memperbarui dashboard.'
        : 'Pilih filter, lalu tekan Terapkan.';
}
function showAppliedScope({ values, labels }) {
    const area = ['kabupaten', 'kecamatan', 'desa'].filter(name => values[name]).map(name => labels[name]).join(' › ');
    find('[data-gov-applied-summary]').textContent = Object.values(labels).join(' • ');
    find('[data-gov-period]').textContent = labels.year;
    find('[data-gov-area]').textContent = area || 'Jawa Barat';
    find('[data-gov-map-scope]').textContent = area || 'filter aktif';
}
function updateVillages() {
    const villages = administration.villages.filter(village => village.subdistrict === kecamatan.value);
    desa.replaceChildren(new Option('Semua desa/kelurahan', ''), ...villages.map(village => new Option(village.name, village.code)));
    desa.disabled = !villages.length;
}
function administrationUrl(district) {
    const url = new URL(root.dataset.administrationUrl, location.origin);
    url.searchParams.set('kabupaten', district);
    return url;
}
function populateSubdistricts() {
    const entries = Object.entries(administration.subdistricts);
    kecamatan.replaceChildren(new Option('Semua kecamatan', ''), ...entries.map(([code, name]) => new Option(name, code)));
    kecamatan.disabled = !entries.length;
    return entries.length;
}
async function loadAdministration() {
    administrationRequest?.abort();
    administration = { subdistricts: {}, villages: [] };
    kecamatan.replaceChildren(new Option('Semua kecamatan', ''));
    kecamatan.disabled = true;
    updateVillages();
    administrationRetry.hidden = true;
    if (!kabupaten.value) {
        administrationStatus.textContent = '';
        return;
    }

    const current = new AbortController();
    administrationRequest = current;
    administrationStatus.textContent = 'Memuat pilihan kecamatan dan desa/kelurahan…';
    try {
        const options = await json(administrationUrl(kabupaten.value), current.signal);
        if (current.signal.aborted) return;
        administration = options;
        administrationStatus.textContent = populateSubdistricts() ? '' : 'Pilihan kecamatan belum tersedia untuk kabupaten/kota ini.';
    } catch (error) {
        if (current.signal.aborted) return;
        administrationStatus.textContent = [401, 403, 422].includes(error.status)
            ? error.message : 'Pilihan wilayah belum dapat dimuat.';
        administrationRetry.hidden = error.status === 422;
    }
}
function node(tag, text, className) {
    const element = document.createElement(tag);
    if (text != null) element.textContent = text;
    if (className) element.className = className;
    return element;
}
function publishViewport() {
    mapElement.dataset.zoom = String(map.getZoom());
    mapElement.dataset.minZoom = String(map.getMinZoom());
    const center = map.getCenter();
    mapElement.dataset.center = `${center.lat},${center.lng}`;
}
function updateZoomFloor() {
    const extent = map.project(homeBounds.getSouthEast(), 0).subtract(map.project(homeBounds.getNorthWest(), 0));
    const available = map.getSize().subtract(provincePadding.multiplyBy(2));
    if (available.x <= 0 || available.y <= 0) return;
    const scale = Math.min(available.x / extent.x, available.y / extent.y);
    const floor = Math.max(4, Math.floor(map.getScaleZoom(scale, 0) / zoomStep) * zoomStep);
    if (map.getZoom() < floor) map.setZoom(floor, { animate: false });
    map.setMinZoom(floor);
}
function home() {
    pendingZoomSteps = 0;
    map.fitBounds(homeBounds, { padding: provincePadding, maxZoom: map.getMinZoom(), animate: false });
}
function maskOutsideProvince(geometry) {
    const province = geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates;
    const exterior = [[-180, -85], [180, -85], [180, 85], [-180, 85], [-180, -85]];
    L.geoJSON({ type: 'Polygon', coordinates: [exterior, ...province.flat()] }, {
        pane: 'government-province-mask', renderer: L.svg({ pane: 'government-province-mask' }),
        interactive: false, stroke: false, fillColor: '#e6eff2', fillOpacity: 1, fillRule: 'evenodd',
    }).addTo(map);
    tilePane.style.visibility = 'visible';
}
async function boundary() {
    if (boundaryReady) return;
    if (loadingBoundary) return loadingBoundary;
    loadingBoundary = (async () => {
        const data = await json(mapElement.dataset.boundaryUrl, life.signal);
        if (!['Polygon', 'MultiPolygon'].includes(data.geometry?.type)) throw new Error('Batas Jawa Barat belum tersedia.');
        boundaryLayer?.remove();
        boundaryLayer = L.geoJSON(data, { interactive: false, style: { color: '#527c82', weight: 1.7, fillOpacity: .04 } }).addTo(map);
        const shouldFit = !viewportRevision;
        homeBounds = boundaryLayer.getBounds();
        map.setMaxBounds(homeBounds.pad(.05));
        updateZoomFloor();
        maskOutsideProvince(data.geometry);
        if (shouldFit) home();
        boundaryReady = true;
    })().finally(() => { loadingBoundary = null; });
    return loadingBoundary;
}
function tileMessage() {
    if (tileFailed) tileStatus.textContent = 'Sebagian peta dasar gagal dimuat. Data penanda tetap dapat dibaca. Coba lagi peta.';
    else if (boundaryReady && tilesReady) tileStatus.textContent = 'Peta dasar siap. Pilih area atau cari wilayah untuk membaca informasi.';
}
function loadTiles() {
    tiles?.off(); tiles?.remove(); tileFailed = false; tilesReady = false;
    tileRetry.hidden = true;
    tileStatus.textContent = 'Memuat peta dasar…';
    tiles = L.tileLayer(mapElement.dataset.tileUrl, { pane: 'government-tiles', bounds: homeBounds,
        updateWhenZooming: false, updateWhenIdle: true,
        attribution: mapElement.dataset.attribution, maxZoom: 18 });
    tiles.on('tileerror', () => { tileFailed = true; tileRetry.hidden = false; tileMessage(); });
    tiles.on('load', () => { tilesReady = true; tileMessage(); });
    tiles.addTo(map);
    boundary().then(tileMessage).catch(error => {
        if (error.name === 'AbortError') return;
        tileStatus.textContent = 'Batas Jawa Barat gagal dimuat. Coba lagi peta. Daftar wilayah tetap dapat digunakan.';
        tileRetry.hidden = false;
    });
}
function selectFeature(feature, marker, pan = false) {
    selectedMarker?.setStyle({ radius: 5, weight: 1 });
    marker.setStyle({ radius: 9, weight: 3 });
    selectedMarker = marker;
    const item = feature.properties;
    find('[data-gov-selected]').textContent = `${item.name} — ${labels[item.category]}. KK sasaran: ${format(item.target_kk)}. Lokasi ini adalah referensi wilayah, bukan posisi rumah warga.`;
    find('[data-gov-village]').value = String(feature.id);
    marker.openPopup();
    if (pan) map.setView(marker.getLatLng(), Math.max(map.getZoom(), 11), { animate: false });
}
function drawMap(collection) {
    markerLayer?.remove(); markers.clear(); selectedMarker = null;
    const select = find('[data-gov-village]');
    select.replaceChildren(new Option('Pilih wilayah', ''));
    const options = document.createDocumentFragment();
    markerLayer = L.geoJSON(collection, {
        pointToLayer: (feature, latlng) => L.circleMarker(latlng, { radius: 5, color: '#ffffff', weight: 1,
            fillColor: colors[feature.properties.category], fillOpacity: .85 }),
        onEachFeature: (feature, marker) => {
            const item = feature.properties;
            const popup = node('div');
            popup.append(node('strong', item.name), node('p', `${labels[item.category]} · KK sasaran: ${format(item.target_kk)}`));
            marker.bindTooltip(item.name).bindPopup(popup).on('click', () => selectFeature(feature, marker));
            markers.set(String(feature.id), { feature, marker });
            options.append(new Option(item.name, String(feature.id)));
        },
    });
    if (points.checked) markerLayer.addTo(map);
    select.append(options);
    find('[data-gov-selected]').textContent = collection.features.length
        ? 'Pilih penanda untuk melihat nama wilayah dan KK sasaran. Nol KK belum berlistrik bukan bukti pemasangan PLTS.'
        : 'Belum ada wilayah berkoordinat valid untuk filter yang diterapkan. Peta dasar tetap dapat digunakan.';
}
function drawChart(data) {
    const bars = find('[data-gov-bars]'), rows = find('[data-gov-status-rows]');
    bars.replaceChildren(); rows.replaceChildren();
    const max = Math.max(1, ...data.statuses.map(item => item.count));
    data.statuses.forEach((item, index) => {
        const button = node('button', null, 'gov-bar'); button.type = 'button';
        button.setAttribute('aria-label', `${item.label}: ${format(item.count)} pengajuan`);
        button.title = button.getAttribute('aria-label');
        const bar = node('i'); bar.style.height = `${item.count / max * 110}px`; bar.setAttribute('aria-hidden', 'true');
        button.append(node('b', format(item.count)), bar, node('span', String(index + 1)));
        const explain = () => { find('[data-gov-chart-focus]').textContent = `${item.label}: ${format(item.count)} pengajuan`; };
        button.addEventListener('focus', explain); button.addEventListener('mouseenter', explain);
        bars.append(button);
        const row = node('tr'); row.dataset.status = item.id;
        row.append(node('td', `${index + 1}. ${item.label}`), node('td', `${format(item.count)} pengajuan`)); rows.append(row);
    });
    find('[data-gov-chart-total]').textContent = format(data.metrics.submitted);
}
function render(data, url, canFit, background, focusSavedArea) {
    for (const [key, value] of Object.entries(data.metrics)) {
        const element = find(`[data-gov-metric="${key}"]`);
        if (element) { element.textContent = format(value); element.dataset.unavailable = String(value == null); }
    }
    find('[data-gov-help="served_kk"]').textContent = `Sasaran: ${format(data.metrics.target_kk)} KK · Realisasi: ${data.metrics.served_percent == null ? '—' : format(data.metrics.served_percent) + '%'}`;
    find('[data-gov-help="completed_plts"]').textContent = 'Sumber penyelesaian pemasangan belum tersedia';
    for (const [key, value] of Object.entries(data.follow_up)) {
        const element = find(`[data-gov-follow="${key}"]`);
        if (element) element.textContent = format(value);
    }
    find('[data-gov-unlinked-note]').hidden = !data.follow_up.unlinked_submissions_global;
    find('[data-gov-scope]').textContent = data.period.description;
    find('[data-gov-empty]').hidden = !data.empty;
    const activeYear = year.value;
    const years = [...new Set([...data.period.years.map(String), ...(activeYear ? [activeYear] : [])])].sort().reverse();
    year.replaceChildren(new Option('Semua periode', ''), ...years.map(value => new Option(value, value)));
    year.value = activeYear;
    find('[data-gov-notes]').replaceChildren(...data.notes.map(note => node('li', note)));
    drawChart(data); drawMap(data.map);
    const mapUpdate = areas.setData(data.areas, data.areas_with_submissions, url, canFit, background, focusSavedArea).then(ready => {
        if (ready === false && root.dataset.state === 'ready') {
            points.checked = true;
            markerLayer?.addTo(map);
        }
    });
    status.textContent = data.empty ? '' : `Data berhasil dimuat · ${format(data.metrics.submitted)} pengajuan · ${format(data.metrics.mapped)} wilayah terpetakan`;
    root.dataset.state = 'ready';
    return mapUpdate;
}

function scheduleRefresh() {
    clearTimeout(refreshTimer);
    if (!pageSuspended && document.visibilityState === 'visible' && appliedFilters) {
        refreshTimer = setTimeout(refreshData, refreshInterval);
    }
}

function refreshData() {
    if (pageSuspended || document.visibilityState !== 'visible' || !appliedFilters) return;
    if (request && !request.signal.aborted) {
        scheduleRefresh();
        return;
    }
    loadData({ background: root.dataset.state === 'ready' });
}

async function loadData({ background = false, focusSavedArea = false } = {}) {
    scheduleRefresh();
    request?.abort(); const current = new AbortController(); request = current;
    const filters = appliedFilters;
    const view = viewportRevision;
    const targetHelp = find('[data-gov-help="served_kk"]');
    if (!background) {
        areas.clear();
        root.dataset.state = 'loading'; retry.hidden = true;
        resultPanels.forEach(panel => panel.setAttribute('aria-busy', 'true'));
        find('[data-gov-empty]').hidden = true; status.textContent = 'Memuat data dashboard…';
        find('[data-gov-unlinked-note]').hidden = true;
        filterControls.forEach(control => control.removeAttribute('aria-invalid'));
        // Clear figures and related text while the new scope is loading.
        root.querySelectorAll('[data-gov-metric], [data-gov-follow], [data-gov-chart-total]').forEach(el => { el.textContent = '—'; delete el.dataset.unavailable; });
        targetHelp.textContent = 'Memuat data sasaran…';
        find('[data-gov-bars]').replaceChildren(); find('[data-gov-status-rows]').replaceChildren();
        find('[data-gov-chart-focus]').textContent = 'Arahkan kursor atau fokus ke batang untuk membaca jumlah.';
        find('[data-gov-notes]').replaceChildren(); markerLayer?.remove(); markers.clear();
        find('[data-gov-village]').replaceChildren(new Option('Pilih wilayah', ''));
        find('[data-gov-selected]').textContent = 'Memuat informasi wilayah…';
        showAppliedScope(filters);
        find('[data-gov-scope]').textContent = 'Indikator akan ditampilkan setelah data berhasil dimuat.';
    }
    const url = new URL(root.dataset.url, location.origin);
    writeFilters(url, filters);
    try {
        const data = await json(url, current.signal);
        if (!current.signal.aborted) {
            retry.hidden = true;
            await render(data, url, () => view === viewportRevision, background, focusSavedArea);
        }
    } catch (error) {
        if (current.signal.aborted) return;
        if (background) {
            status.textContent = 'Pembaruan otomatis gagal. Data sebelumnya masih ditampilkan.';
            retry.hidden = false;
            return;
        }
        root.dataset.state = 'error'; status.textContent = error.message; retry.hidden = error.status === 422;
        for (const name of Object.keys(error.errors ?? {})) form.elements.namedItem(name)?.setAttribute('aria-invalid', 'true');
        targetHelp.textContent = 'Data sasaran belum dapat dimuat.';
        find('[data-gov-selected]').textContent = 'Data penanda belum dapat dimuat.';
    } finally {
        if (request === current) {
            request = null;
            resultPanels.forEach(panel => panel.setAttribute('aria-busy', 'false'));
        }
    }
}
function applyFilters({ focusSavedArea = false } = {}) {
    appliedFilters = readFilters();
    savedViews.updateAvailability();
    updateDraftStatus();
    const url = new URL(location.href);
    writeFilters(url, appliedFilters);
    history.replaceState(null, '', url);
    root.querySelectorAll('a[data-gov-report], a[data-gov-examination]').forEach(link => {
        const destination = new URL(link.href); destination.searchParams.delete('year');
        if (appliedFilters.values.year) destination.searchParams.set('year', appliedFilters.values.year);
        link.href = destination;
    });
    return loadData({ focusSavedArea });
}
async function applySavedView(saved) {
    administrationRequest?.abort();
    const current = new AbortController();
    administrationRequest = current;
    const before = appliedFilters;
    const district = saved.kabupaten ?? '';
    const subdistrict = saved.kecamatan ?? '';
    const village = saved.desa ?? '';
    const options = district ? await json(administrationUrl(district), current.signal) : { subdistricts: {}, villages: [] };
    if (current.signal.aborted || appliedFilters !== before) throw new Error('Filter berubah. Coba terapkan tampilan kembali.');
    if ((district && !Array.from(kabupaten.options).some(option => option.value === district))
        || (subdistrict && !Object.hasOwn(options.subdistricts, subdistrict))
        || (village && !options.villages.some(item => item.code === village && item.subdistrict === subdistrict))) {
        throw new Error('Wilayah tersimpan tidak tersedia. Perbaiki konfigurasi tampilan.');
    }
    kabupaten.value = district;
    administration = options;
    populateSubdistricts();
    kecamatan.value = subdistrict;
    updateVillages();
    desa.value = village;
    const period = String(saved.year ?? '');
    if (period && !Array.from(year.options).some(option => option.value === period)) year.add(new Option(period, period));
    year.value = period;
    form.elements.kondisi.value = saved.kondisi ?? '';
    administrationStatus.textContent = '';
    administrationRetry.hidden = true;
    await applyFilters({ focusSavedArea: true });
    if (root.dataset.state !== 'ready') throw new Error('Data untuk filter tersimpan belum dapat dimuat.');
}
form.addEventListener('submit', event => {
    event.preventDefault();
    applyFilters();
});
find('[data-gov-reset]').addEventListener('click', () => {
    filterControls.forEach(control => { control.value = ''; });
    loadAdministration();
    applyFilters();
});
kabupaten.addEventListener('change', loadAdministration);
kecamatan.addEventListener('change', updateVillages);
form.addEventListener('change', updateDraftStatus);
administrationRetry.addEventListener('click', loadAdministration);
find('[data-gov-village]').addEventListener('change', event => {
    const selected = markers.get(event.target.value);
    if (selected) {
        points.checked = true;
        markerLayer.addTo(map);
        selectFeature(selected.feature, selected.marker, true);
    }
});
find('[data-gov-map-home]').addEventListener('click', areas.home);
retry.addEventListener('click', loadData); tileRetry.addEventListener('click', loadTiles);
const resizeMap = () => {
    const wasOverview = map.getZoom() === map.getMinZoom();
    map.invalidateSize({ animate: false, debounceMoveend: true });
    const previousFloor = map.getMinZoom();
    updateZoomFloor();
    if (wasOverview && previousFloor !== map.getMinZoom()) home();
};
const resize = new ResizeObserver(resizeMap);
resize.observe(mapElement);
window.addEventListener('pagehide', event => {
    pageSuspended = true;
    clearTimeout(refreshTimer);
    map.scrollWheelZoom.disable();
    request?.abort();
    areas.abort();
    if (event.persisted) return;
    administrationRequest?.abort(); life.abort(); resize.disconnect(); map.remove();
});
window.addEventListener('pageshow', event => {
    if (!event.persisted) return;
    pageSuspended = false;
    tabVisible = document.visibilityState === 'visible';
    resizeMap();
    refreshData();
});
document.addEventListener('visibilitychange', () => {
    const visible = document.visibilityState === 'visible';
    if (visible && !tabVisible) refreshData();
    if (!visible) clearTimeout(refreshTimer);
    tabVisible = visible;
});
const savedViews = setupSavedDashboardViews(root, () => appliedFilters, applySavedView);
const focusSavedArea = location.hash === savedViewFocusHash;
if (focusSavedArea) history.replaceState(history.state, '', location.pathname + location.search);
loadTiles();
if (root.dataset.state !== 'error') loadData({ focusSavedArea });

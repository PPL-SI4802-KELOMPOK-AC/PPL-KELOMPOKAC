import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const root = document.querySelector('[data-government-dashboard]');
const find = selector => root.querySelector(selector);
const format = value => value == null ? 'Belum tersedia' : new Intl.NumberFormat('id-ID').format(value);
const status = find('[data-gov-status]');
const retry = find('[data-gov-retry]');
const year = find('#dashboard-year');
const mapElement = find('[data-gov-map]');
const tileStatus = find('[data-gov-tile-status]');
const tileRetry = find('[data-gov-tile-retry]');
const life = new AbortController();
let request, tiles, tileFailed = false, tilesReady = false, boundaryReady = false, loadingBoundary;
let boundaryLayer, markerLayer, selectedMarker;
let homeBounds = L.latLngBounds([[-7.85, 106.35], [-5.9, 108.85]]);
const map = L.map(mapElement, { preferCanvas: true, minZoom: 5, maxZoom: 18, scrollWheelZoom: false });
map.zoomControl.setPosition('topright');
L.control.scale({ imperial: false }).addTo(map);
map.fitBounds(homeBounds, { padding: [10, 10], animate: false });
const colors = { unserved: '#ec9d06', installed: '#2a9d60', needs_data: '#db4347' };
const labels = { unserved: 'Belum berlistrik', installed: 'PLTS terpasang', needs_data: 'Perlu pendataan' };
const markers = new Map();

async function json(url, signal) {
    const response = await fetch(url, { signal, cache: 'no-store', headers: { Accept: 'application/json' } });
    if (!response.ok) {
        const message = response.status === 401 ? 'Sesi berakhir. Silakan masuk kembali.'
            : response.status === 403 ? 'Akun ini tidak memiliki akses dashboard Pemerintah.'
                : response.status === 422 ? 'Periode tidak valid. Pilih tahun antara 1900 dan 2100.'
                    : 'Data dashboard belum dapat dimuat. Periksa koneksi lalu coba kembali.';
        const body = await response.json().catch(() => ({}));
        // Only server-controlled public messages are rendered, always as text.
        throw new Error(response.status === 503 && typeof body.message === 'string' ? body.message : message);
    }
    return response.json();
}
function node(tag, text, className) {
    const element = document.createElement(tag);
    if (text != null) element.textContent = text;
    if (className) element.className = className;
    return element;
}
function home() { map.fitBounds(homeBounds, { padding: [12, 12], animate: false }); }
async function boundary() {
    if (boundaryReady) return;
    if (loadingBoundary) return loadingBoundary;
    loadingBoundary = (async () => {
        const data = await json(mapElement.dataset.boundaryUrl, life.signal);
        if (!['Polygon', 'MultiPolygon'].includes(data.geometry?.type)) throw new Error('Batas Jawa Barat belum tersedia.');
        boundaryLayer?.remove();
        boundaryLayer = L.geoJSON(data, { interactive: false, style: { color: '#527c82', weight: 1.7, fillOpacity: .04 } }).addTo(map);
        homeBounds = boundaryLayer.getBounds();
        map.setMaxBounds(homeBounds.pad(.25));
        home();
        boundaryReady = true;
    })().finally(() => { loadingBoundary = null; });
    return loadingBoundary;
}
function tileMessage() {
    if (tileFailed) tileStatus.textContent = 'Sebagian peta dasar gagal dimuat. Data penanda tetap dapat dibaca. Coba lagi peta.';
    else if (boundaryReady && tilesReady) tileStatus.textContent = 'Peta dasar siap. Pilih penanda atau daftar wilayah untuk membaca informasi.';
}
function loadTiles() {
    tiles?.off(); tiles?.remove(); tileFailed = false; tilesReady = false;
    tileRetry.hidden = true;
    tileStatus.textContent = 'Memuat peta dasar…';
    tiles = L.tileLayer(mapElement.dataset.tileUrl, { attribution: mapElement.dataset.attribution, maxZoom: 18 });
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
    }).addTo(map);
    select.append(options);
    find('[data-gov-selected]').textContent = collection.features.length
        ? 'Pilih penanda untuk melihat nama wilayah dan KK sasaran. Nol KK belum berlistrik bukan bukti pemasangan PLTS.'
        : 'Belum ada wilayah berkoordinat valid pada periode ini. Peta dasar tetap dapat digunakan.';
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
function render(data) {
    root.dataset.state = 'ready';
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
    find('[data-gov-period]').textContent = data.period.label;
    find('[data-gov-unlinked-note]').hidden = data.follow_up.unlinked_submissions === 0;
    find('[data-gov-scope]').textContent = data.period.description;
    find('[data-gov-empty]').hidden = !data.empty;
    const activeYear = year.value;
    const years = [...new Set([...data.period.years.map(String), ...(activeYear ? [activeYear] : [])])].sort().reverse();
    year.replaceChildren(new Option('Semua periode', ''), ...years.map(value => new Option(value, value)));
    year.value = activeYear;
    find('[data-gov-notes]').replaceChildren(...data.notes.map(note => node('li', note)));
    drawChart(data); drawMap(data.map);
    status.textContent = `Data berhasil dimuat · ${format(data.metrics.submitted)} pengajuan · ${format(data.metrics.mapped)} wilayah terpetakan`;
}
async function loadData() {
    request?.abort(); const current = new AbortController(); request = current;
    root.dataset.state = 'loading'; root.setAttribute('aria-busy', 'true'); retry.hidden = true;
    find('[data-gov-empty]').hidden = true; status.textContent = 'Memuat data dashboard…';
    find('[data-gov-unlinked-note]').hidden = true;
    // Do not leave old-period indicators visible as if they belonged to the new selection.
    root.querySelectorAll('[data-gov-metric], [data-gov-follow], [data-gov-chart-total]').forEach(el => { el.textContent = '—'; delete el.dataset.unavailable; });
    find('[data-gov-bars]').replaceChildren(); find('[data-gov-status-rows]').replaceChildren();
    find('[data-gov-chart-focus]').textContent = 'Arahkan kursor atau fokus ke batang untuk membaca jumlah.';
    find('[data-gov-notes]').replaceChildren(); markerLayer?.remove(); markers.clear();
    find('[data-gov-village]').replaceChildren(new Option('Pilih wilayah', ''));
    find('[data-gov-selected]').textContent = 'Memuat informasi wilayah…';
    find('[data-gov-period]').textContent = year.value ? `Tahun ${year.value}` : 'Semua periode';
    find('[data-gov-scope]').textContent = 'Indikator akan ditampilkan setelah data berhasil dimuat.';
    const url = new URL(root.dataset.url, location.origin);
    if (year.value) url.searchParams.set('year', year.value);
    try {
        const data = await json(url, current.signal);
        if (!current.signal.aborted) render(data);
    } catch (error) {
        if (error.name === 'AbortError') return;
        root.dataset.state = 'error'; status.textContent = error.message; retry.hidden = false;
        find('[data-gov-selected]').textContent = 'Data penanda belum dapat dimuat. Gunakan Coba lagi data.';
    } finally {
        if (request === current) root.setAttribute('aria-busy', 'false');
    }
}
find('[data-gov-period-form]').addEventListener('submit', event => {
    event.preventDefault();
    const url = new URL(location.href); url.search = ''; if (year.value) url.searchParams.set('year', year.value);
    history.replaceState(null, '', url);
    root.querySelectorAll('a[data-gov-report], a[data-gov-examination]').forEach(link => {
        const destination = new URL(link.href); destination.searchParams.delete('year');
        if (year.value) destination.searchParams.set('year', year.value);
        link.href = destination;
    });
    loadData();
});
find('[data-gov-village]').addEventListener('change', event => {
    const selected = markers.get(event.target.value);
    if (selected) selectFeature(selected.feature, selected.marker, true);
});
find('[data-gov-map-home]').addEventListener('click', home);
retry.addEventListener('click', loadData); tileRetry.addEventListener('click', loadTiles);
const resize = new ResizeObserver(() => map.invalidateSize({ pan: false })); resize.observe(mapElement);
window.addEventListener('pagehide', () => { request?.abort(); life.abort(); resize.disconnect(); map.remove(); }, { once: true });
loadTiles(); loadData();

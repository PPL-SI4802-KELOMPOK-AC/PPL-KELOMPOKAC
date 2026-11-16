import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const form = document.querySelector('[data-location-form]');
const fields = form.elements;
const mapElement = document.querySelector('[data-location-map]');
const mapStatus = document.querySelector('[data-location-map-status]');
const pointStatus = document.querySelector('[data-location-point-status]');
const administrationStatus = document.querySelector('[data-administration-status]');
const administrationRetry = document.querySelector('[data-administration-retry]');
const mapRetry = document.querySelector('[data-location-map-retry]');
const centerButton = document.querySelector('[data-location-center]');

const clearButton = document.querySelector('[data-location-clear]');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const events = new AbortController();
const collections = new Map();
let villages = JSON.parse(document.querySelector('[data-location-villages]').textContent);
let administrationAbort, geometryAbort, marker, villageLayer, tileLayer;
let boundaryLayer, maskLayer, boundaryReady = false, tilesFailed = false, disposed = false;
let homeBounds = L.latLngBounds([[-7.85, 106.35], [-5.9, 108.85]]);
const map = L.map(mapElement, { minZoom: 5, maxZoom: 19, maxBounds: homeBounds.pad(.1), maxBoundsViscosity: 1, zoomSnap: .25, zoomAnimation: !reducedMotion.matches });
map.zoomControl.setPosition('topright');
const icon = L.divIcon({ className: 'location-point-icon', html: '<span></span>', iconSize: [30, 36], iconAnchor: [13, 30] });

async function json(url, signal) {
    const response = await fetch(url, { signal, headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(response.status === 401 ? 'Sesi berakhir. Masuk kembali lalu muat ulang halaman.' : 'Data belum dapat dimuat. Silakan coba lagi.');
    return response.json();
}
function options(select, records, placeholder) {
    select.replaceChildren(new Option(placeholder, ''));
    for (const [value, label] of records) select.add(new Option(label, value));
}
function clearPoint() {
    marker?.remove(); marker = null;
    fields.latitude.value = ''; fields.longitude.value = '';
    clearButton.disabled = true;
    pointStatus.textContent = 'Belum ada titik dipilih. Draft dapat disimpan berdasarkan alamat.';
}
function coordinates() {
    const latitude = fields.latitude.value.trim().replace(',', '.');
    const longitude = fields.longitude.value.trim().replace(',', '.');
    if (!latitude || !longitude || !Number.isFinite(Number(latitude)) || !Number.isFinite(Number(longitude))) return null;
    const point = L.latLng(Number(latitude), Number(longitude));
    return Math.abs(point.lat) <= 90 && Math.abs(point.lng) <= 180 ? point : null;
}
function syncPoint(pan = false) {
    const point = coordinates();
    if (!point) {
        marker?.remove(); marker = null;
        const hasInput = Boolean(fields.latitude.value.trim() || fields.longitude.value.trim());
        clearButton.disabled = !hasInput;
        pointStatus.textContent = hasInput ? 'Lengkapi lintang dan bujur dengan angka koordinat yang valid.' : 'Belum ada titik dipilih. Draft dapat disimpan berdasarkan alamat.';
        return;
    }
    clearButton.disabled = false;
    if (!marker) {
        marker = L.marker(point, { icon, draggable: true, title: 'Titik lokasi pengajuan; geser untuk menyesuaikan' }).addTo(map);
        marker.on('dragend', () => choosePoint(marker.getLatLng()));
    } else marker.setLatLng(point);
    pointStatus.textContent = `Titik dipilih: ${point.lat.toFixed(7)}, ${point.lng.toFixed(7)}. Geser penanda atau ubah koordinat untuk menyesuaikan.`;
    if (pan) map.setView(point, 13, { animate: !reducedMotion.matches });
}
function choosePoint(point) {
    fields.latitude.value = point.lat.toFixed(7);
    fields.longitude.value = point.lng.toFixed(7);
    syncPoint();
}
function updateProvinceViewport() {
    map.setMaxBounds(homeBounds.pad(.1));
    map.setMinZoom(Math.max(5, map.getBoundsZoom(homeBounds, false, L.point(32, 32))));
}
function home() {
    updateProvinceViewport();
    map.fitBounds(homeBounds, { padding: [16, 16], animate: false });
}
async function loadBoundary() {
    try {
        const boundary = await json(mapElement.dataset.boundaryUrl, events.signal);
        if (disposed) return;
        if (!['Polygon', 'MultiPolygon'].includes(boundary.geometry?.type)) throw new Error('Batas tidak valid.');
        boundaryLayer?.remove(); maskLayer?.remove();
        boundaryLayer = L.geoJSON(boundary, { interactive: false, style: { color: '#527c82', weight: 1.5, fillOpacity: .03 } }).addTo(map);
        const polygons = boundary.geometry.type === 'Polygon' ? [boundary.geometry.coordinates] : boundary.geometry.coordinates;
        const maskRings = [[[-85, -180], [85, -180], [85, 180], [-85, 180]]];
        polygons.forEach(polygon => polygon.forEach(ring => maskRings.push(ring.map(([lng, lat]) => [lat, lng]))));
        if (!map.getPane('location-province-mask')) {
            map.createPane('location-province-mask');
            map.getPane('location-province-mask').style.zIndex = 350;
            map.getPane('location-province-mask').style.pointerEvents = 'none';
        }
        maskLayer = L.polygon(maskRings, {
            pane: 'location-province-mask', interactive: false, stroke: false,
            fillColor: '#e6eff2', fillOpacity: 1, fillRule: 'evenodd',
        }).addTo(map);
        homeBounds = boundaryLayer.getBounds();
        updateProvinceViewport();
        boundaryReady = true;
        if (!coordinates() && !fields.desa.value) home();
        if (!tilesFailed) mapStatus.textContent = 'Peta siap. Klik pada lokasi yang diajukan.';
    } catch (error) {
        if (error.name === 'AbortError') return;
        mapStatus.textContent = 'Batas Jawa Barat belum dapat dimuat. Anda tetap dapat mengetik koordinat.';
        mapRetry.hidden = false;
    }
}
function loadTiles() {
    tileLayer?.off(); tileLayer?.remove();
    tilesFailed = false;
    tileLayer = L.tileLayer(mapElement.dataset.tileUrl, { attribution: mapElement.dataset.attribution, maxZoom: 19 });
    tileLayer.on('tileerror', () => {
        tilesFailed = true;
        mapStatus.textContent = 'Sebagian peta dasar gagal dimuat. Coba lagi atau isi koordinat secara manual.';
        mapRetry.hidden = false;
    });
    tileLayer.on('load', () => {
        if (!tilesFailed && boundaryReady) mapStatus.textContent = 'Peta siap. Klik pada lokasi yang diajukan.';
    });
    tileLayer.addTo(map);
}
async function showVillage(fit = true) {
    geometryAbort?.abort();
    villageLayer?.remove(); villageLayer = null;
    const code = fields.desa.value;
    const district = fields.kabupaten.value;
    if (!code || !district) return;
    const abort = new AbortController(); geometryAbort = abort;
    try {
        let collection = collections.get(district);
        if (!collection) {
            collection = await json(`${form.dataset.areasUrl}/villages-${district}.geojson`, abort.signal);
            if (abort.signal.aborted || disposed) return;
            collections.set(district, collection);
        }
        const feature = collection.features.find(item => item.properties.code === code);
        if (!feature) throw new Error('Batas desa belum tersedia. Tentukan titik melalui alamat dan koordinat.');
        villageLayer = L.geoJSON(feature, { interactive: false, style: { color: '#0d627f', weight: 2, fillColor: '#76bbbc', fillOpacity: .16 } }).addTo(map);
        if (fit) map.fitBounds(villageLayer.getBounds(), { padding: [24, 24], maxZoom: 14, animate: !reducedMotion.matches });
        administrationStatus.textContent = `${fields.desa.selectedOptions[0].textContent} dipilih. Klik peta untuk menentukan lokasi yang tepat.`;
    } catch (error) {
        if (error.name !== 'AbortError' && !disposed) {
            administrationStatus.textContent = error.message;
            mapRetry.hidden = false;
        }
    }
}
function fillVillages() {
    const records = villages.filter(village => village.subdistrict === fields.kecamatan.value);
    options(fields.desa, records.map(village => [village.code, village.name]), 'Pilih desa/kelurahan');
    fields.desa.disabled = !records.length;
}
async function loadAdministration() {
    administrationAbort?.abort(); geometryAbort?.abort();
    const abort = new AbortController(); administrationAbort = abort;
    villageLayer?.remove(); villageLayer = null;
    villages = [];
    options(fields.kecamatan, [], 'Pilih kecamatan');
    options(fields.desa, [], 'Pilih desa/kelurahan');
    fields.kecamatan.disabled = true; fields.desa.disabled = true;
    administrationRetry.hidden = true;
    if (!fields.kabupaten.value) {
        administrationStatus.textContent = 'Pilih kabupaten/kota terlebih dahulu.';
        return;
    }
    administrationStatus.textContent = 'Memuat kecamatan dan desa…';
    try {
        const params = new URLSearchParams({ kabupaten: fields.kabupaten.value });
        const result = await json(`${form.dataset.administrationUrl}?${params}`, abort.signal);
        if (abort.signal.aborted || disposed) return;
        villages = result.villages;
        const names = [...new Set(villages.map(village => village.subdistrict))].sort((a, b) => a.localeCompare(b, 'id'));
        options(fields.kecamatan, names.map(name => [name, name]), 'Pilih kecamatan');
        fields.kecamatan.disabled = !names.length;
        administrationStatus.textContent = 'Pilih kecamatan, lalu desa/kelurahan.';
    } catch (error) {
        if (error.name !== 'AbortError' && !disposed) {
            administrationStatus.textContent = error.message;
            administrationRetry.hidden = false;
        }
    }
}
fields.kabupaten.addEventListener('change', () => { clearPoint(); loadAdministration(); }, { signal: events.signal });
fields.kecamatan.addEventListener('change', () => {
    geometryAbort?.abort(); villageLayer?.remove(); villageLayer = null;
    clearPoint(); fillVillages();
    administrationStatus.textContent = 'Pilih desa/kelurahan, lalu tentukan titik pada peta.';
}, { signal: events.signal });
fields.desa.addEventListener('change', () => { clearPoint(); showVillage(); }, { signal: events.signal });
for (const field of ['latitude', 'longitude']) {
    fields[field].addEventListener('change', () => syncPoint(true), { signal: events.signal });
}
map.on('click', event => choosePoint(event.latlng));
centerButton.addEventListener('click', () => choosePoint(map.getCenter()), { signal: events.signal });

clearButton.addEventListener('click', clearPoint, { signal: events.signal });
administrationRetry.addEventListener('click', loadAdministration, { signal: events.signal });
mapRetry.addEventListener('click', () => {
    mapRetry.hidden = true;
    loadTiles(); loadBoundary(); showVillage(!coordinates());
}, { signal: events.signal });
const resize = new ResizeObserver(() => {
    map.invalidateSize({ animate: false, pan: false });
    updateProvinceViewport();
});
resize.observe(mapElement);
fields.kabupaten.disabled = false;
home(); loadTiles(); loadBoundary();
centerButton.disabled = false;
if (coordinates()) syncPoint(true);
if (fields.desa.value) showVillage(!coordinates());
window.addEventListener('pagehide', () => {
    disposed = true; events.abort(); administrationAbort?.abort(); geometryAbort?.abort();
    resize.disconnect(); tileLayer?.off(); map.remove();
}, { once: true });
window.addEventListener('pageshow', event => { if (event.persisted && disposed) location.reload(); });

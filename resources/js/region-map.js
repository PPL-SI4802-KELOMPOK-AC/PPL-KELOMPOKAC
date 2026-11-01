import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { mountAmbient } from './ambient';

// A province viewport, never a substitute for village/application coordinates.
const WEST_JAVA_BOUNDS = [[-7.85, 106.35], [-5.9, 108.85]];
const element = document.querySelector('[data-region-map]');
const panel = document.querySelector('[data-map-panel]');
const status = document.querySelector('[data-map-status]');
const retry = document.querySelector('[data-map-retry]');
const reset = document.querySelector('[data-map-reset]');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const events = new AbortController();
let layer;
let timeout;
let failed = false;
let disposed = false;
let zooming = false;
let resetAfterZoom = false;
const map = L.map(element, { zoomControl: false, minZoom: 5, maxZoom: 19, zoomAnimation: !reducedMotion.matches, fadeAnimation: !reducedMotion.matches });
map.on('zoomstart', () => { zooming = true; });
map.on('zoomend', () => {
    zooming = false;
    if (resetAfterZoom) { resetAfterZoom = false; home(true); }
});
L.control.zoom({ position: 'topright', zoomInTitle: 'Perbesar peta', zoomOutTitle: 'Perkecil peta' }).addTo(map);
L.control.scale({ position: 'bottomleft', imperial: false }).addTo(map);
function home(animate = false) {
    if (disposed) return;
    // Leaflet ignores a new animated view during a zoom transition. Keep the
    // user's reset request and apply it as soon as that transition finishes.
    if (zooming) { resetAfterZoom = true; return; }
    map.fitBounds(WEST_JAVA_BOUNDS, { padding: [20, 20], animate: animate && !reducedMotion.matches });
}
function setStatus(state, message) {
    if (disposed) return;
    panel.dataset.state = state;
    status.textContent = message;
    element.setAttribute('aria-busy', String(state === 'loading'));
    retry.hidden = state !== 'error';
}
function fail() {
    failed = true;
    clearTimeout(timeout);
    setStatus('error', 'Sebagian atau seluruh peta gagal dimuat. Periksa koneksi internet, lalu coba lagi.');
}
function loading() {
    if (failed) return;
    setStatus('loading', 'Memuat peta dasar…');
    clearTimeout(timeout);
    timeout = setTimeout(fail, 15000);
}
function loadTiles() {
    clearTimeout(timeout);
    if (layer) { layer.off(); layer.remove(); }
    failed = false;
    loading();
    // Keep browser caching and referrer behavior, including on manual retry.
    layer = L.tileLayer(element.dataset.tileUrl, { attribution: element.dataset.attribution, maxZoom: 19, keepBuffer: 2 });
    layer.on('loading', loading);
    layer.on('tileerror', fail);
    layer.on('load', () => { clearTimeout(timeout); if (!failed) setStatus('ready', 'Peta dasar siap dijelajahi'); });
    layer.addTo(map);
}
home();
loadTiles();
reset.disabled = false;
reset.addEventListener('click', () => home(true), { signal: events.signal });
retry.addEventListener('click', loadTiles, { signal: events.signal });
function motionChanged() { map.stop(); map.options.zoomAnimation = !reducedMotion.matches; map.options.fadeAnimation = !reducedMotion.matches; }
reducedMotion.addEventListener('change', motionChanged);
const resize = new ResizeObserver(() => map.invalidateSize({ animate: false, pan: false }));
resize.observe(element);
const unmountAmbient = mountAmbient();
window.addEventListener('pagehide', () => {
    disposed = true;
    events.abort();
    clearTimeout(timeout);
    resize.disconnect();
    reducedMotion.removeEventListener('change', motionChanged);
    unmountAmbient();
    layer.off();
    map.remove();
}, { once: true });
window.addEventListener('pageshow', event => { if (event.persisted && disposed) location.reload(); });

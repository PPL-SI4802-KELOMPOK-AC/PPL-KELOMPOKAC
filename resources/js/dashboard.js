const root = document.querySelector('[data-dashboard]');
const events = new AbortController();
const signal = events.signal;
const number = value => value === null ? 'Belum tersedia' : new Intl.NumberFormat('id-ID').format(value);
let fetchAbort;
let summary;
let selected = null;

function node(tag, text, className = '') {
    const element = document.createElement(tag); element.textContent = text;
    if (className) element.className = className;
    return element;
}

function mountSolarScene() {
    const scene = document.querySelector('[data-solar-scene]');
    const panel = scene.querySelector('[data-solar-panel]');
    const tooltip = scene.querySelector('#solar-tooltip');
    const glow = document.querySelector('[data-dashboard-glow]');
    const surfaces = [...root.querySelectorAll('.home-metrics article, .home-chart, .home-workflow, .home-sdg')].map(element => {
        const light = document.createElement('span');
        light.className = 'dashboard-surface-light'; light.setAttribute('aria-hidden', 'true');
        element.dataset.dashboardSurface = ''; element.append(light);
        return { element, light };
    });
    const precision = matchMedia('(hover: hover) and (pointer: fine)');
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    let frame = 0, hovered = false, pinned = false;
    const current = { x: 0, y: 0, gx: 0, gy: 0 };
    const target = { x: 0, y: 0, gx: 0, gy: 0 };
    const motionAllowed = () => precision.matches && !reduced.matches;
    function glowOpacity(value) {
        glow.style.setProperty('--solar-glow-opacity', value);
        root.style.setProperty('--dashboard-glow-opacity', value);
    }
    function tooltipState(visible) {
        tooltip.hidden = !visible;
        panel.dataset.active = String(visible);
        panel.setAttribute('aria-expanded', String(visible));
    }
    function paint() {
        // Baca seluruh posisi sebelum menulis style; tidak menggeser layout/kontrol.
        const bounds = surfaces.map(({ element }) => element.getBoundingClientRect());
        scene.style.setProperty('--solar-x', `${current.x.toFixed(3)}px`);
        scene.style.setProperty('--solar-y', `${current.y.toFixed(3)}px`);
        glow.style.setProperty('--glow-x', `${current.gx.toFixed(2)}px`);
        glow.style.setProperty('--glow-y', `${current.gy.toFixed(2)}px`);
        surfaces.forEach(({ light }, i) => {
            light.style.setProperty('--surface-x', `${(current.gx - bounds[i].left).toFixed(2)}px`);
            light.style.setProperty('--surface-y', `${(current.gy - bounds[i].top).toFixed(2)}px`);
        });
    }
    function tick() {
        frame = 0;
        let moving = false;
        for (const key of ['x', 'y', 'gx', 'gy']) {
            const delta = target[key] - current[key];
            if (Math.abs(delta) > .03) { current[key] += delta * (key.startsWith('g') ? .09 : .14); moving = true; }
            else current[key] = target[key];
        }
        paint();
        if (moving) frame = requestAnimationFrame(tick);
        else if (!hovered) scene.classList.remove('is-interacting');
    }
    function schedule() { if (!frame) frame = requestAnimationFrame(tick); }
    function resetMotion() {
        hovered = false; target.x = 0; target.y = 0;
        glowOpacity('0'); schedule();
    }
    function mediaChanged() {
        cancelAnimationFrame(frame); frame = 0;
        current.x = target.x = current.y = target.y = 0;
        current.gx = target.gx = innerWidth / 2;
        current.gy = target.gy = innerHeight / 3;
        paint(); glowOpacity('0');
        scene.dataset.motion = motionAllowed() ? 'enabled' : 'static';
        scene.classList.remove('is-interacting');
    }
    document.addEventListener('pointermove', event => {
        if (!motionAllowed() || event.pointerType === 'touch') return;
        const x = Math.max(0, Math.min(innerWidth, event.clientX));
        const y = Math.max(0, Math.min(innerHeight, event.clientY));
        hovered = true;
        target.x = (x / innerWidth * 2 - 1) * 14;
        target.y = (y / innerHeight * 2 - 1) * 12;
        target.gx = x; target.gy = y;
        glowOpacity('1'); scene.classList.add('is-interacting'); schedule();
    }, { signal });
    document.addEventListener('pointerleave', resetMotion, { signal });
    window.addEventListener('blur', resetMotion, { signal });
    window.addEventListener('scroll', () => { if (hovered && motionAllowed()) schedule(); }, { signal, passive: true });
    window.addEventListener('resize', () => { if (motionAllowed()) schedule(); }, { signal, passive: true });
    panel.addEventListener('pointerenter', event => { if (event.pointerType !== 'touch') tooltipState(true); }, { signal });
    panel.addEventListener('pointerleave', () => { if (!pinned && document.activeElement !== panel) tooltipState(false); }, { signal });
    panel.addEventListener('focus', () => tooltipState(true), { signal });
    panel.addEventListener('blur', () => { pinned = false; tooltipState(false); }, { signal });
    panel.addEventListener('click', () => { pinned = !pinned; tooltipState(pinned); }, { signal });
    panel.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); pinned = !pinned; tooltipState(pinned); }
        if (event.key === 'Escape') { pinned = false; tooltipState(false); }
    }, { signal });
    document.addEventListener('pointerdown', event => { if (!panel.contains(event.target)) { pinned = false; tooltipState(false); } }, { signal });
    precision.addEventListener('change', mediaChanged, { signal });
    reduced.addEventListener('change', mediaChanged, { signal });
    mediaChanged();
    return () => {
        cancelAnimationFrame(frame); glowOpacity('0');
        surfaces.forEach(({ element, light }) => { light.remove(); delete element.dataset.dashboardSurface; });
    };
}

const unmountScene = mountSolarScene();
const chart = document.querySelector('[data-chart-state]');
const chartTotal = document.querySelector('[data-chart-total]');
const legend = document.querySelector('[data-condition-legend]');
const tooltip = document.querySelector('[data-condition-tooltip]');
const reset = document.querySelector('[data-chart-reset]');
const help = 'Arahkan kursor atau fokus ke grafik untuk membaca arti kategori. Pilih legenda untuk menyorot.';

function describe(category) {
    tooltip.textContent = `${category.label}: ${number(category.count)} wilayah · KK belum berlistrik: ${number(category.kk_total)}. ${category.meaning}`;
    tooltip.dataset.active = 'true';
}
function resetDescription() {
    const category = summary?.categories.find(item => item.id === selected);
    if (category) describe(category);
    else { tooltip.textContent = help; tooltip.dataset.active = 'false'; }
}
function selectCategory(id) {
    selected = selected === id ? null : id;
    const category = summary.categories.find(item => item.id === selected);
    chartTotal.textContent = number(category ? category.count : summary.metrics.total);
    document.querySelector('[data-chart-selection]').textContent = category ? `Sorotan: ${category.label}` : 'Semua kategori';
    root.querySelectorAll('[data-category-id]').forEach(element => {
        const active = element.dataset.categoryId === selected;
        element.setAttribute('aria-pressed', String(active));
        element.classList.toggle('is-selected', active);
        element.classList.toggle('is-muted', selected !== null && !active && element.classList.contains('condition-segment'));
    });
    reset.disabled = selected === null;
    resetDescription();
}
function interactiveCategory(element, category, svg = false) {
    element.dataset.categoryId = category.id;
    element.setAttribute('aria-pressed', 'false');
    element.setAttribute('aria-label', `${category.label}: ${number(category.count)} wilayah`);
    element.setAttribute('aria-describedby', 'condition-tooltip');
    element.addEventListener('pointerenter', () => describe(category), { signal });
    element.addEventListener('pointerleave', resetDescription, { signal });
    element.addEventListener('focus', () => describe(category), { signal });
    element.addEventListener('blur', resetDescription, { signal });
    element.addEventListener('click', () => selectCategory(category.id), { signal });
    if (svg) element.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); selectCategory(category.id); }
        if (event.key === 'Escape') { selected = null; selectCategory(null); }
    }, { signal });
}
function renderSummary(data) {
    summary = data; selected = null; reset.disabled = true;
    for (const [name, value] of Object.entries(data.metrics)) {
        const element = document.querySelector(`[data-metric="${name}"]`);
        if (element) { element.textContent = number(value); element.classList.toggle('metric-unavailable', value === null); }
    }
    const segments = document.querySelector('[data-chart-segments]');
    const table = document.querySelector('[data-condition-table]');
    segments.replaceChildren(); table.replaceChildren(); legend.replaceChildren();
    chartTotal.textContent = number(data.metrics.total);
    document.querySelector('[data-chart-selection]').textContent = 'Semua kategori'; resetDescription();
    const hasData = data.metrics.total > 0;
    document.querySelector('[data-chart-content]').hidden = !hasData;
    document.querySelector('[data-chart-table-wrap]').hidden = !hasData;
    document.querySelector('[data-chart-placeholder]').hidden = hasData;
    document.querySelector('[data-chart-placeholder]').textContent = 'Belum ada data wilayah tersimpan. Grafik akan muncul setelah data tersedia.';
    chart.dataset.chartState = hasData ? 'ready' : 'empty';
    let offset = 0;
    const circumference = 2 * Math.PI * 94;
    data.categories.forEach(category => {
        const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        circle.classList.add('condition-segment');
        const length = circumference * category.count / data.metrics.total;
        Object.entries({ cx: 130, cy: 130, r: 94, fill: 'none', stroke: category.color, 'stroke-width': 28, 'stroke-linecap': 'butt', 'stroke-dasharray': `${length} ${circumference - length}`, 'stroke-dashoffset': -offset, transform: 'rotate(-90 130 130)', tabindex: 0, role: 'button' }).forEach(([key, value]) => circle.setAttribute(key, String(value)));
        interactiveCategory(circle, category, true); segments.append(circle); offset += length;
        const button = node('button', ''); button.type = 'button'; button.style.setProperty('--category-color', category.color);
        const dot = node('i', ''); dot.setAttribute('aria-hidden', 'true');
        button.append(dot, node('span', category.label), node('strong', number(category.count)));
        interactiveCategory(button, category); legend.append(button);
        const row = document.createElement('tr'); row.append(node('td', category.label), node('td', number(category.count)), node('td', number(category.kk_total)), node('td', category.meaning)); table.append(row);
    });
    document.querySelector('[data-chart-note]').textContent = data.all_unknown ? 'Jumlah KK belum diisi pada seluruh wilayah. Data kosong tidak dihitung sebagai nol.' : `${number(data.coverage.kk_recorded)} wilayah memiliki angka KK · ${number(data.coverage.kk_unknown)} wilayah belum memiliki angka. Pilih kelompok untuk menyorot; kartu ringkasan tetap menampilkan total keseluruhan.`;
}
reset.addEventListener('click', () => { selected = null; selectCategory(null); }, { signal });

async function loadSummary() {
    fetchAbort?.abort(); const abort = new AbortController(); fetchAbort = abort;
    const label = document.querySelector('[data-dashboard-status]');
    const retry = document.querySelector('[data-dashboard-retry]');
    label.textContent = 'Memuat ringkasan dari database…'; label.parentElement.classList.remove('is-error'); retry.hidden = true;
    root.querySelectorAll('[data-metric]').forEach(element => { element.textContent = '—'; });
    chart.dataset.chartState = 'loading';
    document.querySelector('[data-chart-content]').hidden = true; document.querySelector('[data-chart-table-wrap]').hidden = true;
    const placeholder = document.querySelector('[data-chart-placeholder]'); placeholder.hidden = false; placeholder.textContent = 'Menyiapkan visualisasi…'; reset.disabled = true;
    try {
        const response = await fetch(root.dataset.summaryUrl, { signal: abort.signal, headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Ringkasan belum dapat dimuat. Periksa koneksi database lalu coba kembali.');
        const data = await response.json();
        if (abort.signal.aborted) return;
        if (!data.metrics || !Array.isArray(data.categories) || !['total', 'unserved_kk', 'served_kk'].every(key => data.metrics[key] === null || (Number.isInteger(data.metrics[key]) && data.metrics[key] >= 0))
            || !Number.isInteger(data.metrics.total) || !data.coverage
            || !['kk_recorded', 'kk_unknown'].every(key => Number.isInteger(data.coverage[key]) && data.coverage[key] >= 0)
            || data.coverage.kk_recorded + data.coverage.kk_unknown !== data.metrics.total
            || !data.categories.every(category => Number.isInteger(category.count) && category.count > 0 && (category.kk_total === null || (Number.isInteger(category.kk_total) && category.kk_total >= 0)))
            || data.categories.reduce((sum, category) => sum + category.count, 0) !== data.metrics.total) throw new Error('Respons ringkasan belum lengkap. Silakan coba kembali.');
        renderSummary(data);
        const time = new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit' }).format(new Date(data.generated_at));
        label.textContent = `Data langsung dari aplikasi · diperbarui pukul ${time} WIB`;
    } catch (error) {
        if (error.name === 'AbortError') return;
        label.textContent = error.message === 'Failed to fetch' ? 'Ringkasan gagal diambil. Periksa koneksi lalu coba kembali.' : error.message;
        label.parentElement.classList.add('is-error'); retry.hidden = false;
        chart.dataset.chartState = 'error'; placeholder.textContent = 'Grafik belum tersedia karena ringkasan gagal dimuat. Gunakan tombol coba lagi.';
    }
}
document.querySelector('[data-dashboard-retry]').addEventListener('click', loadSummary, { signal });
document.querySelector('.home-how-link').addEventListener('click', () => {
    document.querySelector('#cara-kerja').focus({ preventScroll: true });
}, { signal });
loadSummary();
window.addEventListener('pagehide', () => { events.abort(); fetchAbort?.abort(); unmountScene(); }, { once: true });
window.addEventListener('pageshow', event => { if (event.persisted) location.reload(); });

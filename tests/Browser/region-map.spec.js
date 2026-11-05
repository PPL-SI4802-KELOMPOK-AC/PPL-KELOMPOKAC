import { test, expect } from '@playwright/test';

// Deterministic test tiles prevent repeated tests from burdening public OSM servers.
// The optional live test and manual visual QA use the real provider.
const tile = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6l1sAAAAASUVORK5CYII=', 'base64');
const tilePattern = 'https://tile.openstreetmap.org/**';
const fulfillTile = route => route.fulfill({ contentType: 'image/png', body: tile });
const ready = async page => {
    await expect(page.locator('[data-map-panel]')).toHaveAttribute('data-state', 'ready');
    await expect(page.locator('[data-boundary-state]')).toHaveAttribute('data-boundary-state', 'ready');
};
const paneTransform = page => page.locator('.leaflet-map-pane').evaluate(node => node.style.transform);
// Leaflet dapat mempertahankan container zoom lama sesudah reset. Lapisan aktif
// memiliki z-index tertinggi; urutan node DOM tidak menunjukkan zoom saat ini.
const zoomLevel = page => page.locator('.leaflet-tile-container').evaluateAll(layers => {
    const active = [...layers].filter(layer => layer.querySelector('img')).sort((a, b) => Number(b.style.zIndex) - Number(a.style.zIndex))[0];
    const src = active?.querySelector('img')?.src;
    return src ? Number(new URL(src).pathname.split('/')[1]) : -1;
});
const entranceFinished = page => page.evaluate(() => Promise.all(document.getAnimations().map(animation => animation.finished)));
const visibleTiles = page => page.locator('[data-region-map]').evaluate(map => {
    const box = map.getBoundingClientRect();
    // Lapisan zoom sebelumnya dapat masih berada di DOM selama transisi/pruning.
    const active = [...map.querySelectorAll('.leaflet-tile-container')].filter(layer => layer.querySelector('img')).sort((a, b) => Number(b.style.zIndex) - Number(a.style.zIndex))[0];
    return [...(active?.querySelectorAll('.leaflet-tile-loaded') ?? [])].flatMap(tile => {
        const rect = tile.getBoundingClientRect();
        return rect.right > box.left && rect.left < box.right && rect.bottom > box.top && rect.top < box.bottom
            ? [{ src: tile.getAttribute('src'), x: Math.round(rect.x - box.x), y: Math.round(rect.y - box.y) }] : [];
    }).sort((a, b) => a.src.localeCompare(b.src));
});

test.beforeEach(async ({ page }) => { await page.route(tilePattern, fulfillTile); });

test('navigation, initial Jawa Barat viewport, drag, zoom and return', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('/');
    await page.getByRole('navigation').getByRole('link', { name: 'Peta Wilayah' }).click();
    await ready(page);
    await expect(page.getByRole('navigation').getByRole('link', { name: 'Peta Wilayah' })).toHaveAttribute('aria-current', 'page');
    const initialZoom = await zoomLevel(page);
    const initialTransform = await paneTransform(page);
    const src = await page.locator('.leaflet-tile').first().getAttribute('src');
    const [, z, x, y] = new URL(src).pathname.split('/').map(value => parseInt(value, 10));
    // Tile coverage around West Java, rather than Europe or a 0,0 fallback.
    expect(x / 2 ** z * 360 - 180).toBeGreaterThan(100);
    expect(x / 2 ** z * 360 - 180).toBeLessThan(112);
    expect(y / 2 ** z).toBeGreaterThan(.5);
    const box = await page.locator('[data-region-map]').boundingBox();
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
    await page.mouse.down();
    await page.mouse.move(box.x + box.width / 2 + 170, box.y + box.height / 2 + 60, { steps: 12 });
    await page.mouse.up();
    await expect.poll(() => paneTransform(page)).not.toBe(initialTransform);
    await page.getByRole('button', { name: 'Perbesar peta', exact: true }).click();
    await expect.poll(() => zoomLevel(page)).toBe(initialZoom + 1);
    await page.getByRole('button', { name: 'Kembali ke Jawa Barat' }).click();
    await expect.poll(() => zoomLevel(page)).toBe(initialZoom);
    await ready(page);
    // Compare the geographic view against a fresh fit, including after panning.
    await expect(page.locator('.leaflet-map-pane')).not.toHaveClass(/leaflet-(zoom|pan)-anim/);
    const resetView = await visibleTiles(page);
    await page.reload();
    await ready(page);
    // Leaflet membulatkan posisi layar; hasil reset boleh berbeda 1 piksel
    // setelah panel status/layout selesai diperbarui.
    await expect.poll(async () => {
        const current = await visibleTiles(page);
        return current.length === resetView.length && current.every((tile, i) => tile.src === resetView[i].src && Math.abs(tile.x - resetView[i].x) <= 1 && Math.abs(tile.y - resetView[i].y) <= 1);
    }).toBe(true);
    await page.getByRole('navigation').getByRole('link', { name: 'Beranda' }).click();
    await page.goBack();
    await ready(page);
    await expect(page.locator('.leaflet-control-zoom')).toHaveCount(1);
    expect(errors).toEqual([]);
});

test('loading, tile failure and explicit retry recover without coordinates', async ({ page }) => {
    await page.unroute(tilePattern);
    let fail = true;
    let release;
    const gate = new Promise(resolve => { release = resolve; });
    await page.route(tilePattern, async route => { await gate; return fail ? route.abort('failed') : fulfillTile(route); });
    await page.goto('/peta-wilayah');
    await expect(page.locator('[data-region-map]')).toHaveAttribute('aria-busy', 'true');
    await expect(page.locator('[data-map-status]')).toContainText('Memuat');
    release();
    await expect(page.locator('[data-map-status]')).toContainText('gagal dimuat');
    await expect(page.locator('[data-region-map]')).toHaveAttribute('aria-busy', 'false');
    fail = false;
    await page.getByRole('button', { name: 'Coba lagi' }).click();
    await ready(page);
    await expect(page.getByRole('button', { name: 'Coba lagi' })).toBeHidden();
    await expect(page.locator('.leaflet-control-attribution')).toContainText('OpenStreetMap');
});

test('province outline and mask constrain the map without decorative effects or blocking controls', async ({ page }) => {
    await page.goto('/peta-wilayah');
    await ready(page);
    await expect(page.locator('[data-boundary-state]')).toHaveAttribute('data-boundary-state', 'ready');
    await expect(page.locator('.province-mask')).toHaveCount(1);
    expect(await page.locator('.leaflet-province-mask-pane').evaluate(node => getComputedStyle(node).pointerEvents)).toBe('none');
    await expect(page.locator('.ambient')).toHaveCount(0);
    const initialZoom = await zoomLevel(page);
    await expect(page.getByRole('button', { name: 'Perkecil peta', exact: true })).toHaveAttribute('aria-disabled', 'true');
    await expect.poll(() => zoomLevel(page)).toBe(initialZoom);
});

test('reduced motion disables decoration; keyboard map controls still work', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/peta-wilayah');
    await ready(page);
    await page.mouse.move(800, 140);
    await expect(page.locator('.ambient')).toHaveCount(0);
    expect(await page.locator('.map-panel').evaluate(node => getComputedStyle(node).animationName)).toBe('none');
    const zoom = await zoomLevel(page);
    await page.getByRole('button', { name: 'Perbesar peta', exact: true }).focus();
    expect(await page.locator('.leaflet-control-zoom-in').evaluate(node => getComputedStyle(node).outlineStyle)).toBe('solid');
    await page.keyboard.press('Enter');
    await expect.poll(() => zoomLevel(page)).toBe(zoom + 1);
    await page.locator('[data-map-reset]').focus();
    await page.keyboard.press('Enter');
    await expect.poll(() => zoomLevel(page)).toBe(zoom);
});

test('mobile touch layout at 390px and 320px stays usable with static background', async ({ browser }) => {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    const page = await context.newPage();
    await page.route(tilePattern, fulfillTile);
    await page.goto('/peta-wilayah');
    await ready(page);
    for (const width of [390, 320]) {
        await page.setViewportSize({ width, height: 844 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await expect(page.locator('[data-map-reset]')).toBeVisible();
        await expect(page.locator('.leaflet-control-attribution')).toBeVisible();
        await expect(page.locator('.ambient')).toHaveCount(0);
    }
    await page.setViewportSize({ width: 390, height: 844 });
    const initial = await paneTransform(page);
    const cdp = await context.newCDPSession(page);
    const box = await page.locator('[data-region-map]').boundingBox();
    const point = { x: box.x + 160, y: box.y + 150 };
    await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [point] });
    for (let i = 1; i <= 8; i++) await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: point.x + i * 10, y: point.y + i * 4 }] });
    await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    await expect.poll(() => paneTransform(page)).not.toBe(initial);
    await page.getByRole('button', { name: 'Perbesar peta', exact: true }).tap();
    await page.getByRole('button', { name: 'Kembali ke Jawa Barat' }).tap();
    await context.close();
});

test('live provider serves readable real map tiles', async ({ page, browser }) => {
    test.skip(process.env.MAP_LIVE_TILES !== '1', 'Opt-in real-provider smoke test; standard suite uses local test tiles.');
    await page.unroute(tilePattern);
    await page.goto('/peta-wilayah');
    await ready(page);
    expect(await page.locator('.leaflet-tile-loaded').first().evaluate(node => node.naturalWidth)).toBe(256);
    await entranceFinished(page);
    await page.screenshot({ path: 'test-results/peta-desktop-live.png', fullPage: true });
    const mobile = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    const mobilePage = await mobile.newPage();
    await mobilePage.goto('/peta-wilayah');
    await ready(mobilePage);
    await entranceFinished(mobilePage);
    await mobilePage.screenshot({ path: 'test-results/peta-mobile-live.png', fullPage: true });
    await mobile.close();
});

import { test, expect } from '@playwright/test';

const ready = page => expect(page.locator('[data-chart-state]')).toHaveAttribute('data-chart-state', 'ready');
const format = value => value === null ? 'Belum tersedia' : new Intl.NumberFormat('id-ID').format(value);
const cssNumber = (page, property) => page.locator('[data-solar-scene]').evaluate((node, prop) => parseFloat(node.style.getPropertyValue(prop)), property);
const fixture = () => ({
    metrics: { total: 17, unserved_kk: 27, served_kk: null },
    coverage: { kk_recorded: 15, kk_unknown: 2 },
    categories: [
        { id: 'has_unserved', label: 'Ada KK belum berlistrik', count: 3, kk_total: 27, color: '#c53d48', meaning: 'Jumlah KK dari database.' },
        { id: 'no_unserved', label: 'Tercatat 0 KK belum berlistrik', count: 12, kk_total: 0, color: '#2f8b63', meaning: 'Nol tidak menunjukkan jumlah KK sudah berlistrik.' },
        { id: 'unavailable', label: 'Data KK belum tersedia', count: 2, kk_total: null, color: '#809bad', meaning: 'NULL bukan nol.' },
    ], all_unknown: false, generated_at: new Date().toISOString(),
});

test.describe('Beranda TerangDesa', () => {
    test.skip(process.env.PBI01_MYSQL_TESTS !== '1', 'Pemeriksaan dashboard aktual memakai MySQL lokal, tanpa mengubah referensi.');

    test('kursor seluruh dashboard menggerakkan ilustrasi dan glow tanpa menggeser konten, serta tooltip keyboard', async ({ page }) => {
        const errors = []; page.on('pageerror', error => errors.push(error.message));
        await page.goto('/'); await ready(page);
        const scene = page.locator('[data-solar-scene]');
        await expect(scene).toHaveAttribute('data-motion', 'enabled');
        const viewport = page.viewportSize();
        const titleBefore = await page.locator('#home-title').boundingBox();
        const button = page.getByRole('link', { name: 'Jelajahi Peta Wilayah' });
        const buttonBefore = await button.boundingBox();
        // Kursor di sisi kiri halaman, di luar ilustrasi, tetap mengaktifkan efek.
        await page.mouse.move(viewport.width * .1, viewport.height * .2);
        await expect.poll(() => cssNumber(page, '--solar-x')).toBeLessThan(-9);
        await page.mouse.move(viewport.width * .9, viewport.height * .7);
        await expect.poll(() => cssNumber(page, '--solar-x')).toBeGreaterThan(9);
        const movement = await scene.locator('[data-solar-layer]').evaluateAll(nodes => Object.fromEntries(nodes.map(node => [node.dataset.solarLayer, new DOMMatrix(getComputedStyle(node).transform).m41])));
        expect(movement.sun).toBeGreaterThan(movement.panel);
        expect(movement.panel).toBeGreaterThan(movement.land);
        expect(movement.sun).toBeLessThanOrEqual(14);
        const glow = page.locator('[data-dashboard-glow]');
        const glowNumber = property => glow.evaluate((node, prop) => parseFloat(node.style.getPropertyValue(prop)), property);
        await expect.poll(() => glowNumber('--glow-x')).toBeGreaterThan(viewport.width * .7);
        expect(await glow.evaluate(node => getComputedStyle(node).pointerEvents)).toBe('none');
        expect(await page.locator('#home-title').boundingBox()).toEqual(titleBefore);
        expect(await button.boundingBox()).toEqual(buttonBefore);
        // Efek mencakup header dan sidebar, tidak terpotong batas kotak hero.
        await page.mouse.move(280, 30);
        await expect.poll(() => glowNumber('--solar-glow-opacity')).toBe(1);
        await expect.poll(() => cssNumber(page, '--solar-x')).toBeLessThan(-7);
        await page.mouse.move(70, 230);
        await expect.poll(() => glowNumber('--glow-x')).toBeLessThan(100);
        await expect(page.locator('.dashboard-surface-light')).toHaveCount(6);
        expect(await page.locator('.dashboard-surface-light').first().evaluate(node => getComputedStyle(node).pointerEvents)).toBe('none');
        await page.mouse.move(-20, -20);
        await expect.poll(async () => Math.abs(await cssNumber(page, '--solar-x'))).toBeLessThan(.05);
        await expect.poll(async () => Math.abs(await cssNumber(page, '--solar-y'))).toBeLessThan(.05);
        await expect.poll(() => glowNumber('--solar-glow-opacity')).toBe(0);
        const panel = page.getByRole('button', { name: 'Pelajari cara kerja panel surya' });
        await panel.focus();
        await expect(page.locator('#solar-tooltip')).toBeVisible();
        await expect(panel).toHaveAttribute('aria-expanded', 'true');
        await page.keyboard.press('Escape');
        await expect(page.locator('#solar-tooltip')).toBeHidden();
        await page.keyboard.press('Enter');
        await expect(page.locator('#solar-tooltip')).toHaveText('PLTS memanfaatkan energi matahari');
        await button.focus();
        await expect(page.locator('#solar-tooltip')).toBeHidden();
        await panel.hover();
        await expect(page.locator('#solar-tooltip')).toBeVisible();
        expect(errors).toEqual([]);
    });

    test('jumlah KK dan kelompok wilayah berasal dari TiDB, legenda, reset dan navigasi', async ({ page }) => {
        const expected = await (await page.request.get('/dashboard/data')).json();
        await page.goto('/'); await ready(page);
        for (const [key, value] of Object.entries(expected.metrics)) await expect(page.locator(`[data-metric="${key}"]`)).toHaveText(format(value));
        await expect(page.locator('.condition-segment')).toHaveCount(expected.categories.length);
        await expect(page.locator('[data-condition-table] tr')).toHaveCount(expected.categories.length);
        await expect(page.locator('[data-metric=served_kk]')).toHaveText('Belum tersedia');
        await expect(page.getByText('Wilayah dengan kondisi listrik diisi', { exact: true })).toHaveCount(0);
        const first = expected.categories[0];
        const segment = page.locator('.condition-segment').first();
        await segment.focus();
        await expect(page.locator('[data-condition-tooltip]')).toContainText(`${first.label}: ${format(first.count)} wilayah`);
        await page.keyboard.press('Enter');
        await expect(segment).toHaveAttribute('aria-pressed', 'true');
        await expect(page.locator('[data-chart-selection]')).toContainText(first.label);
        await page.getByRole('button', { name: 'Reset sorotan' }).click();
        await expect(segment).toHaveAttribute('aria-pressed', 'false');
        await expect(page.locator('[data-chart-total]')).toHaveText(format(expected.metrics.total));
        await page.locator('[data-condition-legend] button').first().click();
        await expect(page.locator('[data-condition-legend] button').first()).toHaveAttribute('aria-pressed', 'true');
        await page.getByRole('button', { name: 'Reset sorotan' }).click();
        await page.getByRole('link', { name: 'Lihat Cara Kerja' }).click();
        await expect(page).toHaveURL(/#cara-kerja$/);
        await expect(page.locator('#cara-kerja')).toBeFocused();
        await expect(page.locator('.workflow-steps li')).toHaveCount(4);
        await page.screenshot({ path: 'test-results/dashboard-desktop.png', fullPage: true });
        await page.route('https://tile.openstreetmap.org/**', route => route.fulfill({ contentType: 'image/png', body: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6l1sAAAAASUVORK5CYII=', 'base64') }));
        await page.getByRole('link', { name: 'Jelajahi Peta Wilayah' }).click();
        await expect(page).toHaveURL(/peta-wilayah/);
        await expect(page.locator('[data-dashboard-glow]')).toHaveCount(0);
        await expect(page.locator('[data-boundary-state]')).toHaveAttribute('data-boundary-state', 'ready');
    });

    test('sorotan grafik multi kategori dan reset mengubah pilihan tanpa mengubah total ringkasan', async ({ page }) => {
        // Fixture respons hanya untuk keadaan UI; tidak disimpan ke database aplikasi.
        await page.route('**/dashboard/data', route => route.fulfill({ json: fixture() }));
        await page.goto('/'); await ready(page);
        await page.locator('[data-condition-legend] button').filter({ hasText: 'Ada KK belum berlistrik' }).click();
        await expect(page.locator('[data-chart-total]')).toHaveText('3');
        await expect(page.locator('.condition-segment.is-muted')).toHaveCount(2);
        await expect(page.locator('[data-metric=total]')).toHaveText('17');
        await expect(page.locator('[data-condition-tooltip]')).toContainText('3 wilayah');
        await page.getByRole('button', { name: 'Reset sorotan' }).click();
        await expect(page.locator('[data-chart-total]')).toHaveText('17');
        await expect(page.locator('.condition-segment.is-muted')).toHaveCount(0);
        await page.locator('.condition-segment').last().focus();
        await page.keyboard.press('Space');
        await expect(page.locator('[data-chart-total]')).toHaveText('2');
        await expect(page.locator('[data-condition-tooltip]')).toContainText('Data KK belum tersedia: 2 wilayah');
    });

    test('loading, gagal dan kosong dibedakan; retry tidak menampilkan nol palsu saat gagal', async ({ page }) => {
        let release, calls = 0;
        const gate = new Promise(resolve => { release = resolve; });
        await page.route('**/dashboard/data', async route => {
            calls++;
            if (calls === 1) { await gate; return route.fulfill({ status: 503, json: {} }); }
            return route.fulfill({ json: { metrics: { total: 0, unserved_kk: null, served_kk: null }, coverage: { kk_recorded: 0, kk_unknown: 0 }, categories: [], all_unknown: false, generated_at: new Date().toISOString() } });
        });
        await page.goto('/');
        await expect(page.locator('[data-chart-state]')).toHaveAttribute('data-chart-state', 'loading');
        await expect(page.locator('[data-metric=total]')).toHaveText('—');
        release();
        await expect(page.locator('[data-chart-state]')).toHaveAttribute('data-chart-state', 'error');
        await expect(page.locator('[data-metric=total]')).toHaveText('—');
        await page.getByRole('button', { name: 'Coba lagi ringkasan' }).click();
        await expect(page.locator('[data-chart-state]')).toHaveAttribute('data-chart-state', 'empty');
        await expect(page.locator('[data-metric=total]')).toHaveText('0');
        await expect(page.locator('[data-chart-placeholder]')).toContainText('Belum ada data wilayah tersimpan');
        await expect(page.locator('.condition-segment')).toHaveCount(0);
    });

    test('seluruh data KK NULL ditampilkan belum tersedia, bukan nol atau sudah berlistrik', async ({ page }) => {
        const data = { metrics: { total: 4, unserved_kk: null, served_kk: null }, coverage: { kk_recorded: 0, kk_unknown: 4 },
            categories: [{ id: 'unavailable', label: 'Data KK belum tersedia', count: 4, kk_total: null, color: '#809bad', meaning: 'NULL bukan nol.' }],
            all_unknown: true, generated_at: new Date().toISOString() };
        await page.route('**/dashboard/data', route => route.fulfill({ json: data }));
        await page.goto('/'); await ready(page);
        await expect(page.locator('[data-metric=unserved_kk]')).toHaveText('Belum tersedia');
        await expect(page.locator('[data-metric=served_kk]')).toHaveText('Belum tersedia');
        await expect(page.locator('[data-chart-note]')).toContainText('Data kosong tidak dihitung sebagai nol');
        await expect(page.locator('[data-condition-table] tr td').nth(2)).toHaveText('Belum tersedia');
    });

    test('reduced motion, sentuhan, tooltip tap dan drawer pada 390/320px', async ({ page, browser }) => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.goto('/'); await ready(page);
        const scene = page.locator('[data-solar-scene]');
        await expect(scene).toHaveAttribute('data-motion', 'static');
        const box = await scene.boundingBox();
        await page.mouse.move(box.x + box.width * .9, box.y + box.height * .8);
        await expect.poll(() => cssNumber(page, '--solar-x')).toBe(0);
        expect(await scene.locator('[data-solar-layer=sun]').evaluate(node => getComputedStyle(node).transform)).toBe('none');
        expect(await page.locator('[data-dashboard-glow]').evaluate(node => getComputedStyle(node).display)).toBe('none');
        const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
        const mp = await context.newPage();
        await mp.goto('/'); await ready(mp);
        await expect(mp.locator('[data-solar-scene]')).toHaveAttribute('data-motion', 'static');
        expect(await mp.locator('[data-dashboard-glow]').evaluate(node => getComputedStyle(node).display)).toBe('none');
        await mp.getByRole('button', { name: 'Pelajari cara kerja panel surya' }).tap();
        await expect(mp.locator('#solar-tooltip')).toBeVisible();
        await mp.locator('[data-condition-legend] button').first().tap();
        await expect(mp.locator('[data-condition-tooltip]')).toContainText('wilayah');
        for (const width of [390, 320]) {
            await mp.setViewportSize({ width, height: 844 });
            expect(await mp.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
            await mp.getByRole('button', { name: '☰ Menu', exact: true }).click();
            await expect(mp.getByRole('navigation').getByRole('link', { name: 'Beranda', exact: true })).toHaveAttribute('aria-current', 'page');
            await mp.keyboard.press('Escape');
            await expect(mp.locator('[data-drawer-open]')).toHaveAttribute('aria-expanded', 'false');
        }
        await mp.setViewportSize({ width: 390, height: 844 });
        await mp.screenshot({ path: 'test-results/dashboard-mobile.png', fullPage: true });
        await context.close();
    });
});

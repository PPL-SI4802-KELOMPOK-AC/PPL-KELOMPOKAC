import { test, expect } from '@playwright/test';

const tile = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6l1sAAAAASUVORK5CYII=', 'base64');
const ready = page => expect(page.locator('[data-map-data-state]')).toHaveAttribute('data-map-data-state', 'ready', { timeout: 35000 });
const format = value => new Intl.NumberFormat('id-ID').format(value);

test.describe('Peta wilayah dari TiDB', () => {
    test.setTimeout(90000);
    test.skip(process.env.PBI01_MYSQL_TESTS !== '1', 'Memerlukan koneksi database aplikasi.');
    let baseline;
    test.beforeEach(async ({ page }) => {
        await page.route('https://tile.openstreetmap.org/**', route => route.fulfill({ contentType: 'image/png', body: tile }));
        baseline = await (await page.request.get('/peta-wilayah/data')).json();
        await page.goto('/peta-wilayah');
        await ready(page);
        await expect(page.locator('[data-boundary-state]')).toHaveAttribute('data-boundary-state', 'ready');
    });

    test('saran pencarian hasil merge tersedia dan tetap bisa dipilih dengan keyboard', async ({ page }) => {
        const errors = []; page.on('pageerror', error => errors.push(error.message));
        await expect(page.locator('#map-search-suggestions')).toHaveCount(1);
        await page.getByLabel('Cari wilayah', { exact: true }).fill('BOJONGSOANG');
        await expect(page.getByLabel('Kabupaten/kota')).toHaveValue('3204', { timeout: 35000 });
        await ready(page);
        const suggestions = page.locator('#map-search-suggestions');
        await expect(suggestions).toBeVisible();
        const firstName = await suggestions.locator('button strong').first().innerText();
        await page.getByLabel('Cari wilayah', { exact: true }).press('ArrowDown');
        await expect(suggestions.locator('button').first()).toBeFocused();
        // The input blur delay must not hide options while keyboard focus is inside the list.
        await page.waitForTimeout(200);
        await expect(suggestions).toBeVisible();
        await page.keyboard.press('Enter');
        await expect(page.locator('#map-detail-title')).toHaveText(firstName);
        await expect(suggestions).toBeHidden();
        expect(errors).toEqual([]);
    });

    test('template tanpa kotak saran tetap dapat memuat data dan menggunakan pencarian', async ({ page }) => {
        const errors = []; page.on('pageerror', error => errors.push(error.message));
        await page.addInitScript(() => {
            const observer = new MutationObserver(() => {
                const optional = document.getElementById('map-search-suggestions');
                if (optional) { optional.remove(); observer.disconnect(); }
            });
            observer.observe(document, { childList: true, subtree: true });
        });
        await page.goto('/peta-wilayah'); await ready(page);
        await expect(page.locator('#map-search-suggestions')).toHaveCount(0);
        await expect(page.locator('[data-map-rows] tr')).toHaveCount(15);
        await page.getByLabel('Cari wilayah', { exact: true }).fill('BOJONGSOANG');
        await page.getByRole('button', { name: 'Terapkan', exact: true }).click();
        await expect(page.getByLabel('Kabupaten/kota')).toHaveValue('3204', { timeout: 35000 });
        await ready(page);
        await page.getByLabel('Cari wilayah', { exact: true }).focus();
        await page.keyboard.press('Escape');
        await ready(page);
        expect(await page.locator('[data-map-rows] tr').count()).toBeGreaterThan(0);
        expect(errors).toEqual([]);
    });

    test('pagination, jumlah positif dan nol, pencarian kabupaten, detail, reload, serta reset', async ({ page }) => {
        await expect(page.locator('[data-map-data-status]')).toContainText(`${format(baseline.summary.total)} wilayah sesuai filter`);
        await expect(page.locator('[data-map-rows] tr')).toHaveCount(15);
        await page.getByRole('button', { name: 'Halaman wilayah berikutnya' }).click();
        await expect(page.locator('[data-map-page]')).toContainText('2 /');
        await page.getByLabel('Catatan KK').selectOption('has_unserved');
        await expect(page.locator('[data-map-data-status]')).toContainText(`${format(baseline.summary.has_unserved)} wilayah sesuai filter`);
        await page.getByLabel('Catatan KK').selectOption('no_unserved');
        await expect(page.locator('[data-map-data-status]')).toContainText(`${format(baseline.summary.no_unserved)} wilayah sesuai filter`);
        await expect(page.locator('[data-map-rows] tr').first()).toContainText('0 keluarga');
        await page.getByLabel('Kabupaten/kota').selectOption('3201');
        await page.getByLabel('Cari wilayah', { exact: true }).fill('MALASARI');
        await expect(page.locator('[data-map-rows] tr')).toHaveCount(1);
        await page.getByRole('button', { name: 'Lihat MALASARI', exact: true }).click();
        await expect(page.locator('#map-detail-title')).toHaveText('MALASARI');
        await expect(page.locator('[data-map-detail]')).toContainText('KecamatanNANGGUNG');
        await expect(page.locator('[data-map-detail]')).toContainText('KK belum berlistrik0 keluarga');
        await expect(page.locator('[data-map-detail]')).toContainText('Tahun data2024');
        await expect(page.locator('[data-map-detail]')).toContainText('Ringkasan');
        await page.reload();
        await expect(page.getByLabel('Cari wilayah', { exact: true })).toHaveValue('MALASARI');
        await expect(page.getByLabel('Kabupaten/kota')).toHaveValue('3201');
        await ready(page);
        await page.getByRole('button', { name: 'Reset', exact: true }).click();
        await expect(page.getByLabel('Cari wilayah', { exact: true })).toHaveValue('');
        await expect(page.getByLabel('Catatan KK')).toHaveValue('all');
        await expect(page.getByLabel('Kabupaten/kota')).toHaveValue('');
        await expect(page.locator('[data-map-data-status]')).toContainText(`${format(baseline.summary.total)} wilayah sesuai filter`);
    });

    test('area kabupaten berwarna, hover, keyboard drill-down, desa merah dan hijau, serta kembali', async ({ page }) => {
        await expect(page.locator('.district-area')).toHaveCount(27);
        await expect(page.locator('.village-icon, .cluster-icon')).toHaveCount(0);
        const colors = await page.locator('.district-area').evaluateAll(paths => new Set(paths.map(p => p.getAttribute('fill'))).size);
        expect(colors).toBeGreaterThan(1);
        const bogor = page.locator('.district-area[data-area-code="3201"]');
        // Kabupaten Bogor surrounds Kota Bogor; the SVG bounding-box centre hits the city.
        // Pick a real visible point inside the district rather than hovering its bounding-box centre.
        await bogor.scrollIntoViewIfNeeded();
        const point = await bogor.evaluate(path => {
            const box = path.getBBox(), matrix = path.getScreenCTM();
            for (let x = .1; x < 1; x += .1) for (let y = .1; y < 1; y += .1) {
                const position = new DOMPoint(box.x + box.width * x, box.y + box.height * y).matrixTransform(matrix);
                if (document.elementFromPoint(position.x, position.y) === path) return { x: position.x, y: position.y };
            }
            throw new Error('No visible point inside district polygon.');
        });
        await page.mouse.move(point.x, point.y);
        await expect(page.locator('.leaflet-tooltip')).toContainText('KK belum berlistrik');
        await bogor.focus(); await page.keyboard.press('Enter');
        await expect(page.locator('[data-region-map]')).toHaveAttribute('data-area-level', 'village');
        await ready(page);
        await expect(page.getByLabel('Kabupaten/kota')).toHaveValue('3201');
        expect(await page.locator('.village-area').count()).toBeGreaterThan(400);
        expect(await page.locator('.village-area[fill="#c53d48"]').count()).toBeGreaterThan(0);
        expect(await page.locator('.village-area[fill="#2f8b63"]').count()).toBeGreaterThan(0);
        const malasari = page.locator('.village-area[data-area-code="32.01.21.2001"]');
        await malasari.focus(); await page.keyboard.press('Enter');
        await expect(page.locator('#map-detail-title')).toHaveText('MALASARI');
        await expect(page.locator('[data-map-detail]')).toContainText('KecamatanNANGGUNG');
        await expect(page.locator('[data-map-detail]')).toContainText('0 keluarga');
        await page.getByRole('button', { name: 'Kembali ke Jawa Barat' }).click();
        await expect(page.locator('[data-region-map]')).toHaveAttribute('data-area-level', 'district');
        await ready(page);
        await expect(page.locator('.district-area')).toHaveCount(27);
        await expect(page.getByLabel('Kabupaten/kota')).toHaveValue('');
        await page.getByLabel('Kabupaten/kota').selectOption('3271');
        await ready(page);
        await expect(page.locator('[data-region-map]')).toHaveAttribute('data-area-level', 'village');
        expect(await page.locator('.village-area').count()).toBeGreaterThan(0);
        await expect(page.locator('.village-area:not([fill="#b8c4ce"])')).toHaveCount(0);
    });

    test('filter cepat, ringkasan KK dan perluas peta mendukung keyboard serta tutup detail', async ({ page }) => {
        await expect(page.locator('[data-map-stat=affected_kk]')).toHaveText(format(baseline.summary.affected_kk));
        const group = page.getByRole('group', { name: 'Filter cepat jumlah KK' });
        const positive = group.getByRole('button', { name: 'Ada KK belum berlistrik', exact: true });
        await positive.click(); await ready(page);
        await expect(positive).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByLabel('Catatan KK')).toHaveValue('has_unserved');
        await expect(page.locator('[data-map-stat=total]')).toHaveText(format(baseline.summary.has_unserved));
        await group.getByRole('button', { name: 'Tercatat nol KK' }).click(); await ready(page);
        await expect(page.locator('[data-map-stat=affected_kk]')).toHaveText('0');
        await group.getByRole('button', { name: 'Angka belum tersedia' }).click(); await ready(page);
        await expect(page.getByLabel('Catatan KK')).toHaveValue('unavailable');
        await expect(page.locator('[data-map-stat=affected_kk]')).toHaveText('Belum tersedia');
        await group.getByRole('button', { name: 'Semua wilayah' }).click(); await ready(page);
        await page.getByRole('button', { name: /Perluas peta/ }).click();
        await expect(page.locator('[data-map-panel]')).toHaveClass(/is-expanded/);
        await expect(page.locator('[data-map-panel]')).toHaveAttribute('aria-modal', 'true');
        await expect(page.locator('[data-map-expand]')).toHaveAttribute('aria-expanded', 'true');
        await page.getByRole('button', { name: 'Tutup detail wilayah' }).click();
        await expect(page.locator('[data-map-detail-panel]')).toBeHidden();
        await page.getByRole('button', { name: 'Lihat detail', exact: true }).click();
        await expect(page.locator('[data-map-detail-panel]')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.locator('[data-map-panel]')).not.toHaveClass(/is-expanded/);
        await expect(page.locator('[data-map-expand]')).toBeFocused();
        expect(await page.evaluate(() => document.body.style.overflow)).not.toBe('hidden');
        for (const width of [390, 320]) {
            await page.setViewportSize({ width, height: 844 });
            await page.locator('[data-region-map]').scrollIntoViewIfNeeded();
            await expect.poll(() => page.locator('[data-region-map]').evaluate(map => {
                const box = map.getBoundingClientRect();
                return [...document.querySelectorAll('.district-area')].every(path => {
                    const rect = path.getBoundingClientRect();
                    return rect.left >= box.left - 3 && rect.right <= box.right + 3 && rect.top >= box.top - 3 && rect.bottom <= box.bottom + 3;
                });
            })).toBe(true);
        }
    });

    test('pencarian Bojongsoang memfokuskan Bandung dan menawarkan perbaikan filter kabupaten', async ({ page }) => {
        await page.getByLabel('Kabupaten/kota').selectOption('3217'); await ready(page);
        await page.getByLabel('Cari wilayah', { exact: true }).fill('BOJONGSOANG');
        await page.getByRole('button', { name: 'Terapkan', exact: true }).click(); await ready(page);
        await expect(page.locator('[data-map-search-global]')).toBeVisible();
        await page.getByRole('button', { name: 'Cari di semua kabupaten', exact: true }).click();
        await expect(page.getByLabel('Kabupaten/kota')).toHaveValue('3204'); await ready(page);
        await expect(page.locator('[data-map-context]')).toContainText('BANDUNG');
        await expect(page.locator('[data-region-map]')).toHaveAttribute('data-area-level', 'village');
        const choice = page.getByRole('button', { name: 'Sorot BOJONGSOANG, BOJONGSOANG', exact: true });
        await choice.click();
        await expect(page.locator('#map-detail-title')).toHaveText('BOJONGSOANG');
        await expect(page.locator('[data-map-preview]')).toContainText('BOJONGSOANG');
        await expect(page.locator('.selected-area')).toHaveCount(1);
        await expect(page.locator('[data-map-search-global]')).toBeHidden();
        await page.setViewportSize({ width: 320, height: 844 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    });

    test('wilayah tanpa koordinat tampil pada daftar dengan hasil kosong yang jelas', async ({ page }) => {
        await page.getByText('Filter lanjutan', { exact: true }).click();
        await page.getByLabel('Ketersediaan lokasi').selectOption('missing');
        await expect(page.locator('[data-map-data-status]')).toContainText(`${format(baseline.summary.unlocated)} wilayah sesuai filter`);
        await expect(page.locator('.cluster-icon, .village-icon')).toHaveCount(0);
        await page.locator('[data-map-rows] tr').first().getByRole('button').click();
        await expect(page.locator('[data-map-detail]')).toContainText('Kode wilayah');
        await page.getByLabel('Cari wilayah', { exact: true }).fill('WILAYAH TIDAK ADA 123456');
        await expect(page.locator('[data-map-empty]')).toBeVisible();
        await expect(page.locator('[data-map-data-status]')).not.toContainText('Memuat');
        await expect(page.locator('[data-map-panel]')).toHaveAttribute('data-state', 'ready');
    });

    test('error data dan batas dapat dipulihkan serta seluler tidak overflow', async ({ page }) => {
        await page.route('**/peta-wilayah/data?**', route => route.fulfill({ status: 503, json: { message: 'Layanan data sedang tidak tersedia.' } }));
        await page.getByLabel('Catatan KK').selectOption('has_unserved');
        await expect(page.locator('[data-map-data-state]')).toHaveAttribute('data-map-data-state', 'error');
        await expect(page.locator('[data-map-data-status]')).not.toContainText('Memuat');
        await page.unroute('**/peta-wilayah/data?**');
        await page.getByRole('button', { name: 'Coba lagi data', exact: true }).click();
        await ready(page);
        await page.route('**/data/jawa-barat.geojson', route => route.abort());
        await page.reload();
        await expect(page.locator('[data-boundary-state]')).toHaveAttribute('data-boundary-state', 'error');
        await ready(page);
        await page.unroute('**/data/jawa-barat.geojson');
        await page.getByRole('button', { name: 'Coba lagi batas', exact: true }).click();
        await expect(page.locator('[data-boundary-state]')).toHaveAttribute('data-boundary-state', 'ready');
        for (const width of [390, 320]) {
            await page.setViewportSize({ width, height: 844 });
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
            await page.getByRole('button', { name: '☰ Menu', exact: true }).click();
            await expect(page.locator('[data-drawer-open]')).toHaveAttribute('aria-expanded', 'true');
            await page.keyboard.press('Escape');
        }
    });

    test('NULL dibedakan dari nol dan loading berhenti setelah respons gagal', async ({ page }) => {
        const fixture = await (await page.request.get('/peta-wilayah/data?q=MALASARI&kabupaten=3201')).json();
        fixture.list.data[0].electricity.unserved_families = null;
        fixture.list.data[0].category = 'unavailable';
        fixture.areas.villages['32.01.21.2001'] = fixture.list.data[0];
        await page.route('**/peta-wilayah/data?**', route => route.fulfill({ json: fixture }));
        await page.getByRole('button', { name: 'Terapkan', exact: true }).click();
        await expect(page.locator('[data-map-rows] tr')).toHaveCount(1);
        await expect(page.locator('[data-map-rows] tr')).toContainText('Data belum tersedia');
        await expect(page.locator('[data-map-rows] tr')).not.toContainText('0 keluarga');
        await page.getByRole('button', { name: 'Lihat MALASARI', exact: true }).click();
        await expect(page.locator('[data-map-detail]')).toContainText('KK belum berlistrikData belum tersedia');
        await page.unroute('**/peta-wilayah/data?**');
        let release;
        const gate = new Promise(resolve => { release = resolve; });
        await page.route('**/peta-wilayah/data?**', async route => {
            await gate;
            await route.fulfill({ status: 503, json: { message: 'Layanan data sedang tidak tersedia.' } });
        });
        await page.getByRole('button', { name: 'Terapkan', exact: true }).click();
        await expect(page.locator('[data-map-data-status]')).toHaveText('Memuat data wilayah…');
        release();
        await expect(page.locator('[data-map-data-state]')).toHaveAttribute('data-map-data-state', 'error');
        await expect(page.locator('[data-map-rows]')).toHaveAttribute('aria-busy', 'false');
    });
});

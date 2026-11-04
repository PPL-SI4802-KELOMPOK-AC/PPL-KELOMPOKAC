import { test, expect } from '@playwright/test';

const tile = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6l1sAAAAASUVORK5CYII=', 'base64');
const dataReady = page => expect(page.locator('[data-map-data-state]')).toHaveAttribute('data-map-data-state', 'ready');
test.describe('Peta Jawa Barat dengan database dan CSV lokal', () => {
    test.skip(process.env.PBI01_MYSQL_TESTS !== '1', 'Memerlukan MySQL dan cache CSV aktual; tidak mengubah data referensi.');
    test.beforeEach(async ({ page }) => {
        await page.route('https://tile.openstreetmap.org/**', route => route.fulfill({ contentType: 'image/png', body: tile }));
        await page.goto('/peta-wilayah');
        await dataReady(page);
        await expect(page.locator('[data-boundary-state]')).toHaveAttribute('data-boundary-state', 'ready');
    });

    test('data aktual, filter CSV, kabupaten, pagination, detail dan refresh', async ({ page }) => {
        await expect(page.locator('[data-map-data-status]')).toContainText('5.311 wilayah sesuai filter');
        await expect(page.locator('[data-map-data-status]')).toContainText('5.304 lokasi tersedia · 7 lokasi belum tersedia');
        await expect(page.locator('[data-map-rows] tr')).toHaveCount(15);
        await expect(page.locator('.cluster-icon')).not.toHaveCount(0);
        expect(await page.locator('.cluster-icon').count()).toBeLessThan(100);
        await page.getByRole('button', { name: 'Halaman wilayah berikutnya' }).click();
        await expect(page.locator('[data-map-page]')).toHaveText('2 / 355');
        await page.getByLabel('Kondisi listrik').selectOption('has_unserved');
        await expect(page.locator('[data-map-data-status]')).toContainText('1.197 wilayah sesuai filter');
        await expect(page.locator('[data-map-page]')).toHaveText('1 / 80');
        await expect(page.locator('[data-map-rows] tr').first()).toContainText('Ada KK belum berlistrik');
        await page.getByLabel('Kondisi listrik').selectOption('no_unserved');
        await expect(page.locator('[data-map-data-status]')).toContainText('4.114 wilayah sesuai filter');
        await expect(page.locator('[data-map-rows] tr').first()).toContainText('0 keluarga');
        await expect(page.locator('[data-map-rows] tr').first()).toContainText('Tidak tercatat KK belum berlistrik');
        await page.getByLabel('Kondisi listrik').selectOption('all');
        await page.getByLabel('Kabupaten/kota').selectOption('3201');
        await page.getByLabel('Cari wilayah', { exact: true }).fill('MALASARI');
        const row = page.locator('[data-map-rows] tr').filter({ hasText: 'MALASARI, NANGGUNG' });
        await expect(row).toBeVisible();
        await expect(row).toContainText('0 keluarga');
        await row.getByRole('button', { name: 'Lihat MALASARI', exact: true }).click();
        await expect(page.locator('#map-detail-title')).toHaveText('MALASARI');
        await expect(page.locator('[data-map-detail]')).toContainText('2.690 keluarga');
        await expect(page.locator('[data-map-detail]')).toContainText('Sumber: dataset acuan proyek • Tahun 2024');
        await expect(page.locator('[data-map-detail]')).toContainText('KecamatanNANGGUNG');
        await expect(page.locator('[data-map-detail]')).toContainText('Tidak tercatat KK belum berlistrik');
        await expect(page.locator('[data-map-detail]')).toContainText('Lokasi referensi desa');
        await expect(page.locator('[data-map-detail]')).toContainText('32.01.21.2001');
        await page.reload();
        await expect(page.getByLabel('Cari wilayah', { exact: true })).toHaveValue('MALASARI');
        await expect(page.getByLabel('Kabupaten/kota')).toHaveValue('3201');
        await expect(page.locator('[data-map-rows] tr').filter({ hasText: 'MALASARI, NANGGUNG' })).toBeVisible();
        await page.getByRole('button', { name: 'Reset', exact: true }).click();
        await expect(page.getByLabel('Cari wilayah', { exact: true })).toHaveValue('');
        await expect(page.getByLabel('Kondisi listrik')).toHaveValue('all');
        await expect(page.getByLabel('Kabupaten/kota')).toHaveValue('');
        await expect(page.getByLabel('Ketersediaan lokasi')).toHaveValue('all');
        await expect(page.locator('[data-map-page]')).toHaveText('1 / 355');
        await expect(page.locator('[data-map-data-status]')).toContainText('5.311 wilayah sesuai filter');
        await expect(page.locator('#map-detail-title')).toHaveText('Pilih wilayah');
    });

    test('kelompok bisa diperbesar dan penanda desa membuka informasi tanpa menampilkan rumah rekaan', async ({ page }) => {
        await page.locator('.cluster-icon').first().click();
        await expect(page.locator('.leaflet-popup')).toContainText('Kelompok penanda desa, bukan jumlah keluarga');
        await page.getByRole('button', { name: 'Perbesar kelompok' }).click();
        await dataReady(page);
        await page.getByRole('button', { name: 'Kembali ke Jawa Barat' }).click();
        await page.getByLabel('Kabupaten/kota').selectOption('3201');
        await page.getByLabel('Cari wilayah', { exact: true }).fill('MALASARI');
        await expect(page.locator('.village-icon')).toHaveCount(1);
        await page.locator('.village-icon').click();
        await expect(page.locator('.leaflet-popup')).toContainText('Lokasi referensi desa');
        await expect(page.locator('#map-detail-title')).toHaveText('MALASARI');
        await expect(page.locator('.leaflet-popup')).not.toContainText('rumah belum berlistrik');
    });

    test('wilayah tanpa lokasi dan hasil filter kosong tetap mempertahankan peta dasar', async ({ page }) => {
        await page.getByText('Filter lanjutan', { exact: true }).click();
        await page.getByLabel('Ketersediaan lokasi').selectOption('missing');
        await expect(page.locator('[data-map-data-status]')).toHaveText('7 wilayah sesuai filter · 0 lokasi tersedia · 7 lokasi belum tersedia');
        await expect(page.locator('[data-map-rows] tr')).toHaveCount(7);
        await expect(page.locator('.cluster-icon, .village-icon')).toHaveCount(0);
        await page.getByRole('button', { name: 'Lihat CINTAJAYA', exact: true }).click();
        await expect(page.locator('[data-map-detail]')).toContainText('Lokasi belum tersedia');
        await expect(page.locator('[data-map-detail]')).toContainText('Koordinat dan titik referensi berdasarkan kode belum tersedia');
        await page.getByLabel('Cari wilayah', { exact: true }).fill('WILAYAH TIDAK ADA 123456');
        await expect(page.locator('[data-map-data-status]')).toContainText('Tidak ada wilayah yang sesuai');
        await expect(page.locator('[data-map-empty]')).toBeVisible();
        await expect(page.locator('[data-map-panel]')).toHaveAttribute('data-state', 'ready');
        await expect(page.locator('.leaflet-control-attribution')).toContainText('OpenStreetMap');
    });

    test('kegagalan data berbeda dari kegagalan batas, retry, serta seluler tanpa overflow', async ({ page }) => {
        await page.route('**/peta-wilayah/data?**', route => route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ message: 'Layanan data sedang tidak tersedia.' }) }));
        await page.getByLabel('Kondisi listrik').selectOption('has_unserved');
        await expect(page.locator('[data-map-data-state]')).toHaveAttribute('data-map-data-state', 'error');
        await expect(page.locator('[data-map-data-status]')).not.toContainText('Memuat');
        await expect(page.locator('[data-map-list-count]')).toHaveText('Jumlah hasil belum tersedia');
        await expect(page.locator('[data-map-panel]')).toHaveAttribute('data-state', 'ready');
        await page.unroute('**/peta-wilayah/data?**');
        await page.getByRole('button', { name: 'Coba lagi data', exact: true }).click();
        await expect(page.locator('[data-map-data-status]')).toContainText('1.197 wilayah sesuai filter');
        await page.route('**/data/jawa-barat.geojson', route => route.abort());
        await page.reload();
        await expect(page.locator('[data-boundary-state]')).toHaveAttribute('data-boundary-state', 'error');
        await dataReady(page);
        await expect(page.locator('[data-boundary-status]')).toContainText('Batas provinsi gagal dimuat');
        await page.unroute('**/data/jawa-barat.geojson');
        await page.getByRole('button', { name: 'Coba lagi batas', exact: true }).click();
        await expect(page.locator('[data-boundary-state]')).toHaveAttribute('data-boundary-state', 'ready');
        for (const width of [390, 320]) {
            await page.setViewportSize({ width, height: 844 });
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
            await page.getByRole('button', { name: '☰ Menu', exact: true }).click();
            await expect(page.getByRole('navigation').getByRole('link', { name: 'Peta Wilayah' })).toBeVisible();
            await page.keyboard.press('Escape');
            await expect(page.locator('[data-drawer-open]')).toHaveAttribute('aria-expanded', 'false');
        }
        await page.screenshot({ path: 'test-results/peta-data-mobile.png', fullPage: true });
    });

    test('loading selesai pada error atau hasil kosong, dan NULL tidak ditampilkan sebagai nol', async ({ page }) => {
        const response = await page.request.get('/peta-wilayah/data?q=MALASARI&kabupaten=3201');
        const fixture = await response.json();
        const record = fixture.list.data[0];
        record.csv.belum_listrik = null;
        record.category = 'unavailable';
        fixture.summary = { total: 1, located: 1, unlocated: 0, has_unserved: 0, no_unserved: 0, unavailable: 1 };
        fixture.features = [{ type: 'village', record }];
        fixture.list = { data: [record], total: 1, current_page: 1, last_page: 1, per_page: 15 };
        await page.route('**/peta-wilayah/data?**', route => route.fulfill({ json: fixture }));
        await page.getByRole('button', { name: 'Terapkan', exact: true }).click();
        await expect(page.locator('[data-map-rows] tr')).toHaveCount(1);
        await expect(page.locator('[data-map-rows] tr')).toContainText('Data belum tersedia');
        await expect(page.locator('[data-map-rows] tr')).not.toContainText('0 keluarga');
        await expect(page.locator('.village-dot.unavailable')).toHaveText('?');
        await page.locator('.village-icon').click();
        await expect(page.locator('[data-map-detail]')).toContainText('Keluarga belum berlistrikData belum tersedia');
        await expect(page.locator('[data-map-detail]')).toContainText('2.690 keluarga');

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
        await expect(page.locator('[data-map-data-status]')).not.toContainText('Memuat');
        await expect(page.locator('[data-map-rows]')).toHaveAttribute('aria-busy', 'false');
        await page.unroute('**/peta-wilayah/data?**');
        await page.getByLabel('Cari wilayah', { exact: true }).fill('WILAYAH TIDAK ADA 123456');
        await dataReady(page);
        await expect(page.locator('[data-map-empty]')).toBeVisible();
        await expect(page.locator('[data-map-data-status]')).not.toContainText('Memuat');
    });
});

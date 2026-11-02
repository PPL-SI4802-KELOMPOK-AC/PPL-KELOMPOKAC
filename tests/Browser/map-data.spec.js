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
        await page.getByLabel('Lapisan data').selectOption('csv_positive');
        await expect(page.locator('[data-map-data-status]')).toContainText('1.197 wilayah sesuai filter');
        await expect(page.locator('[data-map-page]')).toHaveText('1 / 80');
        await page.getByLabel('Lapisan data').selectOption('all');
        await page.getByLabel('Kabupaten / kota').selectOption('3201');
        await page.getByLabel('Cari wilayah', { exact: true }).fill('MALASARI');
        const row = page.locator('[data-map-rows] tr').filter({ hasText: 'MALASARI, NANGGUNG' });
        await expect(row).toBeVisible();
        await expect(row).toContainText('0 keluarga');
        await row.getByRole('button', { name: 'Lihat MALASARI', exact: true }).click();
        await expect(page.locator('#map-detail-title')).toHaveText('MALASARI');
        await expect(page.locator('[data-map-detail]')).toContainText('2.690 keluarga');
        await expect(page.locator('[data-map-detail]')).toContainText('Angka dari CSV — belum diverifikasi');
        await expect(page.locator('[data-map-detail]')).toContainText('KK terdampakBelum tersedia');
        await expect(page.locator('[data-map-detail]')).toContainText('Lokasi referensi desa');
        await expect(page.locator('[data-map-detail]')).toContainText('32.01.21.2001');
        await page.reload();
        await expect(page.getByLabel('Cari wilayah', { exact: true })).toHaveValue('MALASARI');
        await expect(page.getByLabel('Kabupaten / kota')).toHaveValue('3201');
        await expect(page.locator('[data-map-rows] tr').filter({ hasText: 'MALASARI, NANGGUNG' })).toBeVisible();
    });

    test('kelompok bisa diperbesar dan penanda desa membuka informasi tanpa menampilkan rumah rekaan', async ({ page }) => {
        await page.locator('.cluster-icon').first().click();
        await expect(page.locator('.leaflet-popup')).toContainText('Kelompok penanda desa, bukan jumlah keluarga');
        await page.getByRole('button', { name: 'Perbesar kelompok' }).click();
        await dataReady(page);
        await page.getByRole('button', { name: 'Kembali ke Jawa Barat' }).click();
        await page.getByLabel('Kabupaten / kota').selectOption('3201');
        await page.getByLabel('Cari wilayah', { exact: true }).fill('MALASARI');
        await expect(page.locator('.village-icon')).toHaveCount(1);
        await page.locator('.village-icon').click();
        await expect(page.locator('.leaflet-popup')).toContainText('Lokasi referensi desa');
        await expect(page.locator('#map-detail-title')).toHaveText('MALASARI');
        await expect(page.locator('.leaflet-popup')).not.toContainText('rumah belum berlistrik');
    });

    test('wilayah tanpa lokasi dan hasil filter kosong tetap mempertahankan peta dasar', async ({ page }) => {
        await page.getByLabel('Ketersediaan lokasi').selectOption('missing');
        await expect(page.locator('[data-map-data-status]')).toHaveText('7 wilayah sesuai filter · 0 lokasi tersedia · 7 lokasi belum tersedia');
        await expect(page.locator('[data-map-rows] tr')).toHaveCount(7);
        await expect(page.locator('.cluster-icon, .village-icon')).toHaveCount(0);
        await page.getByRole('button', { name: 'Lihat CINTAJAYA', exact: true }).click();
        await expect(page.locator('[data-map-detail]')).toContainText('Lokasi belum tersedia');
        await expect(page.locator('[data-map-detail]')).toContainText('Koordinat dan titik referensi berdasarkan kode belum tersedia');
        await page.getByLabel('Lapisan data').selectOption('verified');
        await expect(page.locator('[data-map-data-status]')).toContainText('Tidak ada wilayah yang sesuai');
        await expect(page.locator('[data-map-empty]')).toBeVisible();
        await expect(page.locator('[data-map-panel]')).toHaveAttribute('data-state', 'ready');
        await expect(page.locator('.leaflet-control-attribution')).toContainText('OpenStreetMap');
    });

    test('kegagalan data berbeda dari kegagalan batas, retry, serta seluler tanpa overflow', async ({ page }) => {
        await page.route('**/peta-wilayah/data?**', route => route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ message: 'Layanan data sedang tidak tersedia.' }) }));
        await page.getByLabel('Lapisan data').selectOption('csv_positive');
        await expect(page.locator('[data-map-data-state]')).toHaveAttribute('data-map-data-state', 'error');
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
});

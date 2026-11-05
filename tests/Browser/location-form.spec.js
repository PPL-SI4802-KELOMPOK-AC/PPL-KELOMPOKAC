import { test, expect } from '@playwright/test';
import { unlink } from 'node:fs/promises';
import { basename, dirname, resolve } from 'node:path';

test.skip(process.env.PBI06_SQLITE_TESTS !== '1', 'Gunakan playwright.location.config.js agar pengujian memakai SQLite terpisah.');
const tile = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6l1sAAAAASUVORK5CYII=', 'base64');

test.afterAll(async () => {
    const path = process.env.PBI06_TEST_DATABASE;
    if (path && dirname(path) === resolve('storage/framework/testing') && /^pbi06-e2e-[a-f0-9-]+\.sqlite$/.test(basename(path))) {
        await unlink(path).catch(error => { if (error.code !== 'ENOENT') throw error; });
    }
});

async function openLocationEditor(page) {
    const editor = page.locator('[data-location-editor]');
    const summary = page.locator('[data-location-editor] > summary');
    if (await summary.count() && !await editor.evaluate(element => element.open)) await summary.click();
}

test('isi lokasi, preview foto bukti, simpan draft dan periksa tampilan seluler', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('https://tile.openstreetmap.org/**', route => route.fulfill({ contentType: 'image/png', body: tile }));
    await page.goto('/pengajuan/lokasi');
    await expect(page).toHaveURL(/\/login$/);
    await page.locator('#email').fill('lokasi-e2e@example.test');
    await page.locator('#password').fill('lokasi-e2e-password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/pengajuan\/lokasi$/);
    await page.locator('#kabupaten').selectOption('3205');
    await page.locator('#kecamatan').selectOption('Cikelet');
    await page.locator('#desa').selectOption('32.05.30.2010');
    await expect(page.locator('[data-administration-status]')).toContainText('Awassagara dipilih');
    await page.locator('#alamat').fill('Kampung Uji, RT 03 / RW 02');
    await page.getByRole('button', { name: 'Simpan draft lokasi' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Draft lokasi berhasil disimpan.' })).toBeVisible();
    await expect(page.locator('[data-evidence-upload]')).toHaveAttribute('data-preview-ready', 'true');
    const evidenceInput = page.locator('#photos');
    await evidenceInput.setInputFiles(Array.from({ length: 6 }, (_, index) => ({
        name: `bukti-${index}.png`, mimeType: 'image/png', buffer: tile,
    })));
    await expect(page.locator('[data-evidence-status]')).toContainText('Pilih maksimal 5 foto lagi');
    await expect(page.getByRole('button', { name: 'Unggah foto' })).toBeDisabled();
    await evidenceInput.setInputFiles({ name: 'bukti-lokasi.png', mimeType: 'image/png', buffer: tile });
    await expect(page.locator('[data-evidence-preview] img')).toHaveCount(1);
    await expect(page.locator('[data-evidence-status]')).toContainText('1 foto siap diunggah');
    await page.screenshot({ path: 'test-results/pbi07-evidence-preview.png', fullPage: true });
    await page.reload();
    await openLocationEditor(page);
    await expect(page.locator('#latitude')).toHaveValue('');
    await expect(page.locator('#longitude')).toHaveValue('');
    await expect(page.getByRole('button', { name: 'Pilih titik di tengah peta' })).toBeEnabled();
    await page.getByRole('button', { name: 'Pilih titik di tengah peta' }).click();
    await expect(page.locator('#latitude')).not.toHaveValue('');
    await expect(page.locator('.location-point-icon')).toHaveCount(1);
    await page.locator('#latitude').fill('-7,5812');
    await page.locator('#longitude').fill('107,6984');
    await page.locator('#alamat').fill('Kampung Uji, RT 03 / RW 02');
    await page.getByRole('button', { name: 'Simpan draft lokasi' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Draft lokasi berhasil disimpan.' })).toBeVisible();
    const id = await page.locator('.location-grid [name=draft_id]').inputValue();
    expect(Number(id)).toBeGreaterThan(0);
    await page.reload();
    await openLocationEditor(page);
    await expect(page.locator('#kabupaten')).toHaveValue('3205');
    await expect(page.locator('#kecamatan')).toHaveValue('Cikelet');
    await expect(page.locator('#desa')).toHaveValue('32.05.30.2010');
    await expect(page.locator('#alamat')).toHaveValue('Kampung Uji, RT 03 / RW 02');
    await expect(page.locator('#latitude')).toHaveValue(/^-7\.5812/);
    await expect(page.locator('.location-point-icon')).toHaveCount(1);
    await page.locator('#alamat').fill('Kampung Uji Revisi');
    await page.getByRole('button', { name: 'Simpan draft lokasi' }).click();
    await expect(page.locator('.location-grid [name=draft_id]')).toHaveValue(id);
    await page.screenshot({ path: 'test-results/pbi06-location-desktop.png', fullPage: true });
    await openLocationEditor(page);
    for (const width of [390, 320]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await expect(page.getByRole('button', { name: 'Simpan draft lokasi' })).toBeVisible();
    }
    await page.setViewportSize({ width: 390, height: 900 });
    await page.locator('[data-location-editor] > summary').click();
    await page.evaluate(() => Promise.all(document.getAnimations().map(animation => animation.finished.catch(() => {}))));
    await page.screenshot({ path: 'test-results/pbi06-location-mobile.png', fullPage: true });
    await openLocationEditor(page);
    await page.route('**/pengajuan/lokasi/administrasi?**', route => route.fulfill({ status: 503, json: { message: 'Gangguan uji.' } }));
    await page.locator('#kabupaten').selectOption('3201');
    await expect(page.locator('#desa')).toHaveValue('');
    await expect(page.locator('#latitude')).toHaveValue('');
    await expect(page.locator('[data-administration-retry]')).toBeVisible();
    await page.unroute('**/pengajuan/lokasi/administrasi?**');
    await page.locator('[data-administration-retry]').click();
    await expect(page.locator('#kecamatan')).toBeEnabled();
    await page.reload();
    await openLocationEditor(page);
    await expect(page.getByRole('button', { name: 'Hapus titik' })).toBeEnabled();
    await page.getByRole('button', { name: 'Hapus titik' }).click();
    await expect(page.locator('.location-point-icon')).toHaveCount(0);
    await page.getByRole('button', { name: 'Simpan draft lokasi' }).click();
    await expect(page.locator('.location-grid [name=draft_id]')).toHaveValue(id);
    await page.reload();
    await openLocationEditor(page);
    await expect(page.locator('#latitude')).toHaveValue('');
    await expect(page.locator('#longitude')).toHaveValue('');
    await expect(page.locator('.location-point-icon')).toHaveCount(0);
    await page.unroute('https://tile.openstreetmap.org/**');
    await page.route('https://tile.openstreetmap.org/**', route => route.abort());
    await page.reload();
    await openLocationEditor(page);
    await expect(page.locator('[data-location-map-status]')).toContainText('peta dasar gagal dimuat');
    await expect(page.locator('#alamat')).toHaveValue('Kampung Uji Revisi');
    await expect(page.locator('#latitude')).toHaveValue('');
    await expect(page.getByRole('button', { name: 'Simpan draft lokasi' })).toBeEnabled();
    expect(errors).toEqual([]);
});

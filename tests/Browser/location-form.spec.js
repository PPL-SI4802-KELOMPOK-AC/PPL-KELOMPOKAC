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
    const next = page.getByRole('button', { name: 'Lanjut ke bukti foto' });
    await expect(next).toBeDisabled();
    await page.locator('#kabupaten').selectOption('3205');
    await expect(next).toBeDisabled();
    await page.locator('#kecamatan').selectOption('Cikelet');
    await page.locator('#desa').selectOption('32.05.30.2010');
    await expect(page.locator('[data-administration-status]')).toContainText('Awassagara dipilih');
    await expect(next).toBeDisabled();
    await page.locator('#alamat').fill('   ');
    await expect(next).toBeDisabled();
    await page.locator('#alamat').fill('Kampung Uji, RT 03 / RW 02');
    await expect(next).toBeEnabled();
    await page.getByRole('button', { name: 'Simpan draft', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Draft lokasi berhasil disimpan.' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Tentukan lokasi pengajuan' })).toBeVisible();
    await page.locator('#alamat').fill('');
    await expect(next).toBeDisabled();
    await page.locator('#alamat').fill('Kampung Uji, RT 03 / RW 02');
    await expect(next).toBeEnabled();
    await expect(page.locator('[data-evidence-upload]')).toHaveCount(0);
    await page.getByRole('button', { name: 'Lanjut ke bukti foto' }).click();
    await expect(page).toHaveURL(/\/pengajuan\/lokasi\/bukti$/);
    await expect(page.getByRole('heading', { name: 'Tunjukkan kondisi di lapangan' })).toBeVisible();
    await expect(page.locator('[data-location-form]')).toHaveCount(0);
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
    await page.getByRole('link', { name: 'Kembali ke data lokasi' }).click();
    await expect(page).toHaveURL(/\/pengajuan\/lokasi$/);
    await expect(page.locator('#latitude')).toHaveValue('');
    await expect(page.locator('#longitude')).toHaveValue('');
    await expect(page.getByRole('button', { name: 'Pilih titik di tengah peta' })).toBeEnabled();
    await page.getByRole('button', { name: 'Pilih titik di tengah peta' }).click();
    await expect(page.locator('#latitude')).not.toHaveValue('');
    await expect(page.locator('.location-point-icon')).toHaveCount(1);
    await page.locator('#latitude').fill('-7,5812');
    await page.locator('#longitude').fill('107,6984');
    await page.locator('#alamat').fill('Kampung Uji, RT 03 / RW 02');
    await page.getByRole('button', { name: 'Simpan draft', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Draft lokasi berhasil disimpan.' })).toBeVisible();
    const id = await page.locator('.location-grid [name=draft_id]').inputValue();
    expect(Number(id)).toBeGreaterThan(0);
    await page.reload();
    await expect(page.locator('#kabupaten')).toHaveValue('3205');
    await expect(page.locator('#kecamatan')).toHaveValue('Cikelet');
    await expect(page.locator('#desa')).toHaveValue('32.05.30.2010');
    await expect(page.locator('#alamat')).toHaveValue('Kampung Uji, RT 03 / RW 02');
    await expect(page.locator('#latitude')).toHaveValue(/^-7\.5812/);
    await expect(page.locator('.location-point-icon')).toHaveCount(1);
    await page.locator('#alamat').fill('Kampung Uji Revisi');
    await page.getByRole('button', { name: 'Simpan draft', exact: true }).click();
    await expect(page.locator('.location-grid [name=draft_id]')).toHaveValue(id);
    await page.screenshot({ path: 'test-results/pbi06-location-desktop.png', fullPage: true });
    for (const width of [390, 320]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await expect(page.getByRole('button', { name: 'Simpan draft', exact: true })).toBeVisible();
    }
    await page.setViewportSize({ width: 390, height: 900 });
    await page.evaluate(() => Promise.all(document.getAnimations().map(animation => animation.finished.catch(() => {}))));
    await page.screenshot({ path: 'test-results/pbi06-location-mobile.png', fullPage: true });
    await page.route('**/pengajuan/lokasi/administrasi?**', route => route.fulfill({ status: 503, json: { message: 'Gangguan uji.' } }));
    await page.locator('#kabupaten').selectOption('3201');
    await expect(page.locator('#desa')).toHaveValue('');
    await expect(page.locator('#latitude')).toHaveValue('');
    await expect(page.locator('[data-administration-retry]')).toBeVisible();
    await page.unroute('**/pengajuan/lokasi/administrasi?**');
    await page.locator('[data-administration-retry]').click();
    await expect(page.locator('#kecamatan')).toBeEnabled();
    await page.reload();
    await expect(page.getByRole('button', { name: 'Hapus titik' })).toBeEnabled();
    await page.getByRole('button', { name: 'Hapus titik' }).click();
    await expect(page.locator('.location-point-icon')).toHaveCount(0);
    await page.getByRole('button', { name: 'Simpan draft', exact: true }).click();
    await expect(page.locator('.location-grid [name=draft_id]')).toHaveValue(id);
    await page.reload();
    await expect(page.locator('#latitude')).toHaveValue('');
    await expect(page.locator('#longitude')).toHaveValue('');
    await expect(page.locator('.location-point-icon')).toHaveCount(0);
    await page.unroute('https://tile.openstreetmap.org/**');
    await page.route('https://tile.openstreetmap.org/**', route => route.abort());
    await page.reload();
    await expect(page.locator('[data-location-map-status]')).toContainText('peta dasar gagal dimuat');
    await expect(page.locator('#alamat')).toHaveValue('Kampung Uji Revisi');
    await expect(page.locator('#latitude')).toHaveValue('');
    await expect(page.getByRole('button', { name: 'Simpan draft', exact: true })).toBeEnabled();
    expect(errors).toEqual([]);
});

test('periksa ringkasan, setujui pernyataan, kirim dan buka ulang nomor pengajuan', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    // Foto uji dipenuhi di browser; tidak ada akses bucket R2 sebenarnya.
    await page.route(/\/pengajuan\/lokasi\/bukti\/\d+$/, route => route.fulfill({ contentType: 'image/png', body: tile }));
    await page.route('https://tile.openstreetmap.org/**', route => route.fulfill({ contentType: 'image/png', body: tile }));
    await page.goto('/pengajuan/lokasi/ringkasan');
    await page.locator('#email').fill('pengajuan-e2e@example.test');
    await page.locator('#password').fill('pengajuan-e2e-password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/pengajuan\/lokasi\/ringkasan$/);
    await expect(page.getByRole('heading', { name: 'Periksa sebelum mengirim' })).toBeVisible();
    await expect(page.locator('.location-stepper [aria-current=step]')).toContainText('Ringkasan & kirim');
    await expect(page.locator('.review-evidence img')).toHaveCount(2);
    await expect(page.getByText('Pengusul Uji PBI-08', { exact: true })).toHaveCount(2);
    const send = page.getByRole('button', { name: 'Kirim pengajuan' });
    await expect(send).toBeDisabled();
    await page.locator('#pernyataan').check();
    await expect(send).toBeEnabled();
    await page.locator('#pernyataan').uncheck();
    await expect(send).toBeDisabled();
    await page.screenshot({ path: 'test-results/pbi08-summary-desktop.png', fullPage: true });
    await page.getByRole('link', { name: 'Kembali ke bukti foto' }).click();
    await expect(page.locator('.evidence-grid img')).toHaveCount(2);
    await page.getByRole('link', { name: 'Lanjut ke ringkasan' }).click();
    for (const width of [390, 320]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await expect(send).toBeDisabled();
    }
    await page.screenshot({ path: 'test-results/pbi08-summary-mobile.png', fullPage: true });
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.locator('#pernyataan').check();
    await send.click();
    await expect(page).toHaveURL(/\/pengajuan\/lokasi\/terkirim\/\d+$/);
    await expect(page.getByRole('heading', { name: 'Pengajuan berhasil dikirim' })).toBeVisible();
    const ticket = await page.locator('.submission-ticket').innerText();
    expect(ticket).toMatch(/^PLTS-\d{8}-[0-9A-Z]{26}$/);
    await page.reload();
    await expect(page.locator('.submission-ticket')).toHaveText(ticket);
    await page.screenshot({ path: 'test-results/pbi08-submitted.png', fullPage: true });
    expect(errors).toEqual([]);
});

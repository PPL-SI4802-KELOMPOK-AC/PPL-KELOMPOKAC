import { test, expect } from '@playwright/test';
import { unlink } from 'node:fs/promises';
import { basename, dirname, resolve } from 'node:path';

test.skip(process.env.PBI06_SQLITE_TESTS !== '1', 'Jalankan dengan playwright.navigation.config.js.');
const tile = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6l1sAAAAASUVORK5CYII=', 'base64');
const ready = page => expect(page.locator('[data-map-data-state]')).toHaveAttribute('data-map-data-state', 'ready');
const top = page => page.getByRole('navigation', { name: 'Navigasi utama', exact: true });
const login = async (page, email, password) => {
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(email);
    await page.getByLabel('Kata sandi').fill(password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
};

test.beforeEach(async ({ page }) => {
    await page.route('https://tile.openstreetmap.org/**', route => route.fulfill({ contentType: 'image/png', body: tile }));
});
test.afterAll(async () => {
    const path = process.env.PBI06_TEST_DATABASE;
    if (path && dirname(path) === resolve('storage/framework/testing') && /^pbi06-e2e-[a-f0-9-]+\.sqlite$/.test(basename(path))) {
        await unlink(path).catch(error => { if (error.code !== 'ENOENT') throw error; });
    }
});

test('pengunjung dapat membuka beranda, data publik, filter peta, dan login', async ({ page }) => {
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    const mapRequests = [];
    page.on('request', request => { if (new URL(request.url()).pathname === '/peta-wilayah/data') mapRequests.push(request.url()); });
    await page.goto('/');
    await expect(page.locator('[data-chart-state]')).toHaveAttribute('data-chart-state', 'ready');
    await expect(page.locator('[data-region-map]')).toHaveCount(0);
    await expect(page.locator('#sidebar')).toHaveCount(0);
    await expect(top(page).getByRole('link', { name: 'Beranda' })).toHaveAttribute('aria-current', 'page');
    await expect(page.locator('#sdg-title')).toContainText('SDG 7');
    expect(mapRequests).toEqual([]);
    await page.screenshot({ path: 'test-results/navigation-public-home.png', fullPage: true });
    await top(page).getByRole('link', { name: 'Peta wilayah', exact: true }).click(); await ready(page);
    await expect(page.locator('[data-map-stat="affected_kk"]')).toHaveText('7');
    const response = await page.request.get('/peta-wilayah/data'); expect(response.ok()).toBeTruthy();
    const data = await response.json(); expect(data.summary.total).toBe(1);
    expect(data.list.data[0]).not.toHaveProperty('dibuat_oleh');
    expect(JSON.stringify(data)).not.toMatch(/password|remember_token|123456/);
    await page.getByRole('button', { name: 'Tercatat nol KK', exact: true }).click();
    await expect(page.locator('[data-map-stat="total"]')).toHaveText('0');
    await page.getByRole('button', { name: 'Semua wilayah', exact: true }).click(); await ready(page);
    await page.getByLabel('Cari wilayah', { exact: true }).fill('AWASSAGARA');
    await page.getByRole('button', { name: 'Terapkan', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Lihat AWASSAGARA', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Lihat AWASSAGARA', exact: true }).click();
    await expect(page.locator('#map-detail-title')).toHaveText('AWASSAGARA');
    await page.screenshot({ path: 'test-results/navigation-public-map.png', fullPage: true });
    await top(page).getByRole('link', { name: 'Peta wilayah', exact: true }).click(); await ready(page);
    await expect(page).toHaveURL(url => url.pathname === '/peta-wilayah');
    await expect(top(page).getByRole('link', { name: 'Peta wilayah', exact: true })).toHaveAttribute('aria-current', 'page');
    await page.getByRole('link', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/login$/);
    await page.goto('/pengajuan/riwayat'); await expect(page).toHaveURL(/\/login$/);
    expect(errors).toEqual([]);
});

test('Masyarakat tetap memakai navigasi atas antara peta pengajuan dan riwayat', async ({ page }) => {
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    await login(page, 'lokasi-e2e@example.test', 'lokasi-e2e-password');
    await expect(page).toHaveURL(/\/pengajuan\/lokasi$/);
    const originalBrand = await page.locator('.site-header .brand').boundingBox();
    await top(page).getByRole('link', { name: 'Peta wilayah', exact: true }).click(); await ready(page);
    await expect(page.locator('#sidebar')).toHaveCount(0);
    await expect(top(page).getByRole('link')).toHaveText(['Peta wilayah', 'Pengajuan PLTS', 'Riwayat']);
    await expect(top(page).getByRole('link', { name: 'Peta wilayah', exact: true })).toHaveAttribute('aria-current', 'page');
    const mapBrand = await page.locator('.site-header .brand').boundingBox();
    expect(mapBrand.x).toBeCloseTo(originalBrand.x, 0); expect(mapBrand.y).toBeCloseTo(originalBrand.y, 0);
    await page.getByRole('button', { name: 'Perluas peta', exact: false }).click();
    await expect(page.locator('[data-map-panel]')).toHaveClass(/is-expanded/);
    await page.keyboard.press('Escape'); await expect(page.locator('[data-map-panel]')).not.toHaveClass(/is-expanded/);
    await page.screenshot({ path: 'test-results/navigation-masyarakat-map.png', fullPage: true });
    await top(page).getByRole('link', { name: 'Pengajuan PLTS', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Tentukan lokasi pengajuan' })).toBeVisible();
    await expect(top(page).getByRole('link', { name: 'Pengajuan PLTS' })).toHaveAttribute('aria-current', 'page');
    await top(page).getByRole('link', { name: 'Riwayat', exact: true }).click();
    await expect(page).toHaveURL(/\/pengajuan\/riwayat$/);
    await expect(top(page).getByRole('link', { name: 'Riwayat' })).toHaveAttribute('aria-current', 'page');
    await expect(page.locator('#sidebar')).toHaveCount(0);
    expect((await page.request.get('/pemerintah/wilayah')).status()).toBe(403);
    expect(errors).toEqual([]);
});

test('Pemerintah mempertahankan sidebar dan Admin mendapat beranda tanpa izin Pemerintah', async ({ page }) => {
    await login(page, 'pemerintah-navigation@example.test', 'navigation-e2e-password');
    await expect(page).toHaveURL(/\/pemerintah\/wilayah$/); await expect(page.locator('#sidebar')).toBeVisible();
    await page.locator('#sidebar').getByRole('link', { name: 'Peta Wilayah', exact: true }).click(); await ready(page);
    await expect(page.locator('#sidebar')).toBeVisible();
    await expect(page.locator('.top-nav')).toHaveCount(0);
    await page.screenshot({ path: 'test-results/navigation-pemerintah-map.png', fullPage: true });
    await page.getByRole('button', { name: 'Keluar', exact: true }).click();
    await login(page, 'admin-navigation@example.test', 'navigation-e2e-password');
    await expect(page).toHaveURL('http://127.0.0.1:8017/');
    await expect(page.locator('#sidebar')).toHaveCount(0);
    expect((await page.request.get('/pemerintah/wilayah')).status()).toBe(403);
    expect((await page.request.get('/pengajuan/lokasi')).status()).toBe(403);
});

test('navigasi atas dan peta dapat digunakan pada layar ponsel', async ({ page }) => {
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/');
    await expect(page.locator('[data-chart-state]')).toHaveAttribute('data-chart-state', 'ready');
    await expect(page.locator('[data-region-map]')).toHaveCount(0);
    await expect(top(page).getByRole('link', { name: 'Beranda' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Masuk', exact: true })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
    await login(page, 'lokasi-e2e@example.test', 'lokasi-e2e-password');
    await top(page).getByRole('link', { name: 'Peta wilayah', exact: true }).click(); await ready(page);
    for (const name of ['Peta wilayah', 'Pengajuan PLTS', 'Riwayat']) await expect(top(page).getByRole('link', { name, exact: true })).toBeVisible();
    await expect(page.locator('#sidebar')).toHaveCount(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
    await page.screenshot({ path: 'test-results/navigation-mobile-map.png', fullPage: true });
    expect(errors).toEqual([]);
});

import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

test.describe('PBI-01 dengan MySQL', () => {
    test.setTimeout(90000);
    test.skip(process.env.PBI01_MYSQL_TESTS !== '1', 'Opt-in data uji tersendiri pada database TerangDesa.');
    let fixture;
    test.beforeAll(() => {
        fixture = JSON.parse(execFileSync('php', ['tests/Support/wilayah-browser-fixture.php', 'create'], { encoding: 'utf8' }));
    });
    test.afterAll(() => {
        if (fixture) execFileSync('php', ['tests/Support/wilayah-browser-fixture.php', 'cleanup', String(fixture.id), fixture.email]);
    });
    test.beforeEach(async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Email', { exact: true }).fill(fixture.email);
        await page.getByLabel('Kata sandi').fill(fixture.password);
        await page.getByRole('button', { name: 'Masuk', exact: true }).click();
        await expect(page).toHaveURL(/pemerintah\/wilayah/);
        await expect(page.locator('[data-table-wrap]')).toHaveAttribute('aria-busy', 'false');
        await page.getByLabel('Cari wilayah atau alamat').fill(fixture.prefix);
        await expect(page.locator('[data-list-status]')).toHaveText('19 wilayah ditemukan');
    });

    test('tambah, validasi, simpan ulang, edit, batal, duplikasi, dan hapus bertahan setelah reload', async ({ page }) => {
        const name = `${fixture.prefix} Tambahan`;
        const code = `${fixture.prefix}-new`;
        await page.getByRole('button', { name: 'Tambah wilayah', exact: true }).click();
        await page.getByRole('button', { name: 'Simpan data' }).click();
        await expect(page.locator('#error-nama_wilayah')).toHaveText('Nama wilayah wajib diisi.');
        await page.getByLabel('Nama wilayah', { exact: false }).fill(name);
        await page.getByLabel('Kode referensi', { exact: true }).fill(code);
        await page.getByLabel('Tingkat wilayah').fill('desa_kelurahan');
        await page.getByLabel('Alamat lengkap').fill('Alamat khusus uji browser');
        await page.getByLabel('Lintang', { exact: true }).fill('-6,25');
        await page.getByRole('button', { name: 'Simpan data' }).click();
        await expect(page.locator('#error-longitude')).toContainText('berpasangan');
        await page.getByLabel('Bujur', { exact: true }).fill('107,125');
        // Cegah submit ganda saat request berlangsung.
        let release;
        const gate = new Promise(resolve => { release = resolve; });
        await page.route('**/pemerintah/wilayah', async route => {
            if (route.request().method() === 'POST') await gate;
            await route.continue();
        });
        await page.getByRole('button', { name: 'Simpan data' }).click();
        await expect(page.getByRole('button', { name: 'Menyimpan…' })).toBeDisabled();
        release();
        await expect(page.locator('[data-notice]')).toContainText('berhasil ditambahkan');
        await page.unroute('**/pemerintah/wilayah');
        await expect(page.locator('#form-title')).toContainText(name);
        await page.reload();
        await page.getByLabel('Cari wilayah atau alamat').fill(name);
        await expect(page.locator('[data-rows] tr')).toHaveCount(1);
        const row = page.locator('[data-rows] tr');
        await expect(row).toContainText('Belum tersedia');
        await expect(row).toContainText('Data belum tersedia');
        await row.getByRole('button', { name: `Edit ${name}`, exact: true }).click();
        await expect(page.getByLabel('Lintang', { exact: true })).toHaveValue('-6.2500000');
        await expect(row).toHaveClass('is-selected');
        await page.getByLabel('Jumlah KK terdampak').fill('0');
        await page.getByLabel('Kondisi elektrifikasi').selectOption('akses_terbatas');
        await page.getByRole('button', { name: 'Simpan data' }).click();
        await expect(page.locator('[data-notice]')).toContainText('berhasil disimpan');
        await page.reload();
        await expect(page.locator('[data-rows] tr')).toHaveCount(1);
        await expect(page.locator('[data-rows] tr')).toContainText('Akses listrik terbatas');
        await page.getByRole('button', { name: `Edit ${name}`, exact: true }).click();
        await expect(page.getByLabel('Jumlah KK terdampak')).toHaveValue('0');
        await page.getByLabel('Nama wilayah', { exact: false }).fill('Perubahan belum disimpan');
        page.once('dialog', dialog => dialog.dismiss());
        await page.getByRole('button', { name: 'Batal', exact: true }).click();
        await expect(page.getByLabel('Nama wilayah', { exact: false })).toHaveValue('Perubahan belum disimpan');
        page.once('dialog', dialog => dialog.accept());
        await page.getByRole('button', { name: 'Batal', exact: true }).click();
        await expect(page.getByLabel('Nama wilayah', { exact: false })).toHaveValue('');
        await page.getByLabel('Nama wilayah', { exact: false }).fill(name+' duplikat');
        await page.getByLabel('Kode referensi', { exact: true }).fill(code);
        await page.getByLabel('Tingkat wilayah').fill('desa_kelurahan');
        await page.getByLabel('Alamat lengkap').fill('Uji duplikasi');
        await page.getByRole('button', { name: 'Simpan data' }).click();
        await expect(page.locator('#error-kode_referensi')).toHaveText('Kode referensi sudah digunakan oleh wilayah lain.');
        await expect(page.getByLabel('Nama wilayah', { exact: false })).toHaveValue(name+' duplikat');
        page.once('dialog', dialog => dialog.accept());
        await page.getByRole('button', { name: 'Batal', exact: true }).click();
        page.once('dialog', dialog => { expect(dialog.message()).toContain(name); return dialog.accept(); });
        await page.getByRole('button', { name: `Hapus ${name}`, exact: true }).click();
        await expect(page.locator('[data-notice]')).toContainText('berhasil dihapus');
        await page.reload();
        await expect(page.locator('[data-list-status]')).toContainText('Tidak ada wilayah yang sesuai');
    });

    test('pencarian, filter, pagination, kegagalan daftar dan penyimpanan mempertahankan isian', async ({ page }) => {
        await expect(page.locator('[data-rows] tr')).toHaveCount(15);
        await page.getByRole('button', { name: 'Halaman berikutnya' }).click();
        await expect(page.locator('[data-page]')).toHaveText('2 / 2');
        await expect(page.locator('[data-rows] tr')).toHaveCount(4);
        await page.getByLabel('Kondisi listrik', { exact: true }).selectOption('unknown');
        await expect(page.locator('[data-page]')).toHaveText('1 / 2');
        await expect(page.locator('[data-list-status]')).toHaveText('18 wilayah ditemukan');
        await page.getByLabel('Kondisi listrik', { exact: true }).selectOption('sudah_berlistrik');
        await expect(page.locator('[data-rows] tr')).toHaveCount(1);
        await expect(page.locator('[data-list-status]')).toHaveText('1 wilayah ditemukan');
        await page.route('**/pemerintah/wilayah/data?**', route => route.fulfill({ status: 503, contentType: 'application/json', body: '{}' }));
        await page.getByLabel('Kondisi listrik', { exact: true }).selectOption('');
        await expect(page.locator('[data-list-status]')).toContainText('Periksa koneksi database');
        await expect(page.locator('[data-result-count]')).toHaveText('Jumlah hasil belum tersedia');
        await page.unroute('**/pemerintah/wilayah/data?**');
        await page.getByRole('button', { name: 'Coba lagi' }).click();
        await expect(page.locator('[data-list-status]')).toHaveText('19 wilayah ditemukan');
        await page.getByRole('button', { name: 'Edit', exact: false }).first().click();
        await expect(page.locator('#form-title')).toContainText('Edit wilayah');
        await page.getByLabel('Alamat lengkap').fill('Isian yang dipertahankan saat gagal');
        await page.route('**/pemerintah/wilayah/*', route => route.request().method() === 'PUT' ? route.abort() : route.continue());
        await page.getByRole('button', { name: 'Simpan data' }).click();
        await expect(page.locator('[data-form-error]')).toBeVisible();
        await expect(page.getByLabel('Alamat lengkap')).toHaveValue('Isian yang dipertahankan saat gagal');
        page.once('dialog', dialog => dialog.accept());
        await page.getByRole('button', { name: 'Batal', exact: true }).click();
    });

    test('beranda mempertahankan identitas login dan logout yang sebenarnya', async ({ page }) => {
        await page.getByRole('navigation').getByRole('link', { name: 'Beranda', exact: true }).click();
        await expect(page).toHaveURL(/127\.0\.0\.1:8000\/$/);
        await expect(page.locator('[data-chart-state]')).toHaveAttribute('data-chart-state', 'ready');
        await expect(page.locator('.workspace-identity')).toContainText('Penguji browser PBI-01');
        await expect(page.locator('.workspace-identity')).toContainText('Pemerintah');
        await page.getByRole('button', { name: 'Keluar', exact: true }).click();
        await expect(page).toHaveURL(/\/login$/);
        await page.goto('/pemerintah/wilayah');
        await expect(page).toHaveURL(/\/login$/);
    });

    test('jumlah dan sumber online terbaca pada daftar serta formulir edit', async ({ page }) => {
        const expected = (await (await page.request.get('/pemerintah/wilayah/data?q=MALASARI%2C%20NANGGUNG')).json()).data[0];
        await page.getByLabel('Cari wilayah atau alamat').fill('MALASARI, NANGGUNG');
        await expect(page.locator('[data-rows] tr')).toHaveCount(1);
        const row = page.locator('[data-rows] tr');
        await expect(row.locator('td').nth(1)).toHaveText(String(expected.jumlah_kk_terdampak));
        await row.getByRole('button', { name: 'Edit MALASARI', exact: true }).click();
        await expect(page.getByLabel('Jumlah KK terdampak')).toHaveValue('0');
        await expect(page.getByLabel('Kondisi elektrifikasi')).toHaveValue(expected.kondisi_listrik ?? '');
        await page.locator('.supporting-info summary').click();
        await expect(page.getByLabel('Sumber data', { exact: true })).toHaveValue(expected.sumber_data);
        await expect(page.getByLabel('Tanggal pengamatan')).toHaveValue('');
        await page.getByRole('button', { name: 'Batal', exact: true }).click();
        await page.getByLabel('Cari wilayah atau alamat').fill('ANJUN, PLERED');
        await expect(page.locator('[data-rows] tr')).toHaveCount(1);
        await expect(page.locator('[data-rows] tr td').nth(1)).toHaveText('6');
        await page.screenshot({ path: 'test-results/wilayah-online.png', fullPage: true });
        for (const width of [390, 320]) {
            await page.setViewportSize({ width, height: 844 });
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        }
    });

    test('simpan melalui Pemerintah memperbarui daftar dashboard serta daftar dan detail peta', async ({ page }) => {
        const baseline = (await (await page.request.get('/dashboard/data')).json()).metrics;
        const name = `${fixture.prefix} Peta`;
        await page.getByRole('button', { name: 'Tambah wilayah', exact: true }).click();
        await page.getByLabel('Nama wilayah', { exact: false }).fill(name);
        await page.getByLabel('Kode referensi', { exact: true }).fill(`${fixture.prefix}-map`);
        await page.getByLabel('Tingkat wilayah').fill('desa_kelurahan');
        await page.getByLabel('Alamat lengkap').fill('Desa Uji, KECAMATAN UJI, KABUPATEN UJI, JAWA BARAT');
        await page.getByLabel('Lintang', { exact: true }).fill('-6,9');
        await page.getByLabel('Bujur', { exact: true }).fill('107,6');
        await page.getByLabel('Jumlah KK terdampak').fill('23');
        await page.getByLabel('Kondisi elektrifikasi').selectOption('akses_terbatas');
        await page.locator('.supporting-info summary').click();
        await page.getByLabel('Sumber data', { exact: true }).fill('Uji browser TiDB');
        await page.getByLabel('Tanggal pengamatan').fill('2026-11-05');
        await page.getByRole('button', { name: 'Simpan data' }).click();
        await expect(page.locator('[data-notice]')).toContainText('berhasil ditambahkan');
        await page.goto('/');
        await expect(page.locator('[data-chart-state]')).toHaveAttribute('data-chart-state', 'ready');
        await expect(page.locator('[data-metric=unserved_kk]')).toHaveText(new Intl.NumberFormat('id-ID').format((baseline.unserved_kk ?? 0) + 23));
        await expect(page.locator('[data-metric=served_kk]')).toHaveText('Belum tersedia');
        const mapUrl = `/peta-wilayah?q=${encodeURIComponent(name)}`;
        await page.goto(mapUrl);
        await expect(page.locator('[data-map-data-state]')).toHaveAttribute('data-map-data-state', 'ready');
        await expect(page.locator('[data-map-rows]')).toContainText('Ada KK belum berlistrik');
        await page.getByRole('button', { name: 'Lihat ' + name, exact: true }).click();
        await expect(page.locator('#map-detail-title')).toHaveText(name);
        await expect(page.locator('[data-map-detail]')).toContainText('23 keluarga');
        await expect(page.locator('[data-map-detail]')).toContainText('Tahun data2026');
        await expect(page.locator('[data-map-detail]')).toContainText('Uji browser TiDB');
        await page.goto(`/pemerintah/wilayah?q=${encodeURIComponent(name)}`);
        await page.getByRole('button', { name: `Edit ${name}`, exact: true }).click();
        await expect(page.getByLabel('Jumlah KK terdampak')).toHaveValue('23');
        await page.getByLabel('Jumlah KK terdampak').fill('0');
        await page.getByLabel('Kondisi elektrifikasi').selectOption('');
        await page.getByRole('button', { name: 'Simpan data' }).click();
        await expect(page.locator('[data-notice]')).toContainText('berhasil disimpan');
        await expect(page.locator('[data-rows] tr td').nth(1)).toHaveText('0');
        await page.goto('/');
        await expect(page.locator('[data-chart-state]')).toHaveAttribute('data-chart-state', 'ready');
        await expect(page.locator('[data-metric=unserved_kk]')).toHaveText(new Intl.NumberFormat('id-ID').format(baseline.unserved_kk));
        await page.goto(mapUrl);
        await expect(page.locator('[data-map-data-state]')).toHaveAttribute('data-map-data-state', 'ready');
        await expect(page.locator('[data-map-rows]')).toContainText('Tidak tercatat KK belum berlistrik');
        await page.getByRole('button', { name: 'Lihat ' + name, exact: true }).click();
        await expect(page.locator('[data-map-detail]')).toContainText('0 keluarga');
        await page.screenshot({ path: 'test-results/tidb-map-update.png', fullPage: true });
        await page.goto(`/pemerintah/wilayah?q=${encodeURIComponent(name)}`);
        page.once('dialog', dialog => dialog.accept());
        await page.getByRole('button', { name: `Hapus ${name}`, exact: true }).click();
        await expect(page.locator('[data-notice]')).toContainText('berhasil dihapus');
    });

    test('desktop dan seluler, drawer keyboard, tanpa overflow dan tanpa animasi dekoratif', async ({ page }) => {
        await expect(page.locator('.ambient')).toHaveCount(0);
        const list = await page.locator('.list-panel').boundingBox();
        const form = await page.locator('.form-panel').boundingBox();
        expect(list.x).toBeLessThan(form.x);
        expect(list.width).toBeGreaterThan(form.width);
        await page.screenshot({ path: 'test-results/wilayah-desktop.png', fullPage: true });
        for (const width of [390, 320]) {
            await page.setViewportSize({ width, height: 844 });
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
            const listMobile = await page.locator('.list-panel').boundingBox();
            const formMobile = await page.locator('.form-panel').boundingBox();
            expect(formMobile.y).toBeGreaterThan(listMobile.y);
            await page.getByRole('button', { name: '☰ Menu', exact: true }).click();
            await expect(page.locator('[data-drawer-open]')).toHaveAttribute('aria-expanded', 'true');
            await expect(page.getByRole('navigation').getByRole('link', { name: 'Data wilayah' })).toBeVisible();
            await page.keyboard.press('Escape');
            await expect(page.locator('[data-drawer-open]')).toHaveAttribute('aria-expanded', 'false');
            await expect(page.locator('[data-drawer-open]')).toBeFocused();
        }
        await page.setViewportSize({ width: 390, height: 844 });
        await page.screenshot({ path: 'test-results/wilayah-mobile.png', fullPage: true });
    });
});

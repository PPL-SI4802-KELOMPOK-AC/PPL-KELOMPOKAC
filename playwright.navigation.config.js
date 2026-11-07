import { defineConfig } from '@playwright/test';
import { randomUUID } from 'node:crypto';
import { resolve } from 'node:path';

process.env.PBI06_SQLITE_TESTS = '1';
process.env.PBI06_TEST_DATABASE ??= resolve('storage/framework/testing', `pbi06-e2e-${randomUUID()}.sqlite`);

export default defineConfig({
    testDir: './tests/Browser', testMatch: 'navigation.spec.js', workers: 1, timeout: 60000,
    expect: { timeout: 15000 },
    use: { baseURL: 'http://127.0.0.1:8017', channel: 'chrome', viewport: { width: 1440, height: 1000 }, trace: 'retain-on-failure' },
    webServer: {
        command: 'php tests/Support/navigation-browser-fixture.php && php artisan serve --host=127.0.0.1 --port=8017',
        url: 'http://127.0.0.1:8017/up', reuseExistingServer: false,
        env: {
            APP_ENV: 'testing', APP_KEY: `base64:${Buffer.from('n'.repeat(32)).toString('base64')}`,
            APP_URL: 'http://127.0.0.1:8017', DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.PBI06_TEST_DATABASE,
            SESSION_DRIVER: 'file', CACHE_STORE: 'array', QUEUE_CONNECTION: 'sync',
            PBI06_SQLITE_TESTS: '1', PBI06_TEST_DATABASE: process.env.PBI06_TEST_DATABASE,
        },
    },
});

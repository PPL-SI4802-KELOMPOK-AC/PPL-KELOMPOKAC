import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    workers: 1,
    timeout: 60000,
    expect: { timeout: 15000 },
    use: {
        baseURL: 'http://127.0.0.1:8000',
        channel: 'chrome',
        viewport: { width: 1440, height: 1000 },
        trace: 'retain-on-failure',
    },
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8000',
        url: 'http://127.0.0.1:8000',
        reuseExistingServer: !process.env.CI,
        env: { SESSION_DRIVER: 'file', CACHE_STORE: 'file' },
    },
});

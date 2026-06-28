const { defineConfig } = require('playwright/test');

module.exports = defineConfig({
    testDir: './tests/browser',
    outputDir: './tests/browser-results',
    reporter: 'list',
    use: {
        baseURL: 'http://127.0.0.1:8011',
        channel: 'chrome',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [
        {
            name: 'desktop',
            use: { viewport: { width: 1440, height: 900 } },
        },
        {
            name: 'mobile',
            use: { viewport: { width: 390, height: 844 } },
        },
    ],
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8011',
        url: 'http://127.0.0.1:8011/login',
        reuseExistingServer: false,
        timeout: 120000,
    },
});

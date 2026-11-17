<?php

namespace Tests\Browser\PBI05;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SCRUM-10 / PBI 5: pencarian dan pemilihan wilayah pada peta.
 * Memakai data aplikasi yang sudah ada, tanpa migrasi/seed/perubahan data TiDB.
 * Dataset harus memuat nama atau alamat yang mengandung Cingambul.
 * Peta dan pencarian bersifat publik, termasuk ketika pengguna belum login.
 * Jalankan: php artisan dusk tests/Browser/PBI05
 */
abstract class PBI05BrowserTestCase extends DuskTestCase
{
    protected const KEYWORD = 'Cingambul';

    protected const TIMEOUT = 45;

    protected function openMap(Browser $browser, string $query = ''): Browser
    {
        $browser->visit('/peta-wilayah?'.http_build_query(['q' => $query]));
        $this->waitForResults($browser, $query);

        return $browser;
    }

    protected function waitForResults(Browser $browser, string $query): void
    {
        $browser->waitUsing(self::TIMEOUT, 200, function () use ($browser, $query) {
            $ready = $browser->attribute('[data-map-data-state]', 'data-map-data-state') === 'ready';
            $parameters = [];
            parse_str((string) parse_url($browser->driver->getCurrentURL(), PHP_URL_QUERY), $parameters);

            return $ready && ($parameters['q'] ?? '') === $query
                && $browser->attribute('[data-map-rows]', 'aria-busy') === 'false';
        }, 'Data pencarian belum siap setelah %s detik; periksa server, database, dan aset frontend.');
    }

    protected function resultTotal(Browser $browser): int
    {
        return (int) preg_replace('/[^0-9]/', '', $browser->text('[data-map-stat="total"]'));
    }

    protected function resultIds(Browser $browser): array
    {
        return array_map(fn ($row) => $row->getAttribute('data-id'), $browser->elements('[data-map-rows] tr'));
    }

    protected function assertMatchingRows(Browser $browser, string $keyword): void
    {
        $rows = $browser->elements('[data-map-rows] tr');
        $this->assertNotEmpty($rows, 'Prasyarat dataset: sediakan wilayah dengan nama/alamat yang mengandung '.$keyword.'.');
        foreach ($rows as $row) {
            $text = $row->findElement(\Facebook\WebDriver\WebDriverBy::cssSelector('td:first-child'))->getText();
            $this->assertNotFalse(mb_stripos($text, $keyword), 'Setiap nama/alamat hasil harus sesuai kata kunci.');
        }
        $browser->assertMissing('[data-map-empty]');
    }

}

<?php

namespace Tests\Browser\PBI03;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\Browser\MapDuskTestCase;

class PBI03TC0301Test extends MapDuskTestCase
{
    /** TC-03-01 | Normal: lima wilayah berkoordinat valid tersedia pada peta. */
    public function test_tc_03_01_lima_lokasi_valid_ditampilkan(): void
    {
        $fixtures = [$this->fixture];
        foreach (['3201', '3202', '3203', '3204'] as $district) {
            $fixtures[] = $this->addSampleRegion($district, 10);
        }

        $this->browse(function (Browser $browser) use ($fixtures) {
            $this->openMap($browser);
            $data = $this->mapData($browser);
            $this->assertSame(5, $data['summary']['total']);
            $this->assertSame(5, $data['summary']['located']);
            $this->assertSame(0, $data['summary']['unlocated']);
            $this->assertSame(5, $this->representedLocations($data['features']));
            foreach ($fixtures as $fixture) {
                $record = collect($data['list']['data'])->firstWhere('id', $fixture['id']);
                $this->assertNotNull($record);
                $this->assertEquals($fixture['coordinates'], $record['location']['coordinates']);
                $this->assertCount(2, $record['location']['coordinates']);
            }
            // UI saat ini menggambar poligon administrasi; field features menyediakan titik API.
            $browser->assertCount('.district-area', 27)
                ->assertSeeIn('[data-map-data-status]', '5 wilayah sesuai filter')
                ->assertSeeIn('[data-map-data-status]', '5 lokasi tersedia')
                ->assertCount('[data-map-rows] tr', 5)
                ->assertAttribute('[data-map-panel]', 'data-state', 'ready')
                ->assertPresent('.province-mask');
        });
    }
}

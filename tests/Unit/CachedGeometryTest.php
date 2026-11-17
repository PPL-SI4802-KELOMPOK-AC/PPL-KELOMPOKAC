<?php

namespace Tests\Unit;

use App\Services\Map\CachedGeometry;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\TestCase;

class CachedGeometryTest extends TestCase
{
    public function test_reuses_true_and_false_results_and_rechecks_changed_points_and_boundaries(): void
    {
        $store = new Repository(new ArrayStore);
        $geometry = ['type' => 'Polygon', 'coordinates' => [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]]];
        $cold = new CachedGeometry($store, $geometry);
        $this->assertTrue($cold->contains([1.0, 1.0]));
        $this->assertFalse($cold->contains([15.0, 15.0]));
        $this->assertSame(2, $cold->misses);
        $cold->save();

        $warm = new CachedGeometry($store, $geometry);
        $this->assertTrue($warm->contains([1.0, 1.0]));
        $this->assertFalse($warm->contains([15.0, 15.0]));
        $this->assertSame(2, $warm->hits);
        $this->assertTrue($warm->contains([2.0, 2.0]));
        $this->assertSame(1, $warm->misses);

        $geometry['coordinates'][] = [[0.5, 0.5], [3, 0.5], [3, 3], [0.5, 3], [0.5, 0.5]];
        $changed = new CachedGeometry($store, $geometry);
        $this->assertFalse($changed->contains([1.0, 1.0]));
        $this->assertSame(1, $changed->misses);
    }
}
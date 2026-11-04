<?php

namespace Tests\Unit;

use App\Services\Map\Geometry;
use PHPUnit\Framework\TestCase;

class MapGeometryTest extends TestCase
{
    public function test_interior_point_avoids_holes_and_does_not_use_an_outside_centroid(): void
    {
        $donut = ['type' => 'Polygon', 'coordinates' => [
            [[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]],
            [[3, 3], [7, 3], [7, 7], [3, 7], [3, 3]],
        ]];
        $this->assertFalse(Geometry::contains($donut, [5, 5]));
        $point = Geometry::interiorPoint($donut);
        $this->assertNotNull($point);
        $this->assertTrue(Geometry::contains($donut, $point));
        $concave = ['type' => 'Polygon', 'coordinates' => [[[0, 0], [6, 0], [6, 1], [1, 1], [1, 6], [0, 6], [0, 0]]]];
        $this->assertFalse(Geometry::contains($concave, [3, 3]));
        $this->assertTrue(Geometry::contains($concave, Geometry::interiorPoint($concave)));
    }

    public function test_multipolygon_uses_a_real_island_and_degenerate_geometry_has_no_location(): void
    {
        $geometry = ['type' => 'MultiPolygon', 'coordinates' => [
            [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]],
            [[[10, 10], [15, 10], [15, 15], [10, 15], [10, 10]]],
        ]];
        $point = Geometry::interiorPoint($geometry);
        $this->assertTrue(Geometry::contains($geometry, $point));
        $this->assertGreaterThan(10, $point[0]);
        $this->assertNull(Geometry::interiorPoint(['type' => 'Polygon', 'coordinates' => [[[1, 1], [1, 1], [1, 1]]]]));
    }
}

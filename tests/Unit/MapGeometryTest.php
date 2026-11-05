<?php

namespace Tests\Unit;

use App\Services\Map\Geometry;
use PHPUnit\Framework\TestCase;

class MapGeometryTest extends TestCase
{
    public function test_contains_respects_holes_and_concave_boundaries(): void
    {
        $donut = ['type' => 'Polygon', 'coordinates' => [
            [[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]],
            [[3, 3], [7, 3], [7, 7], [3, 7], [3, 3]],
        ]];
        $this->assertFalse(Geometry::contains($donut, [5, 5]));
        $this->assertTrue(Geometry::contains($donut, [1, 1]));
        $concave = ['type' => 'Polygon', 'coordinates' => [[[0, 0], [6, 0], [6, 1], [1, 1], [1, 6], [0, 6], [0, 0]]]];
        $this->assertFalse(Geometry::contains($concave, [3, 3]));
        $this->assertTrue(Geometry::contains($concave, [.5, 5]));
    }

    public function test_multipolygon_contains_only_points_on_its_islands(): void
    {
        $geometry = ['type' => 'MultiPolygon', 'coordinates' => [
            [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]],
            [[[10, 10], [15, 10], [15, 15], [10, 15], [10, 10]]],
        ]];
        $this->assertTrue(Geometry::contains($geometry, [.5, .5]));
        $this->assertTrue(Geometry::contains($geometry, [12, 12]));
        $this->assertFalse(Geometry::contains($geometry, [5, 5]));
        $this->assertFalse(Geometry::contains(['type' => 'Polygon', 'coordinates' => []], [0, 0]));
    }
}

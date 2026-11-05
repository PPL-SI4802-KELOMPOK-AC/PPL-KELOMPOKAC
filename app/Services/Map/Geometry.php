<?php

namespace App\Services\Map;

class Geometry
{
    public static function contains(array $geometry, array $point): bool
    {
        $polygons = ($geometry['type'] ?? '') === 'Polygon' ? [$geometry['coordinates']] : ($geometry['coordinates'] ?? []);
        foreach ($polygons as $polygon) {
            if (! self::inRing($polygon[0] ?? [], $point)) {
                continue;
            }
            foreach (array_slice($polygon, 1) as $hole) {
                if (self::inRing($hole, $point)) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    private static function inRing(array $ring, array $point): bool
    {
        [$x, $y] = $point;
        $inside = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}

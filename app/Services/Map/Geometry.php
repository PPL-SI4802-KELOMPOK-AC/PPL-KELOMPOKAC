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

    /** Titik di dalam poligon, bukan centroid yang mungkin jatuh di luar/di lubang. */
    public static function interiorPoint(array $geometry): ?array
    {
        $polygons = ($geometry['type'] ?? '') === 'Polygon' ? [$geometry['coordinates']] : ($geometry['coordinates'] ?? []);
        usort($polygons, fn ($a, $b) => abs(self::area($b[0])) <=> abs(self::area($a[0])));
        foreach ($polygons as $polygon) {
            $ring = $polygon[0];
            if (count($ring) < 4) {
                continue;
            }
            $ys = array_column($ring, 1);
            $scanlines = [(min($ys) + max($ys)) / 2];
            sort($ys);
            // Scanline pertengahan tiap rentang vertex menghindari tepi/vertex.
            for ($i = 1; $i < count($ys); $i++) {
                if ($ys[$i] > $ys[$i - 1]) {
                    $scanlines[] = ($ys[$i] + $ys[$i - 1]) / 2;
                }
            }
            foreach ($scanlines as $y) {
                $crossings = [];
                foreach ($polygon as $part) {
                    for ($i = 0, $j = count($part) - 1; $i < count($part); $j = $i++) {
                        [$xi, $yi] = $part[$i];
                        [$xj, $yj] = $part[$j];
                        if (($yi > $y) !== ($yj > $y)) {
                            $crossings[] = $xi + ($y - $yi) * ($xj - $xi) / ($yj - $yi);
                        }
                    }
                }
                sort($crossings);
                $best = null;
                $width = 0;
                for ($i = 1; $i < count($crossings); $i++) {
                    $point = [($crossings[$i] + $crossings[$i - 1]) / 2, $y];
                    if ($crossings[$i] - $crossings[$i - 1] > $width && self::contains(['type' => 'Polygon', 'coordinates' => $polygon], $point)) {
                        $best = $point;
                        $width = $crossings[$i] - $crossings[$i - 1];
                    }
                }
                if ($best) {
                    return $best;
                }
            }
        }

        return null;
    }

    private static function area(array $ring): float
    {
        $area = 0;
        for ($i = 1; $i < count($ring); $i++) {
            $area += $ring[$i - 1][0] * $ring[$i][1] - $ring[$i][0] * $ring[$i - 1][1];
        }

        return $area / 2;
    }
}

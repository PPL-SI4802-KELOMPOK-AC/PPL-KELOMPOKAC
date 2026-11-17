<?php

namespace App\Http\Controllers;

use App\Services\Map\MapData;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegionMapController extends Controller
{
    public function data(Request $request, MapData $map)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'condition' => ['nullable', Rule::in(['all', 'has_unserved', 'no_unserved', 'unavailable'])],
            'location' => ['nullable', Rule::in(['all', 'available', 'missing'])],
            'kabupaten' => ['nullable', 'regex:/^32\d{2}$/'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'zoom' => ['nullable', 'integer', 'min:5', 'max:19'],
            'bbox' => ['nullable', 'string', 'max:120'],
        ]);
        $filters['q'] = trim($filters['q'] ?? '');
        if (! empty($filters['bbox'])) {
            $parts = explode(',', $filters['bbox']);
            if (count($parts) !== 4 || count(array_filter($parts, 'is_numeric')) !== 4) {
                throw ValidationException::withMessages(['bbox' => 'Batas viewport peta tidak valid.']);
            }
            $parts = array_map('floatval', $parts);
            if ($parts[0] < -180 || $parts[2] > 180 || $parts[1] < -90 || $parts[3] > 90 || $parts[0] >= $parts[2] || $parts[1] >= $parts[3]) {
                throw ValidationException::withMessages(['bbox' => 'Batas viewport peta tidak valid.']);
            }
            $filters['bbox'] = $parts;
        }
        try {
            // Temporary local diagnostics; do not log queries or their bindings.
            $measure = app()->environment('local');
            $queryMs = 0;
            $queryCount = 0;

            if ($measure) {
                \Illuminate\Support\Facades\DB::listen(
                    function ($query) use (&$queryMs, &$queryCount) {
                        $queryMs += $query->time;
                        $queryCount++;
                    }
                );
            }

            $start = microtime(true);
            $data = $map->response($filters);

            if ($measure) {
                logger()->debug('Pengukuran data peta', [
                    'total_ms' => round((microtime(true) - $start) * 1000, 2),
                    'query_ms' => round($queryMs, 2),
                    'query_count' => $queryCount,
                ]);
            }

            return response()->json($data)->header('Cache-Control', 'no-store');
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Layanan data sedang tidak tersedia. Silakan coba lagi beberapa saat.'], 503)->header('Cache-Control', 'no-store');
        }
    }
}

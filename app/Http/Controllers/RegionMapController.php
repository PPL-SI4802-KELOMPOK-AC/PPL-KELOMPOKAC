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
            'layer' => ['nullable', Rule::in(['all', 'verified', 'csv_positive', 'unknown'])],
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
            return response()->json($map->response($filters))->header('Cache-Control', 'no-store');
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Data wilayah belum dapat dimuat. Periksa koneksi MySQL dan cache dataset/geografi, lalu coba lagi.'], 503);
        }
    }
}

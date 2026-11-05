<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PrepareMapGeography extends Command
{
    protected $signature = 'map:geography';

    protected $description = 'Perbarui aset batas provinsi Jawa Barat dari geoBoundaries';

    private function fetch(string $url): array
    {
        $data = Http::connectTimeout(15)->timeout(60)->retry(2, 1000)->get($url)->throw()->json();
        if (! is_array($data)) {
            throw new RuntimeException('Layanan batas provinsi mengembalikan respons tidak valid.');
        }

        return $data;
    }

    public function handle(): int
    {
        try {
            $metadata = $this->fetch(config('map.boundary_metadata'));
            $collection = $this->fetch($metadata['simplifiedGeometryGeoJSON']);
            $provinces = array_values(array_filter($collection['features'] ?? [], function ($feature) {
                return in_array($feature['properties']['shapeName'] ?? '', ['West Java', 'Jawa Barat'], true);
            }));
            if (count($provinces) !== 1 || ! in_array($provinces[0]['geometry']['type'] ?? '', ['Polygon', 'MultiPolygon'], true)) {
                throw new RuntimeException('Batas Jawa Barat tidak ditemukan secara unik atau geometrinya tidak valid.');
            }
            $boundary = $provinces[0];
            $boundary['properties'] = [...$boundary['properties'],
                'source' => 'geoBoundaries / OpenStreetMap / Wambacher',
                'source_url' => $metadata['simplifiedGeometryGeoJSON'],
                'license' => $metadata['boundaryLicense'], 'source_year' => $metadata['boundaryYearRepresented'],
                'boundary_id' => $metadata['boundaryID'], 'retrieved_at' => now()->toIso8601String(),
            ];
            File::replace(config('map.boundary_path'), json_encode($boundary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $this->info('Aset batas Jawa Barat diperbarui.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Pembaruan batas provinsi gagal. Aset sebelumnya tetap digunakan.');

            return self::FAILURE;
        }
    }
}

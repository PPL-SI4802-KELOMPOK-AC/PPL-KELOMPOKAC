<?php

namespace App\Console\Commands;

use App\Services\Map\CsvDataset;
use App\Services\Map\Geometry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class PrepareMapGeography extends Command
{
    protected $signature = 'map:geography {--refresh : Unduh ulang batch sumber, tanpa memakai snapshot lokal}';

    protected $description = 'Ambil batas referensi Jawa Barat dan titik dalam poligon desa BIG; tidak menulis database';

    private function fetch(string $url, array $query = []): array
    {
        $data = Http::connectTimeout(15)->timeout(90)->retry(2, 1000)->get($url, $query)->throw()->json();
        if (! is_array($data) || isset($data['error'])) {
            throw new \RuntimeException('Layanan geografis mengembalikan respons tidak valid.');
        }

        return $data;
    }

    public function handle(): int
    {
        try {
            $meta = $this->fetch(config('map.boundary_metadata'));
            // Snapshot berlisensi yang disediakan geoBoundaries, bukan koordinat buatan.
            $all = $this->fetch($meta['simplifiedGeometryGeoJSON']);
            $province = array_values(array_filter($all['features'] ?? [], fn ($f) => in_array($f['properties']['shapeName'] ?? '', ['West Java', 'Jawa Barat'], true)));
            if (count($province) !== 1) {
                throw new \RuntimeException('Batas Jawa Barat tidak ditemukan secara unik.');
            }
            $boundary = $province[0];
            $boundary['properties'] = [...$boundary['properties'],
                'source' => 'geoBoundaries / OpenStreetMap / Wambacher', 'source_url' => $meta['simplifiedGeometryGeoJSON'],
                'license' => $meta['boundaryLicense'], 'source_year' => $meta['boundaryYearRepresented'],
                'boundary_id' => $meta['boundaryID'], 'retrieved_at' => now()->toIso8601String(),
            ];
            $geometry = $boundary['geometry'];
            $service = config('map.village_service');
            $serviceMeta = $this->fetch(dirname($service), ['f' => 'json']);
            $layerMeta = $this->fetch($service, ['f' => 'json']);
            $ids = $this->fetch($service.'/query', ['where' => "KDPPUM='32'", 'returnIdsOnly' => 'true', 'f' => 'json']);
            if (empty($ids['objectIds'])) {
                throw new \RuntimeException('Layanan tidak menyediakan ID desa Jawa Barat.');
            }
            $this->info(count($ids['objectIds']).' poligon desa pada layanan BIG; pengambilan per batch 400, bukan geocoding.');
            $points = [];
            $duplicates = [];
            $outside = 0;
            $unusable = 0;
            foreach (array_chunk($ids['objectIds'], 400) as $index => $batch) {
                $rawPath = storage_path('app/map/sources/big-'.hash('sha256', implode(',', $batch)).'.json');
                // Snapshot lokal membuat perintah dapat dilanjutkan tanpa unduh ulang batch.
                $useSnapshot = is_file($rawPath) && ! $this->option('refresh');
                $data = $useSnapshot ? json_decode(file_get_contents($rawPath), true, 512, JSON_THROW_ON_ERROR)
                    : $this->fetch($service.'/query', [
                        'objectIds' => implode(',', $batch), 'outFields' => 'FID,KDEPUM,NAMOBJ,WADMKK',
                        'returnGeometry' => 'true', 'outSR' => 4326, 'returnZ' => 'false', 'returnM' => 'false',
                        'maxAllowableOffset' => 0.0001, 'geometryPrecision' => 7, 'f' => 'geojson',
                    ]);
                if (count($data['features'] ?? []) !== count($batch) || ! empty($data['exceededTransferLimit'])) {
                    throw new \RuntimeException('Batch poligon tidak lengkap; artefak aktif tidak diganti.');
                }
                if (! $useSnapshot) {
                    CsvDataset::writeJson($rawPath, $data);
                }
                foreach ($data['features'] as $feature) {
                    $code = trim($feature['properties']['KDEPUM'] ?? '');
                    if (! preg_match('/^32\.\d{2}\.\d{2}\.\d{4}$/', $code)) {
                        $unusable++;
                        continue;
                    }
                    if (isset($points[$code])) {
                        $duplicates[$code] = true;
                    }
                    $point = Geometry::interiorPoint($feature['geometry'] ?? []);
                    if (! $point || ! Geometry::contains($geometry, $point)) {
                        $outside++;
                        continue;
                    }
                    $points[$code] = [
                        'coordinates' => array_map(fn ($v) => round($v, 7), $point),
                        'name' => $feature['properties']['NAMOBJ'], 'feature_id' => $feature['properties']['FID'],
                    ];
                }
                $this->line('Batch '.($index + 1).' selesai.');
            }
            foreach (array_keys($duplicates) as $code) {
                unset($points[$code]);
            }
            $report = ['source_features' => count($ids['objectIds']), 'usable_points' => count($points),
                'duplicate_codes' => count($duplicates), 'invalid_codes' => $unusable, 'outside_province_or_invalid_geometry' => $outside];
            CsvDataset::writeJson(config('map.locations_path'), [
                'version' => 1, 'source' => 'Badan Informasi Geospasial — Batas Desa 1:10.000 (Oktober 2020)',
                'source_url' => $service, 'copyright' => $serviceMeta['copyrightText'] ?? 'Badan Informasi Geospasial',
                'usage' => 'Layanan publik Map,Query,Data; kredit BIG. Referensi kartografis, bukan penetapan batas hukum.',
                'retrieved_at' => now()->toIso8601String(), 'boundary_id' => $meta['boundaryID'],
                'report' => $report, 'points' => $points,
            ]);
            CsvDataset::writeJson(config('map.boundary_path'), $boundary);
            CsvDataset::writeJson(storage_path('app/map/sources/big-service-metadata.json'), $serviceMeta);
            CsvDataset::writeJson(storage_path('app/map/sources/big-layer-metadata.json'), $layerMeta);
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
            $this->info('Artefak geografis diperbarui; koordinat database tidak ditimpa.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Persiapan geografis gagal: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}

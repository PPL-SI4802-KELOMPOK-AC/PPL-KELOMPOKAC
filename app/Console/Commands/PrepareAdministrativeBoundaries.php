<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PrepareAdministrativeBoundaries extends Command
{
    protected $signature = 'map:administrative-boundaries';

    protected $description = 'Ambil poligon kabupaten dan desa Jawa Barat dari BIG; tanpa mengubah database';

    private function fetch(string $url, array $query): array
    {
        $data = Http::connectTimeout(15)->timeout(90)->retry(2, 1000)->get($url, $query)->throw()->json();
        if (! is_array($data) || isset($data['error'])) throw new RuntimeException('Respons BIG tidak lengkap.');

        return $data;
    }

    public function handle(): int
    {
        try {
            $base = 'https://geoservices.big.go.id/rbi/rest/services/BATASWILAYAH/';
            $districtUrl = $base.'Administrasi_AR_KabKota_50K/MapServer/0/query';
            $villageUrl = $base.'Administrasi_AR_KelDesa_10K/MapServer/0/query';
            $query = ['f' => 'geojson', 'returnGeometry' => 'true', 'outSR' => 4326,
                'returnZ' => 'false', 'returnM' => 'false', 'geometryPrecision' => 6, 'maxAllowableOffset' => .0003];
            $districts = $this->fetch($districtUrl, [...$query, 'where' => "KDPPUM='32'", 'outFields' => 'KDPKAB,WADMKK']);
            if (count($districts['features'] ?? []) !== 27) throw new RuntimeException('Harus tersedia 27 kabupaten/kota.');
            $features = [];
            foreach ($districts['features'] as $feature) {
                $code = str_replace('.', '', trim($feature['properties']['KDPKAB']));
                if (! preg_match('/^32\d{2}$/', $code) || isset($features[$code])) throw new RuntimeException('Kode kabupaten tidak unik.');
                $features[$code] = ['type' => 'Feature', 'properties' => ['code' => $code, 'name' => $feature['properties']['WADMKK']], 'geometry' => $feature['geometry']];
            }
            $ids = $this->fetch($villageUrl, ['where' => "KDPPUM='32'", 'returnIdsOnly' => 'true', 'f' => 'json'])['objectIds'] ?? [];
            if (count($ids) < 5000) throw new RuntimeException('Daftar desa tidak lengkap.');
            $villages = $index = [];
            foreach (array_chunk($ids, 400) as $i => $batch) {
                $data = $this->fetch($villageUrl, [...$query, 'objectIds' => implode(',', $batch), 'outFields' => 'KDEPUM,NAMOBJ,WADMKC,WADMKK']);
                if (count($data['features'] ?? []) !== count($batch) || ! empty($data['exceededTransferLimit'])) throw new RuntimeException('Batch desa tidak lengkap.');
                foreach ($data['features'] as $feature) {
                    $p = $feature['properties'];
                    $code = trim($p['KDEPUM'] ?? '');
                    if (! preg_match('/^32\.\d{2}\.\d{2}\.\d{4}$/', $code) || isset($index[$code])) throw new RuntimeException('Kode desa tidak unik/valid.');
                    $district = str_replace('.', '', substr($code, 0, 5));
                    if (! isset($features[$district]) || ! in_array($feature['geometry']['type'] ?? '', ['Polygon', 'MultiPolygon'])) throw new RuntimeException('Geometri desa tidak valid.');
                    $index[$code] = ['code' => $code, 'name' => $p['NAMOBJ'], 'subdistrict' => $p['WADMKC'], 'district' => $p['WADMKK']];
                    $villages[$district][] = ['type' => 'Feature', 'properties' => $index[$code], 'geometry' => $feature['geometry']];
                }
                $this->line('Batch '.($i + 1).' selesai.');
            }
            $directory = public_path('data/map-boundaries');
            File::ensureDirectoryExists($directory);
            $metadata = ['source' => 'Badan Informasi Geospasial', 'district_source' => dirname($districtUrl),
                'village_source' => dirname($villageUrl), 'retrieved_at' => now()->toIso8601String(),
                'note' => 'Referensi kartografis yang disederhanakan; bukan penetapan batas hukum. Tidak berisi indikator listrik.'];
            $write = fn ($name, $data) => File::replace($directory.'/'.$name, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            foreach ($villages as $code => $items) $write('villages-'.$code.'.geojson', ['type' => 'FeatureCollection', 'metadata' => $metadata, 'features' => $items]);
            $write('districts.geojson', ['type' => 'FeatureCollection', 'metadata' => $metadata, 'features' => array_values($features)]);
            $write('index.json', ['metadata' => $metadata, 'villages' => $index]);
            $this->info(count($features).' kabupaten/kota dan '.count($index).' poligon desa tersedia.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            report($e);
            $this->error('Pengambilan batas administratif gagal. Periksa log aplikasi.');

            return self::FAILURE;
        }
    }
}

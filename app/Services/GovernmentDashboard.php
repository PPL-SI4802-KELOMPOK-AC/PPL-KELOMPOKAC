<?php

namespace App\Services;

use App\Services\Map\CachedGeometry;
use App\Services\Map\Geometry;
use App\Services\Map\MapData;
use App\Services\Pengajuan\LocationCatalog;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Read-only dashboard. No indicator snapshots: each request reads the current database. */
class GovernmentDashboard
{
    public const STATUSES = [
        'diajukan' => 'Diajukan', 'diperiksa' => 'Diperiksa', 'diverifikasi' => 'Diverifikasi',
        'diproses' => 'Diproses', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak',
    ];

    public function __construct(private LocationCatalog $catalog) {}

    private function firstSubmissions(): Builder
    {
        return DB::table('riwayat_status_pengajuan')->select('pengajuan_id')
            ->selectRaw('MIN(created_at) AS submitted_at')
            ->where('jenis_tindakan', 'pengajuan_baru')->where('status_baru', 'diajukan')
            ->groupBy('pengajuan_id');
    }

    private function submissions(?int $year): Builder
    {
        $query = DB::table('pengajuan_plts as p')
            ->leftJoinSub($this->firstSubmissions(), 'first_submission', 'first_submission.pengajuan_id', '=', 'p.id')
            ->where(fn ($q) => $q->where('p.status_terkini', '!=', 'draft')->orWhereNull('p.status_terkini'));
        if ($year !== null) {
            $query->where('submitted_at', '>=', "$year-01-01 00:00:00")
                ->where('submitted_at', '<', ($year + 1).'-01-01 00:00:00');
        }

        return $query;
    }

    private function masterRegions(?array $regionCodes, ?string $condition): Builder
    {
        $regions = DB::table('wilayah as w');
        if ($regionCodes !== null) {
            $regions->whereIn('w.kode_referensi', $regionCodes);
        }
        if ($condition === 'unknown') {
            $regions->whereNull('w.kondisi_listrik');
        } elseif ($condition !== null) {
            $regions->where('w.kondisi_listrik', $condition);
        }

        return $regions;
    }

    private function indicatorRegions(?int $year, ?array $regionCodes, ?string $condition): Builder
    {
        $regions = $this->masterRegions($regionCodes, $condition);
        if ($year !== null) {
            $cohort = $this->submissions($year)->select('p.wilayah_id')->whereNotNull('p.wilayah_id');
            $regions->where(function ($q) use ($cohort, $year) {
                $q->whereIn('w.id', $cohort)->orWhereExists(function ($progress) use ($year) {
                    $progress->selectRaw('1')->from('progres_elektrifikasi as e')
                        ->whereColumn('e.wilayah_id', 'w.id')->where('e.tanggal_progres', '>=', "$year-01-01")
                        ->where('e.tanggal_progres', '<', ($year + 1).'-01-01');
                });
            });
        }

        return $regions;
    }

    private function hasElectricityNeed(?int $kk, ?string $condition): bool
    {
        return ($kk !== null && $kk > 0) || in_array($condition,
            ['belum_berlistrik', 'akses_terbatas', 'ada_kk_belum_berlistrik'], true);
    }

    private function polygonAreas(?array $regionCodes, ?string $condition): array
    {
        $matching = $regionCodes !== null || $condition !== null
            ? array_fill_keys($this->masterRegions($regionCodes, $condition)->pluck('w.id')->all(), true)
            : null;
        $areas = $eligibleCodesById = $catalogByDistrict = [];
        foreach ($this->masterRegions(null, null)->where('w.kode_referensi', 'like', '32.%')
            ->select(['w.id', 'w.kode_referensi', 'w.jumlah_kk_terdampak', 'w.kondisi_listrik'])->orderBy('w.id')->cursor() as $region) {
            $code = $region->kode_referensi;
            $district = substr($code, 0, 2).substr($code, 3, 2);
            $catalogByDistrict[$district] ??= array_fill_keys(array_column($this->catalog->villages($district), 'code'), true);
            if (! isset($catalogByDistrict[$district][$code])) {
                continue;
            }
            if ($matching !== null && ! isset($matching[$region->id])) {
                $areas[$code] ??= 'outside';

                continue;
            }
            $eligibleCodesById[$region->id] = $code;
            if (isset($areas[$code]) && $areas[$code] !== 'outside') {
                continue;
            }
            $kk = $region->jumlah_kk_terdampak === null ? null : (int) $region->jumlah_kk_terdampak;
            $areas[$code] = $this->hasElectricityNeed($kk, $region->kondisi_listrik) ? 'unserved' : 'needs_data';
        }

        return [$areas, $eligibleCodesById];
    }

    private function polygonSubmissions(?int $year): Builder
    {
        return $this->submissions($year)->whereIn('p.status_terkini', array_keys(self::STATUSES));
    }

    private function submissionPoints(Builder $submissions, string $code): array
    {
        $locations = $submissions->join('lokasi_pengajuan as l', 'l.pengajuan_id', '=', 'p.id')
            ->whereNotNull(['l.latitude', 'l.longitude'])
            ->whereNotExists(fn (Builder $query) => $query->from('lokasi_pengajuan as other')
                ->whereColumn('other.pengajuan_id', 'l.pengajuan_id')->whereColumn('other.id', '!=', 'l.id'))
            ->orderBy('p.id')->get(['p.id', 'p.status_terkini', 'submitted_at', 'l.latitude', 'l.longitude']);
        if ($locations->isEmpty()) {
            return [];
        }

        $district = str_replace('.', '', substr($code, 0, 5));
        $path = public_path("data/map-boundaries/villages-{$district}.geojson");
        $features = is_readable($path) ? (json_decode(file_get_contents($path), true)['features'] ?? []) : [];
        $geometry = collect($features)->firstWhere('properties.code', $code)['geometry'] ?? null;
        if (! in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
            return [];
        }

        $points = [];
        foreach ($locations as $location) {
            if (! is_numeric($location->latitude) || ! is_numeric($location->longitude)) {
                continue;
            }
            $lat = (float) $location->latitude;
            $lng = (float) $location->longitude;
            if (! is_finite($lat) || ! is_finite($lng) || abs($lat) > 90 || abs($lng) > 180
                || ! Geometry::contains($geometry, [$lng, $lat])) {
                continue;
            }
            $points[] = [
                'id' => (string) $location->id,
                'latitude' => $lat,
                'longitude' => $lng,
                'submitted_at' => $location->submitted_at,
                'status' => self::STATUSES[$location->status_terkini] ?? 'Status belum dikenali',
            ];
        }

        return $points;
    }

    private function statusSubmissions(Builder $submissions, string $code, string $status, int $page): array
    {
        $result = $submissions->where('p.status_terkini', $status)
            ->select(['p.id', 'submitted_at'])
            ->addSelect(['location_address' => DB::table('lokasi_pengajuan as l')
                ->selectRaw('CASE WHEN COUNT(*) = 1 THEN MIN(l.alamat_deskriptif) END')
                ->whereColumn('l.pengajuan_id', 'p.id')])
            ->orderByRaw('CASE WHEN submitted_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('submitted_at')->orderByDesc('p.id')->paginate(10, ['*'], 'halaman', $page);

        $items = $result->getCollection()->map(function ($submission) use ($code, $status) {
            $location = null;
            if (is_string($submission->location_address)) {
                $fields = $this->catalog->addressFields($submission->location_address);
                if ($fields['desa'] === $code) {
                    $village = $this->catalog->selection($fields['kabupaten'], $fields['kecamatan'], $fields['desa']);
                    $location = implode(' · ', [$village['name'], $fields['kecamatan'], $this->catalog->districts()[$fields['kabupaten']]]);
                }
            }

            return [
                'id' => (string) $submission->id,
                'submitted_at' => $submission->submitted_at,
                'location' => $location,
                'status' => self::STATUSES[$status],
            ];
        });

        return ['items' => $items, 'page' => $result->currentPage(), 'last_page' => $result->lastPage(), 'total' => $result->total()];
    }

    public function areaDetail(string $code, ?int $year, ?array $regionCodes, ?string $condition, ?string $status = null, int $page = 1): array
    {
        $exactCode = fn ($region) => $region->kode_referensi === $code;
        $regions = $this->masterRegions($regionCodes, $condition)->where('w.kode_referensi', $code)->orderBy('w.id')
            ->get(['w.id', 'w.kode_referensi', 'w.kondisi_listrik', 'w.jumlah_kk_terdampak'])->filter($exactCode);
        $region = $regions->first();
        if (! $region) {
            $exists = $this->masterRegions(null, null)->where('w.kode_referensi', $code)
                ->get(['w.kode_referensi'])->contains($exactCode);

            return ['status' => $exists ? 'outside' : 'missing_master', 'detail' => null];
        }
        $submissions = $this->polygonSubmissions($year)->whereIn('p.wilayah_id', $regions->pluck('id'));
        if ($status !== null) {
            return ['status' => 'available', 'submission_list' => $this->statusSubmissions($submissions, $code, $status, $page)];
        }
        $statuses = (clone $submissions)
            ->selectRaw('p.status_terkini, COUNT(*) AS total')->groupBy('p.status_terkini')->pluck('total', 'status_terkini');
        $progress = DB::table('progres_elektrifikasi')->where('wilayah_id', $region->id);
        if ($year !== null) {
            $progress->where('tanggal_progres', '>=', "$year-01-01")->where('tanggal_progres', '<', ($year + 1).'-01-01');
        }

        return ['status' => 'available', 'detail' => [
            'condition' => config('wilayah.kondisi_listrik')[$region->kondisi_listrik] ?? $region->kondisi_listrik,
            'affected_kk' => $region->jumlah_kk_terdampak === null ? null : (int) $region->jumlah_kk_terdampak,
            'linked_submissions' => (int) $statuses->sum(),
            'submission_points' => $this->submissionPoints($submissions, $code),
            'submissions' => $statuses->map(fn ($count, $status) => [
                'id' => $status,
                'status' => self::STATUSES[$status] ?? 'Status belum dikenali', 'count' => (int) $count,
            ])->values(),
            'progress' => $progress->orderByDesc('tanggal_progres')->orderByDesc('id')->limit(10)->get(['tahap_progres', 'tanggal_progres']),
            'unavailable' => ['KK terlayani', 'Persentase realisasi', 'PLTS selesai'],
        ]];
    }

    public function data(?int $year = null, ?array $regionCodes = null, ?string $condition = null): array
    {
        $scoped = $regionCodes !== null || $condition !== null;
        $regions = $this->indicatorRegions($year, $regionCodes, $condition);
        $geometry = app(MapData::class)->boundary()['geometry'];
        $coordinates = new CachedGeometry(Cache::store('file'), $geometry);
        $features = [];
        $matchingIds = [];
        $catalogCodesByDistrict = [];
        $regionCount = $unlocated = $unserved = $needsData = $unknownTarget = $excluded = 0;
        $target = 0;
        // Cursor selects only map/indicator fields; never serialize users, applications or full master records.
        foreach ($regions->select(['w.id', 'w.kode_referensi', 'w.nama_wilayah', 'w.alamat_deskriptif',
            'w.latitude', 'w.longitude', 'w.jumlah_kk_terdampak', 'w.kondisi_listrik'])->orderBy('w.id')->cursor() as $region) {
            $point = null;
            if (is_numeric($region->latitude) && is_numeric($region->longitude)
                && abs((float) $region->latitude) <= 90 && abs((float) $region->longitude) <= 180
                && $coordinates->contains([(float) $region->longitude, (float) $region->latitude])) {
                $point = [(float) $region->longitude, (float) $region->latitude];
            }
            $isJabar = $point !== null || $regionCodes !== null
                || preg_match('/(?:^|,)\s*JAWA BARAT\s*$/iu', $region->alamat_deskriptif);
            $code = (string) $region->kode_referensi;
            if (! $isJabar && preg_match('/^(32)\.(\d{2})\.\d{2}\.\d{4}$/D', $code, $parts)) {
                if ($scoped) {
                    $district = $parts[1].$parts[2];
                    $catalogCodesByDistrict[$district] ??= array_fill_keys(array_column($this->catalog->villages($district), 'code'), true);
                    $isJabar = isset($catalogCodesByDistrict[$district][$code]);
                } else {
                    $isJabar = true;
                }
            }
            if (! $isJabar) {
                $excluded++;

                continue;
            }
            $regionCount++;
            if ($scoped) {
                $matchingIds[] = (int) $region->id;
            }
            $kk = $region->jumlah_kk_terdampak === null ? null : (int) $region->jumlah_kk_terdampak;
            if ($kk === null) {
                $unknownTarget++;
            } else {
                $target += $kk;
            }
            $hasNeed = $this->hasElectricityNeed($kk, $region->kondisi_listrik);
            if ($hasNeed) {
                $unserved++;
            } else {
                $needsData++;
            }
            if ($point === null) {
                $unlocated++;

                continue;
            }
            $features[] = ['type' => 'Feature', 'id' => $region->id,
                'geometry' => ['type' => 'Point', 'coordinates' => $point],
                'properties' => ['name' => $region->nama_wilayah, 'target_kk' => $kk,
                    'category' => $hasNeed ? 'unserved' : 'needs_data']];
        }
        $coordinates->save();

        $submissions = $this->submissions($year);
        $undatedSubmissions = $this->submissions(null)->whereNull('submitted_at');
        if ($scoped) {
            // Use the same master IDs for the map, status counts and completeness notes.
            $submissions->whereIntegerInRaw('p.wilayah_id', $matchingIds);
            $undatedSubmissions->whereIntegerInRaw('p.wilayah_id', $matchingIds);
        }
        $statusRows = $submissions->select('p.status_terkini')
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN p.wilayah_id IS NULL THEN 1 ELSE 0 END) AS unlinked')
            ->groupBy('p.status_terkini')->get();
        $groups = $statusRows->pluck('total', 'status_terkini');
        $unlinked = (int) $statusRows->sum('unlinked');
        $globalUnlinked = $scoped ? $this->submissions($year)->whereNull('p.wilayah_id')->count() : $unlinked;
        if ($groups->keys()->diff(array_keys(self::STATUSES))->isNotEmpty()) {
            throw new DomainException('Ada status pengajuan di luar enam status layanan. Pemetaan status perlu diperiksa sebelum ringkasan dapat ditampilkan.');
        }
        $statuses = [];
        foreach (self::STATUSES as $key => $label) {
            $statuses[] = ['id' => $key, 'label' => $label, 'count' => (int) ($groups[$key] ?? 0)];
        }
        $total = (int) $groups->sum();
        $undated = $undatedSubmissions->count();
        $years = DB::query()->fromSub($this->firstSubmissions(), 'dates')
            ->selectRaw('DISTINCT SUBSTR(submitted_at, 1, 4) AS year')->pluck('year')->map(fn ($year) => (int) $year);
        // The period options stay available while the dashboard figures are filtered.
        $progressYears = DB::table('progres_elektrifikasi')->whereNotNull('tanggal_progres')
            ->selectRaw('DISTINCT SUBSTR(tanggal_progres, 1, 4) AS year')->pluck('year')->map(fn ($year) => (int) $year);
        $years = $years->merge($progressYears)->unique()->sortDesc()->values()->all();

        $notes = [
            'KK terlayani belum tersedia: realisasi pelayanan KK belum dicatat secara terstruktur.',
            'PLTS selesai dan wilayah PLTS terpasang belum tersedia: data identitas serta penyelesaian pemasangan belum tersedia.',
            'Antrean pemeriksaan menggunakan status Diajukan. Status grafik adalah status terkini, bukan snapshot historis.',
        ];
        if ($unknownTarget) {
            $notes[] = "$unknownTarget wilayah belum memiliki angka KK sasaran. Jumlah sasaran lengkap belum tersedia.";
        }
        if ($undated) {
            $notes[] = "$undated pengajuan tidak memiliki riwayat pengiriman awal dan tidak disertakan pada filter tahun.";
        }
        if ($unlinked) {
            $notes[] = "$unlinked pengajuan belum terhubung ke master wilayah. Lokasi pengajuan tersebut belum dapat dihitung sebagai wilayah terpetakan.";
        }
        if ($scoped) {
            $notes[] = 'Saat filter wilayah atau kondisi listrik aktif, pengajuan dihitung jika terhubung ke master wilayah yang sesuai.';
        } elseif ($excluded) {
            $notes[] = "$excluded wilayah di luar atau belum teridentifikasi sebagai Jawa Barat dikecualikan.";
        }

        $description = $year
            ? 'Pengajuan dikirim dan wilayah dengan pengajuan/progres pada tahun terpilih. Status serta KK sasaran memakai data terkini.'
            : 'Seluruh pengajuan terkirim dan master wilayah Jawa Barat. Status serta KK sasaran memakai data terkini.';
        if ($scoped) {
            $description = $year
                ? 'Pengajuan yang dikirim pada tahun terpilih dan wilayah dengan pengajuan/progres pada tahun tersebut, sesuai filter aktif.'
                : 'Pengajuan terkirim dan master wilayah Jawa Barat sesuai filter aktif.';
        }

        [$areas, $eligibleCodesById] = $this->polygonAreas($regionCodes, $condition);
        $areasWithSubmissions = $this->polygonSubmissions($year)
            ->join('wilayah as w', 'w.id', '=', 'p.wilayah_id')
            ->where('w.kode_referensi', 'like', '32.%')->distinct()->pluck('p.wilayah_id')
            ->map(fn ($id) => $eligibleCodesById[$id] ?? null)->filter()->unique()->sort()->values()->all();

        return [
            'period' => ['year' => $year, 'label' => $year ? "Tahun $year" : 'Semua periode', 'years' => $years,
                'description' => $description],
            'metrics' => ['submitted' => $total, 'mapped' => count($features), 'target_kk' => $unknownTarget ? null : $target,
                'known_target_kk' => $target, 'served_kk' => null, 'served_percent' => null, 'completed_plts' => null],
            'statuses' => $statuses,
            'map' => ['type' => 'FeatureCollection', 'features' => $features],
            'areas' => (object) $areas,
            'areas_with_submissions' => $areasWithSubmissions,
            'follow_up' => ['regions' => $regionCount, 'unlocated' => $unlocated, 'unserved' => $unserved,
                'installed' => null, 'needs_data' => $needsData, 'waiting' => (int) ($groups['diajukan'] ?? 0),
                'unlinked_submissions' => $unlinked, 'unlinked_submissions_global' => $globalUnlinked],
            'notes' => $notes, 'empty' => $total === 0 && $regionCount === 0,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}

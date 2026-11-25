<?php

namespace App\Services;

use App\Services\Map\CachedGeometry;
use App\Services\Map\MapData;
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

    public function data(?int $year = null): array
    {
        // Group before joining anything one-to-many, so repeated history/progress cannot multiply counts.
        $statusRows = $this->submissions($year)->select('p.status_terkini')
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN p.wilayah_id IS NULL THEN 1 ELSE 0 END) AS unlinked')
            ->groupBy('p.status_terkini')->get();
        $groups = $statusRows->pluck('total', 'status_terkini');
        $unlinked = (int) $statusRows->sum('unlinked');
        if ($groups->keys()->diff(array_keys(self::STATUSES))->isNotEmpty()) {
            throw new DomainException('Ada status pengajuan di luar enam status layanan. Pemetaan status perlu diperiksa sebelum ringkasan dapat ditampilkan.');
        }
        $statuses = [];
        foreach (self::STATUSES as $key => $label) {
            $statuses[] = ['id' => $key, 'label' => $label, 'count' => (int) ($groups[$key] ?? 0)];
        }
        $total = (int) $groups->sum();
        $undated = $this->submissions(null)->whereNull('submitted_at')->count();
        $years = DB::query()->fromSub($this->firstSubmissions(), 'dates')
            ->selectRaw('DISTINCT SUBSTR(submitted_at, 1, 4) AS year')->pluck('year')->map(fn ($year) => (int) $year);
        // Only distinct dates are needed; progress values have no defined completion/served-KK contract yet.
        $progressYears = DB::table('progres_elektrifikasi')->whereNotNull('tanggal_progres')
            ->selectRaw('DISTINCT SUBSTR(tanggal_progres, 1, 4) AS year')->pluck('year')->map(fn ($year) => (int) $year);
        $years = $years->merge($progressYears)->unique()->sortDesc()->values()->all();

        $regions = DB::table('wilayah as w');
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
        $geometry = app(MapData::class)->boundary()['geometry'];
        $coordinates = new CachedGeometry(Cache::store('file'), $geometry);
        $features = [];
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
            $isJabar = $point !== null || preg_match('/^32\.\d{2}\.\d{2}\.\d{4}$/', (string) $region->kode_referensi)
                || preg_match('/(?:^|,)\s*JAWA BARAT\s*$/iu', $region->alamat_deskriptif);
            if (! $isJabar) {
                $excluded++;

                continue;
            }
            $regionCount++;
            $kk = $region->jumlah_kk_terdampak === null ? null : (int) $region->jumlah_kk_terdampak;
            if ($kk === null) {
                $unknownTarget++;
            } else {
                $target += $kk;
            }
            $hasNeed = ($kk !== null && $kk > 0) || in_array($region->kondisi_listrik,
                ['belum_berlistrik', 'akses_terbatas', 'ada_kk_belum_berlistrik'], true);
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
        if ($excluded) {
            $notes[] = "$excluded wilayah di luar atau belum teridentifikasi sebagai Jawa Barat dikecualikan.";
        }

        return [
            'period' => ['year' => $year, 'label' => $year ? "Tahun $year" : 'Semua periode', 'years' => $years,
                'description' => $year
                    ? 'Pengajuan dikirim dan wilayah dengan pengajuan/progres pada tahun terpilih. Status serta KK sasaran memakai data terkini.'
                    : 'Seluruh pengajuan terkirim dan master wilayah Jawa Barat. Status serta KK sasaran memakai data terkini.'],
            'metrics' => ['submitted' => $total, 'mapped' => count($features), 'target_kk' => $unknownTarget ? null : $target,
                'known_target_kk' => $target, 'served_kk' => null, 'served_percent' => null, 'completed_plts' => null],
            'statuses' => $statuses,
            'map' => ['type' => 'FeatureCollection', 'features' => $features],
            'follow_up' => ['regions' => $regionCount, 'unlocated' => $unlocated, 'unserved' => $unserved,
                'installed' => null, 'needs_data' => $needsData, 'waiting' => (int) ($groups['diajukan'] ?? 0), 'unlinked_submissions' => $unlinked],
            'notes' => $notes, 'empty' => $total === 0 && $regionCount === 0,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}

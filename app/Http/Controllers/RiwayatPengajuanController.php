<?php

namespace App\Http\Controllers;

use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RiwayatPengajuanController extends Controller
{
    private const STATUS_LABELS = [
        'diajukan' => 'Diajukan',
        'diperiksa' => 'Sedang Diperiksa',
        'diverifikasi' => 'Diverifikasi',
        'diproses' => 'Dalam Proses',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak',
    ];

    public function index(Request $request)
    {
        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'max:50'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'periode' => ['nullable', 'date_format:Y-m'],
        ]);

        $filters = [
            'q' => trim($input['q'] ?? ''),
            'status' => $input['status'] ?? '',
            'tanggal' => $input['tanggal'] ?? '',
            'periode' => empty($input['tanggal']) ? ($input['periode'] ?? '') : '',
        ];

        $submitted = $request->user()->pengajuans()
            ->where('status_terkini', '!=', 'draft');

        $summaryRow = (clone $submitted)
            ->reorder()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status_terkini IN (?, ?, ?, ?) THEN 1 ELSE 0 END), 0) AS in_follow_up',
                ['diajukan', 'diperiksa', 'diverifikasi', 'diproses']
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status_terkini = ? THEN 1 ELSE 0 END), 0) AS completed',
                ['selesai']
            )
            ->first();

        $summary = [
            'total' => (int) $summaryRow->total,
            'in_follow_up' => (int) $summaryRow->in_follow_up,
            'completed' => (int) $summaryRow->completed,
        ];

        $statusOptions = self::STATUS_LABELS;

        $firstSubmission = DB::table('riwayat_status_pengajuan')
            ->select('created_at')
            ->whereColumn('riwayat_status_pengajuan.pengajuan_id', 'pengajuan_plts.id')
            ->where('jenis_tindakan', 'pengajuan_baru')
            ->orderBy('created_at')
            ->limit(1);

        $query = (clone $submitted)->with('lokasi');

        if ($filters['status'] !== '') {
            $query->where('status_terkini', $filters['status']);
        }

        if ($filters['tanggal'] !== '' || $filters['periode'] !== '') {
            $hasDate = $filters['tanggal'] !== '';
            $start = $hasDate
                ? Carbon::createFromFormat('!Y-m-d', $filters['tanggal'])
                : Carbon::createFromFormat('!Y-m', $filters['periode']);
            $end = $hasDate ? $start->copy()->addDay() : $start->copy()->addMonth();

            $query->where(clone $firstSubmission, '>=', $start)
                ->where(clone $firstSubmission, '<', $end);
        }

        if ($filters['q'] !== '') {
            $term = $filters['q'];
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
            $candidates = (clone $query)->select('pengajuan_plts.id')
                ->whereHas('lokasi', fn ($location) => $location
                    ->whereRaw("LOWER(alamat_deskriptif) LIKE ? ESCAPE '!'", [$pattern]))
                ->lazyById(250, 'pengajuan_plts.id', 'id');
            $matchingIds = [];

            foreach ($candidates as $candidate) {
                $name = $this->villageNameForSearch($candidate->lokasi?->alamat_deskriptif ?? '');
                if (mb_stripos($name, $term) !== false) {
                    $matchingIds[] = $candidate->id;
                }
            }

            $query->whereIntegerInRaw('pengajuan_plts.id', $matchingIds);
        }

        $pengajuans = $query
            ->addSelect(['tanggal_pengiriman_pertama' => clone $firstSubmission])
            ->orderByRaw('CASE WHEN tanggal_pengiriman_pertama IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('tanggal_pengiriman_pertama')
            ->orderByDesc('pengajuan_plts.id')
            ->paginate(10)
            ->appends(array_filter($filters, fn ($value) => $value !== ''));

        return view('pengajuan.riwayat', compact('pengajuans', 'statusOptions', 'filters', 'summary'));
    }

    private function villageNameForSearch(string $address): string
    {
        $parts = array_map('trim', explode(',', $address));
        // LocationCatalog writes the village four positions from the end.
        if (count($parts) >= 5 && strcasecmp(end($parts), 'Jawa Barat') === 0
            && preg_match('/^(?:Kabupaten|Kota)\s+/iu', $parts[count($parts) - 2])) {
            return $parts[count($parts) - 4];
        }

        foreach ($parts as $part) {
            if (preg_match('/^(?:Desa|Kelurahan)\s+(.+)$/iu', $part, $match)) {
                return $match[1];
            }
        }

        return '';
    }

    public function show(Request $request, string $pengajuan, LocationCatalog $catalog)
    {
        $submission = $request->user()->pengajuans()
            ->whereKey($pengajuan)
            ->where('status_terkini', '!=', 'draft')
            ->with(['lokasi', 'bukti' => fn ($query) => $query->orderBy('id')])
            ->firstOrFail();

        // The status log has no field that marks notes or verification results as public.
        $history = DB::table('riwayat_status_pengajuan')
            ->where('pengajuan_id', $submission->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['status_baru', 'jenis_tindakan', 'created_at']);
        $submittedAt = $history->firstWhere('jenis_tindakan', 'pengajuan_baru')?->created_at;

        $address = trim((string) $submission->lokasi?->alamat_deskriptif);
        $addressFields = $catalog->addressFields($address);
        $districtCode = $addressFields['kabupaten'];
        $village = $districtCode === '' ? null : $catalog->selection(
            $districtCode, $addressFields['kecamatan'], $addressFields['desa']
        );
        $district = $village ? ($catalog->districts()[$districtCode] ?? null) : null;
        $province = preg_match('/(?:^|,)\s*Jawa Barat$/iu', $address) ? 'Jawa Barat' : null;

        return response()->view('pengajuan.detail', [
            'pengajuan' => $submission,
            'ownerName' => $request->user()->name,
            'address' => $address,
            'village' => $village['name'] ?? null,
            'subdistrict' => $village['subdistrict'] ?? null,
            'district' => $district,
            'province' => $province,
            'history' => $history,
            'submittedAt' => $submittedAt,
            'statusLabels' => self::STATUS_LABELS,
        ])->header('Cache-Control', 'private, no-store');
    }
}

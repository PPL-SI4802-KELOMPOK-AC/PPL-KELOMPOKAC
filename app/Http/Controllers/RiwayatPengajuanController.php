<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RiwayatPengajuanController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'max:50'],
            'periode' => ['nullable', 'date_format:Y-m'],
        ]);

        $filters = [
            'q' => trim($input['q'] ?? ''),
            'status' => $input['status'] ?? '',
            'periode' => $input['periode'] ?? '',
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

        $statusOptions = (clone $submitted)
            ->reorder()
            ->select('status_terkini')
            ->distinct()
            ->orderBy('status_terkini')
            ->pluck('status_terkini');

        $firstSubmission = DB::table('riwayat_status_pengajuan')
            ->select('created_at')
            ->whereColumn('riwayat_status_pengajuan.pengajuan_id', 'pengajuan_plts.id')
            ->where('jenis_tindakan', 'pengajuan_baru')
            ->orderBy('created_at')
            ->limit(1);

        $query = (clone $submitted)
            ->with('lokasi')
            ->addSelect(['tanggal_pengiriman_pertama' => clone $firstSubmission]);

        if ($filters['q'] !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';

            $query->where(function ($search) use ($pattern) {
                $search->whereRaw("pengajuan_plts.nomor_tiket LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('lokasi', fn ($location) => $location
                        ->whereRaw("alamat_deskriptif LIKE ? ESCAPE '!'", [$pattern]));
            });
        }

        if ($filters['status'] !== '') {
            $query->where('status_terkini', $filters['status']);
        }

        if ($filters['periode'] !== '') {
            $periodStart = Carbon::createFromFormat('!Y-m', $filters['periode']);
            $periodEnd = $periodStart->copy()->addMonth();

            $query->whereExists(function ($history) use ($periodStart, $periodEnd) {
                $history->selectRaw('1')
                    ->from('riwayat_status_pengajuan')
                    ->whereColumn('riwayat_status_pengajuan.pengajuan_id', 'pengajuan_plts.id')
                    ->where('jenis_tindakan', 'pengajuan_baru')
                    ->where('created_at', '>=', $periodStart)
                    ->where('created_at', '<', $periodEnd)
                    ->whereRaw(
                        'riwayat_status_pengajuan.created_at = (SELECT MIN(earliest_history.created_at) FROM riwayat_status_pengajuan AS earliest_history WHERE earliest_history.pengajuan_id = riwayat_status_pengajuan.pengajuan_id AND earliest_history.jenis_tindakan = ?)',
                        ['pengajuan_baru']
                    );
            });
        }

        $pengajuans = $query
            ->orderByRaw('CASE WHEN tanggal_pengiriman_pertama IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('tanggal_pengiriman_pertama')
            ->orderByDesc('pengajuan_plts.id')
            ->paginate(10)
            ->appends($filters);

        return view('pengajuan.riwayat', compact('pengajuans', 'statusOptions', 'filters', 'summary'));
    }
}

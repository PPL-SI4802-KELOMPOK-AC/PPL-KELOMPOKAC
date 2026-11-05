<?php

namespace App\Services;

use App\Models\Wilayah;

class DashboardSummary
{
    public function data(): array
    {
        // One grouped query; no manual condition or guessed electrified-family count.
        $expression = "CASE WHEN jumlah_kk_terdampak IS NULL THEN 'unavailable'
            WHEN jumlah_kk_terdampak > 0 THEN 'has_unserved' ELSE 'no_unserved' END";
        $groups = Wilayah::query()->selectRaw($expression.' AS category')
            ->selectRaw('COUNT(*) AS total, SUM(jumlah_kk_terdampak) AS kk_total')
            ->groupByRaw($expression)->get();
        $definitions = [
            'has_unserved' => ['label' => 'Ada KK belum berlistrik', 'color' => '#c53d48',
                'meaning' => 'Wilayah mencatat lebih dari nol KK belum berlistrik. Jumlah KK pada kelompok ini dijumlahkan dari database.'],
            'no_unserved' => ['label' => 'Tercatat 0 KK belum berlistrik', 'color' => '#2f8b63',
                'meaning' => 'Wilayah mencatat nol KK belum berlistrik. Angka ini tidak memberikan jumlah KK yang sudah berlistrik.'],
            'unavailable' => ['label' => 'Data KK belum tersedia', 'color' => '#809bad',
                'meaning' => 'Jumlah KK belum diisi (NULL). Data ini tidak dianggap nol atau sudah berlistrik.'],
        ];
        $categories = [];
        $total = $known = $unknown = 0;
        $families = null;
        foreach ($groups as $group) {
            $key = $group->category;
            $count = (int) $group->total;
            $kk = $group->kk_total === null ? null : (int) $group->kk_total;
            $total += $count;
            if ($key === 'unavailable') $unknown += $count;
            else {
                $known += $count;
                $families = ($families ?? 0) + $kk;
            }
            $categories[$key] = ['id' => $key, ...$definitions[$key], 'count' => $count, 'kk_total' => $kk];
        }
        $ordered = [];
        foreach (array_keys($definitions) as $key) if (isset($categories[$key])) $ordered[] = $categories[$key];

        return [
            'metrics' => ['total' => $total, 'unserved_kk' => $families, 'served_kk' => null],
            'coverage' => ['kk_recorded' => $known, 'kk_unknown' => $unknown],
            'categories' => $ordered,
            'all_unknown' => $total > 0 && $known === 0,
            'source' => 'Jumlah KK terdampak pada tabel wilayah di database aplikasi',
            'generated_at' => now()->toIso8601String(),
            'data_note' => 'Jumlah KK sudah berlistrik belum tersedia. Nol KK belum berlistrik tidak menunjukkan jumlah KK yang sudah berlistrik.',
        ];
    }
}

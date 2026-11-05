<?php

namespace App\Services;

use App\Models\Wilayah;

class DashboardSummary
{
    public function data(): array
    {
        // Satu agregasi: ringkasan dan grafik membaca snapshot query yang sama.
        $groups = Wilayah::query()->select('kondisi_listrik')->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL
                AND latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180
                AND NOT (latitude = 0 AND longitude = 0) THEN 1 ELSE 0 END) AS valid_coordinates')
            ->groupBy('kondisi_listrik')->get();
        $labels = config('wilayah.kondisi_listrik');
        $definitions = [
            'belum_berlistrik' => 'Kondisi dicatat belum berlistrik. Isian ini belum otomatis membuktikan verifikasi.',
            'akses_terbatas' => 'Kondisi dicatat memiliki akses listrik terbatas. Catatan verifikasi belum tersedia.',
            'sudah_berlistrik' => 'Kondisi dicatat sudah berlistrik. Ini bukan jumlah PLTS terpasang atau keluarga terlayani.',
            'ada_kk_belum_berlistrik' => 'Data tersimpan mencatat keluarga belum berlistrik. Desa juga dapat memiliki pengguna PLN.',
            'tidak_tercatat_kk_belum_berlistrik' => 'Data tersimpan mencatat nol keluarga belum berlistrik. Ini tidak membuktikan seluruh warga sudah berlistrik.',
        ];
        $colors = ['belum_berlistrik' => '#0d3f5b', 'akses_terbatas' => '#c18b00', 'sudah_berlistrik' => '#308378',
            'ada_kk_belum_berlistrik' => '#bb363b', 'tidak_tercatat_kk_belum_berlistrik' => '#226fa6'];
        $categories = [];
        $total = 0;
        $coordinates = 0;
        $filled = 0;
        foreach ($groups as $group) {
            $value = trim((string) $group->kondisi_listrik);
            $count = (int) $group->total;
            $total += $count;
            $coordinates += (int) $group->valid_coordinates;
            $recognized = array_key_exists($value, $labels);
            $filled += $recognized ? $count : 0;
            $key = $value === '' ? 'unknown' : 'condition-'.sha1($value);
            if (! isset($categories[$key])) {
                $pending = in_array(mb_strtolower($value), ['belum_diverifikasi', 'belum diverifikasi', 'unknown', 'belum_diketahui'], true);
                $categories[$key] = [
                    'id' => $key, 'value' => $value === '' ? null : $value,
                    'label' => $value === '' ? 'Belum diketahui' : ($labels[$value] ?? ($pending ? 'Status belum diketahui / diverifikasi' : $value)),
                    'count' => 0, 'condition_filled' => $recognized,
                    'meaning' => $value === '' ? 'Kondisi listrik masih NULL atau kosong, sehingga belum diketahui.'
                        : ($definitions[$value] ?? ($pending ? 'Nilai ini merupakan status belum diketahui atau belum diverifikasi, bukan kondisi akses listrik.' : 'Nilai lain yang tersimpan. Belum dipetakan ke pilihan kondisi aplikasi dan belum menjadi bukti verifikasi.')),
                    'color' => $colors[$value] ?? ($value === '' || $pending ? '#809bad' : '#786b83'),
                ];
            }
            $categories[$key]['count'] += $count;
        }
        $categories = array_values($categories);
        usort($categories, fn ($a, $b) => ($b['count'] <=> $a['count']) ?: strcmp($a['label'], $b['label']));

        return [
            'metrics' => ['total' => $total, 'valid_coordinates' => $coordinates, 'condition_filled' => $filled],
            'categories' => $categories,
            'all_unknown' => $total > 0 && count($categories) === 1 && $categories[0]['id'] === 'unknown',
            'source' => 'Tabel wilayah pada database aplikasi',
            'generated_at' => now()->toIso8601String(),
            'verification_note' => 'Kondisi telah diisi tidak sama dengan kondisi telah diverifikasi. Skema saat ini belum menyimpan catatan verifikasi kondisi wilayah.',
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AdminActivityLog;
use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\DashboardSummary;

class AdminDashboardController extends Controller
{
    public const STATUSES = [
        'diajukan' => 'Diajukan', 'diperiksa' => 'Diperiksa', 'diverifikasi' => 'Diverifikasi',
        'diproses' => 'Diproses', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak',
    ];

    public function index()
    {
        $local = app()->environment('local');
        $started = hrtime(true);
        $timings = [];
        $measure = function (string $name, callable $operation) use (&$timings, $local) {
            if (! $local) return $operation();
            $start = hrtime(true);
            $result = $operation();
            $timings[$name] = round((hrtime(true) - $start) / 1e6, 2);
            return $result;
        };
        $userStats = $measure('users_summary_ms', fn () => User::query()->select('role')
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN is_active = ? THEN 1 ELSE 0 END) AS active_total', [true])
            ->groupBy('role')->get());
        $roles = $userStats->pluck('total', 'role');
        $activeUsers = (int) $userStats->sum('active_total');
        $statuses = $measure('statuses_ms', fn () => PengajuanPlts::query()->select('status_terkini')
            ->selectRaw('COUNT(*) AS total')->groupBy('status_terkini')->pluck('total', 'status_terkini'));
        $submitted = $statuses->except('draft')->sum();
        $inProgress = collect(['diajukan', 'diperiksa', 'diverifikasi', 'diproses'])
            ->sum(fn ($status) => (int) ($statuses[$status] ?? 0));

        $view = view('admin.dashboard', [
            'roles' => $roles, 'roleLabels' => AdminUserController::ROLES,
            'activeUsers' => $activeUsers, 'regionCount' => $measure('region_count_ms', fn () => app(DashboardSummary::class)->data()['metrics']['total']),
            'statuses' => $statuses, 'statusLabels' => self::STATUSES,
            'submitted' => $submitted, 'inProgress' => $inProgress,
            'recentActivities' => $measure('recent_activities_ms', fn () => AdminActivityLog::query()->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get()),
        ]);
        if (! $local) return $view;

        $html = $measure('render_ms', fn () => $view->render());
        $timings['controller_total_ms'] = round((hrtime(true) - $started) / 1e6, 2);
        logger()->debug('Pengukuran dashboard admin', $timings);
        $serverTiming = [];
        foreach ($timings as $name => $duration) {
            $serverTiming[] = $name.';dur='.$duration;
        }
        return response($html)->header('Server-Timing', implode(', ', $serverTiming));
    }
}

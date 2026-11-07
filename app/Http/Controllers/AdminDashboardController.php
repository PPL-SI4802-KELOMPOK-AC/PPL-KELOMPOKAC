<?php

namespace App\Http\Controllers;

use App\Models\AdminActivityLog;
use App\Models\PengajuanPlts;
use App\Models\User;
use App\Models\Wilayah;

class AdminDashboardController extends Controller
{
    public const STATUSES = [
        'diajukan' => 'Diajukan', 'diperiksa' => 'Diperiksa', 'diverifikasi' => 'Diverifikasi',
        'diproses' => 'Diproses', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak',
    ];

    public function index()
    {
        $roles = User::query()->select('role')->selectRaw('COUNT(*) AS total')->groupBy('role')->pluck('total', 'role');
        $activeUsers = User::query()->where('is_active', true)->count();
        $statuses = PengajuanPlts::query()->select('status_terkini')
            ->selectRaw('COUNT(*) AS total')->groupBy('status_terkini')->pluck('total', 'status_terkini');
        $submitted = $statuses->except('draft')->sum();
        $inProgress = collect(['diajukan', 'diperiksa', 'diverifikasi', 'diproses'])
            ->sum(fn ($status) => (int) ($statuses[$status] ?? 0));

        return view('admin.dashboard', [
            'roles' => $roles, 'roleLabels' => AdminUserController::ROLES,
            'activeUsers' => $activeUsers, 'regionCount' => Wilayah::query()->count(),
            'statuses' => $statuses, 'statusLabels' => self::STATUSES,
            'submitted' => $submitted, 'inProgress' => $inProgress,
            'recentActivities' => AdminActivityLog::query()->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get(),
        ]);
    }
}

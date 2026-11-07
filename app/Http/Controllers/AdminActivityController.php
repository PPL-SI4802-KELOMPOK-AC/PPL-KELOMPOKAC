<?php

namespace App\Http\Controllers;

use App\Models\AdminActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AdminActivityController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'action' => ['nullable', Rule::in(array_keys(AdminActivityLog::ACTIONS))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = AdminActivityLog::query();
        if (($search = trim($filters['q'] ?? '')) !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(fn ($q) => $q->whereRaw("actor_name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("subject_name LIKE ? ESCAPE '!'", [$pattern]));
        }
        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::createFromFormat('!Y-m-d', $filters['from'], 'Asia/Jakarta')->utc());
        }
        if (! empty($filters['until'])) {
            $query->where('created_at', '<', Carbon::createFromFormat('!Y-m-d', $filters['until'], 'Asia/Jakarta')->addDay()->utc());
        }
        $activities = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->withQueryString();
        if ($activities->currentPage() > $activities->lastPage()) {
            return redirect()->route('admin.activities.index', array_merge($request->only(['q', 'action', 'from', 'until']), ['page' => $activities->lastPage()]));
        }
        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $todayCount = AdminActivityLog::query()->where('created_at', '>=', $today->copy()->utc())
            ->where('created_at', '<', $today->copy()->addDay()->utc())->count();

        return view('admin.activities', [
            'activities' => $activities, 'filters' => $filters, 'actions' => AdminActivityLog::ACTIONS,
            'totalActivities' => AdminActivityLog::query()->count(), 'todayCount' => $todayCount,
        ]);
    }
}

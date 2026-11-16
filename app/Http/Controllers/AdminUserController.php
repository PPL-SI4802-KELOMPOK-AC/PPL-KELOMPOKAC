<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAdminUserRequest;
use App\Models\User;
use App\Models\AdminActivityLog;
use App\Services\AdminActivityRecorder;
use App\Models\Wilayah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public const ROLES = ['masyarakat' => 'Masyarakat', 'pemerintah' => 'Pemerintah', 'admin' => 'Admin'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'role' => ['nullable', Rule::in(array_keys(self::ROLES))],
            'access' => ['nullable', Rule::in(['active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = User::query()->select(['id', 'name', 'email', 'role', 'is_active', 'updated_at']);
        if (($search = trim($filters['q'] ?? '')) !== '') {
            // Escape LIKE wildcards: pencarian memperlakukan input sebagai teks.
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern]));
        }
        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        if (! empty($filters['access'])) {
            $query->where('is_active', $filters['access'] === 'active');
        }
        $users = $query->orderBy('name')->orderBy('id')->paginate(10)->withQueryString();
        if ($users->currentPage() > $users->lastPage()) {
            return redirect()->route('admin.users.index', array_merge($request->only(['q', 'role', 'access']), ['page' => $users->lastPage()]));
        }
        $counts = User::query()->select('role')->selectRaw('COUNT(*) AS total')->groupBy('role')->pluck('total', 'role');
        $regionCount = Wilayah::query()->count();
        $latestUser = User::query()->select(['name', 'updated_at'])->latest('updated_at')->latest('id')->first();

        return view('admin.users.index', [
            'users' => $users, 'filters' => $filters, 'roles' => self::ROLES,
            'counts' => $counts, 'regionCount' => $regionCount, 'latestUser' => $latestUser,
        ]);
    }

    public function create()
    {
        return view('admin.users.form', ['managedUser' => new User, 'roles' => self::ROLES]);
    }

    public function store(SaveAdminUserRequest $request, AdminActivityRecorder $recorder)
    {
        $input = $request->validated();
        DB::transaction(function () use ($request, $input, $recorder) {
            $actor = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($actor->role === 'admin' && $actor->is_active !== false, 403);
            $user = new User;
            $this->assign($user, $input);
            $user->save();
            $recorder->record($actor, $user, created: true);
        });

        return redirect()->route('admin.users.index')->with('notice', 'Pengguna baru berhasil ditambahkan.');
    }

    public function edit(User $managedUser)
    {
        return view('admin.users.form', ['managedUser' => $managedUser, 'roles' => self::ROLES]);
    }

    public function update(SaveAdminUserRequest $request, User $managedUser, AdminActivityRecorder $recorder)
    {
        $input = $request->validated();
        DB::transaction(function () use ($request, $managedUser, $input, $recorder) {
            // Urutan kunci tetap juga melindungi dua admin yang mengubah akses bersamaan.
            User::query()->where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            $actor = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($actor->role === 'admin' && $actor->is_active !== false, 403);
            $target = User::query()->lockForUpdate()->findOrFail($managedUser->id);
            if ($target->id === $actor->id && ($input['role'] !== 'admin' || ! $input['is_active'])) {
                throw ValidationException::withMessages([
                    'role' => 'Anda tidak dapat mengubah peran atau menonaktifkan akses admin yang sedang digunakan.',
                ]);
            }
            $before = $target->only(array_keys(AdminActivityLog::FIELDS));
            $this->assign($target, $input);
            $target->save();
            $recorder->record($actor, $target, $before);
        });

        return redirect()->route('admin.users.index')->with('notice', 'Perubahan pengguna berhasil disimpan.');
    }

    private function assign(User $user, array $input): void
    {
        $user->name = $input['name'];
        $user->email = $input['email'];
        $user->role = $input['role'];
        $user->is_active = (bool) $input['is_active'];
        if (! empty($input['password'])) {
            $user->password = $input['password'];
        }
    }
}

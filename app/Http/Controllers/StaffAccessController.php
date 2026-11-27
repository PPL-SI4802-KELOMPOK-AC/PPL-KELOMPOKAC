<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignStaffProfileRequest;
use App\Http\Requests\SaveStaffProfileRequest;
use App\Models\StaffAccessProfile;
use App\Models\StaffProfileAssignment;
use App\Models\User;
use App\Services\StaffPermissionCatalog;
use App\Services\StaffProfileManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class StaffAccessController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'page' => ['nullable', 'integer', 'min:1']]);
        $profiles = StaffAccessProfile::withCount('assignments');
        if (($q = trim($input['q'] ?? '')) !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $profiles->where(fn ($query) => $query->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("description LIKE ? ESCAPE '!'", [$like]));
        }

        return response()->view('admin.access.index', ['profiles' => $profiles->orderBy('name')->orderBy('id')->paginate(10)->withQueryString(),
            'q' => $input['q'] ?? '', 'profileCount' => StaffAccessProfile::count(), 'activeCount' => StaffAccessProfile::where('is_active', true)->count(),
            'assignedCount' => StaffProfileAssignment::count()])->header('Cache-Control', 'private, no-store');
    }

    public function create()
    {
        return view('admin.access.form', ['profile' => new StaffAccessProfile, 'catalog' => StaffPermissionCatalog::ITEMS, 'permissions' => [], 'usage' => 0]);
    }

    public function edit(StaffAccessProfile $profile)
    {
        return view('admin.access.form', ['profile' => $profile, 'catalog' => StaffPermissionCatalog::ITEMS,
            'permissions' => $profile->permissionCodes(), 'usage' => $profile->assignments()->count()]);
    }

    public function show(StaffAccessProfile $profile)
    {
        return view('admin.access.show', ['profile' => $profile, 'permissions' => $profile->permissionCodes(), 'catalog' => StaffPermissionCatalog::ITEMS,
            'assignments' => $profile->assignments()->with('user')->orderBy('id')->paginate(10), 'usage' => $profile->assignments()->count()]);
    }

    public function preview(SaveStaffProfileRequest $request, StaffProfileManager $manager)
    {
        $input = $request->validated();
        $profile = ! empty($input['profile_id']) ? StaffAccessProfile::findOrFail($input['profile_id']) : null;
        $usage = $profile?->assignments()->count() ?? 0;
        if ($profile && ($profile->version !== (int) $input['expected_version'] || $usage !== (int) $input['expected_usage_count'])) {
            return redirect()->route('admin.access.edit', $profile)->withInput()->withErrors(['profile' => 'Profil atau petugas terkait sudah berubah. Muat ulang formulir dan tinjau kembali.']);
        }

        return view('admin.access.review', ['profile' => $profile, 'input' => $input, 'usage' => $usage,
            'before' => $profile ? $manager->snapshot($profile) : [], 'catalog' => StaffPermissionCatalog::ITEMS]);
    }

    private function mutation(Request $request, string $back, callable $operation, string $notice)
    {
        try {
            $result = $operation();

            return $request->expectsJson() ? response()->json(['message' => $notice, 'profile_id' => $result?->id])
                : redirect()->route('admin.access.index')->with('notice', $notice);
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return redirect($back)->withInput()->withErrors($exception->errors());
        } catch (UniqueConstraintViolationException $exception) {
            $error = ['name_key' => 'Nama profil sudah digunakan. Muat ulang dan gunakan nama lain.'];

            return $request->expectsJson() ? response()->json(['message' => $error['name_key'], 'errors' => $error], 422)
                : redirect($back)->withInput()->withErrors($error);
        } catch (\Throwable $exception) {
            if ($exception instanceof HttpExceptionInterface || $exception instanceof ModelNotFoundException) {
                throw $exception;
            }
            report($exception);
            $message = 'Perubahan belum dapat disimpan. Tidak ada perubahan sebagian. Periksa koneksi lalu coba kembali.';

            return $request->expectsJson() ? response()->json(['message' => $message], 503)
                : redirect($back)->withInput()->withErrors(['profile' => $message]);
        }
    }

    public function store(SaveStaffProfileRequest $request, StaffProfileManager $manager)
    {
        return $this->mutation($request, route('admin.access.create'), fn () => $manager->save($request->user(), null, $request->validated()), 'Profil hak akses berhasil ditambahkan.');
    }

    public function update(SaveStaffProfileRequest $request, StaffAccessProfile $profile, StaffProfileManager $manager)
    {
        return $this->mutation($request, route('admin.access.edit', $profile), fn () => $manager->save($request->user(), $profile->id, $request->validated()), 'Profil dan izin berhasil diperbarui.');
    }

    public function confirmDelete(StaffAccessProfile $profile)
    {
        return view('admin.access.delete', ['profile' => $profile, 'usage' => $profile->assignments()->count()]);
    }

    public function destroy(Request $request, StaffAccessProfile $profile, StaffProfileManager $manager)
    {
        $input = $request->validate(['confirmed' => ['required', 'accepted'], 'expected_version' => ['required', 'integer', 'min:1'], 'expected_usage_count' => ['required', 'integer', 'min:0']]);

        return $this->mutation($request, route('admin.access.show', $profile), function () use ($manager, $request, $profile, $input) {
            $manager->delete($request->user(), $profile->id, $input);

            return null;
        }, 'Profil hak akses berhasil dihapus.');
    }

    public function assignment(User $staff)
    {
        return view('admin.access.assignment', ['staff' => $staff, 'assignment' => $staff->staffAssignment()->with('profile')->first(),
            'profiles' => StaffAccessProfile::where('is_active', true)->orderBy('name')->get()]);
    }

    public function previewAssignment(AssignStaffProfileRequest $request)
    {
        $input = $request->validated();
        $staff = User::findOrFail($input['user_id']);
        $profile = StaffAccessProfile::findOrFail($input['profile_id']);
        if ($staff->role !== 'pemerintah' || $staff->is_active !== true || ! $profile->is_active) {
            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['profile_id' => 'Pilih petugas Pemerintah aktif dan profil aktif.']);
            }

            return redirect()->route('admin.access.assignment', $staff)->withInput()->withErrors(['profile_id' => 'Pilih petugas Pemerintah aktif dan profil aktif.']);
        }

        return view('admin.access.assignment-review', ['staff' => $staff, 'profile' => $profile, 'catalog' => StaffPermissionCatalog::ITEMS,
            'permissions' => $profile->permissionCodes(), 'assignment' => $staff->staffAssignment()->with('profile')->first()]);
    }

    public function assign(AssignStaffProfileRequest $request, StaffProfileManager $manager)
    {
        $input = $request->validated();

        return $this->mutation($request, route('admin.access.assignment', $input['user_id']), function () use ($request, $manager, $input) {
            $manager->assign($request->user(), (int) $input['user_id'], (int) $input['profile_id'], isset($input['expected_profile_id']) ? (int) $input['expected_profile_id'] : null, (int) $input['expected_version']);

            return null;
        }, 'Profil petugas berhasil ditetapkan.');
    }
}

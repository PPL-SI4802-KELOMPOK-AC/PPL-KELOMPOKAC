<?php

namespace App\Http\Requests;

use App\Models\StaffAccessProfile;
use App\Services\StaffPermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStaffProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' && $this->user()?->is_active === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('permissions_present') && ! $this->has('permissions')) {
            $this->merge(['permissions' => []]);
        }
        if (is_string($this->input('name'))) {
            $name = StaffPermissionCatalog::name($this->input('name'));
            $this->merge(['name' => $name, 'name_key' => StaffPermissionCatalog::key($name)]);
        }
    }

    public function rules(): array
    {
        $profile = $this->route('profile');
        $id = $profile instanceof StaffAccessProfile ? $profile->id : $this->input('profile_id');
        $id = is_scalar($id) && ctype_digit((string) $id) ? (int) $id : null;

        return [
            'profile_id' => ['nullable', 'integer', 'exists:staff_access_profiles,id'],
            'name' => ['required', 'string', 'max:150'],
            'name_key' => ['required', Rule::unique('staff_access_profiles', 'name_key')->ignore($id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'permissions' => ['present', 'array', 'max:'.count(StaffPermissionCatalog::ITEMS)],
            'permissions.*' => ['required', 'string', 'distinct', Rule::in(array_keys(StaffPermissionCatalog::ITEMS)), 'exists:staff_permissions,code'],
            'expected_version' => [$id ? 'required' : 'nullable', 'integer', 'min:1'],
            'expected_usage_count' => [$id ? 'required' : 'nullable', 'integer', 'min:0'],
            'confirmed' => $this->routeIs('admin.access.preview') ? ['exclude'] : ['required', 'accepted'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $permissions = $this->input('permissions');
            if (! is_array($permissions)) {
                return;
            }
            foreach ($permissions as $code) {
                if (! is_string($code)) {
                    continue;
                }
                foreach (StaffPermissionCatalog::ITEMS[$code]['requires'] ?? [] as $required) {
                    if (! in_array($required, $permissions, true)) {
                        $validator->errors()->add('permissions', 'Izin tindakan Data wilayah memerlukan izin melihat daftar dan rincian wilayah.');
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return ['name.required' => 'Nama profil wajib diisi.', 'name_key.unique' => 'Nama profil sudah digunakan (kapitalisasi dan spasi diabaikan).',
            'name.max' => 'Nama profil maksimal 150 karakter.', 'description.max' => 'Deskripsi maksimal 2.000 karakter.',
            'permissions.*.in' => 'Kode izin tidak dikenal.', 'permissions.*.exists' => 'Izin belum terdaftar dalam katalog.',
            'permissions.*.distinct' => 'Izin tidak boleh berulang.', 'confirmed.accepted' => 'Setujui ringkasan dampak terlebih dahulu.',
            'confirmed.required' => 'Konfirmasi perubahan diperlukan.'];
    }

    protected function getRedirectUrl(): string
    {
        $profile = $this->route('profile');
        $id = $profile instanceof StaffAccessProfile ? $profile->id : $this->input('profile_id');

        return is_scalar($id) && ctype_digit((string) $id) ? route('admin.access.edit', $id) : route('admin.access.create');
    }
}

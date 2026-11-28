<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AssignStaffProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' && $this->user()?->is_active === true;
    }

    public function rules(): array
    {
        $rules = ['user_id' => ['required', 'integer', 'exists:users,id'], 'profile_id' => ['required', 'integer', 'exists:staff_access_profiles,id']];
        if (! $this->routeIs('admin.access.assignment.preview')) {
            $rules += ['expected_profile_id' => ['present', 'nullable', 'integer', 'min:1'], 'expected_version' => ['required', 'integer', 'min:1'], 'confirmed' => ['required', 'accepted']];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['profile_id.required' => 'Pilih profil aktif untuk petugas.', 'profile_id.exists' => 'Profil sudah tidak tersedia. Pilih kembali.',
            'user_id.exists' => 'Akun petugas sudah tidak tersedia.', 'confirmed.accepted' => 'Setujui penetapan profil terlebih dahulu.'];
    }

    protected function getRedirectUrl(): string
    {
        $id = $this->input('user_id');

        return is_scalar($id) && ctype_digit((string) $id) && User::whereKey($id)->exists()
            ? route('admin.access.assignment', $id) : route('admin.access.index');
    }
}

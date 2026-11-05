<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreBuktiPengajuanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'masyarakat';
    }

    public function rules(): array
    {
        return [
            'draft_id' => ['required', 'integer', 'min:1'],
            'photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('5mb')],
        ];
    }

    public function messages(): array
    {
        return [
            'draft_id.required' => 'Draft lokasi harus disimpan sebelum foto diunggah.',
            'photos.required' => 'Pilih minimal satu foto bukti.',
            'photos.min' => 'Pilih minimal satu foto bukti.',
            'photos.max' => 'Maksimal lima foto dapat diunggah dalam satu kali pilihan.',
            'photos.*.image' => 'Setiap berkas harus berupa foto yang valid.',
            'photos.*.types' => 'Format foto harus JPG, JPEG, PNG, atau WebP.',
            'photos.*.max' => 'Ukuran setiap foto maksimal 5 MB.',
        ];
    }

    public function attributes(): array
    {
        return ['photos' => 'foto bukti', 'photos.*' => 'foto bukti'];
    }
}

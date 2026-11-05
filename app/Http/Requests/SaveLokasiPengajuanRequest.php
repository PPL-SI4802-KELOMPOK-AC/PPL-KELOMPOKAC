<?php

namespace App\Http\Requests;

use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveLokasiPengajuanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'masyarakat';
    }

    protected function prepareForValidation(): void
    {
        foreach (['latitude', 'longitude'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => str_replace(',', '.', trim($this->input($field)))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'draft_id' => ['nullable', 'integer', 'min:1'],
            'provinsi' => ['required', Rule::in(['Jawa Barat'])],
            'kabupaten' => ['required', 'string', Rule::in(array_keys(app(LocationCatalog::class)->districts()))],
            'kecamatan' => ['required', 'string', 'max:150'],
            'desa' => ['required', 'string', 'max:20'],
            'alamat' => ['required', 'string', 'max:2000'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90', 'decimal:0,7'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180', 'decimal:0,7'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $catalog = app(LocationCatalog::class);
            if (! $catalog->selection($this->input('kabupaten'), $this->input('kecamatan'), $this->input('desa'))) {
                $validator->errors()->add('desa', 'Desa/kelurahan harus sesuai dengan kecamatan dan kabupaten/kota yang dipilih.');
            }
            if ($this->filled('latitude') && $this->filled('longitude')
                && ! $catalog->containsPoint((float) $this->input('latitude'), (float) $this->input('longitude'))) {
                $validator->errors()->add('latitude', 'Pilih titik lokasi di wilayah Jawa Barat.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'required_with' => 'Lintang dan bujur harus diisi berpasangan, atau keduanya dikosongkan.',
            'string' => ':attribute harus berupa teks.',
            'max.string' => ':attribute maksimal :max karakter.',
            'numeric' => ':attribute harus berupa angka desimal.',
            'between' => ':attribute berada di luar rentang koordinat yang valid.',
            'decimal' => ':attribute maksimal 7 angka di belakang koma.',
            'in' => 'Pilih :attribute yang tersedia.',
        ];
    }

    public function attributes(): array
    {
        return ['provinsi' => 'Provinsi', 'kabupaten' => 'Kabupaten/kota', 'kecamatan' => 'Kecamatan',
            'desa' => 'Desa/kelurahan', 'alamat' => 'Alamat/kampung', 'latitude' => 'Lintang', 'longitude' => 'Bujur'];
    }
}

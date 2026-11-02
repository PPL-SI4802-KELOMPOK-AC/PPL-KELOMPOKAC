<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWilayahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'pemerintah';
    }

    protected function prepareForValidation(): void
    {
        $coordinates = [];
        foreach (['latitude', 'longitude'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $coordinates[$field] = str_replace(',', '.', trim($value));
            }
        }
        $this->merge($coordinates);
    }

    public function rules(): array
    {
        return [
            'nama_wilayah' => ['required', 'string', 'max:150'],
            'kode_referensi' => ['nullable', 'string', 'max:32', Rule::unique('wilayah', 'kode_referensi')->ignore($this->route('wilayah'))],
            'tingkat_wilayah' => ['required', 'string', 'max:50'],
            'alamat_deskriptif' => ['required', 'string', 'max:10000'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90', 'decimal:0,7'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180', 'decimal:0,7'],
            'jumlah_kk_terdampak' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'jumlah_warga_terdampak' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'kondisi_listrik' => ['nullable', Rule::in(array_keys(config('wilayah.kondisi_listrik')))],
            'sumber_data' => ['nullable', 'string', 'max:255'],
            'data_per_tanggal' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'max.string' => ':attribute maksimal :max karakter.',
            'kode_referensi.unique' => 'Kode referensi sudah digunakan oleh wilayah lain.',
            'required_with' => 'Lintang dan bujur harus diisi berpasangan.',
            'numeric' => ':attribute harus berupa angka desimal.',
            'between' => ':attribute harus berada antara :min dan :max.',
            'decimal' => ':attribute maksimal 7 angka di belakang koma.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'min' => ':attribute tidak boleh negatif.',
            'max.numeric' => ':attribute maksimal :max.',
            'in' => 'Pilih kondisi listrik yang tersedia.',
            'date_format' => 'Tanggal pengamatan harus berupa tanggal lengkap yang valid.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_wilayah' => 'Nama wilayah', 'kode_referensi' => 'Kode referensi',
            'tingkat_wilayah' => 'Tingkat wilayah', 'alamat_deskriptif' => 'Alamat lengkap',
            'latitude' => 'Lintang', 'longitude' => 'Bujur',
            'jumlah_kk_terdampak' => 'Jumlah KK', 'jumlah_warga_terdampak' => 'Jumlah warga',
            'kondisi_listrik' => 'Kondisi listrik', 'sumber_data' => 'Sumber data',
            'data_per_tanggal' => 'Tanggal pengamatan',
        ];
    }
}

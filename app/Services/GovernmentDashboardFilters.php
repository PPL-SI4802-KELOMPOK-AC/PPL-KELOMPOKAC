<?php

namespace App\Services;

use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GovernmentDashboardFilters
{
    public const EMPTY_FILTERS = ['year' => null, 'kabupaten' => null, 'kecamatan' => null, 'desa' => null, 'kondisi' => null];

    public const EMPTY_OPTIONS = ['subdistricts' => [], 'villages' => []];

    public function __construct(private LocationCatalog $catalog) {}

    public function validate(array $input): array
    {
        $filters = Validator::make($input, [
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'kabupaten' => ['nullable', 'required_with:kecamatan,desa', 'string', 'regex:/^32\d{2}$/D'],
            'kecamatan' => ['nullable', 'required_with:desa', 'string', 'regex:/^32\.\d{2}\.\d{2}$/D'],
            'desa' => ['nullable', 'string', 'regex:/^32\.\d{2}\.\d{2}\.\d{4}$/D'],
            'kondisi' => ['nullable', 'string', Rule::in([...array_keys(config('wilayah.kondisi_listrik')), 'unknown'])],
        ], [
            'year.integer' => 'Pilih tahun antara 1900 dan 2100.',
            'year.between' => 'Pilih tahun antara 1900 dan 2100.',
            'kabupaten.required_with' => 'Pilih kabupaten/kota terlebih dahulu.',
            'kabupaten.regex' => 'Kode kabupaten/kota tidak valid.',
            'kecamatan.required_with' => 'Pilih kecamatan terlebih dahulu.',
            'kecamatan.regex' => 'Kode kecamatan tidak valid.',
            'desa.regex' => 'Kode desa/kelurahan tidak valid.',
            'kondisi.in' => 'Pilih kondisi listrik yang tersedia.',
        ])->validate();
        $filters['year'] = isset($filters['year']) ? (int) $filters['year'] : null;
        $filters += self::EMPTY_FILTERS;

        if ($filters['kabupaten'] === null) {
            return [$filters, self::EMPTY_OPTIONS, null];
        }

        $options = $this->districtOptions($filters['kabupaten']);
        if ($filters['kecamatan'] !== null && ! isset($options['subdistricts'][$filters['kecamatan']])) {
            throw ValidationException::withMessages(['kecamatan' => 'Kecamatan tidak tersedia di kabupaten/kota yang dipilih.']);
        }

        $codes = [];
        foreach ($options['villages'] as $village) {
            if ($filters['kecamatan'] === null || $village['subdistrict'] === $filters['kecamatan']) {
                $codes[] = $village['code'];
            }
        }
        if ($filters['desa'] !== null) {
            if (! in_array($filters['desa'], $codes, true)) {
                throw ValidationException::withMessages(['desa' => 'Desa/kelurahan tidak tersedia di kecamatan yang dipilih.']);
            }
            $codes = [$filters['desa']];
        }

        return [$filters, $options, $codes];
    }

    public function districtOptions(string $district): array
    {
        if (! array_key_exists($district, $this->catalog->districts())) {
            throw ValidationException::withMessages(['kabupaten' => 'Kabupaten/kota tidak tersedia di katalog wilayah.']);
        }

        $subdistricts = $villages = [];
        foreach ($this->catalog->villages($district) as $record) {
            $code = substr($record['code'], 0, 8);
            $subdistricts[$code] = $record['subdistrict'];
            $villages[] = ['code' => $record['code'], 'name' => $record['name'], 'subdistrict' => $code];
        }
        asort($subdistricts, SORT_NATURAL | SORT_FLAG_CASE);

        return compact('subdistricts', 'villages');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\GovernmentDashboardView;
use App\Services\GovernmentDashboardFilters;
use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GovernmentDashboardViewController extends Controller
{
    private const DUPLICATE_NAME = 'Nama tampilan sudah digunakan.';

    public function __construct(private GovernmentDashboardFilters $filters) {}

    public function index(Request $request, LocationCatalog $catalog): JsonResponse
    {
        $districts = $catalog->districts();
        $optionsByDistrict = [];
        $conditions = config('wilayah.kondisi_listrik');
        $views = $request->user()->governmentDashboardViews()->latest('id')->paginate(20)
            ->through(function (GovernmentDashboardView $view) use ($districts, $conditions, &$optionsByDistrict) {
                $district = $districts[$view->kabupaten] ?? null;
                $options = $district === null ? GovernmentDashboardFilters::EMPTY_OPTIONS
                    : ($optionsByDistrict[$view->kabupaten] ??= $this->filters->districtOptions($view->kabupaten));
                $subdistrict = $options['subdistricts'][$view->kecamatan] ?? null;
                $village = null;
                if ($subdistrict !== null && $view->desa !== null) {
                    foreach ($options['villages'] as $record) {
                        if ($record['code'] === $view->desa && $record['subdistrict'] === $view->kecamatan) {
                            $village = $record['name'];
                            break;
                        }
                    }
                }
                $label = fn ($value, $name, $fallback) => $value === null ? $fallback : ($name ?? "$value (tidak tersedia)");

                return $view->toArray() + ['filter_labels' => [
                    'year' => $view->year === null ? 'Semua periode' : "Tahun {$view->year}",
                    'kabupaten' => $label($view->kabupaten, $district, 'Semua kabupaten/kota'),
                    'kecamatan' => $label($view->kecamatan, $subdistrict, 'Semua kecamatan'),
                    'desa' => $label($view->desa, $village, 'Semua desa/kelurahan'),
                    'kondisi' => $label($view->kondisi, $view->kondisi === 'unknown'
                        ? 'Data belum tersedia' : ($conditions[$view->kondisi] ?? null), 'Semua kondisi listrik'),
                ]];
            });

        return response()->json($views)->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $view): JsonResponse
    {
        $saved = $request->user()->governmentDashboardViews()->findOrFail($view);
        $errors = [];
        $url = null;

        try {
            [$filters] = $this->filters->validate($saved->only(array_keys(GovernmentDashboardFilters::EMPTY_FILTERS)));
            $url = route('pemerintah.dashboard', array_filter($filters, fn ($value) => $value !== null));
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
        }

        return response()->json(['data' => $saved, 'dashboard_url' => $url, 'filter_errors' => (object) $errors])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $views = $request->user()->governmentDashboardViews();
        $attributes = $this->attributes($request);

        if ((clone $views)->where('normalized_name', $attributes['normalized_name'])->exists()) {
            throw ValidationException::withMessages(['name' => self::DUPLICATE_NAME]);
        }

        try {
            $saved = $views->create($attributes);
        } catch (UniqueConstraintViolationException $exception) {
            if (! (clone $views)->where('normalized_name', $attributes['normalized_name'])->exists()) {
                throw $exception;
            }
            throw ValidationException::withMessages(['name' => self::DUPLICATE_NAME]);
        }

        return response()->json(['data' => $saved], 201)
            ->header('Location', route('pemerintah.dashboard.tampilan.show', $saved->getKey()))
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $view): JsonResponse
    {
        $views = $request->user()->governmentDashboardViews();
        $saved = (clone $views)->findOrFail($view);
        $attributes = $this->attributes($request, replacing: true);
        $duplicate = (clone $views)->whereKeyNot($saved->getKey())->where('normalized_name', $attributes['normalized_name']);

        if ((clone $duplicate)->exists()) {
            throw ValidationException::withMessages(['name' => self::DUPLICATE_NAME]);
        }

        try {
            (clone $views)->whereKey($saved->getKey())->update($attributes);
        } catch (UniqueConstraintViolationException $exception) {
            if (! $duplicate->exists()) {
                throw $exception;
            }
            throw ValidationException::withMessages(['name' => self::DUPLICATE_NAME]);
        }

        return response()->json(['data' => $views->findOrFail($saved->getKey())])
            ->header('Cache-Control', 'private, no-store');
    }

    public function destroy(Request $request, string $view): Response
    {
        $views = $request->user()->governmentDashboardViews();
        abort_unless($views->whereKey($view)->delete() === 1, 404);

        return response()->noContent()->header('Cache-Control', 'private, no-store');
    }

    private function attributes(Request $request, bool $replacing = false): array
    {
        $input = $request->all();
        if (is_string($input['name'] ?? null)) {
            $input['name'] = trim(preg_replace('/[\s\p{Z}]+/u', ' ', $input['name']) ?? '');
        }

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'id' => ['missing'],
            'user_id' => ['missing'],
            'normalized_name' => ['missing'],
        ];
        if ($replacing) {
            $rules += array_fill_keys(array_keys(GovernmentDashboardFilters::EMPTY_FILTERS), ['present']);
        }

        $name = Validator::make($input, $rules)->validate()['name'];
        $normalized = mb_convert_case($name, MB_CASE_FOLD, 'UTF-8');
        if (strlen($normalized) > 1024) {
            throw ValidationException::withMessages(['name' => 'Nama tampilan terlalu panjang setelah dinormalisasi.']);
        }

        [$filters] = $this->filters->validate($input);

        return $filters + ['name' => $name, 'normalized_name' => $normalized];
    }
}

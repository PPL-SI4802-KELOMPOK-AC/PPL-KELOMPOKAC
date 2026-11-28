<?php

namespace App\Http\Controllers;

use App\Services\GovernmentDashboard;
use App\Services\GovernmentDashboardFilters;
use App\Services\Pengajuan\LocationCatalog;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GovernmentDashboardController extends Controller
{
    public function __construct(private GovernmentDashboardFilters $dashboardFilters) {}

    public function index(Request $request, LocationCatalog $catalog)
    {
        $filterErrors = [];
        try {
            [$filters, $options] = $this->dashboardFilters->validate($request->all());
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }
            $filters = GovernmentDashboardFilters::EMPTY_FILTERS;
            $options = GovernmentDashboardFilters::EMPTY_OPTIONS;
            $filterErrors = $exception->errors();
        }
        $year = $filters['year'];
        $districts = $catalog->districts();
        $conditions = config('wilayah.kondisi_listrik');

        return response()->view('pemerintah.dashboard', compact('filters', 'options', 'year', 'districts', 'conditions', 'filterErrors'),
            $filterErrors ? 422 : 200)
            ->header('Cache-Control', 'private, no-store');
    }

    public function administration(Request $request)
    {
        $input = $request->validate(['kabupaten' => ['required', 'string', 'regex:/^32\d{2}$/D']], [
            'kabupaten.required' => 'Pilih kabupaten/kota terlebih dahulu.',
            'kabupaten.regex' => 'Kode kabupaten/kota tidak valid.',
        ]);

        return response()->json($this->dashboardFilters->districtOptions($input['kabupaten']))->header('Cache-Control', 'private, no-store');
    }

    public function area(Request $request, GovernmentDashboard $dashboard, LocationCatalog $catalog)
    {
        [$filters, , $codes] = $this->dashboardFilters->validate($request->all());
        $input = $request->validate([
            'wilayah' => ['required', 'string', 'regex:/^32\.\d{2}\.\d{2}\.\d{4}$/D'],
            'status_pengajuan' => ['sometimes', 'required', 'string', Rule::in(array_keys(GovernmentDashboard::STATUSES))],
            'halaman' => ['sometimes', 'required', 'integer', 'min:1', 'max:214748364'],
        ]);
        $code = $input['wilayah'];
        $district = substr($code, 0, 2).substr($code, 3, 2);
        abort_unless(in_array($code, array_column($catalog->villages($district), 'code'), true), 404);

        return response()->json($dashboard->areaDetail(
            $code, $filters['year'], $codes, $filters['kondisi'], $input['status_pengajuan'] ?? null, (int) ($input['halaman'] ?? 1),
        ))
            ->header('Cache-Control', 'private, no-store');
    }

    public function data(Request $request, GovernmentDashboard $dashboard)
    {
        [$filters, , $codes] = $this->dashboardFilters->validate($request->all());
        try {
            return response()->json($dashboard->data($filters['year'], $codes, $filters['kondisi']))
                ->header('Cache-Control', 'private, no-store');
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503)->header('Cache-Control', 'private, no-store');
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Data dashboard belum dapat dimuat. Periksa koneksi lalu coba kembali.'], 503)
                ->header('Cache-Control', 'private, no-store');
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\GovernmentDashboard;
use DomainException;
use Illuminate\Http\Request;

class GovernmentDashboardController extends Controller
{
    private function year(Request $request): ?int
    {
        $input = $request->validate(['year' => ['nullable', 'integer', 'between:1900,2100']]);

        return isset($input['year']) ? (int) $input['year'] : null;
    }

    public function index(Request $request)
    {
        return response()->view('pemerintah.dashboard', ['year' => $this->year($request)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function data(Request $request, GovernmentDashboard $dashboard)
    {
        $year = $this->year($request);
        try {
            return response()->json($dashboard->data($year))->header('Cache-Control', 'private, no-store');
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503)->header('Cache-Control', 'private, no-store');
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Data dashboard belum dapat dimuat. Periksa koneksi lalu coba kembali.'], 503)
                ->header('Cache-Control', 'private, no-store');
        }
    }
}

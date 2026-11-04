<?php

namespace App\Http\Controllers;

use App\Services\DashboardSummary;

class DashboardController extends Controller
{
    public function data(DashboardSummary $summary)
    {
        try {
            return response()->json($summary->data())->header('Cache-Control', 'no-store');
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Ringkasan belum dapat dimuat. Periksa koneksi database lalu coba kembali.'], 503);
        }
    }
}

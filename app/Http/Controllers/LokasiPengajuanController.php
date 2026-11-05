<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveLokasiPengajuanRequest;
use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LokasiPengajuanController extends Controller
{
    public function edit(Request $request, LocationCatalog $catalog)
    {
        $draft = PengajuanPlts::with('lokasi')->where('user_id', $request->user()->id)
            ->where('status_terkini', 'draft')->latest('id')->first();
        $values = $draft?->lokasi ? $catalog->addressFields($draft->lokasi->alamat_deskriptif) : [];
        $values += ['kabupaten' => '', 'kecamatan' => '', 'desa' => '', 'alamat' => ''];
        $values += ['latitude' => $draft?->lokasi?->latitude ?? '', 'longitude' => $draft?->lokasi?->longitude ?? ''];
        foreach (array_keys($values) as $field) {
            $value = old($field, $values[$field]);
            $values[$field] = is_scalar($value) ? (string) $value : '';
        }
        $villages = is_string($values['kabupaten']) ? $catalog->villages($values['kabupaten']) : [];

        return response()->view('pengajuan.lokasi', compact('draft', 'values', 'villages') + ['districts' => $catalog->districts()])
            ->header('Cache-Control', 'private, no-store');
    }

    public function administration(Request $request, LocationCatalog $catalog)
    {
        $input = $request->validate(['kabupaten' => ['required', Rule::in(array_keys($catalog->districts()))]]);

        return response()->json(['villages' => $catalog->villages($input['kabupaten'])]);
    }

    public function store(SaveLokasiPengajuanRequest $request, LocationCatalog $catalog)
    {
        $input = $request->validated();
        DB::transaction(function () use ($request, $input, $catalog) {
            // Satu draft lokasi aktif per akun. Kunci akun mencegah duplikasi saat
            // dua permintaan pertama diterima bersamaan atau tombol diklik ulang.
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $query = PengajuanPlts::where('user_id', $request->user()->id)->lockForUpdate();
            if (! empty($input['draft_id'])) {
                $draft = $query->whereKey($input['draft_id'])->firstOrFail();
                abort_unless($draft->status_terkini === 'draft', 409, 'Pengajuan ini sudah bukan draft. Muat ulang halaman.');
            } else {
                $draft = $query->where('status_terkini', 'draft')->latest('id')->first();
            }
            if (! $draft) {
                $draft = new PengajuanPlts;
                $draft->user_id = $request->user()->id;
                $draft->nomor_tiket = 'DRAFT-'.Str::uuid();
                $draft->status_terkini = 'draft';
                $draft->status_diperbarui_pada = now();
            }
            $village = $catalog->selection($input['kabupaten'], $input['kecamatan'], $input['desa']);
            $draft->ringkasan_pengajuan = Str::limit('Lokasi '.$village['name'].', '.$catalog->districts()[$input['kabupaten']], 255, '');
            $draft->save();
            $draft->lokasi()->updateOrCreate([], [
                'alamat_deskriptif' => $catalog->address($input),
                'latitude' => $input['latitude'] ?? null, 'longitude' => $input['longitude'] ?? null,
            ]);
        });

        return redirect()->route('pengajuan.lokasi')->with('location_saved', 'Draft lokasi berhasil disimpan.');
    }
}

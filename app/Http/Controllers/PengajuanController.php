<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitPengajuanRequest;
use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PengajuanController extends Controller
{
    public function review(Request $request, LocationCatalog $catalog)
    {
        $draft = PengajuanPlts::with(['lokasi', 'bukti'])
            ->where('user_id', $request->user()->id)->where('status_terkini', 'draft')->latest('id')->first();

        if (! $draft || ! $this->locationComplete($draft, $catalog)) {
            return redirect()->route('pengajuan.lokasi')
                ->with('location_required', 'Lengkapi dan simpan data lokasi sebelum memeriksa ringkasan.');
        }
        if ($draft->bukti->isEmpty()) {
            return redirect()->route('pengajuan.bukti')->withErrors(['photos' => 'Unggah minimal satu foto bukti sebelum melanjutkan.']);
        }

        $location = $catalog->addressFields($draft->lokasi->alamat_deskriptif);
        $village = $catalog->selection($location['kabupaten'], $location['kecamatan'], $location['desa']);
        $district = $catalog->districts()[$location['kabupaten']];

        return response()->view('pengajuan.ringkasan', compact('draft', 'location', 'village', 'district'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function submit(SubmitPengajuanRequest $request, LocationCatalog $catalog)
    {
        $input = $request->validated();
        $pengajuan = DB::transaction(function () use ($request, $input, $catalog) {
            // Urutan kunci sama dengan penyimpanan lokasi; pengiriman ulang memakai baris yang sama.
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $draft = PengajuanPlts::whereKey($input['draft_id'])->where('user_id', $request->user()->id)
                ->lockForUpdate()->firstOrFail();

            if ($draft->status_terkini !== 'draft') {
                return $draft;
            }
            $draft->load(['lokasi', 'bukti']);
            if (! $this->locationComplete($draft, $catalog)) {
                throw ValidationException::withMessages(['lokasi' => 'Lengkapi dan simpan data lokasi sebelum mengirim.']);
            }
            if ($draft->bukti->isEmpty() || $draft->bukti->count() > 5) {
                throw ValidationException::withMessages(['photos' => 'Pengajuan harus memiliki satu hingga lima foto bukti tersimpan.']);
            }

            $draft->nomor_tiket = 'PLTS-'.now()->format('Ymd').'-'.Str::ulid();
            $draft->status_terkini = 'diajukan';
            $draft->status_diperbarui_pada = now();
            $draft->save();

            DB::table('riwayat_status_pengajuan')->insert([
                'pengajuan_id' => $draft->id,
                'diubah_oleh' => $request->user()->id,
                // Draft belum merupakan status penanganan pada enum riwayat.
                'status_sebelumnya' => null,
                'status_baru' => 'diajukan',
                'jenis_tindakan' => 'pengajuan_baru',
                'catatan' => 'Pengusul menyetujui pernyataan kebenaran data dan bersedia dihubungi untuk verifikasi.',
                'created_at' => now(),
            ]);

            return $draft;
        });

        return redirect()->route('pengajuan.terkirim', $pengajuan->id);
    }

    public function receipt(Request $request, int $pengajuan)
    {
        $pengajuan = PengajuanPlts::whereKey($pengajuan)->where('user_id', $request->user()->id)
            ->where('status_terkini', '!=', 'draft')->firstOrFail();

        return response()->view('pengajuan.terkirim', compact('pengajuan'))
            ->header('Cache-Control', 'private, no-store');
    }

    private function locationComplete(PengajuanPlts $draft, LocationCatalog $catalog): bool
    {
        if (! $draft->lokasi) {
            return false;
        }
        $location = $catalog->addressFields($draft->lokasi->alamat_deskriptif);

        return trim($location['alamat']) !== ''
            && $catalog->selection($location['kabupaten'], $location['kecamatan'], $location['desa']) !== null;
    }
}

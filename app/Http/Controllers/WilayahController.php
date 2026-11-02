<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveWilayahRequest;
use App\Models\Wilayah;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WilayahController extends Controller
{
    public function index()
    {
        return view('wilayah.manage', ['conditions' => config('wilayah.kondisi_listrik')]);
    }

    public function data(Request $request)
    {
        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'kondisi' => ['nullable', Rule::in([...array_keys(config('wilayah.kondisi_listrik')), 'unknown'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $query = Wilayah::query();
        if ($search = trim($input['q'] ?? '')) {
            // Alamat dicari sebagai teks utuh, bukan dipecah menjadi administrasi rekaan.
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(fn ($q) => $q->whereRaw("nama_wilayah LIKE ? ESCAPE '!'", [$search])
                ->orWhereRaw("alamat_deskriptif LIKE ? ESCAPE '!'", [$search]));
        }
        if (($input['kondisi'] ?? '') === 'unknown') {
            $query->whereNull('kondisi_listrik');
        } elseif (! empty($input['kondisi'])) {
            $query->where('kondisi_listrik', $input['kondisi']);
        }

        return response()->json($query->orderBy('nama_wilayah')->orderBy('id')->paginate(15));
    }

    public function show(Wilayah $wilayah)
    {
        return response()->json($wilayah);
    }

    public function store(SaveWilayahRequest $request)
    {
        $wilayah = new Wilayah($request->validated());
        $wilayah->created_by = $request->user()->id;
        $this->save($wilayah);

        return response()->json(['message' => 'Wilayah berhasil ditambahkan.', 'wilayah' => $wilayah->fresh()], 201);
    }

    public function update(SaveWilayahRequest $request, Wilayah $wilayah)
    {
        $wilayah->fill($request->validated());
        $this->save($wilayah);

        return response()->json(['message' => 'Perubahan wilayah berhasil disimpan.', 'wilayah' => $wilayah->fresh()]);
    }

    private function save(Wilayah $wilayah): void
    {
        try {
            $wilayah->save();
        } catch (QueryException $e) {
            // Menangani duplikasi yang terjadi setelah validasi (request bersamaan).
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw ValidationException::withMessages(['kode_referensi' => 'Kode referensi sudah digunakan oleh wilayah lain.']);
            }
            throw $e;
        }
    }

    public function destroy(Wilayah $wilayah)
    {
        $message = 'Wilayah masih digunakan oleh data pengajuan atau progres sehingga belum dapat dihapus.';
        try {
            $deleted = DB::transaction(function () use ($wilayah) {
                $locked = Wilayah::whereKey($wilayah->id)->lockForUpdate()->firstOrFail();
                if (DB::table('pengajuan_plts')->where('wilayah_id', $locked->id)->exists()
                    || DB::table('progres_elektrifikasi')->where('wilayah_id', $locked->id)->exists()) {
                    return false;
                }
                $locked->delete();

                return true;
            });
            if (! $deleted) {
                return response()->json(['message' => $message], 409);
            }
        } catch (QueryException $e) {
            // FK RESTRICT tetap menjadi pertahanan terakhir, tanpa cascade.
            if (in_array($e->errorInfo[1] ?? null, [1451, 1217], true)) {
                return response()->json(['message' => $message], 409);
            }
            throw $e;
        }

        return response()->json(['message' => 'Wilayah berhasil dihapus.']);
    }
}

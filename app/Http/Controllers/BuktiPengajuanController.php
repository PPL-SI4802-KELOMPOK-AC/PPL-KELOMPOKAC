<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBuktiPengajuanRequest;
use App\Models\BuktiPengajuan;
use App\Models\PengajuanPlts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class BuktiPengajuanController extends Controller
{
    private const MAX_FILES = 5;

    public function store(StoreBuktiPengajuanRequest $request)
    {
        $input = $request->validated();
        $draft = PengajuanPlts::whereKey($input['draft_id'])
            ->where('user_id', $request->user()->id)->firstOrFail();
        abort_unless($draft->status_terkini === 'draft', 409, 'Bukti hanya dapat ditambahkan selama pengajuan masih berupa draft.');

        $files = $request->file('photos', []);
        if ($draft->bukti()->count() + count($files) > self::MAX_FILES) {
            throw ValidationException::withMessages([
                'photos' => 'Maksimal lima foto bukti dapat disimpan untuk satu draft.',
            ]);
        }

        $disk = null;
        $savedPaths = [];

        try {
            $disk = Storage::disk('r2');
            $metadata = [];
            foreach ($files as $file) {
                $mime = $file->getMimeType();
                $extension = match ($mime) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    default => throw ValidationException::withMessages(['photos' => 'Format foto tidak didukung.']),
                };
                $path = $disk->putFileAs('bukti-pengajuan/'.$draft->id, $file, str()->uuid().'.'.$extension);
                if (! is_string($path) || $path === '') {
                    throw new \RuntimeException('Berkas tidak berhasil disimpan.');
                }
                $savedPaths[] = $path;
                $metadata[] = [
                    'pengajuan_id' => $draft->id,
                    'nama_file_asli' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'path_file' => $path,
                    'mime_type' => $mime,
                    'ukuran_byte' => $file->getSize(),
                    'keterangan' => null,
                ];
            }

            DB::transaction(function () use ($draft, $metadata) {
                $lockedDraft = PengajuanPlts::whereKey($draft->id)->lockForUpdate()->firstOrFail();
                abort_unless($lockedDraft->user_id === $draft->user_id && $lockedDraft->status_terkini === 'draft', 409,
                    'Pengajuan ini sudah tidak dapat menerima bukti. Muat ulang halaman.');
                if ($lockedDraft->bukti()->count() + count($metadata) > self::MAX_FILES) {
                    throw ValidationException::withMessages(['photos' => 'Maksimal lima foto bukti dapat disimpan untuk satu draft.']);
                }
                DB::table('bukti_pengajuan')->insert($metadata);
            });
        } catch (ValidationException $exception) {
            $this->cleanupNewObjects($disk, $savedPaths, (int) $draft->id);
            throw $exception;
        } catch (Throwable $exception) {
            $this->cleanupNewObjects($disk, $savedPaths, (int) $draft->id);
            Log::warning('Evidence upload failed.', [
                'draft_id' => (int) $draft->id,
                'reason' => class_basename($exception),
            ]);

            return redirect()->route('pengajuan.lokasi')
                ->withErrors(['photos' => 'Foto gagal disimpan. Periksa koneksi dan coba lagi.']);
        }

        return redirect()->route('pengajuan.lokasi')->with('evidence_saved', count($savedPaths).' foto bukti berhasil disimpan.');
    }

    public function show(Request $request, int $bukti)
    {
        $evidence = BuktiPengajuan::query()->whereKey($bukti)
            ->whereHas('pengajuan', fn ($query) => $query->where('user_id', $request->user()->id)
                ->where('status_terkini', 'draft'))
            ->firstOrFail();

        return redirect()->away(Storage::disk('r2')->temporaryUrl($evidence->path_file, now()->addMinutes(5)))
            ->header('Cache-Control', 'private, no-store');
    }

    private function cleanupNewObjects($disk, array $paths, int $draftId): void
    {
        foreach ($paths as $path) {
            try {
                if (! $disk?->delete($path)) {
                    Log::warning('Evidence object cleanup failed.', [
                        'draft_id' => $draftId,
                        'reason' => 'delete_returned_false',
                    ]);
                }
            } catch (Throwable $exception) {
                Log::warning('Evidence object cleanup failed.', [
                    'draft_id' => $draftId,
                    'reason' => class_basename($exception),
                ]);
            }
        }
    }
}

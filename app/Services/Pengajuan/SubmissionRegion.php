<?php

namespace App\Services\Pengajuan;

use Illuminate\Support\Facades\DB;

class SubmissionRegion
{
    public function __construct(private LocationCatalog $catalog) {}

    public function fromCode(string $code): int|string|null
    {
        $matches = DB::table('wilayah')->where('kode_referensi', $code)
            ->get(['id', 'kode_referensi'])
            ->filter(fn ($region) => $region->kode_referensi === $code);

        return $matches->count() === 1 ? $matches->first()->id : null;
    }

    public function fromAddress(string $address): int|string|null
    {
        $fields = $this->catalog->addressFields($address);
        $district = $fields['kabupaten'];
        $village = $this->catalog->selection($district, $fields['kecamatan'], $fields['desa']);
        if ($village === null || count(array_keys($this->catalog->districts(), $this->catalog->districts()[$district], true)) !== 1) {
            return null;
        }

        // The address parser returns the first match. Linking requires a unique hierarchy.
        $matches = array_filter($this->catalog->villages($district), fn ($candidate) =>
            $candidate['name'] === $village['name'] && $candidate['subdistrict'] === $fields['kecamatan']);

        return count($matches) === 1 ? $this->fromCode($village['code']) : null;
    }
}

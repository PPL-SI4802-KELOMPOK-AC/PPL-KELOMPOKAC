<?php

namespace App\Services\Pengajuan;

use App\Models\PengajuanPlts;

class PengajuanCompleteness
{
    private const MAX_PHOTOS = 5;

    private const MAX_PHOTO_BYTES = 5 * 1024 * 1024;

    private const PHOTO_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Return the same section-level errors for the review page and final submission.
     * Coordinates stay optional, but a saved point must contain both valid values.
     *
     * @return array<string, string>
     */
    public function errors(PengajuanPlts $draft, LocationCatalog $catalog): array
    {
        $errors = [];

        if (trim((string) $draft->ringkasan_pengajuan) === '') {
            $errors['data_pengajuan'] = 'Data utama pengajuan belum lengkap. Periksa kembali data lokasi dan simpan ulang.';
        }

        if (! $this->locationIsComplete($draft, $catalog)) {
            $errors['lokasi'] = 'Data lokasi belum lengkap. Lengkapi wilayah, alamat, dan pasangan koordinat jika diisi.';
        }

        if (! $this->evidenceIsComplete($draft)) {
            $errors['photos'] = 'Bukti wajib belum lengkap atau format/ukuran foto tidak sesuai. Unggah 1-5 foto JPG, PNG, atau WebP, maksimal 5 MB per foto.';
        }

        return $errors;
    }

    private function locationIsComplete(PengajuanPlts $draft, LocationCatalog $catalog): bool
    {
        $location = $draft->lokasi;
        if (! $location) {
            return false;
        }

        $address = $catalog->addressFields($location->alamat_deskriptif);
        if (trim($address['alamat']) === ''
            || $catalog->selection($address['kabupaten'], $address['kecamatan'], $address['desa']) === null) {
            return false;
        }

        $latitude = $location->latitude;
        $longitude = $location->longitude;
        if (($latitude === null) !== ($longitude === null)) {
            return false;
        }

        if ($latitude !== null && $longitude !== null) {
            return is_numeric($latitude) && is_numeric($longitude)
                && (float) $latitude >= -90 && (float) $latitude <= 90
                && (float) $longitude >= -180 && (float) $longitude <= 180;
        }

        return true;
    }

    private function evidenceIsComplete(PengajuanPlts $draft): bool
    {
        $photos = $draft->bukti;
        if ($photos->count() < 1 || $photos->count() > self::MAX_PHOTOS) {
            return false;
        }

        return $photos->every(fn ($photo) => in_array($photo->mime_type, self::PHOTO_MIME_TYPES, true)
            && (int) $photo->ukuran_byte > 0
            && (int) $photo->ukuran_byte <= self::MAX_PHOTO_BYTES);
    }
}

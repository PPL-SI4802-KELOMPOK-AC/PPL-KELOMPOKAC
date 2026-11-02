<?php

// CLI-only fixture, bukan route/backdoor autentikasi. Tidak menjalankan migrasi.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (PHP_SAPI !== 'cli' || getenv('PBI01_MYSQL_TESTS') !== '1' || ! $app->environment('local', 'testing')) {
    fwrite(STDERR, "Fixture hanya diizinkan pada local/testing dengan PBI01_MYSQL_TESTS=1.\n");
    exit(1);
}
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Support\Facades\DB;

if (($argv[1] ?? '') === 'create') {
    $fixture = DB::transaction(function () {
        $prefix = 'PBI-E2E-'.bin2hex(random_bytes(6));
        $password = bin2hex(random_bytes(16));
        $user = new User(['name' => 'Penguji browser PBI-01', 'email' => strtolower($prefix).'@example.test', 'password' => $password]);
        $user->role = 'pemerintah';
        $user->save();
        for ($i = 0; $i < 19; $i++) {
            $region = new Wilayah([
                'nama_wilayah' => $prefix.' Desa '.sprintf('%02d', $i), 'kode_referensi' => $prefix.'-'.$i,
                'tingkat_wilayah' => 'desa_kelurahan', 'alamat_deskriptif' => 'Alamat fixture browser, Kecamatan Uji, Kabupaten Uji',
                'kondisi_listrik' => $i === 18 ? 'sudah_berlistrik' : null,
            ]);
            $region->created_by = $user->id;
            $region->save();
        }

        return ['id' => $user->id, 'email' => $user->email, 'password' => $password, 'prefix' => $prefix];
    });
    echo json_encode($fixture);
} elseif (($argv[1] ?? '') === 'cleanup') {
    $id = $argv[2] ?? '';
    $email = $argv[3] ?? '';
    if (! ctype_digit($id) || ! preg_match('/^pbi-e2e-[a-f0-9]{12}@example\.test$/', $email)) {
        exit(1);
    }
    DB::transaction(function () use ($id, $email) {
        $user = User::whereKey($id)->where('email', $email)->where('name', 'Penguji browser PBI-01')->firstOrFail();
        Wilayah::where('created_by', $user->id)->delete();
        $user->delete();
    });
    echo "Fixture browser dibersihkan.\n";
} else {
    exit(1);
}

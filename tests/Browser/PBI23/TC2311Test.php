<?php

namespace Tests\Browser\PBI23;

/** Jalankan: php artisan dusk --filter=TC2311Test */
class TC2311Test extends PBI23TestCase
{
    public function test_tc_23_11_tolak_hapus_akun_berelasi(): void
    {
        $this->pendingDeletion('TC-23-11');
    }
}

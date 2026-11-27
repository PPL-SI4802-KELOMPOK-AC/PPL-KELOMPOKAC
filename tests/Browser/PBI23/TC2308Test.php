<?php

namespace Tests\Browser\PBI23;

/** Jalankan: php artisan dusk --filter=TC2308Test */
class TC2308Test extends PBI23TestCase
{
    public function test_tc_23_08_hapus_akun_memenuhi_syarat(): void
    {
        $this->pendingDeletion('TC-23-08');
    }
}

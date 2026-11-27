<?php

namespace Tests\Browser\PBI23;

/** Jalankan: php artisan dusk --filter=TC2314Test */
class TC2314Test extends PBI23TestCase
{
    public function test_tc_23_14_referensi_baru_saat_hapus(): void
    {
        $this->pendingDeletion('TC-23-14');
    }
}

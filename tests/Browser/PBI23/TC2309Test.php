<?php

namespace Tests\Browser\PBI23;

/** Jalankan: php artisan dusk --filter=TC2309Test */
class TC2309Test extends PBI23TestCase
{
    public function test_tc_23_09_batal_konfirmasi_hapus(): void
    {
        $this->pendingDeletion('TC-23-09');
    }
}

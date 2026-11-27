<?php

namespace Tests\Browser\PBI23;

/** Jalankan: php artisan dusk --filter=TC2315Test */
class TC2315Test extends PBI23TestCase
{
    public function test_tc_23_15_kelayakan_hapus_tidak_diketahui(): void
    {
        $this->pendingDeletion('TC-23-15');
    }
}

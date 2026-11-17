<?php

namespace Tests\Browser\PBI12;

use Illuminate\Support\Facades\Hash;

class TC12PasswordHashTest extends PBI12TestCase
{
    /** TC-PBI12-12: Password akun tersimpan dalam bentuk hash. */
    public function test_tc_pbi12_12_password_tersimpan_dalam_bentuk_hash(): void
    {
        foreach ([
            'masyarakat' => self::PASSWORD_MASYARAKAT,
            'pemerintah' => self::PASSWORD_PEMERINTAH,
            'admin' => self::PASSWORD_ADMIN,
        ] as $role => $password) {
            $user = $this->fixture[$role];

            $this->assertNotSame($password, $user->password);
            $this->assertTrue(Hash::check($password, $user->password));
        }
    }
}

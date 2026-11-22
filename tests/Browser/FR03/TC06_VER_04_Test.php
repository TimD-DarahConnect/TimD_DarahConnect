<?php

namespace Tests\Browser\FR03;

class TC06_VER_04_Test extends DuskTestCase
{
    /**
     * TC06_VER_04: Pengguna guest diarahkan ke /login dan role non-petugas UDD
     * (Pendonor / Pencari Darah) ditolak dengan halaman Error 403.
     */
    public function test_TC06_VER_04_guest_diarahkan_ke_login_dan_role_non_petugas_udd_ditolak_403(): void
    {
        $this->browse(function (Browser $browser) {
            // 1. Akses sebagai guest (belum login) diarahkan ke /login
            $browser->logout()
                ->visit('/udd/verifications')
                ->assertPathIs('/login')
                ->assertSee('Masuk ke DarahConnect');

            // 2. Akses sebagai Pendonor ditolak dengan status 403
            $browser->loginAs($this->donorUser)
                ->visit('/udd/verifications')
                ->assertSee('403');

            // 3. Akses sebagai Pencari Darah ditolak dengan status 403
            $browser->loginAs($this->seekerUser)
                ->visit('/udd/verifications')
                ->assertSee('403')
                ->screenshot('TC06_VER_04');
        });
    }
}

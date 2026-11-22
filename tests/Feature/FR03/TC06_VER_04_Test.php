<?php

namespace Tests\Feature\FR03;

use Tests\Feature\FR03\Concerns\InteractsWithMedicalVerificationData;
use Tests\TestCase;

class TC06_VER_04_Test extends TestCase
{
    use InteractsWithMedicalVerificationData;

    /**
     * TC06_VER_04: Pengguna guest diarahkan ke /login dan role non-petugas UDD
     * (pendonor / pencari_darah) ditolak dengan status 403 Forbidden.
     */
    public function test_TC06_VER_04_guest_diarahkan_ke_login_dan_role_non_petugas_udd_ditolak_403(): void
    {
        $guestResponse = $this->get(route('verifications.index'));
        $guestResponse->assertRedirect(route('auth.login'));

        $donorResponse = $this->actingAs($this->donorUser)->get(route('verifications.index'));
        $donorResponse->assertForbidden();

        $seekerResponse = $this->actingAs($this->seekerUser)->get(route('verifications.index'));
        $seekerResponse->assertForbidden();
    }
}

<?php

namespace Tests\Browser\FR03;

class TC07_APP_02_Test extends DuskTestCase
{
    /**
     * TC07_APP_02: Validasi batas waktu masa aktif tidak valid
     * (kosong, waktu di masa lalu, atau melebihi 14 hari ke depan).
     */
    public function test_TC07_APP_02_gagal_approve_jika_expires_at_kosong_masa_lalu_atau_lebih_14_hari(): void
    {
        $bloodRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-INVEXP',
        ], true);

        $this->browse(function (Browser $browser) use ($bloodRequest) {
            // 1. Kosongkan batas waktu masa aktif
            $browser->loginAs($this->uddStaff)
                ->visit('/udd/verifications/'.$bloodRequest->id)
                ->press('Setujui & Publikasikan')
                ->clear('#approveExpiresAt')
                ->press('Setujui & Publikasikan ke Pendonor')
                ->assertPathIs('/udd/verifications/'.$bloodRequest->id)
                ->assertSee('Batas waktu masa aktif publikasi permohonan wajib ditetapkan.');

            // 2. Isi waktu di masa lalu
            $browser->visit('/udd/verifications/'.$bloodRequest->id)
                ->press('Setujui & Publikasikan')
                ->type('#approveExpiresAt', now()->subHours(2)->format('Y-m-d\TH:i'))
                ->press('Setujui & Publikasikan ke Pendonor')
                ->assertSee('Batas waktu aktif publikasi harus berada di waktu mendatang.');

            // 3. Isi waktu melebihi 14 hari ke depan
            $browser->visit('/udd/verifications/'.$bloodRequest->id)
                ->press('Setujui & Publikasikan')
                ->type('#approveExpiresAt', now()->addDays(15)->format('Y-m-d\TH:i'))
                ->press('Setujui & Publikasikan ke Pendonor')
                ->assertSee('Masa aktif publikasi darurat maksimal 14 hari ke depan.')
                ->screenshot('TC07_APP_02');
        });
    }
}

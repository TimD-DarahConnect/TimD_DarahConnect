<?php

namespace Tests\Browser\FR03;

use App\Enums\BloodRequestStatus;

class TC07_APP_03_Test extends DuskTestCase
{
    /**
     * TC07_APP_03: Pencegahan persetujuan permohonan tanpa berkas surat rujukan dokter
     * atau kasus yang sudah diputuskan sebelumnya.
     */
    public function test_TC07_APP_03_gagal_approve_jika_tanpa_surat_rujukan_atau_status_bukan_pending(): void
    {
        $noDocRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-NODOC1',
        ], false);

        $alreadyActiveRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-ACTV01',
            'status' => BloodRequestStatus::ACTIVE->value,
            'verified_by' => $this->uddStaff->id,
            'verified_at' => now()->subHour(),
            'expires_at' => now()->addHours(24),
        ], true);

        $this->browse(function (Browser $browser) use ($noDocRequest, $alreadyActiveRequest) {
            // 1. Permohonan tanpa surat rujukan dokter: tombol Setujui & Publikasikan disabled
            $browser->loginAs($this->uddStaff)
                ->visit('/udd/verifications/'.$noDocRequest->id)
                ->assertSee('Berkas Surat Rujukan Belum Tersedia')
                ->assertDisabled('button[title*="Berkas surat rujukan"]');

            // 2. Permohonan yang sudah diputuskan (Aktif): tombol Setujui & Publikasikan tidak tampil lagi
            $browser->visit('/udd/verifications/'.$alreadyActiveRequest->id)
                ->assertSee('Putusan Terkini: Aktif')
                ->assertDontSee('Setujui & Publikasikan ke Pendonor')
                ->screenshot('TC07_APP_03');
        });
    }
}

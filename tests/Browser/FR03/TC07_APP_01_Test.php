<?php

namespace Tests\Browser\FR03;

use App\Enums\BloodType;
use App\Enums\Rhesus;

class TC07_APP_01_Test extends DuskTestCase
{
    /**
     * TC07_APP_01: Petugas UDD menyetujui permohonan valid dengan pilihan durasi
     * '+48 Jam (Standar Darurat)' hingga tayang di katalog pendonor (/donor/discovery).
     */
    public function test_TC07_APP_01_petugas_udd_menyetujui_permohonan_valid_dan_tayang_di_katalog_pendonor(): void
    {
        $bloodRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-APPR01',
            'patient_name' => 'Tn. Darmawan',
            'blood_type' => BloodType::O->value,
            'rhesus' => Rhesus::POSITIVE->value,
        ], true);

        $this->browse(function (Browser $browser) use ($bloodRequest) {
            $browser->loginAs($this->uddStaff)
                ->visit('/udd/verifications/'.$bloodRequest->id)
                ->press('Setujui & Publikasikan')
                ->waitFor('[data-hours="48"]')
                ->click('[data-hours="48"]')
                ->type('verification_notes', 'Surat rujukan DPJP dan indikasi Hb tervalidasi. Pendonor diarahkan ke loket BDRS.')
                ->press('Setujui & Publikasikan ke Pendonor')
                ->assertPathIs('/udd/verifications/'.$bloodRequest->id)
                ->assertSee('resmi disetujui dan dipublikasikan ke komunitas pendonor')
                ->assertSee('Putusan Terkini: Aktif');

            // Verifikasi permohonan tampil di katalog kebutuhan darah sisi Pendonor
            $browser->loginAs($this->donorUser)
                ->visit('/donor/discovery')
                ->assertSee($bloodRequest->request_code)
                ->screenshot('TC07_APP_01');
        });
    }
}

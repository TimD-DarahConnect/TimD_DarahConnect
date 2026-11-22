<?php

namespace Tests\Browser\FR03;

class TC06_VER_03_Test extends DuskTestCase
{
    /**
     * TC06_VER_03: Petugas UDD menekan 'Tinjau Kasus', mencentang 4 item SOP verifikasi,
     * dan memeriksa pratinjau/unduhan surat rujukan dokter.
     */
    public function test_TC06_VER_03_petugas_udd_membuka_detail_kasus_dan_mengakses_berkas_rujukan(): void
    {
        $bloodRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-DOC001',
            'patient_name' => 'Ny. Lestari Wulandari',
        ], true);

        $this->browse(function (Browser $browser) use ($bloodRequest) {
            $browser->loginAs($this->uddStaff)
                ->visit('/udd/verifications')
                ->clickLink('Tinjau Kasus')
                ->assertPathIs('/udd/verifications/'.$bloodRequest->id)
                ->assertSee('Ny. Lestari Wulandari')
                ->assertSee('Daftar Periksa Validasi Medis')
                ->assertSeeIn('#checklistBadge', '0 / 4 Diperiksa')
                ->check('#chk_identity')
                ->check('#chk_blood')
                ->check('#chk_clinical')
                ->check('#chk_doctor')
                ->assertSeeIn('#checklistBadge', '4 / 4 Diperiksa')
                ->assertSee('Surat Rujukan Dokter / BDRS')
                ->assertPresent('iframe')
                ->assertSee('Layar Penuh')
                ->assertSee('Unduh')
                ->screenshot('TC06_VER_03');
        });
    }
}

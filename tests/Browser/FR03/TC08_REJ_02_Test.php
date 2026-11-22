<?php

namespace Tests\Browser\FR03;

class TC08_REJ_02_Test extends DuskTestCase
{
    /**
     * TC08_REJ_02: Petugas UDD memilih 'Tolak Permohonan Secara Medis' dengan
     * alasan penolakan resmi >= 15 karakter hingga permohonan ditutup dari publikasi.
     */
    public function test_TC08_REJ_02_petugas_udd_menolak_permohonan_secara_medis_dan_tertutup_dari_publikasi(): void
    {
        $bloodRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-REJ001',
        ], true);

        $this->browse(function (Browser $browser) use ($bloodRequest) {
            $browser->loginAs($this->uddStaff)
                ->visit('/udd/verifications/'.$bloodRequest->id)
                ->press('Minta Revisi / Tolak')
                ->waitFor('#reviewNotesInput')
                ->radio('decision', 'rejected')
                ->assertSeeIn('#rejectSubmitLabel', 'Konfirmasi Tolak Permohonan')
                ->type('review_notes', 'Dokumen yang dilampirkan tidak valid / bukan merupakan surat pengantar permintaan transfusi darah resmi.')
                ->press('Konfirmasi Tolak Permohonan')
                ->assertPathIs('/udd/verifications/'.$bloodRequest->id)
                ->assertSee('resmi ditolak secara medis dan ditutup dari antrean publikasi.')
                ->assertSee('Putusan Terkini: Ditolak Medis');

            // Verifikasi kasus yang ditolak tidak tayang di katalog pendonor
            $browser->loginAs($this->donorUser)
                ->visit('/donor/discovery')
                ->assertDontSee($bloodRequest->request_code)
                ->screenshot('TC08_REJ_02');
        });
    }
}

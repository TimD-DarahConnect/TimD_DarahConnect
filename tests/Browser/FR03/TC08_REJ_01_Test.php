<?php

namespace Tests\Browser\FR03;

class TC08_REJ_01_Test extends DuskTestCase
{
    /**
     * TC08_REJ_01: Petugas UDD memilih 'Minta Revisi Data / Perbaikan Berkas'
     * menggunakan template catatan klinis cepat atau catatan manual >= 15 karakter.
     */
    public function test_TC08_REJ_01_petugas_udd_meminta_revisi_data_dengan_catatan_resmi(): void
    {
        $bloodRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-REV001',
        ], true);

        $this->browse(function (Browser $browser) use ($bloodRequest) {
            $browser->loginAs($this->uddStaff)
                ->visit('/udd/verifications/'.$bloodRequest->id)
                ->press('Minta Revisi / Tolak')
                ->waitFor('#reviewNotesInput')
                ->radio('decision', 'need_revision')
                ->click('.js-reject-note-chip')
                ->press('Kirim Permintaan Revisi ke Pemohon')
                ->assertPathIs('/udd/verifications/'.$bloodRequest->id)
                ->assertSee('dikembalikan kepada pemohon dengan status "Perlu Revisi Data"')
                ->assertSee('Putusan Terkini: Perlu Revisi Data')
                ->screenshot('TC08_REJ_01');
        });
    }
}

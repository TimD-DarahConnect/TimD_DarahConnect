<?php

namespace Tests\Browser\FR03;

class TC08_REJ_03_Test extends DuskTestCase
{
    /**
     * TC08_REJ_03: Validasi catatan resmi kosong atau kurang dari 15 karakter
     * pada formulir 'Minta Revisi atau Tolak Permohonan'.
     */
    public function test_TC08_REJ_03_gagal_revisi_atau_tolak_jika_catatan_kosong_atau_kurang_15_karakter(): void
    {
        $bloodRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-INVNOTE',
        ], true);

        $this->browse(function (Browser $browser) use ($bloodRequest) {
            // 1. Kolom catatan resmi dikosongkan
            $browser->loginAs($this->uddStaff)
                ->visit('/udd/verifications/'.$bloodRequest->id)
                ->press('Minta Revisi / Tolak')
                ->clear('#reviewNotesInput')
                ->press('Kirim Permintaan Revisi ke Pemohon')
                ->assertPathIs('/udd/verifications/'.$bloodRequest->id)
                ->assertSee('Catatan resmi verifikasi medis wajib diisi sebagai acuan perbaikan atau alasan penolakan bagi pemohon.');

            // 2. Kolom catatan resmi diisi kurang dari 15 karakter ('Foto buram')
            $browser->visit('/udd/verifications/'.$bloodRequest->id)
                ->press('Minta Revisi / Tolak')
                ->type('review_notes', 'Foto buram')
                ->press('Kirim Permintaan Revisi ke Pemohon')
                ->assertPathIs('/udd/verifications/'.$bloodRequest->id)
                ->assertSee('Catatan resmi verifikasi minimal terdiri dari 15 karakter agar instruksi medis jelas.')
                ->screenshot('TC08_REJ_03');
        });
    }
}

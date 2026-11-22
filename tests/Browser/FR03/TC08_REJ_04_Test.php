<?php

namespace Tests\Browser\FR03;

use App\Enums\BloodRequestStatus;

class TC08_REJ_04_Test extends DuskTestCase
{
    /**
     * TC08_REJ_04: Pembatasan akses akun non-petugas UDD (Error 403)
     * dan pencegahan tindak lanjut ulang pada kasus yang sudah diputuskan.
     */
    public function test_TC08_REJ_04_role_non_petugas_ditolak_403_dan_kasus_sudah_diproses_tidak_bisa_diulang(): void
    {
        $pendingRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-PEND04',
        ], true);

        $processedRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-PROC04',
            'status' => BloodRequestStatus::REJECTED->value,
            'verified_by' => $this->uddStaff->id,
            'verified_at' => now()->subHour(),
            'verification_notes' => 'Berkas rujukan tidak memenuhi standar klinis transfusi.',
        ], true);

        $this->browse(function (Browser $browser) use ($pendingRequest, $processedRequest) {
            // 1. Akun non-petugas UDD (Pendonor & Pencari Darah) ditolak Error 403 saat membuka halaman detail
            $browser->loginAs($this->donorUser)
                ->visit('/udd/verifications/'.$pendingRequest->id)
                ->assertSee('403');

            $browser->loginAs($this->seekerUser)
                ->visit('/udd/verifications/'.$pendingRequest->id)
                ->assertSee('403');

            // 2. Kasus yang sudah diputuskan tidak lagi menampilkan tombol tindak lanjut revisi/penolakan
            $browser->loginAs($this->uddStaff)
                ->visit('/udd/verifications/'.$processedRequest->id)
                ->assertSee('Putusan Terkini: Ditolak Medis')
                ->assertDontSee('Minta Revisi / Tolak')
                ->assertDontSee('Setujui & Publikasikan')
                ->screenshot('TC08_REJ_04');
        });
    }
}

<?php

namespace Tests\Feature\FR03;

use App\Enums\BloodRequestStatus;
use App\Enums\VerificationDecision;
use Tests\Feature\FR03\Concerns\InteractsWithMedicalVerificationData;
use Tests\TestCase;

class TC08_REJ_04_Test extends TestCase
{
    use InteractsWithMedicalVerificationData;

    /**
     * TC08_REJ_04: Role non-petugas UDD ditolak (403) dan kasus yang sudah diproses tidak bisa ditindaklanjuti ulang.
     */
    public function test_TC08_REJ_04_role_non_petugas_ditolak_403_dan_kasus_sudah_diproses_tidak_bisa_diulang(): void
    {
        $pendingRequest = $this->createBloodRequest([], true);
        $validNotes = 'Surat rujukan belum mencantumkan tanda tangan dokter penanggung jawab (DPJP) atau stempel resmi instalasi RS.';

        // 1. Non-petugas UDD (Pendonor & Pencari Darah) ditolak 403
        $donorRejectResponse = $this->actingAs($this->donorUser)
            ->post(route('verifications.reject', $pendingRequest), [
                'decision' => VerificationDecision::REJECTED->value,
                'review_notes' => $validNotes,
            ]);
        $donorRejectResponse->assertForbidden();

        $seekerRejectResponse = $this->actingAs($this->seekerUser)
            ->post(route('verifications.reject', $pendingRequest), [
                'decision' => VerificationDecision::NEED_REVISION->value,
                'review_notes' => $validNotes,
            ]);
        $seekerRejectResponse->assertForbidden();

        // 2. Kasus yang sudah diproses (misal sudah Ditolak / Aktif) tidak bisa ditindaklanjuti ulang
        $processedRequest = $this->createBloodRequest([
            'status' => BloodRequestStatus::REJECTED->value,
            'verified_by' => $this->uddStaff->id,
            'verified_at' => now()->subHour(),
        ], true);

        $reprocessResponse = $this->actingAs($this->uddStaff)
            ->post(route('verifications.reject', $processedRequest), [
                'decision' => VerificationDecision::NEED_REVISION->value,
                'review_notes' => $validNotes,
            ]);

        $reprocessResponse->assertRedirect(route('verifications.show', $processedRequest));
        $reprocessResponse->assertSessionHas('warning');
        $this->assertEquals(BloodRequestStatus::REJECTED, $processedRequest->fresh()->status);
    }
}

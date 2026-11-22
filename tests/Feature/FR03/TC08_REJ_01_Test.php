<?php

namespace Tests\Feature\FR03;

use App\Enums\BloodRequestStatus;
use App\Enums\VerificationDecision;
use Tests\Feature\FR03\Concerns\InteractsWithMedicalVerificationData;
use Tests\TestCase;

class TC08_REJ_01_Test extends TestCase
{
    use InteractsWithMedicalVerificationData;

    /**
     * TC08_REJ_01: Petugas UDD memilih need_revision dengan catatan resmi >= 15 karakter,
     * status berubah menjadi need_revision dan expires_at menjadi null.
     */
    public function test_TC08_REJ_01_petugas_udd_meminta_revisi_data_dengan_catatan_resmi(): void
    {
        $bloodRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-REV001',
        ], true);

        $revisionNotes = 'Lampiran foto/scan surat rujukan dokter buram atau terpotong, mohon unggah ulang dokumen yang terbaca jelas.';

        $response = $this->actingAs($this->uddStaff)
            ->post(route('verifications.reject', $bloodRequest), [
                'decision' => VerificationDecision::NEED_REVISION->value,
                'review_notes' => $revisionNotes,
            ]);

        $response->assertRedirect(route('verifications.show', $bloodRequest));
        $response->assertSessionHas('success');

        $updated = $bloodRequest->fresh();
        $this->assertEquals(BloodRequestStatus::NEED_REVISION, $updated->status);
        $this->assertNull($updated->expires_at);
        $this->assertEquals($revisionNotes, $updated->verification_notes);

        $this->assertDatabaseHas('request_verifications', [
            'blood_request_id' => $bloodRequest->id,
            'reviewer_id' => $this->uddStaff->id,
            'decision' => VerificationDecision::NEED_REVISION->value,
            'previous_status' => BloodRequestStatus::PENDING_VERIFICATION->value,
            'resulting_status' => BloodRequestStatus::NEED_REVISION->value,
            'notes' => $revisionNotes,
        ]);
    }
}

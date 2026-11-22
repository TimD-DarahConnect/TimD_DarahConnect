<?php

namespace Tests\Feature\FR03;

use App\Enums\BloodRequestStatus;
use App\Enums\VerificationDecision;
use Tests\Feature\FR03\Concerns\InteractsWithMedicalVerificationData;
use Tests\TestCase;

class TC08_REJ_02_Test extends TestCase
{
    use InteractsWithMedicalVerificationData;

    /**
     * TC08_REJ_02: Petugas UDD memilih rejected dengan catatan resmi >= 15 karakter,
     * status berubah menjadi rejected dan tertutup dari publikasi.
     */
    public function test_TC08_REJ_02_petugas_udd_menolak_permohonan_secara_medis_dan_tertutup_dari_publikasi(): void
    {
        $bloodRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-REJ001',
        ], true);

        $rejectionNotes = 'Dokumen yang dilampirkan tidak valid / bukan merupakan surat pengantar permintaan transfusi darah resmi.';

        $response = $this->actingAs($this->uddStaff)
            ->post(route('verifications.reject', $bloodRequest), [
                'decision' => VerificationDecision::REJECTED->value,
                'review_notes' => $rejectionNotes,
            ]);

        $response->assertRedirect(route('verifications.show', $bloodRequest));
        $response->assertSessionHas('success');

        $updated = $bloodRequest->fresh();
        $this->assertEquals(BloodRequestStatus::REJECTED, $updated->status);
        $this->assertNull($updated->expires_at);
        $this->assertEquals($rejectionNotes, $updated->verification_notes);

        $this->assertDatabaseHas('request_verifications', [
            'blood_request_id' => $bloodRequest->id,
            'reviewer_id' => $this->uddStaff->id,
            'decision' => VerificationDecision::REJECTED->value,
            'previous_status' => BloodRequestStatus::PENDING_VERIFICATION->value,
            'resulting_status' => BloodRequestStatus::REJECTED->value,
            'notes' => $rejectionNotes,
        ]);

        session()->forget('success');

        $discoveryResponse = $this->actingAs($this->donorUser)->get(route('discovery.index'));
        $discoveryResponse->assertOk();
        $discoveryResponse->assertDontSee($bloodRequest->request_code);
    }
}

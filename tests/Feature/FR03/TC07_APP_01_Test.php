<?php

namespace Tests\Feature\FR03;

use App\Enums\BloodRequestStatus;
use App\Enums\BloodType;
use App\Enums\Rhesus;
use App\Enums\VerificationDecision;
use Tests\Feature\FR03\Concerns\InteractsWithMedicalVerificationData;
use Tests\TestCase;

class TC07_APP_01_Test extends TestCase
{
    use InteractsWithMedicalVerificationData;

    /**
     * TC07_APP_01: Petugas UDD menyetujui permohonan valid dengan expires_at (+48 jam),
     * status berubah menjadi active, tercatat di request_verifications, dan permohonan tampil di /donor/discovery.
     */
    public function test_TC07_APP_01_petugas_udd_menyetujui_permohonan_valid_dan_tayang_di_katalog_pendonor(): void
    {
        $bloodRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-APPR01',
            'patient_name' => 'Tn. Darmawan',
            'blood_type' => BloodType::O->value,
            'rhesus' => Rhesus::POSITIVE->value,
        ], true);

        $expiresAt = now()->addHours(48)->startOfMinute()->format('Y-m-d\TH:i');
        $notes = 'Surat rujukan DPJP dan indikasi Hb tervalidasi. Pendonor diarahkan ke loket BDRS.';

        $approveResponse = $this->actingAs($this->uddStaff)
            ->post(route('verifications.approve', $bloodRequest), [
                'expires_at' => $expiresAt,
                'verification_notes' => $notes,
            ]);

        $approveResponse->assertRedirect(route('verifications.show', $bloodRequest));
        $approveResponse->assertSessionHas('success');

        $this->assertDatabaseHas('blood_requests', [
            'id' => $bloodRequest->id,
            'status' => BloodRequestStatus::ACTIVE->value,
            'verified_by' => $this->uddStaff->id,
            'verification_notes' => $notes,
        ]);

        $this->assertDatabaseHas('request_verifications', [
            'blood_request_id' => $bloodRequest->id,
            'reviewer_id' => $this->uddStaff->id,
            'decision' => VerificationDecision::APPROVED->value,
            'previous_status' => BloodRequestStatus::PENDING_VERIFICATION->value,
            'resulting_status' => BloodRequestStatus::ACTIVE->value,
            'notes' => $notes,
        ]);

        session()->forget('success');

        $discoveryResponse = $this->actingAs($this->donorUser)->get(route('discovery.index'));
        $discoveryResponse->assertOk();
        $discoveryResponse->assertSee($bloodRequest->request_code);
    }
}

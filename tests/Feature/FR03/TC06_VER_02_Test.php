<?php

namespace Tests\Feature\FR03;

use App\Enums\BloodType;
use App\Enums\UrgencyLevel;
use Tests\Feature\FR03\Concerns\InteractsWithMedicalVerificationData;
use Tests\TestCase;

class TC06_VER_02_Test extends TestCase
{
    use InteractsWithMedicalVerificationData;

    /**
     * TC06_VER_02: Pencarian (q) dan filter (urgency, blood_type) menyaring daftar antrean dengan benar.
     */
    public function test_TC06_VER_02_pencarian_dan_filter_menyaring_daftar_antrean_dengan_benar(): void
    {
        $matchingRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-MATCH1',
            'patient_name' => 'Hendrik Setiawan',
            'urgency_level' => UrgencyLevel::CRITICAL->value,
            'blood_type' => BloodType::O->value,
        ]);

        $otherBloodTypeRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-OTHER1',
            'patient_name' => 'Rina Permata',
            'urgency_level' => UrgencyLevel::CRITICAL->value,
            'blood_type' => BloodType::A->value,
        ]);

        $otherUrgencyRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-OTHER2',
            'patient_name' => 'Bagus Saputra',
            'urgency_level' => UrgencyLevel::STANDARD->value,
            'blood_type' => BloodType::O->value,
        ]);

        $response = $this->actingAs($this->uddStaff)->get(route('verifications.index', [
            'q' => 'Hendrik',
            'urgency' => UrgencyLevel::CRITICAL->value,
            'blood_type' => BloodType::O->value,
        ]));

        $response->assertOk();
        $response->assertSee($matchingRequest->request_code);
        $response->assertSee('Hendrik Setiawan');
        $response->assertDontSee($otherBloodTypeRequest->request_code);
        $response->assertDontSee($otherUrgencyRequest->request_code);
    }
}

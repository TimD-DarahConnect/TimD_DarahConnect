<?php

namespace Tests\Feature\FR03;

use App\Enums\UrgencyLevel;
use Tests\Feature\FR03\Concerns\InteractsWithMedicalVerificationData;
use Tests\TestCase;

class TC06_VER_01_Test extends TestCase
{
    use InteractsWithMedicalVerificationData;

    /**
     * TC06_VER_01: Petugas UDD mengakses /udd/verifications dan melihat daftar permohonan
     * pending_verification terurut berdasarkan prioritas klinis (urgensi critical & anemia berat Hb < 7.0 di urutan teratas).
     */
    public function test_TC06_VER_01_petugas_udd_melihat_antrean_terurut_berdasarkan_prioritas_klinis(): void
    {
        $standardRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-STND03',
            'patient_name' => 'Pasien Standar Elektif',
            'urgency_level' => UrgencyLevel::STANDARD->value,
            'hemoglobin_level' => 10.5,
            'needed_at' => now()->addHours(36),
        ]);

        $urgentRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-URGT02',
            'patient_name' => 'Pasien Mendesak',
            'urgency_level' => UrgencyLevel::URGENT->value,
            'hemoglobin_level' => 8.2,
            'needed_at' => now()->addHours(12),
        ]);

        $criticalLowHbRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-CRIT01',
            'patient_name' => 'Pasien Kritis Anemia Berat',
            'urgency_level' => UrgencyLevel::CRITICAL->value,
            'hemoglobin_level' => 5.4,
            'needed_at' => now()->addHours(4),
        ]);

        $criticalModerateHbRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-CRIT02',
            'patient_name' => 'Pasien Kritis Hb Sedang',
            'urgency_level' => UrgencyLevel::CRITICAL->value,
            'hemoglobin_level' => 7.8,
            'needed_at' => now()->addHours(5),
        ]);

        $response = $this->actingAs($this->uddStaff)->get(route('verifications.index'));

        $response->assertOk();
        $response->assertSee('Antrean Permohonan Darah Darurat');
        $response->assertSeeInOrder([
            $criticalLowHbRequest->request_code,
            $criticalModerateHbRequest->request_code,
            $urgentRequest->request_code,
            $standardRequest->request_code,
        ]);
    }
}

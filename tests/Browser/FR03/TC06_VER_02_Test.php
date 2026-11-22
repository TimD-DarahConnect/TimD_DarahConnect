<?php

namespace Tests\Browser\FR03;

use App\Enums\BloodType;
use App\Enums\UrgencyLevel;

class TC06_VER_02_Test extends DuskTestCase
{
    /**
     * TC06_VER_02: Petugas UDD mencari permohonan berdasarkan kata kunci,
     * memilih filter urgensi dan golongan darah, lalu menekan tombol 'Saring'.
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

        $this->browse(function (Browser $browser) use ($matchingRequest, $otherBloodTypeRequest, $otherUrgencyRequest) {
            $browser->loginAs($this->uddStaff)
                ->visit('/udd/verifications')
                ->type('q', 'Hendrik')
                ->select('urgency', UrgencyLevel::CRITICAL->value)
                ->select('blood_type', BloodType::O->value)
                ->press('Saring')
                ->assertPathIs('/udd/verifications')
                ->assertQueryStringHas('q', 'Hendrik')
                ->assertQueryStringHas('urgency', 'critical')
                ->assertQueryStringHas('blood_type', 'O')
                ->assertSee($matchingRequest->request_code)
                ->assertSee('Hendrik Setiawan')
                ->assertDontSee($otherBloodTypeRequest->request_code)
                ->assertDontSee($otherUrgencyRequest->request_code)
                ->screenshot('TC06_VER_02');
        });
    }
}

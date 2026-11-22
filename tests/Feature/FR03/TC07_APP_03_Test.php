<?php

namespace Tests\Feature\FR03;

use App\Enums\BloodRequestStatus;
use Tests\Feature\FR03\Concerns\InteractsWithMedicalVerificationData;
use Tests\TestCase;

class TC07_APP_03_Test extends TestCase
{
    use InteractsWithMedicalVerificationData;

    /**
     * TC07_APP_03: Gagal approve jika permohonan belum memiliki surat rujukan dokter
     * atau statusnya bukan pending_verification.
     */
    public function test_TC07_APP_03_gagal_approve_jika_tanpa_surat_rujukan_atau_status_bukan_pending(): void
    {
        $validExpiresAt = now()->addHours(48)->format('Y-m-d\TH:i');

        // 1. Permohonan tanpa surat rujukan dokter
        $noDocRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-NODOC1',
        ], false);

        $noDocResponse = $this->actingAs($this->uddStaff)
            ->post(route('verifications.approve', $noDocRequest), [
                'expires_at' => $validExpiresAt,
            ]);

        $noDocResponse->assertRedirect(route('verifications.show', $noDocRequest));
        $noDocResponse->assertSessionHas('error');
        $this->assertEquals(
            BloodRequestStatus::PENDING_VERIFICATION,
            $noDocRequest->fresh()->status
        );

        // 2. Permohonan yang sudah diproses (status active)
        $alreadyActiveRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-ACTV01',
            'status' => BloodRequestStatus::ACTIVE->value,
            'verified_by' => $this->uddStaff->id,
            'verified_at' => now()->subHour(),
            'expires_at' => now()->addHours(24),
        ], true);

        $reApproveResponse = $this->actingAs($this->uddStaff)
            ->post(route('verifications.approve', $alreadyActiveRequest), [
                'expires_at' => $validExpiresAt,
            ]);

        $reApproveResponse->assertRedirect(route('verifications.show', $alreadyActiveRequest));
        $reApproveResponse->assertSessionHas('warning');
    }
}

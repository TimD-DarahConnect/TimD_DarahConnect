<?php

namespace Tests\Feature\FR03;

use App\Enums\BloodRequestStatus;
use Tests\Feature\FR03\Concerns\InteractsWithMedicalVerificationData;
use Tests\TestCase;

class TC07_APP_02_Test extends TestCase
{
    use InteractsWithMedicalVerificationData;

    /**
     * TC07_APP_02: Gagal approve jika expires_at kosong, berada di masa lalu, atau melebihi 14 hari ke depan.
     */
    public function test_TC07_APP_02_gagal_approve_jika_expires_at_kosong_masa_lalu_atau_lebih_14_hari(): void
    {
        $bloodRequest = $this->createBloodRequest([], true);

        // 1. expires_at kosong
        $emptyResponse = $this->actingAs($this->uddStaff)
            ->from(route('verifications.show', $bloodRequest))
            ->post(route('verifications.approve', $bloodRequest), [
                'expires_at' => '',
            ]);

        $emptyResponse->assertRedirect(route('verifications.show', $bloodRequest));
        $emptyResponse->assertSessionHasErrors([
            'expires_at' => 'Batas waktu masa aktif publikasi permohonan wajib ditetapkan.',
        ]);

        // 2. expires_at di masa lalu
        $pastResponse = $this->actingAs($this->uddStaff)
            ->from(route('verifications.show', $bloodRequest))
            ->post(route('verifications.approve', $bloodRequest), [
                'expires_at' => now()->subHours(2)->format('Y-m-d\TH:i'),
            ]);

        $pastResponse->assertRedirect(route('verifications.show', $bloodRequest));
        $pastResponse->assertSessionHasErrors([
            'expires_at' => 'Batas waktu aktif publikasi harus berada di waktu mendatang.',
        ]);

        // 3. expires_at lebih dari 14 hari ke depan
        $tooFarResponse = $this->actingAs($this->uddStaff)
            ->from(route('verifications.show', $bloodRequest))
            ->post(route('verifications.approve', $bloodRequest), [
                'expires_at' => now()->addDays(15)->format('Y-m-d\TH:i'),
            ]);

        $tooFarResponse->assertRedirect(route('verifications.show', $bloodRequest));
        $tooFarResponse->assertSessionHasErrors([
            'expires_at' => 'Masa aktif publikasi darurat maksimal 14 hari ke depan.',
        ]);

        $this->assertDatabaseHas('blood_requests', [
            'id' => $bloodRequest->id,
            'status' => BloodRequestStatus::PENDING_VERIFICATION->value,
        ]);
    }
}

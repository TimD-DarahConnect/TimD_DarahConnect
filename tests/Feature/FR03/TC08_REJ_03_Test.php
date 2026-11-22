<?php

namespace Tests\Feature\FR03;

use App\Enums\BloodRequestStatus;
use App\Enums\VerificationDecision;
use Tests\Feature\FR03\Concerns\InteractsWithMedicalVerificationData;
use Tests\TestCase;

class TC08_REJ_03_Test extends TestCase
{
    use InteractsWithMedicalVerificationData;

    /**
     * TC08_REJ_03: Gagal jika review_notes dikosongkan atau kurang dari 15 karakter.
     */
    public function test_TC08_REJ_03_gagal_revisi_atau_tolak_jika_catatan_kosong_atau_kurang_15_karakter(): void
    {
        $bloodRequest = $this->createBloodRequest([], true);

        // 1. review_notes kosong
        $emptyNotesResponse = $this->actingAs($this->uddStaff)
            ->from(route('verifications.show', $bloodRequest))
            ->post(route('verifications.reject', $bloodRequest), [
                'decision' => VerificationDecision::NEED_REVISION->value,
                'review_notes' => '',
            ]);

        $emptyNotesResponse->assertRedirect(route('verifications.show', $bloodRequest));
        $emptyNotesResponse->assertSessionHasErrors([
            'review_notes' => 'Catatan resmi verifikasi medis wajib diisi sebagai acuan perbaikan atau alasan penolakan bagi pemohon.',
        ]);

        // 2. review_notes kurang dari 15 karakter
        $shortNotesResponse = $this->actingAs($this->uddStaff)
            ->from(route('verifications.show', $bloodRequest))
            ->post(route('verifications.reject', $bloodRequest), [
                'decision' => VerificationDecision::REJECTED->value,
                'review_notes' => 'Foto buram',
            ]);

        $shortNotesResponse->assertRedirect(route('verifications.show', $bloodRequest));
        $shortNotesResponse->assertSessionHasErrors([
            'review_notes' => 'Catatan resmi verifikasi minimal terdiri dari 15 karakter agar instruksi medis jelas.',
        ]);

        $this->assertEquals(
            BloodRequestStatus::PENDING_VERIFICATION,
            $bloodRequest->fresh()->status
        );
    }
}

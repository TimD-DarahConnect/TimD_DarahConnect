<?php

namespace Tests\Feature\FR03;

use Tests\Feature\FR03\Concerns\InteractsWithMedicalVerificationData;
use Tests\TestCase;

class TC06_VER_03_Test extends TestCase
{
    use InteractsWithMedicalVerificationData;

    /**
     * TC06_VER_03: Petugas UDD membuka detail kasus (/udd/verifications/{id})
     * dan mengunduh/membuka berkas surat rujukan (/udd/verifications/{id}/document).
     */
    public function test_TC06_VER_03_petugas_udd_membuka_detail_kasus_dan_mengakses_berkas_rujukan(): void
    {
        $bloodRequest = $this->createBloodRequest([
            'request_code' => 'DC-REQ-DOC001',
            'patient_name' => 'Ny. Lestari Wulandari',
        ], true);

        $detailResponse = $this->actingAs($this->uddStaff)
            ->get(route('verifications.show', $bloodRequest));

        $detailResponse->assertOk();
        $detailResponse->assertSee('Ny. Lestari Wulandari');
        $detailResponse->assertSee('Daftar Periksa Validasi Medis');
        $detailResponse->assertSee('0 / 4 Diperiksa');
        $detailResponse->assertSee('Surat Rujukan Dokter / BDRS');

        // Pratinjau dokumen (Layar Penuh / inline)
        $inlineDocResponse = $this->actingAs($this->uddStaff)
            ->get(route('verifications.document', $bloodRequest));

        $inlineDocResponse->assertOk();
        $this->assertStringContainsString(
            'inline',
            (string) $inlineDocResponse->headers->get('Content-Disposition')
        );

        // Unduh dokumen (Unduh / attachment)
        $downloadDocResponse = $this->actingAs($this->uddStaff)
            ->get(route('verifications.document', ['bloodRequest' => $bloodRequest->id, 'download' => 1]));

        $downloadDocResponse->assertOk();
        $this->assertStringContainsString(
            'attachment',
            (string) $downloadDocResponse->headers->get('Content-Disposition')
        );
    }
}

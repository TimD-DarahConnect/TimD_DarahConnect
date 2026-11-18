<?php

namespace Tests\Browser\EmergencyRequest;

use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CreateEmergencyRequestTest extends DuskTestCase
{
    use DatabaseTruncation;

    protected bool $seed = true;

    /**
     * TC03_ERQ_01: Pencari Darah mengajukan permohonan darah darurat lengkap disertai surat rujukan.
     */
    public function test_seeker_can_submit_complete_emergency_request(): void
    {
        $seeker = User::where('email', 'pencari@darahconnect.id')->firstOrFail();
        $hospital = Facility::hospitals()->firstOrFail();
        $pdfPath = storage_path('app/private/referral-letters/seed/surat-rujukan-contoh.pdf');

        $this->browse(function (Browser $browser) use ($seeker, $hospital, $pdfPath) {
            $browser->loginAs($seeker)
                ->visit('/emergency-requests/create')
                ->type('patient_name', 'Siti Aminah')
                ->type('patient_age', '34')
                ->select('patient_gender', 'P')
                ->type('clinical_diagnosis', 'Perdarahan Post-Partum')
                ->type('hemoglobin_level', '6.4')
                ->type('bags_needed', '3')
                ->select('urgency_level', 'critical')
                ->value('#needed_at', now()->addDay()->format('Y-m-d\TH:i'))
                ->select('facility_id', (string) $hospital->id)
                ->type('ward_room', 'Ruang ICU Bed 03')
                ->type('doctor_name', 'dr. Lorem Ipsum, Sp.Pg')
                ->attach('referral_letter', $pdfPath)
                ->pause(1000)
                ->press('Ajukan ke Verifikasi UDD')
                ->pause(2000)
                ->assertSee('Siti Aminah')
                ->assertSee('Menunggu Verifikasi');
        });
    }

    /**
     * TC03_ERQ_02: Pencari Darah menyimpan permohonan sebagai Draf tanpa surat rujukan dokter.
     */
    public function test_seeker_can_save_emergency_request_as_draft(): void
    {
        $seeker = User::where('email', 'pencari@darahconnect.id')->firstOrFail();
        $hospital = Facility::hospitals()->firstOrFail();

        $this->browse(function (Browser $browser) use ($seeker, $hospital) {
            $browser->loginAs($seeker)
                ->visit('/emergency-requests/create')
                ->type('patient_name', 'Ahmad Fauzi Draf')
                ->type('bags_needed', '2')
                ->select('urgency_level', 'urgent')
                ->select('facility_id', (string) $hospital->id)
                ->press('Simpan sebagai Draf')
                ->pause(2000)
                ->assertSee('Draf permohonan darah')
                ->assertSee('Ahmad Fauzi Draf');
        });
    }

    /**
     * TC03_ERQ_03: Pengajuan verifikasi ditolak jika surat rujukan dokter belum dilampirkan.
     */
    public function test_final_submission_requires_referral_letter(): void
    {
        $seeker = User::where('email', 'pencari@darahconnect.id')->firstOrFail();
        $hospital = Facility::hospitals()->firstOrFail();

        $this->browse(function (Browser $browser) use ($seeker, $hospital) {
            $browser->loginAs($seeker)
                ->visit('/emergency-requests/create')
                ->type('patient_name', 'Pasien Tanpa Rujukan')
                ->type('patient_age', '40')
                ->select('patient_gender', 'L')
                ->type('clinical_diagnosis', 'Anemia Aplastik')
                ->type('hemoglobin_level', '6.8')
                ->type('bags_needed', '2')
                ->value('#needed_at', now()->addDay()->format('Y-m-d\TH:i'))
                ->select('facility_id', (string) $hospital->id)
                ->type('ward_room', 'Bangsal Melati 02')
                ->type('doctor_name', 'dr. Hendra, Sp.PD')
                ->press('Ajukan ke Verifikasi UDD')
                ->pause(2000)
                ->assertPathIs('/emergency-requests/create')
                ->assertSee('Berkas surat rujukan dokter wajib dilampirkan');
        });
    }

    /**
     * TC03_ERQ_04: Validasi batas nilai klinis (kantong > 20 dan Hb < 1.5 g/dL).
     */
    public function test_form_validates_bags_and_hemoglobin_boundaries(): void
    {
        $seeker = User::where('email', 'pencari@darahconnect.id')->firstOrFail();
        $hospital = Facility::hospitals()->firstOrFail();
        $pdfPath = storage_path('app/private/referral-letters/seed/surat-rujukan-contoh.pdf');

        $this->browse(function (Browser $browser) use ($seeker, $hospital, $pdfPath) {
            $browser->loginAs($seeker)
                ->visit('/emergency-requests/create')
                ->type('patient_name', 'Uji Batas Klinis')
                ->type('patient_age', '29')
                ->select('patient_gender', 'P')
                ->type('clinical_diagnosis', 'Thalassemia Mayor')
                ->type('hemoglobin_level', '0.5')
                ->type('bags_needed', '25')
                ->value('#needed_at', now()->addDay()->format('Y-m-d\TH:i'))
                ->select('facility_id', (string) $hospital->id)
                ->type('ward_room', 'Ruang Thalassemia 01')
                ->type('doctor_name', 'dr. Dewi, Sp.A')
                ->attach('referral_letter', $pdfPath)
                ->press('Ajukan ke Verifikasi UDD')
                ->assertPathIs('/emergency-requests/create')
                ->assertSee('Nilai kadar hemoglobin tidak valid (minimal 1.5 g/dL).')
                ->assertSee('Maksimal permohonan dalam satu pengajuan adalah 20 kantong darah.');
        });
    }

    /**
     * TC03_ERQ_05: Menolak berkas surat rujukan dengan format ekstensi tidak didukung.
     */
    public function test_referral_letter_rejects_invalid_file_format(): void
    {
        $seeker = User::where('email', 'pencari@darahconnect.id')->firstOrFail();

        $this->browse(function (Browser $browser) use ($seeker) {
            $browser->loginAs($seeker)
                ->visit('/emergency-requests/create')
                ->attach('referral_letter', base_path('composer.json'))
                ->assertSee('Format berkas harus PDF, JPG, JPEG, atau PNG.');
        });
    }
}

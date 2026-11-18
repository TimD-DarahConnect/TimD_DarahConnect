<?php

namespace Tests\Browser\EmergencyRequest;

use App\Enums\BloodRequestStatus;
use App\Models\BloodRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class EditEmergencyRequestTest extends DuskTestCase
{
    use DatabaseTruncation;

    protected bool $seed = true;

    /**
     * TC04_EED_01: Melengkapi permohonan berstatus Draf dan mengirimkannya ke verifikasi UDD.
     */
    public function test_seeker_can_complete_draft_and_submit_to_udd(): void
    {
        $seeker = User::where('email', 'pencari@darahconnect.id')->firstOrFail();
        $draft = BloodRequest::where('status', BloodRequestStatus::DRAFT->value)->firstOrFail();
        $pdfPath = storage_path('app/private/referral-letters/seed/surat-rujukan-contoh.pdf');

        $this->browse(function (Browser $browser) use ($seeker, $draft, $pdfPath) {
            $browser->loginAs($seeker)
                ->visit('/emergency-requests/'.$draft->id.'/edit')
                ->type('ward_room', 'Ruang Anak Kenanga Bed 08')
                ->type('clinical_diagnosis', 'Demam Berdarah Dengue Derajat III')
                ->type('hemoglobin_level', '9.8')
                ->value('#needed_at', now()->addDay()->format('Y-m-d\TH:i'))
                ->type('doctor_name', 'dr. Karina Putri, Sp.A')
                ->attach('referral_letter', $pdfPath)
                ->press('Ajukan ke Verifikasi UDD')
                ->assertPathIs('/emergency-requests/'.$draft->id)
                ->assertSee('Menunggu Verifikasi');
        });
    }

    /**
     * TC04_EED_02: Memperbarui jumlah kantong darah pada permohonan berstatus Menunggu Verifikasi.
     */
    public function test_seeker_can_update_bags_while_pending_verification(): void
    {
        $seeker = User::where('email', 'pencari@darahconnect.id')->firstOrFail();
        $pending = BloodRequest::where('status', BloodRequestStatus::PENDING_VERIFICATION->value)->firstOrFail();

        $this->browse(function (Browser $browser) use ($seeker, $pending) {
            $browser->loginAs($seeker)
                ->visit('/emergency-requests/'.$pending->id.'/edit')
                ->type('bags_needed', '4')
                ->value('#needed_at', now()->addDay()->format('Y-m-d\TH:i'))
                ->press('Simpan Pembaruan Rincian')
                ->assertPathIs('/emergency-requests/'.$pending->id)
                ->assertSee('4 Kantong');
        });
    }

    /**
     * TC04_EED_03: Permohonan yang sudah masuk antrean verifikasi tidak dapat dikembalikan menjadi draf.
     */
    public function test_pending_request_cannot_be_reverted_to_draft(): void
    {
        $seeker = User::where('email', 'pencari@darahconnect.id')->firstOrFail();
        $pending = BloodRequest::where('status', BloodRequestStatus::PENDING_VERIFICATION->value)->firstOrFail();

        $this->browse(function (Browser $browser) use ($seeker, $pending) {
            $browser->loginAs($seeker)
                ->visit('/emergency-requests/'.$pending->id.'/edit')
                ->assertDontSee('Simpan Perubahan Draf')
                ->value('button[name="submit_action"]', 'draft')
                ->press('Simpan Pembaruan Rincian')
                ->assertSee('Permohonan yang sudah masuk antrean verifikasi tidak dapat dikembalikan menjadi draf.');
        });
    }

    /**
     * TC04_EED_04: Permohonan yang sudah disetujui UDD (active) dikunci dari pengeditan.
     */
    public function test_active_request_is_locked_from_editing(): void
    {
        $seeker = User::where('email', 'pencari@darahconnect.id')->firstOrFail();
        $active = BloodRequest::where('status', BloodRequestStatus::ACTIVE->value)->firstOrFail();

        $this->browse(function (Browser $browser) use ($seeker, $active) {
            $browser->loginAs($seeker)
                ->visit('/emergency-requests/'.$active->id.'/edit')
                ->assertPathIs('/emergency-requests/'.$active->id)
                ->assertSee('telah dikunci sehingga rincian medis tidak dapat diubah kembali');
        });
    }

    /**
     * TC04_EED_05: Pencari Darah lain ditolak (403) saat mengakses halaman edit permohonan yang bukan miliknya.
     */
    public function test_other_seeker_cannot_edit_someone_elses_request(): void
    {
        $otherSeeker = User::factory()->create(['role' => 'pencari_darah']);
        $draft = BloodRequest::where('status', BloodRequestStatus::DRAFT->value)->firstOrFail();

        $this->browse(function (Browser $browser) use ($otherSeeker, $draft) {
            $browser->loginAs($otherSeeker)
                ->visit('/emergency-requests/'.$draft->id.'/edit')
                ->assertSee('403');
        });
    }
}

<?php

namespace Tests\Browser\EmergencyRequest;

use App\Enums\BloodRequestStatus;
use App\Models\BloodRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CancelEmergencyRequestTest extends DuskTestCase
{
    use DatabaseTruncation;

    protected bool $seed = true;

    /**
     * TC05_ECN_01: Pencari Darah membatalkan permohonan berstatus Menunggu Verifikasi dengan alasan valid.
     */
    public function test_seeker_can_cancel_pending_request_with_valid_reason(): void
    {
        $seeker = User::where('email', 'pencari@darahconnect.id')->firstOrFail();
        $pending = BloodRequest::where('status', BloodRequestStatus::PENDING_VERIFICATION->value)->firstOrFail();

        $this->browse(function (Browser $browser) use ($seeker, $pending) {
            $browser->loginAs($seeker)
                ->visit('/emergency-requests/' . $pending->id)
                ->press('Batalkan Permohonan')
                ->pause(400)
                ->type('cancellation_reason', 'Kebutuhan darah pasien sudah terpenuhi dari stok internal RS.')
                ->press('Ya, Batalkan Permohonan')
                ->assertSee('Dibatalkan Pemohon');
        });
    }

    /**
     * TC05_ECN_02: Membatalkan permohonan aktif otomatis melepas komitmen pendonor aktif terkait.
     */
    public function test_cancelling_active_request_releases_active_donor_commitments(): void
    {
        $seeker = User::where('email', 'pencari@darahconnect.id')->firstOrFail();
        $active = BloodRequest::where('status', BloodRequestStatus::ACTIVE->value)->firstOrFail();

        $this->browse(function (Browser $browser) use ($seeker, $active) {
            $browser->loginAs($seeker)
                ->visit('/emergency-requests/' . $active->id)
                ->press('Batalkan Permohonan')
                ->pause(400)
                ->type('cancellation_reason', 'Operasi pasien ditunda sehingga komitmen donor dilepas.')
                ->press('Ya, Batalkan Permohonan')
                ->assertSee('komitmen relawan pendonor aktif telah dilepas secara otomatis');
        });
    }

    /**
     * TC05_ECN_03: Pembatalan ditolak jika alasan pembatalan kurang dari 10 karakter.
     */
    public function test_cancellation_rejects_short_reason(): void
    {
        $seeker = User::where('email', 'pencari@darahconnect.id')->firstOrFail();
        $active = BloodRequest::where('status', BloodRequestStatus::ACTIVE->value)->firstOrFail();

        $this->browse(function (Browser $browser) use ($seeker, $active) {
            $browser->loginAs($seeker)
                ->visit('/emergency-requests/' . $active->id)
                ->press('Batalkan Permohonan')
                ->pause(400)
                ->type('cancellation_reason', 'Salah     ')
                ->press('Ya, Batalkan Permohonan')
                ->assertSee('Alasan pembatalan minimal terdiri dari 10 karakter');
        });
    }

    /**
     * TC05_ECN_04: Permohonan yang sudah dibatalkan tidak menampilkan kembali tombol Batalkan Permohonan.
     */
    public function test_cancelled_request_cannot_be_cancelled_again(): void
    {
        $seeker = User::where('email', 'pencari@darahconnect.id')->firstOrFail();
        $request = BloodRequest::where('status', BloodRequestStatus::DRAFT->value)->firstOrFail();
        $request->update([
            'status' => BloodRequestStatus::CANCELLED->value,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Sudah dibatalkan sebelumnya.',
        ]);

        $this->browse(function (Browser $browser) use ($seeker, $request) {
            $browser->loginAs($seeker)
                ->visit('/emergency-requests/' . $request->id)
                ->assertSee('Permohonan Telah Dibatalkan')
                ->assertDontSee('Batalkan Permohonan');
        });
    }

    /**
     * TC05_ECN_05: Pencari Darah lain ditolak (403) saat mengakses permohonan milik orang lain.
     */
    public function test_other_seeker_cannot_access_or_cancel_someone_elses_request(): void
    {
        $otherSeeker = User::factory()->create(['role' => 'pencari_darah']);
        $active = BloodRequest::where('status', BloodRequestStatus::ACTIVE->value)->firstOrFail();

        $this->browse(function (Browser $browser) use ($otherSeeker, $active) {
            $browser->loginAs($otherSeeker)
                ->visit('/emergency-requests/' . $active->id)
                ->assertSee('403');
        });
    }
}

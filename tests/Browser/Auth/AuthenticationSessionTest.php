<?php

namespace Tests\Browser\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AuthenticationSessionTest extends DuskTestCase
{
    use DatabaseTruncation;

    protected bool $seed = true;

    /**
     * TC02_LOG_01: Login menggunakan email dan password yang sudah terdaftar.
     */
    public function test_registered_user_can_login_with_valid_credentials(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout()
                ->visit('/login')
                ->type('email', 'pencari@darahconnect.id')
                ->type('password', 'Password123')
                ->press('Masuk Sekarang')
                ->pause(1000)
                ->assertPathIs('/workspace')
                ->assertSee('Selamat datang kembali');
        });
    }

    /**
     * TC02_LOG_02: Login ditolak jika menggunakan kata sandi yang salah.
     */
    public function test_login_fails_with_invalid_password(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout()
                ->visit('/login')
                ->type('email', 'pencari@darahconnect.id')
                ->type('password', 'PasswordSalah123')
                ->press('Masuk Sekarang')
                ->assertPathIs('/login')
                ->pause(2000)
                ->assertSee('Kombinasi email dan kata sandi tidak sesuai dengan data akun kami.');
        });
    }

    /**
     * TC02_LOG_03: Validasi wajib isi saat form login dikirim dalam keadaan kosong.
     */
    public function test_login_validates_empty_email_and_password(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout()
                ->visit('/login')
                ->press('Masuk Sekarang')
                ->pause(2000)
                ->assertPathIs('/login')
                ->assertSee('Alamat email wajib diisi.')
                ->assertSee('Kata sandi wajib diisi.');
        });
    }

    /**
     * TC02_LOG_04: Proteksi Brute-Force mengunci login pada percobaan gagal ke-6.
     */
    public function test_login_locks_out_after_five_failed_attempts(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout();

            for ($i = 1; $i <= 5; $i++) {
                $browser->visit('/login')
                    ->type('email', 'pencari@darahconnect.id')
                    ->type('password', 'Salah123')
                    ->press('Masuk Sekarang');
            }

            $browser->visit('/login')
                ->type('email', 'pencari@darahconnect.id')
                ->type('password', 'Salah123')
                ->press('Masuk Sekarang')
                ->assertSee('Terlalu banyak percobaan masuk gagal.');
        });
    }

    /**
     * TC02_LOG_05: User berhasil logout dan tidak dapat mengakses halaman ruang kerja kembali.
     */
    public function test_authenticated_user_can_logout(): void
    {
        $seeker = User::where('email', 'pencari@darahconnect.id')->firstOrFail();

        $this->browse(function (Browser $browser) use ($seeker) {
            $browser->loginAs($seeker)
                ->visit('/workspace')
                ->press('Akhiri Sesi')
                ->assertPathIs('/login')
                ->assertSee('Sesi login Anda telah diakhiri dengan aman.')
                ->visit('/workspace')
                ->assertPathIs('/login');
        });
    }

    /**
     * TC02_LOG_06: RoleMiddleware memblokir Pendonor yang mengakses halaman Pencari Darah (403).
     */
    public function test_role_middleware_forbids_unauthorized_role(): void
    {
        $donor = User::where('email', 'pendonor@darahconnect.id')->firstOrFail();

        $this->browse(function (Browser $browser) use ($donor) {
            $browser->loginAs($donor)
                ->visit('/emergency-requests/create')
                ->assertSee('403');
        });
    }
}

<?php

namespace Tests\Browser\Auth;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class UserRegistrationTest extends DuskTestCase
{
    use DatabaseTruncation;

    protected bool $seed = true;

    /**
     * TC01_REG_01: Registrasi akun Pencari Darah dengan data valid.
     */
    public function test_seeker_can_register_with_valid_data(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->press('Pencari Darah')
                ->type('name', 'Budi Santoso')
                ->type('phone', '081299887766')
                ->select('region_id', '1')
                ->type('email', 'budi.pemohon@darahconnect.id')
                ->type('password', 'DarahConnect123')
                ->type('password_confirmation', 'DarahConnect123')
                ->press('Daftarkan Akun Pencari Darah')
                ->assertPathIs('/login')
                ->assertSee('Registrasi berhasil!');
        });
    }

    /**
     * TC01_REG_02: Registrasi akun Pendonor beserta profil biologis valid.
     */
    public function test_donor_can_register_with_valid_biological_profile(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->press('Pendonor Sukarela')
                ->type('name', 'Raka Pratama')
                ->type('phone', '081377665544')
                ->select('region_id', '1')
                ->select('gender', 'L')
                ->value('#birth_date', '2000-05-15')
                ->type('weight_kg', '65')
                ->type('email', 'raka.pendonor@darahconnect.id')
                ->type('password', 'DarahConnect123')
                ->type('password_confirmation', 'DarahConnect123')
                ->press('Daftarkan Akun Pendonor')
                ->assertPathIs('/login')
                ->assertSee('Registrasi berhasil!');
        });
    }

    /**
     * TC01_REG_03: Registrasi ditolak jika menggunakan email yang sudah terdaftar.
     */
    public function test_registration_rejects_duplicate_email(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->press('Pencari Darah')
                ->type('name', 'Duplikat Pemohon')
                ->type('phone', '081355443322')
                ->select('region_id', '1')
                ->type('email', 'pencari@darahconnect.id')
                ->type('password', 'DarahConnect123')
                ->type('password_confirmation', 'DarahConnect123')
                ->press('Daftarkan Akun Pencari Darah')
                ->assertPathIs('/register')
                ->assertSee('Alamat email ini sudah terdaftar pada akun lain.');
        });
    }

    /**
     * TC01_REG_04: Registrasi Pendonor ditolak jika berat badan < 45 kg atau usia < 17 tahun.
     */
    public function test_donor_registration_rejects_under_minimum_weight_and_age(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->press('Pendonor Sukarela')
                ->type('name', 'Calon Muda')
                ->type('phone', '081311223344')
                ->select('region_id', '1')
                ->select('gender', 'P')
                ->value('#birth_date', now()->subYears(16)->toDateString())
                ->type('weight_kg', '44')
                ->type('email', 'calon.muda@darahconnect.id')
                ->type('password', 'DarahConnect123')
                ->type('password_confirmation', 'DarahConnect123')
                ->press('Daftarkan Akun Pendonor')
                ->assertPathIs('/register')
                ->assertSee('Berat badan minimal calon pendonor adalah 45 kg');
        });
    }

    /**
     * TC01_REG_05: Registrasi ditolak jika kata sandi < 8 karakter atau konfirmasi tidak cocok.
     */
    public function test_registration_rejects_short_or_mismatched_password(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->press('Pencari Darah')
                ->type('name', 'Sandi Lemah')
                ->type('phone', '081388990011')
                ->select('region_id', '1')
                ->type('email', 'sandi.lemah@darahconnect.id')
                ->type('password', 'Darah12')
                ->type('password_confirmation', 'Beda1234')
                ->press('Daftarkan Akun Pencari Darah')
                ->assertPathIs('/register')
                ->assertSee('Kata sandi minimal terdiri dari 8 karakter.')
                ->assertSee('Konfirmasi kata sandi tidak cocok.');
        });
    }
}

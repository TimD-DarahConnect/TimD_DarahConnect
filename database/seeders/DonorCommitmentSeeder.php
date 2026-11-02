<?php

namespace Database\Seeders;

use App\Enums\BloodType;
use App\Enums\DonorCommitmentStatus;
use App\Enums\Rhesus;
use App\Enums\UserRole;
use App\Models\BloodRequest;
use App\Models\DonorCommitment;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DonorCommitmentSeeder extends Seeder
{
    /**
     * Seed a sample incoming volunteer commitment so blood seekers immediately see ETA tracking,
     * while keeping pendonor@darahconnect.id free to test new pledges interactively.
     */
    public function run(): void
    {
        $activeRequest = BloodRequest::where('request_code', 'DC-REQ-SEED02')->first();
        if (! $activeRequest) {
            return;
        }

        $bandungRegion = Region::where('code', '3273')->first() ?? Region::first();

        $volunteerDonor = User::updateOrCreate(
            ['email' => 'relawan.bandung@darahconnect.id'],
            [
                'name' => 'Rizky Pratama',
                'role' => UserRole::PENDONOR->value,
                'phone' => '081211110099',
                'password' => Hash::make('Password123'),
                'email_verified_at' => now(),
                'region_id' => $bandungRegion?->id,
                'blood_type' => BloodType::O->value,
                'rhesus' => Rhesus::POSITIVE->value,
                'gender' => 'L',
                'birth_date' => '1997-04-12',
                'weight_kg' => 70,
                'address' => 'Kec. Bandung Wetan, Kota Bandung',
                'latitude' => -6.9052100,
                'longitude' => 107.6184100,
                'is_active' => true,
            ]
        );

        DonorCommitment::updateOrCreate(
            ['pledge_code' => 'DC-PLG-SEED01'],
            [
                'blood_request_id' => $activeRequest->id,
                'donor_id' => $volunteerDonor->id,
                'status' => DonorCommitmentStatus::COMMITTED->value,
                'bags_pledged' => 1,
                'estimated_arrival_at' => now()->addMinutes(75),
                'health_readiness_confirmed' => true,
                'pledge_notes' => 'Berangkat menuju instalasi BDRS RS Santo Borromeus membawa KTP dan kartu donor.',
                'meeting_point' => 'Loket Registrasi Bank Darah Rumah Sakit (BDRS) Lantai 1',
                'coordination_code' => 'BDG742',
            ]
        );
    }
}

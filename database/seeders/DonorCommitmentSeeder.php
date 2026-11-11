<?php

namespace Database\Seeders;

use App\Enums\BloodType;
use App\Enums\DonorCommitmentStatus;
use App\Enums\Rhesus;
use App\Enums\UserRole;
use App\Models\BloodRequest;
use App\Models\CoordinationMessage;
use App\Models\DonorCommitment;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DonorCommitmentSeeder extends Seeder
{
    /**
     * Seed a sample incoming volunteer commitment and structured hospital meeting-point coordination
     * so blood seekers immediately see ETA tracking and privacy-safe coordination,
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

        $commitmentData = [
            'blood_request_id' => $activeRequest->id,
            'donor_id' => $volunteerDonor->id,
            'status' => DonorCommitmentStatus::COMMITTED->value,
            'bags_pledged' => 1,
            'estimated_arrival_at' => now()->addMinutes(75),
            'health_readiness_confirmed' => true,
            'pledge_notes' => 'Berangkat menuju instalasi BDRS RS Santo Borromeus membawa KTP dan kartu donor.',
            'meeting_point' => 'Loket Registrasi Bank Darah Rumah Sakit (BDRS)',
            'coordination_code' => 'BDG742',
        ];

        if (Schema::hasColumn('donor_commitments', 'building_floor')) {
            $commitmentData['building_floor'] = 'Gedung Utama Lantai 1 Sayap Timur';
            $commitmentData['companion_alias'] = 'Perwakilan keluarga mengenakan kartu tunggu pasien di ruang tunggu BDRS';
            $commitmentData['meeting_instructions'] = 'Silakan masuk dari lobi utama menuju koridor Laboratorium / BDRS, lalu sebutkan Token Loket BDG742.';
            $commitmentData['meeting_point_updated_at'] = now()->subMinutes(15);
        }

        $commitment = DonorCommitment::updateOrCreate(
            ['pledge_code' => 'DC-PLG-SEED01'],
            $commitmentData
        );

        if (Schema::hasTable('coordination_messages') && $activeRequest->requester_id) {
            CoordinationMessage::updateOrCreate(
                [
                    'donor_commitment_id' => $commitment->id,
                    'preset_key' => 'officer_informed',
                ],
                [
                    'sender_id' => $activeRequest->requester_id,
                    'sender_role' => 'pencari_darah',
                    'message_type' => 'arrival_check',
                    'body' => 'Petugas jaga BDRS sudah kami informasikan mengenai kode kasus dan token verifikasi kehadiran Anda.',
                    'contained_masked_contact' => false,
                ]
            );

            CoordinationMessage::updateOrCreate(
                [
                    'donor_commitment_id' => $commitment->id,
                    'preset_key' => 'on_the_way',
                ],
                [
                    'sender_id' => $volunteerDonor->id,
                    'sender_role' => 'pendonor',
                    'message_type' => 'status_update',
                    'body' => 'Saya sedang dalam perjalanan menuju instalasi Bank Darah (BDRS) rumah sakit rujukan.',
                    'contained_masked_contact' => false,
                ]
            );
        }
    }
}

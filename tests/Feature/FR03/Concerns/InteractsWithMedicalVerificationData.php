<?php

namespace Tests\Feature\FR03\Concerns;

use App\Enums\BloodComponent;
use App\Enums\BloodRequestStatus;
use App\Enums\BloodType;
use App\Enums\Rhesus;
use App\Enums\UrgencyLevel;
use App\Enums\UserRole;
use App\Models\BloodRequest;
use App\Models\Facility;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

trait InteractsWithMedicalVerificationData
{
    use RefreshDatabase;

    protected Region $region;
    protected Facility $uddFacility;
    protected Facility $hospitalFacility;
    protected User $uddStaff;
    protected User $donorUser;
    protected User $seekerUser;

    public function createApplication()
    {
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';

        $app = parent::createApplication();

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->region = Region::create([
            'code' => '3273',
            'province_name' => 'Jawa Barat',
            'name' => 'Kota Bandung',
            'latitude' => -6.9174639,
            'longitude' => 107.6191228,
        ]);

        $this->uddFacility = Facility::create([
            'region_id' => $this->region->id,
            'code' => 'UDD-BDG-01',
            'name' => 'UDD PMI Kota Bandung',
            'type' => Facility::TYPE_UDD,
            'address' => 'Jl. Aceh No. 79, Kota Bandung',
            'phone' => '0224207052',
            'operating_hours' => '24 Jam',
            'latitude' => -6.9083000,
            'longitude' => 107.6186000,
            'is_active' => true,
        ]);

        $this->hospitalFacility = Facility::create([
            'region_id' => $this->region->id,
            'code' => 'RS-BDG-01',
            'name' => 'RSUP Dr. Hasan Sadikin Bandung',
            'type' => Facility::TYPE_HOSPITAL,
            'address' => 'Jl. Pasteur No. 38, Kota Bandung',
            'phone' => '0222034953',
            'operating_hours' => '24 Jam',
            'latitude' => -6.8969000,
            'longitude' => 107.5993000,
            'is_active' => true,
        ]);

        $password = Hash::make('Password123');

        $this->uddStaff = User::create([
            'name' => 'dr. Ratna Kusuma (Petugas UDD)',
            'email' => 'udd.bandung@darahconnect.id',
            'password' => $password,
            'role' => UserRole::PETUGAS_UDD->value,
            'phone' => '081100000002',
            'region_id' => $this->region->id,
            'facility_id' => $this->uddFacility->id,
            'is_active' => true,
        ]);

        $this->donorUser = User::create([
            'name' => 'Dimas Aditya (Pendonor)',
            'email' => 'pendonor@darahconnect.id',
            'password' => $password,
            'role' => UserRole::PENDONOR->value,
            'phone' => '081211110005',
            'region_id' => $this->region->id,
            'blood_type' => BloodType::O->value,
            'rhesus' => Rhesus::POSITIVE->value,
            'gender' => 'L',
            'birth_date' => '1999-08-17',
            'weight_kg' => 68,
            'latitude' => -6.8914800,
            'longitude' => 107.6106590,
            'is_active' => true,
        ]);

        $this->seekerUser = User::create([
            'name' => 'Nadia Maharani (Pencari Darah)',
            'email' => 'pencari@darahconnect.id',
            'password' => $password,
            'role' => UserRole::PENCARI_DARAH->value,
            'phone' => '081211110006',
            'region_id' => $this->region->id,
            'is_active' => true,
        ]);
    }

    /**
     * Helper untuk membuat data permohonan darah darurat beserta opsi lampiran surat rujukan dokter.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function createBloodRequest(array $overrides = [], bool $withReferralFile = true): BloodRequest
    {
        $docPath = null;
        $docOriginalName = null;
        $docMime = null;
        $docSize = null;

        if ($withReferralFile) {
            $docPath = 'private/referral-letters/test-rujukan-'.uniqid().'.pdf';
            $pdfContent = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";
            Storage::disk('local')->put($docPath, $pdfContent);
            $docOriginalName = 'Surat_Rujukan_DPJP.pdf';
            $docMime = 'application/pdf';
            $docSize = strlen($pdfContent);
        }

        return BloodRequest::create(array_merge([
            'request_code' => 'DC-REQ-'.strtoupper(substr(md5(uniqid('', true)), 0, 6)),
            'requester_id' => $this->seekerUser->id,
            'facility_id' => $this->hospitalFacility->id,
            'patient_name' => 'Tn. Suryadi Kartawijaya',
            'patient_age' => 52,
            'patient_gender' => 'L',
            'patient_relationship' => 'Keluarga Inti',
            'medical_record_number' => 'RM-2026-1001',
            'ward_room' => 'Ruang HCU Bed 04',
            'clinical_diagnosis' => 'Anemia Gravis et causa Perdarahan Saluran Cerna',
            'hemoglobin_level' => 5.8,
            'blood_type' => BloodType::O->value,
            'rhesus' => Rhesus::POSITIVE->value,
            'component_type' => BloodComponent::PRC->value,
            'bags_needed' => 3,
            'bags_fulfilled' => 0,
            'urgency_level' => UrgencyLevel::CRITICAL->value,
            'needed_at' => now()->addHours(6),
            'doctor_name' => 'dr. Arief Budiman, Sp.PD',
            'referral_letter_path' => $docPath,
            'referral_letter_original_name' => $docOriginalName,
            'referral_letter_mime' => $docMime,
            'referral_letter_size' => $docSize,
            'status' => BloodRequestStatus::PENDING_VERIFICATION->value,
            'submitted_at' => now()->subMinutes(30),
        ], $overrides));
    }
}

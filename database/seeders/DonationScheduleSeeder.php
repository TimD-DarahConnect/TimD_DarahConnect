<?php

namespace Database\Seeders;

use App\Enums\DonationScheduleType;
use App\Enums\SessionOperationalStatus;
use App\Models\DonationSchedule;
use App\Models\Facility;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;

class DonationScheduleSeeder extends Seeder
{
    /**
     * Seed upcoming daily UDD building schedules and open session quotas.
     */
    public function run(): void
    {
        $uddBandung = Facility::where('code', 'UDD-BDG-01')->first();
        $uddJakarta = Facility::where('code', 'UDD-DKI-01')->first();

        $staffBandung = User::where('email', 'udd.bandung@darahconnect.id')->first();
        $staffJakarta = User::where('email', 'udd.jakarta@darahconnect.id')->first();

        $seedSpecs = [];

        if ($uddBandung) {
            foreach ([1, 2, 3] as $offset) {
                $seedSpecs[] = [
                    'code' => 'DC-SCH-BDG0'.$offset,
                    'facility' => $uddBandung,
                    'creator_id' => $staffBandung?->id,
                    'date' => today()->addDays($offset)->toDateString(),
                    'title' => 'Pelayanan Donor Rutin Gedung UDD PMI Kota Bandung',
                    'notes' => 'Tersedia layanan flebotomi Whole Blood (WB) dan Apheresis terjadwal.',
                ];
            }
        }

        if ($uddJakarta) {
            foreach ([1, 2] as $offset) {
                $seedSpecs[] = [
                    'code' => 'DC-SCH-DKI0'.$offset,
                    'facility' => $uddJakarta,
                    'creator_id' => $staffJakarta?->id,
                    'date' => today()->addDays($offset)->toDateString(),
                    'title' => 'Pelayanan Donor Harian Gedung UDD PMI DKI Jakarta',
                    'notes' => 'Harap hadir 15 menit sebelum sesi dimulai dengan membawa KTP atau Kartu Donor.',
                ];
            }
        }

        foreach ($seedSpecs as $spec) {
            /** @var Facility $facility */
            $facility = $spec['facility'];

            $schedule = DonationSchedule::updateOrCreate(
                ['schedule_code' => $spec['code']],
                [
                    'facility_id' => $facility->id,
                    'region_id' => $facility->region_id,
                    'created_by' => $spec['creator_id'],
                    'schedule_type' => DonationScheduleType::UDD_BUILDING->value,
                    'title' => $spec['title'],
                    'service_date' => $spec['date'],
                    'opening_time' => '08:00:00',
                    'closing_time' => '18:00:00',
                    'venue_name' => $facility->name,
                    'venue_address' => $facility->address,
                    'latitude' => $facility->latitude,
                    'longitude' => $facility->longitude,
                    'target_bags' => 70,
                    'publication_status' => 'published',
                    'notes' => $spec['notes'],
                ]
            );

            $sessions = [
                [
                    'sort_order' => 1,
                    'session_label' => 'Sesi 1 — Pelayanan Pagi',
                    'start_time' => '08:00:00',
                    'end_time' => '11:00:00',
                    'quota_total' => 25,
                ],
                [
                    'sort_order' => 2,
                    'session_label' => 'Sesi 2 — Pelayanan Siang',
                    'start_time' => '11:30:00',
                    'end_time' => '14:30:00',
                    'quota_total' => 25,
                ],
                [
                    'sort_order' => 3,
                    'session_label' => 'Sesi 3 — Pelayanan Sore',
                    'start_time' => '15:00:00',
                    'end_time' => '18:00:00',
                    'quota_total' => 20,
                ],
            ];

            foreach ($sessions as $sessionData) {
                $schedule->sessions()->updateOrCreate(
                    ['sort_order' => $sessionData['sort_order']],
                    array_merge($sessionData, [
                        'operational_status' => SessionOperationalStatus::OPEN->value,
                    ])
                );
            }
        }

        $bandungRegion = Region::where('code', '3273')->first();
        $jakartaPusatRegion = Region::where('code', '3171')->first();

        $mobileSpecs = [];

        if ($uddBandung) {
            $mobileSpecs[] = [
                'code' => 'DC-MOB-BDG01',
                'facility' => $uddBandung,
                'region_id' => $bandungRegion?->id ?? $uddBandung->region_id,
                'creator_id' => $staffBandung?->id,
                'date' => today()->addDays(4)->toDateString(),
                'title' => 'Bakti Sosial Donor Darah Civitas Akademika & Alumni ITB',
                'partner_institution' => 'Institut Teknologi Bandung (ITB)',
                'venue_name' => 'Aula Barat Kampus ITB Ganesha',
                'venue_address' => 'Jl. Ganesa No. 10, Lb. Siliwangi, Kec. Coblong, Kota Bandung',
                'latitude' => -6.8914800,
                'longitude' => 107.6106591,
                'target_bags' => 100,
                'publication_status' => DonationSchedule::PUBLICATION_PUBLISHED,
                'notes' => 'Armada 1 Bus Mobile Unit + 5 bed lipat di dalam Aula Barat. Terbuka untuk mahasiswa dan masyarakat umum.',
            ];

            $mobileSpecs[] = [
                'code' => 'DC-MOB-BDG02',
                'facility' => $uddBandung,
                'region_id' => $bandungRegion?->id ?? $uddBandung->region_id,
                'creator_id' => $staffBandung?->id,
                'date' => today()->addDays(7)->toDateString(),
                'title' => 'Donor Darah ASN Peduli Kemanusiaan Pemprov Jawa Barat',
                'partner_institution' => 'Sekretariat Daerah Provinsi Jawa Barat',
                'venue_name' => 'Aula Barat Gedung Sate',
                'venue_address' => 'Jl. Diponegoro No. 22, Citarum, Kec. Bandung Wetan, Kota Bandung',
                'latitude' => -6.9024812,
                'longitude' => 107.6188100,
                'target_bags' => 120,
                'publication_status' => DonationSchedule::PUBLICATION_DRAFT,
                'notes' => 'Masih berstatus draf sambil menunggu surat izin penggunaan area parkir utara Gedung Sate.',
            ];
        }

        if ($uddJakarta) {
            $mobileSpecs[] = [
                'code' => 'DC-MOB-DKI01',
                'facility' => $uddJakarta,
                'region_id' => $jakartaPusatRegion?->id ?? $uddJakarta->region_id,
                'creator_id' => $staffJakarta?->id,
                'date' => today()->addDays(5)->toDateString(),
                'title' => 'Aksi Donor Darah Bersama Balai Kota DKI Jakarta',
                'partner_institution' => 'Pemerintah Provinsi DKI Jakarta',
                'venue_name' => 'Pendopo Balai Agung Balai Kota DKI Jakarta',
                'venue_address' => 'Jl. Medan Merdeka Selatan No. 8-9, Gambir, Kota Jakarta Pusat',
                'latitude' => -6.1812130,
                'longitude' => 106.8286400,
                'target_bags' => 150,
                'publication_status' => DonationSchedule::PUBLICATION_PUBLISHED,
                'notes' => 'Peserta wajib membawa KTP asli atau menunjukkan profil E-Donor DarahConnect saat registrasi.',
            ];
        }

        foreach ($mobileSpecs as $mobileSpec) {
            /** @var Facility $mobileFacility */
            $mobileFacility = $mobileSpec['facility'];

            $mobileSchedule = DonationSchedule::updateOrCreate(
                ['schedule_code' => $mobileSpec['code']],
                [
                    'facility_id' => $mobileFacility->id,
                    'region_id' => $mobileSpec['region_id'],
                    'created_by' => $mobileSpec['creator_id'],
                    'schedule_type' => DonationScheduleType::MOBILE_UNIT->value,
                    'title' => $mobileSpec['title'],
                    'partner_institution' => $mobileSpec['partner_institution'],
                    'service_date' => $mobileSpec['date'],
                    'opening_time' => '08:30:00',
                    'closing_time' => '15:00:00',
                    'venue_name' => $mobileSpec['venue_name'],
                    'venue_address' => $mobileSpec['venue_address'],
                    'latitude' => $mobileSpec['latitude'],
                    'longitude' => $mobileSpec['longitude'],
                    'target_bags' => $mobileSpec['target_bags'],
                    'publication_status' => $mobileSpec['publication_status'],
                    'notes' => $mobileSpec['notes'],
                ]
            );

            $mobileSessions = [
                [
                    'sort_order' => 1,
                    'session_label' => 'Gelombang 1 — Sesi Pagi Mitra',
                    'start_time' => '08:30:00',
                    'end_time' => '11:30:00',
                    'quota_total' => (int) ceil($mobileSpec['target_bags'] * 0.55),
                ],
                [
                    'sort_order' => 2,
                    'session_label' => 'Gelombang 2 — Sesi Siang Mitra',
                    'start_time' => '12:30:00',
                    'end_time' => '15:00:00',
                    'quota_total' => (int) floor($mobileSpec['target_bags'] * 0.45),
                ],
            ];

            foreach ($mobileSessions as $sessionData) {
                $mobileSchedule->sessions()->updateOrCreate(
                    ['sort_order' => $sessionData['sort_order']],
                    array_merge($sessionData, [
                        'operational_status' => SessionOperationalStatus::OPEN->value,
                    ])
                );
            }
        }
    }
}

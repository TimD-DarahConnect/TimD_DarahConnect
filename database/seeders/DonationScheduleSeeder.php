<?php

namespace Database\Seeders;

use App\Enums\DonationScheduleType;
use App\Enums\SessionOperationalStatus;
use App\Models\DonationSchedule;
use App\Models\Facility;
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
    }
}

<?php

namespace Database\Seeders;

use App\Enums\DonorBookingStatus;
use App\Models\DonationSchedule;
use App\Models\DonorBooking;
use App\Models\User;
use Illuminate\Database\Seeder;

class DonorBookingSeeder extends Seeder
{
    /**
     * Seed a sample confirmed reservation for relawan.bandung@darahconnect.id so occupancy metrics
     * show realistic data while keeping pendonor@darahconnect.id free to book a slot interactively.
     */
    public function run(): void
    {
        $volunteer = User::where('email', 'relawan.bandung@darahconnect.id')->first();
        $schedule = DonationSchedule::with('sessions')->where('schedule_code', 'DC-SCH-BDG01')->first();
        $morningSession = $schedule?->sessions->first();

        if (! $volunteer || ! $schedule || ! $morningSession) {
            return;
        }

        $booking = DonorBooking::updateOrCreate(
            ['booking_code' => 'DC-BKG-SEED01'],
            [
                'donor_id' => $volunteer->id,
                'donation_schedule_id' => $schedule->id,
                'donation_session_id' => $morningSession->id,
                'status' => DonorBookingStatus::CONFIRMED->value,
                'donation_type' => 'WB',
                'donor_notes' => 'Reservasi rutin donor darah sukarela Whole Blood.',
                'screening_passed' => true,
                'screening_completed_at' => now()->subMinutes(20),
                'confirmed_at' => now()->subMinutes(20),
            ]
        );

        if ($booking->wasRecentlyCreated) {
            $morningSession->increment('quota_booked');
        } else {
            $confirmedCount = DonorBooking::where('donation_session_id', $morningSession->id)
                ->whereIn('status', [
                    DonorBookingStatus::CONFIRMED->value,
                    DonorBookingStatus::CHECKED_IN->value,
                    DonorBookingStatus::COMPLETED->value,
                ])
                ->count();
            $morningSession->update(['quota_booked' => $confirmedCount]);
        }
    }
}

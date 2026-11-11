<?php

namespace App\Http\Controllers\SessionBooking;

use App\Enums\DonorBookingStatus;
use App\Enums\SessionOperationalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SessionBooking\SubmitAnamnesisScreeningRequest;
use App\Models\BookingScreening;
use App\Models\DonationSession;
use App\Models\DonorBooking;
use App\Models\ScreeningQuestion;
use App\Services\SessionBooking\AnamnesisScreeningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnamnesisScreeningController extends Controller
{
    /**
     * Render the 20-question digital self-screening medical anamnesis form before confirming a booking.
     */
    public function show(Request $request, DonorBooking $booking): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isDonor() || (int) $booking->donor_id !== (int) $user->id) {
            abort(403, 'Anda tidak berwenang mengakses kuesioner anamnesis untuk reservasi ini.');
        }

        $booking->load(['schedule.facility.region', 'session', 'screening']);

        if (! $booking->requiresSelfScreening()) {
            return redirect()
                ->route('bookings.show', $booking)
                ->with('status', 'Kuesioner penapisan mandiri untuk reservasi '.$booking->booking_code.' telah dievaluasi.');
        }

        ScreeningQuestion::ensureCatalogExists();
        $questions = ScreeningQuestion::active()->get();

        $groupedSections = $questions
            ->groupBy('section_key')
            ->map(function ($items, string $key): array {
                /** @var ScreeningQuestion $first */
                $first = $items->first();

                return [
                    'key' => $key,
                    'title' => $first->section_title,
                    'questions' => $items->values(),
                ];
            })
            ->values();

        return view('session-bookings.screening', [
            'booking' => $booking,
            'questions' => $questions,
            'groupedSections' => $groupedSections,
        ]);
    }

    /**
     * Evaluate the 20 medical anamnesis responses and atomically confirm or defer the session reservation.
     */
    public function store(
        SubmitAnamnesisScreeningRequest $request,
        DonorBooking $booking,
        AnamnesisScreeningService $evaluator
    ): RedirectResponse {
        if (! $booking->requiresSelfScreening()) {
            return redirect()
                ->route('bookings.show', $booking)
                ->with('status', 'Kuesioner penapisan mandiri untuk reservasi ini sudah diproses sebelumnya.');
        }

        ScreeningQuestion::ensureCatalogExists();
        $questions = ScreeningQuestion::active()->get();
        $evaluation = $evaluator->evaluate($questions, (array) $request->validated('answers'));

        $outcome = DB::transaction(function () use ($booking, $request, $evaluation): string {
            /** @var DonorBooking $lockedBooking */
            $lockedBooking = DonorBooking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedBooking->requiresSelfScreening()) {
                return 'already_processed';
            }

            /** @var DonationSession $lockedSession */
            $lockedSession = DonationSession::query()
                ->whereKey($lockedBooking->donation_session_id)
                ->lockForUpdate()
                ->firstOrFail();

            $now = now();

            BookingScreening::updateOrCreate(
                ['donor_booking_id' => $lockedBooking->id],
                [
                    'donor_id' => $request->user()->id,
                    'total_questions' => $evaluation['total_questions'],
                    'passed_count' => $evaluation['passed_count'],
                    'flagged_count' => $evaluation['flagged_count'],
                    'is_eligible' => $evaluation['is_eligible'],
                    'responses' => $evaluation['responses'],
                    'flagged_reasons' => $evaluation['flagged_reasons'],
                    'consent_confirmed_at' => $now,
                    'evaluated_at' => $now,
                ]
            );

            if (! $evaluation['is_eligible']) {
                $lockedBooking->update([
                    'status' => DonorBookingStatus::DEFERRED_SCREENING->value,
                    'screening_passed' => false,
                    'screening_completed_at' => $now,
                    'screening_summary_note' => $evaluation['summary_note'],
                    'confirmed_at' => null,
                ]);

                return 'deferred';
            }

            if ($lockedSession->operational_status !== SessionOperationalStatus::OPEN || $lockedSession->remaining_quota <= 0) {
                $lockedBooking->update([
                    'status' => DonorBookingStatus::CANCELLED->value,
                    'screening_passed' => true,
                    'screening_completed_at' => $now,
                    'screening_summary_note' => $evaluation['summary_note'],
                    'cancelled_at' => $now,
                    'cancellation_reason' => 'Kuota sesi penuh atau ditutup sementara saat penyelesaian kuesioner anamnesis.',
                ]);

                return 'slot_unavailable';
            }

            $lockedBooking->update([
                'status' => DonorBookingStatus::CONFIRMED->value,
                'screening_passed' => true,
                'screening_completed_at' => $now,
                'screening_summary_note' => $evaluation['summary_note'],
                'confirmed_at' => $now,
            ]);

            $lockedSession->increment('quota_booked');

            return 'confirmed';
        });

        if ($outcome === 'slot_unavailable') {
            return redirect()
                ->route('bookings.schedules')
                ->with('error', 'Hasil anamnesis Anda memenuhi syarat, namun kuota pada sesi tersebut baru saja penuh. Silakan pilih slot sesi lain yang masih tersedia.');
        }

        if ($outcome === 'deferred') {
            return redirect()
                ->route('bookings.show', $booking)
                ->with('warning', 'Berdasarkan evaluasi penapisan mandiri 20 pertanyaan medis, keberangkatan donor Anda ditunda sementara demi menjaga kesehatan Anda. Kuota sesi tidak dipotong.');
        }

        return redirect()
            ->route('bookings.show', $booking)
            ->with('success', 'Selamat! Anda lolos seluruh 20 kriteria penapisan mandiri anamnesis dan reservasi jadwal donor ('.$booking->booking_code.') resmi terkonfirmasi.');
    }
}

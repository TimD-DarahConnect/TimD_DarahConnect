<?php

namespace App\Http\Controllers\SessionBooking;

use App\Enums\DonorBookingStatus;
use App\Enums\SessionOperationalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SessionBooking\CancelSessionBookingRequest;
use App\Http\Requests\SessionBooking\RescheduleSessionBookingRequest;
use App\Models\DonationSchedule;
use App\Models\DonationSession;
use App\Models\DonorBooking;
use App\Services\SessionBooking\BookingTicketQrService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookingTicketController extends Controller
{
    public function __construct(
        private readonly BookingTicketQrService $qrService
    ) {}

    /**
     * Display the Apple Wallet-style digital QR booking pass for a confirmed & screened donor reservation.
     */
    public function ticket(Request $request, DonorBooking $booking): View|RedirectResponse
    {
        $user = $request->user();
        $booking->load([
            'donor',
            'schedule.facility.region',
            'schedule.region',
            'session',
            'previousSession.schedule',
            'screening',
        ]);

        $isOwnerDonor = $user->isDonor() && (int) $booking->donor_id === (int) $user->id;
        $isAuthorizedStaff = $user->isAdmin()
            || ($user->isUddStaff() && (! $user->facility_id || (int) $booking->schedule?->facility_id === (int) $user->facility_id));

        if (! $isOwnerDonor && ! $isAuthorizedStaff) {
            abort(403, 'Anda tidak berwenang mengakses tiket digital reservasi ini.');
        }

        if ($booking->requiresSelfScreening()) {
            $targetRoute = $isOwnerDonor
                ? route('bookings.screening.show', $booking)
                : route('bookings.show', $booking);

            return redirect()->to($targetRoute)
                ->with('status', 'Selesaikan 20 kuesioner penapisan mandiri (anamnesis) terlebih dahulu sebelum tiket QR digital diterbitkan.');
        }

        if (! $booking->hasDigitalTicket()) {
            return redirect()
                ->route('bookings.show', $booking)
                ->with('warning', 'Tiket QR digital hanya berlaku bagi reservasi aktif yang telah terkonfirmasi lolos anamnesis.');
        }

        $rescheduleSchedules = ($isOwnerDonor && $booking->canBeRescheduled())
            ? self::loadAvailableRescheduleSchedules($booking)
            : collect();

        return view('session-bookings.ticket', [
            'booking' => $booking,
            'isOwnerDonor' => $isOwnerDonor,
            'qrSvg' => $this->qrService->generateSvg($booking, 224),
            'rescheduleSchedules' => $rescheduleSchedules,
        ]);
    }

    /**
     * Atomically reschedule an active booking to another open upcoming session,
     * transferring session quota (-1 old, +1 new) and rotating the QR security token.
     */
    public function reschedule(RescheduleSessionBookingRequest $request, DonorBooking $booking): RedirectResponse
    {
        $targetSessionId = (int) $request->validated('target_session_id');
        if ($targetSessionId === (int) $booking->donation_session_id) {
            return back()
                ->withInput()
                ->withErrors(['target_session_id' => 'Pilih slot sesi yang berbeda dari jadwal kedatangan Anda saat ini.']);
        }

        $targetPreview = DonationSession::with('schedule')->findOrFail($targetSessionId);
        $user = $request->user();

        if ($user->last_donated_at !== null && $targetPreview->schedule?->service_date !== null) {
            $daysInterval = $user->last_donated_at->diffInDays($targetPreview->schedule->service_date, false);
            if ($daysInterval < 60) {
                $eligibleDate = $user->last_donated_at->copy()->addDays(60)->translatedFormat('d M Y');

                return back()
                    ->withInput()
                    ->with('warning', "Berdasarkan jeda medis minimal 60 hari antar-donor, Anda baru dapat menjadwalkan ulang mulai tanggal {$eligibleDate}.");
            }
        }

        $note = trim((string) ($request->validated('reschedule_note') ?? ''));

        $outcome = DB::transaction(function () use ($booking, $targetSessionId, $note): string {
            /** @var DonorBooking $lockedBooking */
            $lockedBooking = DonorBooking::with('schedule')
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedBooking->canBeRescheduled()) {
                return 'not_reschedulable';
            }

            $oldSessionId = (int) $lockedBooking->donation_session_id;
            if ($oldSessionId === $targetSessionId) {
                return 'same_session';
            }

            // Lock both session rows in deterministic ascending ID order to avoid deadlocks
            $lockedSessions = DonationSession::with('schedule')
                ->whereIn('id', [$oldSessionId, $targetSessionId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var DonationSession|null $oldSession */
            $oldSession = $lockedSessions->get($oldSessionId);
            /** @var DonationSession|null $newSession */
            $newSession = $lockedSessions->get($targetSessionId);

            if (! $oldSession || ! $newSession || ! $newSession->schedule) {
                return 'unavailable';
            }

            $newSchedule = $newSession->schedule;
            if (
                $newSchedule->publication_status !== 'published'
                || $newSchedule->service_date->lt(today())
                || $newSession->operational_status !== SessionOperationalStatus::OPEN
                || $newSession->remaining_quota <= 0
            ) {
                return 'unavailable';
            }

            if ($lockedBooking->status->holdsSessionQuota()) {
                $oldSession->update([
                    'quota_booked' => max(0, (int) $oldSession->quota_booked - 1),
                ]);
                $newSession->increment('quota_booked');
            }

            $lockedBooking->update([
                'donation_schedule_id' => $newSchedule->id,
                'donation_session_id' => $newSession->id,
                'rescheduled_from_session_id' => $oldSession->id,
                'reschedule_count' => (int) $lockedBooking->reschedule_count + 1,
                'qr_token' => DonorBooking::generateFreshQrToken(),
                'donor_notes' => $note !== '' ? $note : $lockedBooking->donor_notes,
            ]);

            return 'rescheduled';
        });

        if ($outcome === 'not_reschedulable') {
            return redirect()
                ->route('bookings.show', $booking)
                ->with('warning', 'Reservasi ini sudah mencapai batas maksimal penjadwalan ulang ('.DonorBooking::MAX_RESCHEDULE_COUNT.' kali) atau statusnya sudah tidak aktif.');
        }

        if ($outcome !== 'rescheduled') {
            return back()
                ->withInput()
                ->with('error', 'Sesi tujuan baru saja penuh atau sedang dijeda oleh petugas UDD. Silakan pilih sesi lain yang tersedia.');
        }

        $refreshed = $booking->fresh(['schedule', 'session']);
        $destinationRoute = $refreshed && $refreshed->hasDigitalTicket()
            ? route('bookings.ticket', $refreshed)
            : route('bookings.show', $booking);

        return redirect()->to($destinationRoute)->with(
            'success',
            'Jadwal kedatangan donor berhasil dipindahkan ke '.$refreshed?->schedule?->service_date?->translatedFormat('d M Y').' ('.$refreshed?->session?->session_label.'). Token QR tiket digital Anda telah diperbarui.'
        );
    }

    /**
     * Atomically cancel an active donor session booking and release held session quota back to UDD availability.
     */
    public function cancel(CancelSessionBookingRequest $request, DonorBooking $booking): RedirectResponse
    {
        $reason = (string) $request->validated('cancellation_reason');

        $result = DB::transaction(function () use ($booking, $reason): array {
            /** @var DonorBooking $lockedBooking */
            $lockedBooking = DonorBooking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedBooking->canBeCancelled()) {
                return ['cancelled' => false, 'released_quota' => false];
            }

            /** @var DonationSession|null $lockedSession */
            $lockedSession = DonationSession::query()
                ->whereKey($lockedBooking->donation_session_id)
                ->lockForUpdate()
                ->first();

            $heldQuota = $lockedBooking->status->holdsSessionQuota();

            $lockedBooking->update([
                'status' => DonorBookingStatus::CANCELLED->value,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            if ($heldQuota && $lockedSession !== null) {
                $lockedSession->update([
                    'quota_booked' => max(0, (int) $lockedSession->quota_booked - 1),
                ]);
            }

            return ['cancelled' => true, 'released_quota' => $heldQuota];
        });

        if (! $result['cancelled']) {
            return redirect()
                ->route('bookings.show', $booking)
                ->with('warning', 'Reservasi ini sudah berstatus tidak aktif sehingga tidak dapat dibatalkan ulang.');
        }

        $quotaNotice = $result['released_quota']
            ? ' Kursi kuota sesi Anda telah dikembalikan otomatis agar dapat dimanfaatkan pendonor lain.'
            : '';

        return redirect()
            ->route('bookings.show', $booking)
            ->with('success', "Tiket reservasi jadwal ({$booking->booking_code}) berhasil dibatalkan.{$quotaNotice}");
    }

    /**
     * Load upcoming published schedules that have at least one bookable session different from the booking's current session.
     *
     * @return Collection<int, DonationSchedule>
     */
    public static function loadAvailableRescheduleSchedules(DonorBooking $booking): Collection
    {
        return DonationSchedule::with(['facility', 'region', 'sessions'])
            ->published()
            ->upcoming()
            ->orderBy('service_date')
            ->orderBy('opening_time')
            ->get()
            ->map(function (DonationSchedule $schedule) use ($booking): DonationSchedule {
                $availableSessions = $schedule->sessions->filter(
                    fn (DonationSession $session): bool => (int) $session->id !== (int) $booking->donation_session_id
                        && $session->isBookable()
                )->values();

                $schedule->setRelation('sessions', $availableSessions);

                return $schedule;
            })
            ->filter(fn (DonationSchedule $schedule): bool => $schedule->sessions->isNotEmpty())
            ->values();
    }
}

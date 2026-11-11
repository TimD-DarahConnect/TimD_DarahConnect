<?php

namespace App\Http\Controllers\SessionBooking;

use App\Enums\DonationScheduleType;
use App\Enums\DonorBookingStatus;
use App\Enums\SessionOperationalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SessionBooking\StoreSessionBookingRequest;
use App\Models\DonationSchedule;
use App\Models\DonationSession;
use App\Models\DonorBooking;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class SessionBookingController extends Controller
{
    /**
     * Browse published upcoming UDD building and Mobile Unit schedules available for slot booking.
     */
    public function schedules(Request $request): View
    {
        $typeFilter = (string) $request->query('type', '');
        if ($typeFilter !== '' && ! in_array($typeFilter, DonationScheduleType::values(), true)) {
            $typeFilter = '';
        }

        $regionFilter = (int) $request->query('region_id', 0);
        $dateFilter = (string) $request->query('date', '');

        $query = DonationSchedule::with(['facility.region', 'region', 'sessions'])
            ->published()
            ->upcoming();

        if ($typeFilter !== '') {
            $query->where('schedule_type', $typeFilter);
        }

        if ($regionFilter > 0) {
            $query->where(function (Builder $q) use ($regionFilter): void {
                $q->where('region_id', $regionFilter)
                    ->orWhereHas('facility', fn (Builder $fq) => $fq->where('region_id', $regionFilter));
            });
        }

        if ($dateFilter !== '' && strtotime($dateFilter) !== false) {
            $query->whereDate('service_date', $dateFilter);
        }

        $schedules = $query
            ->orderBy('service_date')
            ->orderBy('opening_time')
            ->paginate(9)
            ->withQueryString();

        $activeBooking = null;
        if ($request->user() && $request->user()->isDonor()) {
            $activeBooking = DonorBooking::with(['schedule.facility', 'session'])
                ->forDonor($request->user()->id)
                ->activeReservation()
                ->whereHas('schedule', fn (Builder $q) => $q->upcoming())
                ->first();
        }

        $regions = Region::orderBy('province_name')->orderBy('name')->get();

        return view('session-bookings.schedules', [
            'schedules' => $schedules,
            'regions' => $regions,
            'typeFilter' => $typeFilter,
            'regionFilter' => $regionFilter,
            'dateFilter' => $dateFilter,
            'activeBooking' => $activeBooking,
            'scheduleTypes' => DonationScheduleType::cases(),
        ]);
    }

    /**
     * Display a specific donation schedule's venue details, map coordinates, and selectable time sessions.
     */
    public function showSchedule(Request $request, DonationSchedule $schedule): View
    {
        if ($schedule->publication_status !== 'published' || $schedule->service_date->lt(today())) {
            abort(404, 'Jadwal layanan donor ini tidak tersedia atau telah berakhir.');
        }

        $schedule->load(['facility.region', 'region', 'sessions']);

        $activeBooking = null;
        if ($request->user() && $request->user()->isDonor()) {
            $activeBooking = DonorBooking::with(['schedule', 'session'])
                ->forDonor($request->user()->id)
                ->activeReservation()
                ->whereHas('schedule', fn (Builder $q) => $q->upcoming())
                ->first();
        }

        return view('session-bookings.schedule-detail', [
            'schedule' => $schedule,
            'activeBooking' => $activeBooking,
        ]);
    }

    /**
     * List the authenticated donor's scheduled session reservations.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $bookings = DonorBooking::with(['schedule.facility.region', 'session'])
            ->forDonor($user->id)
            ->orderByRaw("CASE status WHEN 'pending_screening' THEN 1 WHEN 'confirmed' THEN 2 ELSE 3 END ASC")
            ->orderByDesc('created_at')
            ->paginate(10);

        $activeBooking = DonorBooking::with(['schedule.facility', 'session'])
            ->forDonor($user->id)
            ->activeReservation()
            ->whereHas('schedule', fn (Builder $q) => $q->upcoming())
            ->first();

        return view('session-bookings.index', [
            'bookings' => $bookings,
            'activeBooking' => $activeBooking,
        ]);
    }

    /**
     * Reserve a specific UDD/Mobile Unit session slot atomically with concurrency and eligibility checks.
     */
    public function store(StoreSessionBookingRequest $request, DonationSession $session): RedirectResponse
    {
        $user = $request->user();
        $session->load('schedule');
        $schedule = $session->schedule;

        if (! $schedule || $schedule->publication_status !== 'published' || $schedule->service_date->lt(today())) {
            return back()->with('error', 'Jadwal layanan donor sudah tidak aktif untuk pemesanan baru.');
        }

        if ($user->last_donated_at !== null) {
            $daysSinceLastDonation = $user->last_donated_at->diffInDays($schedule->service_date, false);
            if ($daysSinceLastDonation < 60) {
                $eligibleDate = $user->last_donated_at->copy()->addDays(60)->translatedFormat('d M Y');

                return back()->with('warning', 'Berdasarkan jeda medis minimal 60 hari antar-donor, Anda baru dapat memesan jadwal mulai tanggal '.$eligibleDate.'.');
            }
        }

        $requiresScreeningStep = Route::has('bookings.screening.show');

        $result = DB::transaction(function () use ($request, $session, $schedule, $user, $requiresScreeningStep): array {
            $existingActive = DonorBooking::query()
                ->forDonor($user->id)
                ->activeReservation()
                ->whereHas('schedule', fn (Builder $q) => $q->upcoming())
                ->lockForUpdate()
                ->first();

            if ($existingActive) {
                return ['status' => 'duplicate', 'booking' => $existingActive];
            }

            /** @var DonationSession $lockedSession */
            $lockedSession = DonationSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSession->operational_status !== SessionOperationalStatus::OPEN || $lockedSession->remaining_quota <= 0) {
                return ['status' => 'unavailable', 'booking' => null];
            }

            $initialStatus = $requiresScreeningStep
                ? DonorBookingStatus::PENDING_SCREENING->value
                : DonorBookingStatus::CONFIRMED->value;

            $booking = DonorBooking::create([
                'donor_id' => $user->id,
                'donation_schedule_id' => $schedule->id,
                'donation_session_id' => $lockedSession->id,
                'status' => $initialStatus,
                'donation_type' => $request->validated('donation_type'),
                'donor_notes' => $request->validated('donor_notes'),
                'confirmed_at' => $requiresScreeningStep ? null : now(),
            ]);

            if (! $requiresScreeningStep) {
                $lockedSession->increment('quota_booked');
            }

            return ['status' => 'created', 'booking' => $booking];
        });

        if ($result['status'] === 'duplicate' && $result['booking']) {
            return redirect()
                ->route('bookings.show', $result['booking'])
                ->with('warning', 'Anda masih memiliki reservasi jadwal donor aktif ('.$result['booking']->booking_code.'). Satu akun hanya dapat memegang 1 reservasi aktif pada satu waktu.');
        }

        if ($result['status'] !== 'created' || ! $result['booking']) {
            return back()->with('error', 'Maaf, kuota sesi yang Anda pilih baru saja penuh atau ditutup sementara oleh petugas UDD.');
        }

        /** @var DonorBooking $createdBooking */
        $createdBooking = $result['booking'];

        if ($requiresScreeningStep) {
            return redirect()
                ->route('bookings.screening.show', $createdBooking)
                ->with('status', 'Slot sesi dipilih! Lengkapi 20 kuesioner penapisan mandiri (anamnesis) untuk mengonfirmasi reservasi Anda.');
        }

        return redirect()
            ->route('bookings.show', $createdBooking)
            ->with('success', 'Reservasi jadwal donor Anda ('.$createdBooking->booking_code.') berhasil dikonfirmasi!');
    }

    /**
     * Display the donor's session booking confirmation details.
     */
    public function show(Request $request, DonorBooking $booking): View
    {
        $user = $request->user();
        $booking->load(['donor', 'schedule.facility.region', 'session', 'screening']);

        $isOwnerDonor = $user->isDonor() && (int) $booking->donor_id === (int) $user->id;
        $isAuthorizedStaff = $user->hasRole('petugas_udd', 'admin');

        if (! $isOwnerDonor && ! $isAuthorizedStaff) {
            abort(403, 'Anda tidak berwenang mengakses bukti reservasi jadwal ini.');
        }

        return view('session-bookings.show', [
            'booking' => $booking,
            'isOwnerDonor' => $isOwnerDonor,
        ]);
    }
}

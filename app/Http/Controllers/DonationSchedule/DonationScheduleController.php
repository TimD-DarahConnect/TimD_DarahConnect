<?php

namespace App\Http\Controllers\DonationSchedule;

use App\Enums\DonationScheduleType;
use App\Enums\SessionOperationalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DonationSchedule\StoreUddScheduleRequest;
use App\Models\DonationSchedule;
use App\Models\Facility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DonationScheduleController extends Controller
{
    /**
     * Display the UDD building daily schedules and session capacity overview.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $scopeFilter = (string) $request->query('period', 'upcoming');
        if (! in_array($scopeFilter, ['upcoming', 'all'], true)) {
            $scopeFilter = 'upcoming';
        }

        $facilityFilter = (int) $request->query('facility_id', 0);

        $query = DonationSchedule::with(['facility.region', 'sessions', 'creator'])
            ->uddBuilding();

        if ($user->isUddStaff() && $user->facility_id) {
            $query->where('facility_id', $user->facility_id);
        } elseif ($facilityFilter > 0) {
            $query->where('facility_id', $facilityFilter);
        }

        if ($scopeFilter === 'upcoming') {
            $query->upcoming();
        }

        $schedules = $query
            ->orderBy('service_date')
            ->orderBy('opening_time')
            ->paginate(10)
            ->withQueryString();

        $summaryBase = DonationSchedule::with('sessions')
            ->uddBuilding()
            ->upcoming();

        if ($user->isUddStaff() && $user->facility_id) {
            $summaryBase->where('facility_id', $user->facility_id);
        }

        $upcomingCollection = $summaryBase->get();

        $summary = [
            'upcoming_days' => $upcomingCollection->count(),
            'active_sessions' => (int) $upcomingCollection->sum(fn (DonationSchedule $s): int => $s->sessions->count()),
            'total_capacity' => (int) $upcomingCollection->sum(fn (DonationSchedule $s): int => $s->total_quota),
            'available_slots' => (int) $upcomingCollection->sum(fn (DonationSchedule $s): int => $s->remaining_quota),
        ];

        $uddFacilities = Facility::active()->udd()->orderBy('name')->get();

        return view('donation-schedules.index', [
            'schedules' => $schedules,
            'summary' => $summary,
            'scopeFilter' => $scopeFilter,
            'facilityFilter' => $facilityFilter,
            'uddFacilities' => $uddFacilities,
            'isLockedToSingleUdd' => $user->isUddStaff() && (bool) $user->facility_id,
        ]);
    }

    /**
     * Show the interactive master daily schedule and session builder for UDD building services.
     */
    public function create(Request $request): View
    {
        $user = $request->user();

        $uddFacilitiesQuery = Facility::with('region')->active()->udd()->orderBy('name');
        if ($user->isUddStaff() && $user->facility_id) {
            $uddFacilitiesQuery->whereKey($user->facility_id);
        }

        $uddFacilities = $uddFacilitiesQuery->get();

        $defaultSessions = [
            [
                'session_label' => 'Sesi 1 — Pelayanan Pagi',
                'start_time' => '08:00',
                'end_time' => '11:00',
                'quota_total' => 25,
            ],
            [
                'session_label' => 'Sesi 2 — Pelayanan Siang',
                'start_time' => '11:30',
                'end_time' => '14:30',
                'quota_total' => 25,
            ],
            [
                'session_label' => 'Sesi 3 — Pelayanan Sore',
                'start_time' => '15:00',
                'end_time' => '18:00',
                'quota_total' => 20,
            ],
        ];

        return view('donation-schedules.create', [
            'uddFacilities' => $uddFacilities,
            'selectedFacilityId' => $user->facility_id ?: $uddFacilities->first()?->id,
            'defaultDate' => now()->addDays(4)->toDateString(),
            'defaultSessions' => $defaultSessions,
        ]);
    }

    /**
     * Persist a new UDD building daily master schedule and its ordered time sessions atomically.
     */
    public function store(StoreUddScheduleRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $facility = Facility::findOrFail((int) $validated['facility_id']);

        $orderedSessions = collect((array) $validated['sessions'])
            ->sortBy('start_time')
            ->values();

        $totalQuota = (int) $orderedSessions->sum(fn (array $s): int => (int) $s['quota_total']);

        $schedule = DB::transaction(function () use ($request, $validated, $facility, $orderedSessions, $totalQuota): DonationSchedule {
            $schedule = DonationSchedule::create([
                'facility_id' => $facility->id,
                'region_id' => $facility->region_id,
                'created_by' => $request->user()->id,
                'schedule_type' => DonationScheduleType::UDD_BUILDING->value,
                'title' => $validated['title'],
                'service_date' => $validated['service_date'],
                'opening_time' => $validated['opening_time'],
                'closing_time' => $validated['closing_time'],
                'venue_name' => $facility->name,
                'venue_address' => $facility->address,
                'latitude' => $facility->latitude,
                'longitude' => $facility->longitude,
                'target_bags' => $totalQuota,
                'publication_status' => 'published',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($orderedSessions as $index => $sessionData) {
                $schedule->sessions()->create([
                    'session_label' => $sessionData['session_label'],
                    'start_time' => $sessionData['start_time'],
                    'end_time' => $sessionData['end_time'],
                    'quota_total' => (int) $sessionData['quota_total'],
                    'quota_booked' => 0,
                    'operational_status' => SessionOperationalStatus::OPEN->value,
                    'sort_order' => $index + 1,
                ]);
            }

            return $schedule;
        });

        return redirect()
            ->route('schedules.show', $schedule)
            ->with('success', 'Master jadwal harian gedung UDD ('.$schedule->schedule_code.') beserta '.$orderedSessions->count().' sesi pelayanan berhasil diterbitkan.');
    }

    /**
     * Display detailed daily schedule breakdown and per-session quota capacity.
     */
    public function show(Request $request, DonationSchedule $schedule): View
    {
        $user = $request->user();
        if ($user->isUddStaff() && $user->facility_id && (int) $schedule->facility_id !== (int) $user->facility_id) {
            abort(403, 'Anda hanya dapat meninjau detail jadwal pada unit UDD instansi Anda.');
        }

        $schedule->load([
            'facility.region',
            'region',
            'sessions.bookings' => function ($query): void {
                $query->whereIn('status', ['confirmed', 'pending_screening'])
                    ->with('donor')
                    ->orderBy('created_at');
            },
            'creator',
        ]);

        $hasActiveBookings = $schedule->bookings()->activeReservation()->exists() || $schedule->total_booked > 0;

        return view('donation-schedules.show', [
            'schedule' => $schedule,
            'hasActiveBookings' => $hasActiveBookings,
        ]);
    }
}

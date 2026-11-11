<?php

namespace App\Http\Controllers\DonationSchedule;

use App\Enums\DonationScheduleType;
use App\Enums\SessionOperationalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DonationSchedule\StoreMobileUnitScheduleRequest;
use App\Http\Requests\DonationSchedule\UpdateMobileUnitScheduleRequest;
use App\Models\DonationSchedule;
use App\Models\Facility;
use App\Models\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MobileUnitEventController extends Controller
{
    /**
     * Display the Mobile Unit & external partner donation events dashboard.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $scopeFilter = (string) $request->query('period', 'upcoming');
        if (! in_array($scopeFilter, ['upcoming', 'all'], true)) {
            $scopeFilter = 'upcoming';
        }

        $statusFilter = (string) $request->query('status', 'all');
        if (! in_array($statusFilter, ['all', DonationSchedule::PUBLICATION_PUBLISHED, DonationSchedule::PUBLICATION_DRAFT], true)) {
            $statusFilter = 'all';
        }

        $facilityFilter = (int) $request->query('facility_id', 0);

        $query = DonationSchedule::with(['facility.region', 'region', 'sessions', 'creator'])
            ->mobileUnit();

        if ($user->isUddStaff() && $user->facility_id) {
            $query->where('facility_id', $user->facility_id);
        } elseif ($facilityFilter > 0) {
            $query->where('facility_id', $facilityFilter);
        }

        if ($scopeFilter === 'upcoming') {
            $query->upcoming();
        }

        if ($statusFilter !== 'all') {
            $query->where('publication_status', $statusFilter);
        }

        $events = $query
            ->orderBy('service_date')
            ->orderBy('opening_time')
            ->paginate(10)
            ->withQueryString();

        $summaryBase = DonationSchedule::with('sessions')
            ->mobileUnit()
            ->upcoming();

        if ($user->isUddStaff() && $user->facility_id) {
            $summaryBase->where('facility_id', $user->facility_id);
        }

        $upcomingEvents = $summaryBase->get();

        $summary = [
            'upcoming_events' => $upcomingEvents->count(),
            'published_events' => $upcomingEvents->where('publication_status', DonationSchedule::PUBLICATION_PUBLISHED)->count(),
            'draft_events' => $upcomingEvents->where('publication_status', DonationSchedule::PUBLICATION_DRAFT)->count(),
            'target_bags_total' => (int) $upcomingEvents->sum('target_bags'),
        ];

        $uddFacilities = Facility::active()->udd()->orderBy('name')->get();

        return view('donation-schedules.mobile-index', [
            'events' => $events,
            'summary' => $summary,
            'scopeFilter' => $scopeFilter,
            'statusFilter' => $statusFilter,
            'facilityFilter' => $facilityFilter,
            'uddFacilities' => $uddFacilities,
            'isLockedToSingleUdd' => $user->isUddStaff() && (bool) $user->facility_id,
        ]);
    }

    /**
     * Show the interactive Mobile Unit event planner with Leaflet map pin picker.
     */
    public function create(Request $request): View
    {
        $user = $request->user();

        $uddFacilitiesQuery = Facility::with('region')->active()->udd()->orderBy('name');
        if ($user->isUddStaff() && $user->facility_id) {
            $uddFacilitiesQuery->whereKey($user->facility_id);
        }

        $uddFacilities = $uddFacilitiesQuery->get();
        $selectedFacility = $uddFacilities->firstWhere('id', (int) $user->facility_id) ?? $uddFacilities->first();
        $regions = Region::orderBy('province_name')->orderBy('name')->get();

        $defaultLat = $selectedFacility?->latitude ?? -6.9024812;
        $defaultLng = $selectedFacility?->longitude ?? 107.6188100;
        $defaultRegionId = $selectedFacility?->region_id ?? $regions->first()?->id;

        $defaultSessions = [
            [
                'session_label' => 'Gelombang 1 — Sesi Pagi Mitra',
                'start_time' => '08:30',
                'end_time' => '11:30',
                'quota_total' => 45,
            ],
            [
                'session_label' => 'Gelombang 2 — Sesi Siang Mitra',
                'start_time' => '12:30',
                'end_time' => '15:00',
                'quota_total' => 35,
            ],
        ];

        return view('donation-schedules.mobile-create', [
            'uddFacilities' => $uddFacilities,
            'selectedFacilityId' => $selectedFacility?->id,
            'regions' => $regions,
            'selectedRegionId' => $defaultRegionId,
            'defaultDate' => now()->addDays(5)->toDateString(),
            'defaultLat' => $defaultLat,
            'defaultLng' => $defaultLng,
            'defaultSessions' => $defaultSessions,
        ]);
    }

    /**
     * Persist a new Mobile Unit external donation event and its ordered time sessions atomically.
     */
    public function store(StoreMobileUnitScheduleRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $facility = Facility::findOrFail((int) $validated['facility_id']);

        $orderedSessions = collect((array) $validated['sessions'])
            ->sortBy('start_time')
            ->values();

        $schedule = DB::transaction(function () use ($request, $validated, $facility, $orderedSessions): DonationSchedule {
            $schedule = DonationSchedule::create([
                'facility_id' => $facility->id,
                'region_id' => (int) $validated['region_id'],
                'created_by' => $request->user()->id,
                'schedule_type' => DonationScheduleType::MOBILE_UNIT->value,
                'title' => $validated['title'],
                'partner_institution' => $validated['partner_institution'],
                'service_date' => $validated['service_date'],
                'opening_time' => $validated['opening_time'],
                'closing_time' => $validated['closing_time'],
                'venue_name' => $validated['venue_name'],
                'venue_address' => $validated['venue_address'],
                'latitude' => round((float) $validated['latitude'], 7),
                'longitude' => round((float) $validated['longitude'], 7),
                'target_bags' => (int) $validated['target_bags'],
                'publication_status' => $validated['publication_status'],
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

        $statusNotice = $schedule->isPublished()
            ? 'dipublikasikan ke katalog jadwal pendonor'
            : 'disimpan sebagai rencana draf internal';

        return redirect()
            ->route('schedules.show', $schedule)
            ->with('success', 'Agenda Mobile Unit ('.$schedule->schedule_code.') bersama '.$schedule->partner_institution.' berhasil '.$statusNotice.'.');
    }

    /**
     * Render the form to update Mobile Unit partner metadata, map pin coordinates, target bags, or publication status.
     */
    public function edit(Request $request, DonationSchedule $schedule): View
    {
        $this->authorizeMobileUnitAccess($request, $schedule);

        $schedule->load(['facility.region', 'region', 'sessions']);
        $regions = Region::orderBy('province_name')->orderBy('name')->get();
        $hasActiveBookings = $schedule->bookings()->activeReservation()->exists() || $schedule->total_booked > 0;

        return view('donation-schedules.mobile-edit', [
            'schedule' => $schedule,
            'regions' => $regions,
            'hasActiveBookings' => $hasActiveBookings,
        ]);
    }

    /**
     * Update an existing Mobile Unit agenda item.
     */
    public function update(UpdateMobileUnitScheduleRequest $request, DonationSchedule $schedule): RedirectResponse
    {
        $validated = $request->validated();

        $schedule->update([
            'region_id' => (int) $validated['region_id'],
            'service_date' => $validated['service_date'],
            'title' => $validated['title'],
            'partner_institution' => $validated['partner_institution'],
            'venue_name' => $validated['venue_name'],
            'venue_address' => $validated['venue_address'],
            'latitude' => round((float) $validated['latitude'], 7),
            'longitude' => round((float) $validated['longitude'], 7),
            'target_bags' => (int) $validated['target_bags'],
            'opening_time' => $validated['opening_time'],
            'closing_time' => $validated['closing_time'],
            'publication_status' => $validated['publication_status'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('schedules.show', $schedule)
            ->with('success', 'Rincian agenda Mobile Unit ('.$schedule->schedule_code.') berhasil diperbarui.');
    }

    /**
     * Toggle or explicitly set publication status (`draft` <-> `published`) for a Mobile Unit agenda.
     */
    public function updatePublication(Request $request, DonationSchedule $schedule): RedirectResponse
    {
        $this->authorizeMobileUnitAccess($request, $schedule);

        $validated = $request->validate([
            'publication_status' => [
                'required',
                'string',
                Rule::in([DonationSchedule::PUBLICATION_DRAFT, DonationSchedule::PUBLICATION_PUBLISHED]),
            ],
        ]);

        $targetStatus = (string) $validated['publication_status'];

        if ($targetStatus === DonationSchedule::PUBLICATION_DRAFT) {
            $schedule->loadMissing('sessions');
            $hasActiveBookings = $schedule->bookings()->activeReservation()->exists() || $schedule->total_booked > 0;

            if ($hasActiveBookings) {
                return back()->with(
                    'warning',
                    'Agenda Mobile Unit ini sudah memiliki pendaftar aktif sehingga tidak dapat dikembalikan ke Draf. Gunakan kontrol status operasional sesi bila terdapat kendala lapangan.'
                );
            }
        }

        $schedule->update([
            'publication_status' => $targetStatus,
        ]);

        $message = $schedule->isPublished()
            ? 'Agenda Mobile Unit ('.$schedule->schedule_code.') resmi dipublikasikan dan kini tampil bagi pendonor.'
            : 'Agenda Mobile Unit ('.$schedule->schedule_code.') ditarik ke status Draf Internal dan disembunyikan dari publik.';

        return back()->with('success', $message);
    }

    private function authorizeMobileUnitAccess(Request $request, DonationSchedule $schedule): void
    {
        if (! $schedule->isMobileUnit()) {
            abort(404, 'Jadwal yang dipilih bukan merupakan agenda kegiatan Mobile Unit.');
        }

        $user = $request->user();
        if ($user->isUddStaff() && $user->facility_id && (int) $schedule->facility_id !== (int) $user->facility_id) {
            abort(403, 'Anda hanya berwenang mengelola agenda Mobile Unit pada unit UDD instansi Anda sendiri.');
        }
    }
}

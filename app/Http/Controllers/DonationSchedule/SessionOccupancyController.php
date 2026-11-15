<?php

namespace App\Http\Controllers\DonationSchedule;

use App\Enums\DonationScheduleType;
use App\Enums\DonorBookingStatus;
use App\Enums\SessionOperationalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DonationSchedule\UpdateSessionOperationalStatusRequest;
use App\Models\DonationSchedule;
use App\Models\DonationSession;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SessionOccupancyController extends Controller
{
    /**
     * Real-time UDD session occupancy monitor and operational slot status control center.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $periodFilter = (string) $request->query('period', 'upcoming');
        if (! in_array($periodFilter, ['today', 'upcoming', 'all'], true)) {
            $periodFilter = 'upcoming';
        }

        $typeFilter = (string) $request->query('type', 'all');
        if (! in_array($typeFilter, ['all', ...DonationScheduleType::values()], true)) {
            $typeFilter = 'all';
        }

        $statusFilter = (string) $request->query('status', 'all');
        if (! in_array($statusFilter, ['all', ...SessionOperationalStatus::values()], true)) {
            $statusFilter = 'all';
        }

        $facilityFilter = (int) $request->query('facility_id', 0);

        $schedulesQuery = DonationSchedule::with([
            'facility.region',
            'region',
            'sessions.bookings' => function ($query): void {
                $query->whereIn('status', [
                    DonorBookingStatus::CONFIRMED->value,
                    DonorBookingStatus::PENDING_SCREENING->value,
                ])->with('donor')->orderBy('created_at');
            },
        ]);

        if ($user->isUddStaff() && $user->facility_id) {
            $schedulesQuery->where('facility_id', $user->facility_id);
        } elseif ($facilityFilter > 0) {
            $schedulesQuery->where('facility_id', $facilityFilter);
        }

        if ($periodFilter === 'today') {
            $schedulesQuery->whereDate('service_date', today());
        } elseif ($periodFilter === 'upcoming') {
            $schedulesQuery->upcoming();
        }

        if ($typeFilter !== 'all') {
            $schedulesQuery->where('schedule_type', $typeFilter);
        }

        if ($statusFilter !== 'all') {
            $schedulesQuery->whereHas('sessions', function (Builder $sessionQuery) use ($statusFilter): void {
                $sessionQuery->where('operational_status', $statusFilter);
            });
        }

        $schedules = $schedulesQuery
            ->orderBy('service_date')
            ->orderBy('opening_time')
            ->get();

        $allSessions = $schedules->flatMap(fn (DonationSchedule $s) => $s->sessions);
        if ($statusFilter !== 'all') {
            $filteredSessions = $allSessions->filter(
                fn (DonationSession $session): bool => $session->operational_status->value === $statusFilter
            );
        } else {
            $filteredSessions = $allSessions;
        }

        $totalQuota = (int) $filteredSessions->sum('quota_total');
        $totalBooked = (int) $filteredSessions->sum('quota_booked');

        $summary = [
            'total_sessions' => $filteredSessions->count(),
            'total_quota' => $totalQuota,
            'total_booked' => $totalBooked,
            'occupancy_rate' => $totalQuota > 0 ? (int) min(100, round(($totalBooked / $totalQuota) * 100)) : 0,
            'open_sessions' => $filteredSessions->filter(
                fn (DonationSession $s): bool => $s->operational_status === SessionOperationalStatus::OPEN
            )->count(),
            'disrupted_sessions' => $filteredSessions->filter(
                fn (DonationSession $s): bool => $s->operational_status !== SessionOperationalStatus::OPEN
            )->count(),
            'full_sessions' => $filteredSessions->filter(
                fn (DonationSession $s): bool => $s->operational_status === SessionOperationalStatus::OPEN && $s->remaining_quota <= 0
            )->count(),
        ];

        $uddFacilities = Facility::active()->udd()->orderBy('name')->get();

        return view('donation-schedules.occupancy', [
            'schedules' => $schedules,
            'summary' => $summary,
            'periodFilter' => $periodFilter,
            'typeFilter' => $typeFilter,
            'statusFilter' => $statusFilter,
            'facilityFilter' => $facilityFilter,
            'uddFacilities' => $uddFacilities,
            'statusOptions' => SessionOperationalStatus::cases(),
            'isLockedToSingleUdd' => $user->isUddStaff() && (bool) $user->facility_id,
        ]);
    }

    /**
     * Atomically update a session slot's operational status (open / paused / closed_emergency),
     * technical disruption note, and optional seat capacity so donor booking availability updates immediately.
     */
    public function updateSessionStatus(
        UpdateSessionOperationalStatusRequest $request,
        DonationSchedule $schedule,
        DonationSession $session
    ): RedirectResponse {
        if ((int) $session->donation_schedule_id !== (int) $schedule->id) {
            abort(404, 'Sesi pelayanan tidak ditemukan pada jadwal yang dipilih.');
        }

        $validated = $request->validated();
        $targetStatus = SessionOperationalStatus::from((string) $validated['operational_status']);
        $noteInput = trim((string) ($validated['operational_note'] ?? ''));

        $quotaBlockedError = null;

        DB::transaction(function () use ($session, $validated, $targetStatus, $noteInput, &$quotaBlockedError): void {
            /** @var DonationSession $lockedSession */
            $lockedSession = DonationSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            $newQuotaTotal = isset($validated['quota_total']) && $validated['quota_total'] !== ''
                ? (int) $validated['quota_total']
                : (int) $lockedSession->quota_total;

            if ($newQuotaTotal < (int) $lockedSession->quota_booked) {
                $quotaBlockedError = "Kapasitas kuota tidak dapat diturunkan ke {$newQuotaTotal} karena sudah terdapat {$lockedSession->quota_booked} pendaftar terkonfirmasi.";

                return;
            }

            $resolvedNote = $targetStatus === SessionOperationalStatus::OPEN && $noteInput === ''
                ? null
                : ($noteInput !== '' ? $noteInput : null);

            $lockedSession->update([
                'operational_status' => $targetStatus->value,
                'operational_note' => $resolvedNote,
                'quota_total' => $newQuotaTotal,
            ]);
        });

        if ($quotaBlockedError !== null) {
            return back()
                ->withInput()
                ->with('error', $quotaBlockedError);
        }

        $feedbackPrefix = "Status operasional slot \"{$session->session_label}\" pada jadwal {$schedule->schedule_code} berhasil diperbarui menjadi {$targetStatus->label()}.";

        if ($targetStatus !== SessionOperationalStatus::OPEN) {
            return back()->with(
                'warning',
                $feedbackPrefix.' Pemesanan baru oleh pendonor pada slot ini dikunci sementara hingga status dibuka kembali.'
            );
        }

        return back()->with('success', $feedbackPrefix);
    }
}

<?php

namespace App\Http\Controllers\DonorCommitment;

use App\Enums\BloodComponent;
use App\Enums\BloodRequestStatus;
use App\Enums\DonorCommitmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DonorCommitment\StoreDonorCommitmentRequest;
use App\Models\BloodRequest;
use App\Models\DonorCommitment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DonorCommitmentController extends Controller
{
    /**
     * Display the authenticated donor's pledges OR the blood seeker's incoming volunteer commitments.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->isSeeker()) {
            $incomingCommitments = DonorCommitment::with(['donor', 'bloodRequest.facility'])
                ->whereHas('bloodRequest', function ($query) use ($user): void {
                    $query->where('requester_id', $user->id);
                })
                ->orderByRaw("CASE status WHEN 'arrived' THEN 1 WHEN 'committed' THEN 2 ELSE 3 END ASC")
                ->orderBy('estimated_arrival_at')
                ->paginate(10);

            $summary = [
                'active_incoming' => DonorCommitment::query()
                    ->activeCommitment()
                    ->whereHas('bloodRequest', fn ($q) => $q->where('requester_id', $user->id))
                    ->count(),
                'arrived_count' => DonorCommitment::query()
                    ->where('status', DonorCommitmentStatus::ARRIVED->value)
                    ->whereHas('bloodRequest', fn ($q) => $q->where('requester_id', $user->id))
                    ->count(),
                'total_incoming' => $incomingCommitments->total(),
            ];

            return view('donor-commitments.index', [
                'mode' => 'seeker',
                'commitments' => $incomingCommitments,
                'summary' => $summary,
            ]);
        }

        $donorCommitments = DonorCommitment::with(['bloodRequest.facility.region'])
            ->forDonor($user->id)
            ->orderByRaw("CASE status WHEN 'arrived' THEN 1 WHEN 'committed' THEN 2 ELSE 3 END ASC")
            ->orderByDesc('created_at')
            ->paginate(10);

        $activeCommitment = DonorCommitment::with(['bloodRequest.facility.region'])
            ->forDonor($user->id)
            ->activeCommitment()
            ->first();

        $summary = [
            'active_count' => $activeCommitment ? 1 : 0,
            'completed_count' => DonorCommitment::forDonor($user->id)
                ->where('status', DonorCommitmentStatus::COMPLETED->value)
                ->count(),
            'total_pledges' => $donorCommitments->total(),
        ];

        return view('donor-commitments.index', [
            'mode' => 'donor',
            'commitments' => $donorCommitments,
            'activeCommitment' => $activeCommitment,
            'summary' => $summary,
        ]);
    }

    /**
     * Show the pledge form for an active emergency blood request with ETA selection.
     */
    public function create(Request $request, BloodRequest $bloodRequest): View|RedirectResponse
    {
        $this->ensureRequestAcceptsPledges($bloodRequest);

        $user = $request->user();

        $existingOnSameRequest = DonorCommitment::query()
            ->forDonor($user->id)
            ->where('blood_request_id', $bloodRequest->id)
            ->activeCommitment()
            ->first();

        if ($existingOnSameRequest) {
            return redirect()
                ->route('commitments.show', $existingOnSameRequest)
                ->with('status', 'Anda sudah memiliki komitmen aktif pada permohonan darah ini.');
        }

        $bloodRequest->load(['facility.region']);

        $activePledgesCount = DonorCommitment::query()
            ->where('blood_request_id', $bloodRequest->id)
            ->activeCommitment()
            ->count();

        $activeOtherCommitment = DonorCommitment::with('bloodRequest')
            ->forDonor($user->id)
            ->activeCommitment()
            ->first();

        $compatibility = $this->evaluateDonorCompatibility($user, $bloodRequest);

        return view('donor-commitments.create', [
            'bloodRequest' => $bloodRequest,
            'activePledgesCount' => $activePledgesCount,
            'activeOtherCommitment' => $activeOtherCommitment,
            'compatibility' => $compatibility,
            'defaultEta' => now()->addMinutes(90)->format('Y-m-d\TH:i'),
        ]);
    }

    /**
     * Persist a new volunteer donor commitment (pledge) with atomic concurrency validation.
     */
    public function store(StoreDonorCommitmentRequest $request, BloodRequest $bloodRequest): RedirectResponse
    {
        $user = $request->user();
        $compatibility = $this->evaluateDonorCompatibility($user, $bloodRequest);

        if (! $compatibility['compatible']) {
            return back()
                ->withInput()
                ->withErrors(['compatibility' => $compatibility['reason']]);
        }

        $eta = Carbon::parse((string) $request->validated('estimated_arrival_at'));

        if ($bloodRequest->expires_at !== null && $eta->greaterThan($bloodRequest->expires_at)) {
            return back()
                ->withInput()
                ->withErrors([
                    'estimated_arrival_at' => 'Estimasi kehadiran tidak boleh melewati masa aktif permohonan ('.$bloodRequest->expires_at->translatedFormat('d M Y, H:i').' WIB).',
                ]);
        }

        $commitment = DB::transaction(function () use ($request, $bloodRequest, $user, $eta): ?DonorCommitment {
            /** @var BloodRequest $lockedRequest */
            $lockedRequest = BloodRequest::query()
                ->whereKey($bloodRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $isExpired = $lockedRequest->expires_at !== null && $lockedRequest->expires_at->isPast();
            if ($lockedRequest->status !== BloodRequestStatus::ACTIVE || $isExpired || $lockedRequest->remaining_bags <= 0) {
                return null;
            }

            $hasActiveCommitment = DonorCommitment::query()
                ->forDonor($user->id)
                ->activeCommitment()
                ->lockForUpdate()
                ->exists();

            if ($hasActiveCommitment) {
                return null;
            }

            $activePledgesForCase = DonorCommitment::query()
                ->where('blood_request_id', $lockedRequest->id)
                ->activeCommitment()
                ->count();

            $maxAllowedPledges = $lockedRequest->remaining_bags + 2;
            if ($activePledgesForCase >= $maxAllowedPledges) {
                return null;
            }

            return DonorCommitment::create([
                'blood_request_id' => $lockedRequest->id,
                'donor_id' => $user->id,
                'status' => DonorCommitmentStatus::COMMITTED->value,
                'bags_pledged' => 1,
                'estimated_arrival_at' => $eta,
                'health_readiness_confirmed' => true,
                'pledge_notes' => $request->validated('pledge_notes'),
                'meeting_point' => 'Instalasi Bank Darah Rumah Sakit (BDRS) / Ruang Donor Terkait',
            ]);
        });

        if (! $commitment) {
            return back()
                ->withInput()
                ->with('warning', 'Komitmen belum dapat diproses karena Anda masih memiliki komitmen aktif lain atau kuota relawan siaga pada kasus ini sudah mencukupi.');
        }

        return redirect()
            ->route('commitments.show', $commitment)
            ->with('success', 'Terima kasih! Komitmen kesediaan donor Anda ('.$commitment->pledge_code.') telah tercatat dan diteruskan kepada pemohon.');
    }

    /**
     * Display structured commitment receipt, ETA countdown, and privacy-safe hospital instructions.
     */
    public function show(Request $request, DonorCommitment $commitment): View
    {
        $user = $request->user();
        $commitment->load(['donor', 'bloodRequest.facility.region']);

        $isOwnerDonor = $user->isDonor() && (int) $commitment->donor_id === (int) $user->id;
        $isOwnerSeeker = $user->isSeeker() && (int) $commitment->bloodRequest?->requester_id === (int) $user->id;
        $isMedicalStaff = $user->hasRole('petugas_udd', 'petugas_rs', 'admin');

        if (! $isOwnerDonor && ! $isOwnerSeeker && ! $isMedicalStaff) {
            abort(403, 'Anda tidak memiliki akses terhadap rincian komitmen donor ini.');
        }

        $activePledgesCount = DonorCommitment::query()
            ->where('blood_request_id', $commitment->blood_request_id)
            ->activeCommitment()
            ->count();

        return view('donor-commitments.show', [
            'commitment' => $commitment,
            'bloodRequest' => $commitment->bloodRequest,
            'isOwnerDonor' => $isOwnerDonor,
            'isOwnerSeeker' => $isOwnerSeeker,
            'activePledgesCount' => $activePledgesCount,
        ]);
    }

    private function ensureRequestAcceptsPledges(BloodRequest $bloodRequest): void
    {
        $isExpired = $bloodRequest->expires_at !== null && $bloodRequest->expires_at->isPast();

        if ($bloodRequest->status !== BloodRequestStatus::ACTIVE || $isExpired || $bloodRequest->remaining_bags <= 0) {
            abort(404, 'Permohonan darah ini sudah tidak membuka komitmen relawan baru.');
        }
    }

    /**
     * Check transfusion compatibility between donor profile and patient component requirement.
     *
     * @return array{compatible: bool, label: string, reason: string}
     */
    private function evaluateDonorCompatibility(User $donor, BloodRequest $bloodRequest): array
    {
        if (! $donor->blood_type || ! $donor->rhesus) {
            return [
                'compatible' => false,
                'label' => 'Golongan Darah Profil Belum Lengkap',
                'reason' => 'Lengkapi golongan darah ABO dan Rhesus pada akun Pendonor Anda sebelum menyatakan komitmen donor.',
            ];
        }

        $donorAbo = $donor->blood_type->value;
        $donorRh = $donor->rhesus->value;
        $recipientAbo = $bloodRequest->blood_type->value;
        $recipientRh = $bloodRequest->rhesus->value;
        $component = $bloodRequest->component_type;

        $aboCompatible = match ($component) {
            BloodComponent::WB => $donorAbo === $recipientAbo,
            BloodComponent::FFP => match ($donorAbo) {
                'AB' => true,
                'A' => in_array($recipientAbo, ['A', 'O'], true),
                'B' => in_array($recipientAbo, ['B', 'O'], true),
                'O' => $recipientAbo === 'O',
                default => false,
            },
            default => match ($donorAbo) { // PRC & TC cellular compatibility
                'O' => true,
                'A' => in_array($recipientAbo, ['A', 'AB'], true),
                'B' => in_array($recipientAbo, ['B', 'AB'], true),
                'AB' => $recipientAbo === 'AB',
                default => false,
            },
        };

        $rhCompatible = $component === BloodComponent::FFP
            ? true
            : ($donorRh === '-' || $recipientRh === '+');

        if (! $aboCompatible || ! $rhCompatible) {
            return [
                'compatible' => false,
                'label' => 'Tidak Kompatibel Secara Aturan Transfusi',
                'reason' => "Golongan darah Anda ({$donorAbo}{$donorRh}) tidak sesuai dengan aturan kompatibilitas komponen {$component->value} untuk pasien golongan {$recipientAbo}{$recipientRh}.",
            ];
        }

        $isExactMatch = ($donorAbo === $recipientAbo && $donorRh === $recipientRh);

        return [
            'compatible' => true,
            'label' => $isExactMatch
                ? "Golongan Identik ({$donorAbo}{$donorRh} -> {$recipientAbo}{$recipientRh})"
                : "Kompatibel Medis ({$donorAbo}{$donorRh} -> {$recipientAbo}{$recipientRh} untuk {$component->value})",
            'reason' => 'Profil golongan darah Anda memenuhi syarat kompatibilitas komponen yang dibutuhkan pasien.',
        ];
    }
}

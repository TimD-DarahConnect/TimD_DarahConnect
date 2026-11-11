<?php

namespace App\Http\Controllers\MedicalVerification;

use App\Enums\BloodRequestStatus;
use App\Enums\BloodType;
use App\Enums\UrgencyLevel;
use App\Enums\VerificationDecision;
use App\Http\Controllers\Controller;
use App\Http\Requests\MedicalVerification\ApproveBloodRequest;
use App\Http\Requests\MedicalVerification\RejectOrReviseBloodRequest;
use App\Models\BloodRequest;
use App\Models\RequestVerification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MedicalVerificationController extends Controller
{
    /**
     * Display the UDD medical verification queue sorted by clinical urgency and anemia severity.
     */
    public function index(Request $request): View
    {
        $queueTab = (string) $request->query('queue', 'pending');
        if (! in_array($queueTab, ['pending', 'reviewed', 'all'], true)) {
            $queueTab = 'pending';
        }

        $urgencyFilter = (string) $request->query('urgency', '');
        if ($urgencyFilter !== '' && ! in_array($urgencyFilter, UrgencyLevel::values(), true)) {
            $urgencyFilter = '';
        }

        $bloodTypeFilter = (string) $request->query('blood_type', '');
        if ($bloodTypeFilter !== '' && ! in_array($bloodTypeFilter, BloodType::values(), true)) {
            $bloodTypeFilter = '';
        }

        $searchQuery = trim((string) $request->query('q', ''));

        $baseSubmittedQuery = BloodRequest::query()
            ->where('status', '!=', BloodRequestStatus::DRAFT->value);

        $summary = [
            'pending' => (clone $baseSubmittedQuery)
                ->where('status', BloodRequestStatus::PENDING_VERIFICATION->value)
                ->count(),
            'critical_pending' => (clone $baseSubmittedQuery)
                ->where('status', BloodRequestStatus::PENDING_VERIFICATION->value)
                ->where(function (Builder $q): void {
                    $q->where('urgency_level', UrgencyLevel::CRITICAL->value)
                        ->orWhere(function (Builder $hbQuery): void {
                            $hbQuery->whereNotNull('hemoglobin_level')
                                ->where('hemoglobin_level', '<', 7.0);
                        });
                })
                ->count(),
            'active_published' => BloodRequest::query()->active()->count(),
            'reviewed_today' => (clone $baseSubmittedQuery)
                ->whereNotNull('verified_at')
                ->whereDate('verified_at', today())
                ->count(),
        ];

        $query = BloodRequest::with(['requester.region', 'facility.region', 'verifier'])
            ->where('status', '!=', BloodRequestStatus::DRAFT->value);

        if ($queueTab === 'pending') {
            $query->where('status', BloodRequestStatus::PENDING_VERIFICATION->value);
        } elseif ($queueTab === 'reviewed') {
            $query->whereIn('status', [
                BloodRequestStatus::ACTIVE->value,
                BloodRequestStatus::NEED_REVISION->value,
                BloodRequestStatus::REJECTED->value,
                BloodRequestStatus::FULFILLED->value,
                BloodRequestStatus::EXPIRED->value,
            ]);
        }

        if ($urgencyFilter !== '') {
            $query->where('urgency_level', $urgencyFilter);
        }

        if ($bloodTypeFilter !== '') {
            $query->where('blood_type', $bloodTypeFilter);
        }

        if ($searchQuery !== '') {
            $like = '%'.$searchQuery.'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('request_code', 'like', $like)
                    ->orWhere('patient_name', 'like', $like)
                    ->orWhere('clinical_diagnosis', 'like', $like)
                    ->orWhere('doctor_name', 'like', $like)
                    ->orWhereHas('facility', function (Builder $facilityQuery) use ($like): void {
                        $facilityQuery->where('name', 'like', $like);
                    });
            });
        }

        if ($queueTab === 'pending') {
            $this->applyClinicalTriageOrder($query);
        } else {
            $query
                ->orderByDesc('verified_at')
                ->orderByDesc('submitted_at')
                ->orderByDesc('id');
        }

        $requests = $query->paginate(10)->withQueryString();

        return view('medical-verifications.index', [
            'requests' => $requests,
            'summary' => $summary,
            'queueTab' => $queueTab,
            'urgencyFilter' => $urgencyFilter,
            'bloodTypeFilter' => $bloodTypeFilter,
            'searchQuery' => $searchQuery,
            'urgencyOptions' => UrgencyLevel::cases(),
            'bloodTypes' => BloodType::cases(),
        ]);
    }

    /**
     * Display the split-pane clinical dossier and medical referral document viewer.
     */
    public function show(BloodRequest $bloodRequest): View
    {
        if ($bloodRequest->status === BloodRequestStatus::DRAFT) {
            abort(404, 'Draf yang belum dikirim tidak tersedia di antrean verifikasi medis.');
        }

        $bloodRequest->load(['requester.region', 'facility.region', 'verifier']);

        $verificationLogs = Schema::hasTable('request_verifications')
            ? $bloodRequest->verifications()->with('reviewer')->get()
            : collect();

        $nextPendingQuery = BloodRequest::query()
            ->where('status', BloodRequestStatus::PENDING_VERIFICATION->value)
            ->where('id', '!=', $bloodRequest->id);

        $this->applyClinicalTriageOrder($nextPendingQuery);
        $nextPendingRequest = $nextPendingQuery->first(['id', 'request_code', 'patient_name', 'urgency_level']);

        $mime = strtolower((string) $bloodRequest->referral_letter_mime);
        $isPdfAttachment = $bloodRequest->hasReferralLetter() && str_contains($mime, 'pdf');
        $isImageAttachment = $bloodRequest->hasReferralLetter() && str_starts_with($mime, 'image/');

        $fileSizeFormatted = null;
        if ($bloodRequest->referral_letter_size) {
            $kb = $bloodRequest->referral_letter_size / 1024;
            $fileSizeFormatted = $kb >= 1024
                ? number_format($kb / 1024, 2).' MB'
                : number_format($kb, 0).' KB';
        }

        $recommendedHours = match ($bloodRequest->urgency_level) {
            UrgencyLevel::CRITICAL => 48,
            UrgencyLevel::URGENT => 72,
            default => 120,
        };

        $defaultExpiresAt = now()->addHours($recommendedHours)->startOfMinute();
        if (
            $bloodRequest->needed_at !== null
            && $bloodRequest->needed_at->isAfter(now()->addHours(2))
            && $bloodRequest->needed_at->isBefore(now()->addDays(12))
        ) {
            $candidate = $bloodRequest->needed_at->copy()->addHours(6)->startOfMinute();
            if ($candidate->isAfter(now()->addHours(12))) {
                $defaultExpiresAt = $candidate;
            }
        }

        return view('medical-verifications.show', [
            'bloodRequest' => $bloodRequest,
            'nextPendingRequest' => $nextPendingRequest,
            'verificationLogs' => $verificationLogs,
            'defaultExpiresAt' => $defaultExpiresAt->format('Y-m-d\TH:i'),
            'isSevereAnemia' => $bloodRequest->hemoglobin_level !== null && $bloodRequest->hemoglobin_level < 7.0,
            'isPdfAttachment' => $isPdfAttachment,
            'isImageAttachment' => $isImageAttachment,
            'fileSizeFormatted' => $fileSizeFormatted,
        ]);
    }

    /**
     * Approve a verified emergency blood request, set its active publication deadline, and publish to donors.
     */
    public function approve(ApproveBloodRequest $request, BloodRequest $bloodRequest): RedirectResponse
    {
        if ($bloodRequest->status === BloodRequestStatus::DRAFT) {
            abort(404, 'Draf permohonan belum dikirim ke antrean verifikasi medis.');
        }

        $validated = $request->validated();
        $expiresAt = Carbon::parse($validated['expires_at']);
        $notes = $validated['verification_notes'] ?? null;

        $abortReason = null;
        $abortLevel = 'warning';

        $approvedRequest = DB::transaction(function () use (
            $bloodRequest,
            $request,
            $expiresAt,
            $notes,
            &$abortReason,
            &$abortLevel
        ): ?BloodRequest {
            $locked = BloodRequest::query()
                ->whereKey($bloodRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== BloodRequestStatus::PENDING_VERIFICATION) {
                $abortReason = "Permohonan {$locked->request_code} saat ini berstatus \"{$locked->status->label()}\" sehingga tidak dapat disetujui ulang.";
                $abortLevel = 'warning';

                return null;
            }

            if (! $locked->hasReferralLetter()) {
                $abortReason = "Permohonan {$locked->request_code} belum melampirkan berkas surat rujukan dokter sehingga tidak dapat disahkan untuk publikasi.";
                $abortLevel = 'error';

                return null;
            }

            $decidedAt = now();
            $previousStatus = $locked->status->value;

            $locked->update([
                'status' => BloodRequestStatus::ACTIVE->value,
                'verified_by' => $request->user()->id,
                'verified_at' => $decidedAt,
                'verification_notes' => $notes,
                'expires_at' => $expiresAt,
            ]);

            if (Schema::hasTable('request_verifications')) {
                RequestVerification::create([
                    'blood_request_id' => $locked->id,
                    'reviewer_id' => $request->user()->id,
                    'decision' => VerificationDecision::APPROVED->value,
                    'previous_status' => $previousStatus,
                    'resulting_status' => BloodRequestStatus::ACTIVE->value,
                    'expires_at' => $expiresAt,
                    'notes' => $notes,
                    'decided_at' => $decidedAt,
                ]);
            }

            return $locked->fresh();
        });

        if (! $approvedRequest instanceof BloodRequest) {
            return redirect()
                ->route('verifications.show', $bloodRequest)
                ->with($abortLevel, $abortReason ?? 'Permohonan tidak dapat diproses saat ini.');
        }

        return redirect()
            ->route('verifications.show', $approvedRequest)
            ->with(
                'success',
                "Permohonan {$approvedRequest->request_code} ({$approvedRequest->patient_name}) resmi disetujui dan dipublikasikan ke komunitas pendonor hingga {$expiresAt->translatedFormat('d M Y, H:i')} WIB."
            );
    }

    /**
     * Reject an invalid blood request or return it to the requester (`need_revision`) with mandatory official clinical notes.
     */
    public function reject(RejectOrReviseBloodRequest $request, BloodRequest $bloodRequest): RedirectResponse
    {
        if ($bloodRequest->status === BloodRequestStatus::DRAFT) {
            abort(404, 'Draf permohonan belum dikirim ke antrean verifikasi medis.');
        }

        $validated = $request->validated();
        $decision = VerificationDecision::from((string) $validated['decision']);
        $notes = (string) $validated['review_notes'];

        $resultingStatus = $decision === VerificationDecision::NEED_REVISION
            ? BloodRequestStatus::NEED_REVISION
            : BloodRequestStatus::REJECTED;

        $abortReason = null;

        $updatedRequest = DB::transaction(function () use (
            $bloodRequest,
            $request,
            $decision,
            $resultingStatus,
            $notes,
            &$abortReason
        ): ?BloodRequest {
            /** @var BloodRequest $locked */
            $locked = BloodRequest::query()
                ->whereKey($bloodRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== BloodRequestStatus::PENDING_VERIFICATION) {
                $abortReason = "Permohonan {$locked->request_code} saat ini berstatus \"{$locked->status->label()}\" sehingga keputusan revisi/penolakan tidak dapat diterapkan.";

                return null;
            }

            $decidedAt = now();
            $previousStatus = $locked->status->value;

            $locked->update([
                'status' => $resultingStatus->value,
                'verified_by' => $request->user()->id,
                'verified_at' => $decidedAt,
                'verification_notes' => $notes,
                'expires_at' => null,
            ]);

            if (Schema::hasTable('request_verifications')) {
                RequestVerification::create([
                    'blood_request_id' => $locked->id,
                    'reviewer_id' => $request->user()->id,
                    'decision' => $decision->value,
                    'previous_status' => $previousStatus,
                    'resulting_status' => $resultingStatus->value,
                    'expires_at' => null,
                    'notes' => $notes,
                    'decided_at' => $decidedAt,
                ]);
            }

            return $locked->fresh();
        });

        if (! $updatedRequest instanceof BloodRequest) {
            return redirect()
                ->route('verifications.show', $bloodRequest)
                ->with('warning', $abortReason ?? 'Permohonan tidak dapat diproses saat ini.');
        }

        $successMessage = $decision === VerificationDecision::NEED_REVISION
            ? "Permohonan {$updatedRequest->request_code} dikembalikan kepada pemohon dengan status \"Perlu Revisi Data\" beserta catatan perbaikan resmi UDD."
            : "Permohonan {$updatedRequest->request_code} resmi ditolak secara medis dan ditutup dari antrean publikasi.";

        return redirect()
            ->route('verifications.show', $updatedRequest)
            ->with('success', $successMessage);
    }

    /**
     * Stream or download the protected referral document for UDD clinical inspection.
     */
    public function document(Request $request, BloodRequest $bloodRequest): StreamedResponse
    {
        if ($bloodRequest->status === BloodRequestStatus::DRAFT) {
            abort(404);
        }

        if (! $bloodRequest->hasReferralLetter() || ! Storage::disk('local')->exists($bloodRequest->referral_letter_path)) {
            abort(404, 'Berkas surat rujukan dokter tidak ditemukan pada penyimpanan.');
        }

        $filename = $bloodRequest->referral_letter_original_name ?: basename($bloodRequest->referral_letter_path);
        $mime = $bloodRequest->referral_letter_mime ?: 'application/pdf';
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return Storage::disk('local')->response(
            $bloodRequest->referral_letter_path,
            $filename,
            [
                'Content-Type' => $mime,
                'Content-Disposition' => $disposition.'; filename="'.addslashes($filename).'"',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /**
     * Prioritize critical cases, lowest hemoglobin, earliest clinical deadline, and FIFO arrival.
     */
    private function applyClinicalTriageOrder(Builder $query): void
    {
        $query
            ->orderByUrgency()
            ->orderByRaw('CASE WHEN hemoglobin_level IS NULL THEN 999 ELSE hemoglobin_level END ASC')
            ->orderByRaw('CASE WHEN needed_at IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('needed_at')
            ->orderBy('submitted_at')
            ->orderBy('id');
    }
}

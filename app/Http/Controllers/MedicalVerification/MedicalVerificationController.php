<?php

namespace App\Http\Controllers\MedicalVerification;

use App\Enums\BloodRequestStatus;
use App\Enums\BloodType;
use App\Enums\UrgencyLevel;
use App\Http\Controllers\Controller;
use App\Models\BloodRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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

        return view('medical-verifications.show', [
            'bloodRequest' => $bloodRequest,
            'nextPendingRequest' => $nextPendingRequest,
            'isSevereAnemia' => $bloodRequest->hemoglobin_level !== null && $bloodRequest->hemoglobin_level < 7.0,
            'isPdfAttachment' => $isPdfAttachment,
            'isImageAttachment' => $isImageAttachment,
            'fileSizeFormatted' => $fileSizeFormatted,
        ]);
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

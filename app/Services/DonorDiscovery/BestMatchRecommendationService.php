<?php

namespace App\Services\DonorDiscovery;

use App\Enums\UrgencyLevel;
use App\Models\BloodRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BestMatchRecommendationService
{
    /**
     * Minimum whole-blood / red-cell recovery interval under Indonesian UDD clinical guidelines (60 days).
     */
    public const MIN_DONATION_INTERVAL_DAYS = 60;

    public function __construct(
        private readonly TransfusionCompatibilityService $compatibilityService
    ) {}

    /**
     * Evaluate whether the current viewer can receive personalized biological & geospatial Best Match recommendations.
     *
     * @return array{
     *     state: string,
     *     is_ready: bool,
     *     blood_group: ?string,
     *     cooldown_active: bool,
     *     days_since_last_donation: ?int,
     *     days_until_eligible: int,
     *     next_eligible_date_label: ?string,
     *     cooldown_notice: ?string
     * }
     */
    public function evaluateDonorReadiness(?User $user): array
    {
        if ($user === null) {
            return [
                'state' => 'guest',
                'is_ready' => false,
                'blood_group' => null,
                'cooldown_active' => false,
                'days_since_last_donation' => null,
                'days_until_eligible' => 0,
                'next_eligible_date_label' => null,
                'cooldown_notice' => null,
            ];
        }

        if (! $user->isDonor()) {
            return [
                'state' => 'non_donor',
                'is_ready' => false,
                'blood_group' => $user->blood_group_label,
                'cooldown_active' => false,
                'days_since_last_donation' => null,
                'days_until_eligible' => 0,
                'next_eligible_date_label' => null,
                'cooldown_notice' => null,
            ];
        }

        if ($user->blood_type === null || $user->rhesus === null) {
            return [
                'state' => 'incomplete_profile',
                'is_ready' => false,
                'blood_group' => null,
                'cooldown_active' => false,
                'days_since_last_donation' => null,
                'days_until_eligible' => 0,
                'next_eligible_date_label' => null,
                'cooldown_notice' => null,
            ];
        }

        $cooldownActive = false;
        $daysSinceLast = null;
        $daysUntilEligible = 0;
        $nextEligibleLabel = null;
        $cooldownNotice = null;

        if ($user->last_donated_at instanceof Carbon) {
            $today = Carbon::today();
            $lastDate = $user->last_donated_at->copy()->startOfDay();

            if ($lastDate->lte($today)) {
                $daysSinceLast = (int) $lastDate->diffInDays($today);
                if ($daysSinceLast < self::MIN_DONATION_INTERVAL_DAYS) {
                    $cooldownActive = true;
                    $daysUntilEligible = self::MIN_DONATION_INTERVAL_DAYS - $daysSinceLast;
                    $nextEligibleDate = $lastDate->copy()->addDays(self::MIN_DONATION_INTERVAL_DAYS);
                    $nextEligibleLabel = $nextEligibleDate->translatedFormat('d M Y');
                    $cooldownNotice = sprintf(
                        'Catatan medis: riwayat donor terakhir Anda tercatat %d hari lalu. Jeda pemulihan standar adalah %d hari (estimasi layak kembali mulai %s).',
                        $daysSinceLast,
                        self::MIN_DONATION_INTERVAL_DAYS,
                        $nextEligibleLabel
                    );
                }
            }
        }

        return [
            'state' => 'ready',
            'is_ready' => true,
            'blood_group' => $user->blood_group_label,
            'cooldown_active' => $cooldownActive,
            'days_since_last_donation' => $daysSinceLast,
            'days_until_eligible' => $daysUntilEligible,
            'next_eligible_date_label' => $nextEligibleLabel,
            'cooldown_notice' => $cooldownNotice,
        ];
    }

    /**
     * Compute a transparent rule-based match score (0–100) for a donor against an active BloodRequest.
     *
     * Weight distribution (max 100 pts):
     * - Biological Transfusion Compatibility (ABO/Rhesus per Blood Component): 35 pts
     * - Clinical Urgency & Hemoglobin Severity: 35 pts
     * - Geospatial Distance & Regional Proximity: 20 pts
     * - Time-to-Need Deadline & Remaining Bag Deficit: 10 pts
     *
     * @return array{
     *     eligible: bool,
     *     score: int,
     *     tier_key: string,
     *     tier_label: string,
     *     pill_class: string,
     *     icon: string,
     *     breakdown: array{compatibility: int, clinical: int, proximity: int, scarcity: int},
     *     reasons: array<int, string>
     * }
     */
    public function scoreRequestForDonor(User $donor, BloodRequest $bloodRequest): array
    {
        $compatMeta = $bloodRequest->getAttribute('compatibility_meta');
        if (! is_array($compatMeta)) {
            $compatMeta = $this->compatibilityService->evaluateForRequest(
                $donor->blood_type,
                $donor->rhesus,
                $bloodRequest
            );
            $bloodRequest->setAttribute('compatibility_meta', $compatMeta);
        }

        if (! ($compatMeta['compatible'] ?? false)) {
            return [
                'eligible' => false,
                'score' => 0,
                'tier_key' => 'incompatible',
                'tier_label' => 'Tidak Kompatibel',
                'pill_class' => 'dc-pill-neutral',
                'icon' => 'bi-slash-circle',
                'breakdown' => [
                    'compatibility' => 0,
                    'clinical' => 0,
                    'proximity' => 0,
                    'scarcity' => 0,
                ],
                'reasons' => [],
            ];
        }

        $reasons = [];

        // 1. Biological Compatibility Score (max 35)
        $isExactMatch = (bool) ($compatMeta['exact_match'] ?? false);
        $compatibilityPoints = $isExactMatch ? 35 : 26;
        $donorGroup = $donor->blood_group_label ?? '';
        $recipientGroup = $bloodRequest->blood_group_label;
        $componentCode = $bloodRequest->component_type->value;

        if ($isExactMatch) {
            $reasons[] = "Golongan Identik {$donorGroup} ({$componentCode})";
        } else {
            $reasons[] = "Kompatibel {$componentCode} ({$donorGroup} → {$recipientGroup})";
        }

        // 2. Clinical Urgency & Anemia Severity Score (max 35)
        $urgencyPoints = match ($bloodRequest->urgency_level) {
            UrgencyLevel::CRITICAL => 23,
            UrgencyLevel::URGENT => 15,
            UrgencyLevel::STANDARD => 8,
        };

        $hb = $bloodRequest->hemoglobin_level;
        if ($hb !== null) {
            $hbPoints = match (true) {
                $hb < 7.0 => 12,
                $hb < 8.5 => 8,
                $hb < 10.0 => 5,
                default => 3,
            };
        } else {
            $hbPoints = 6;
        }
        $clinicalPoints = min(35, $urgencyPoints + $hbPoints);

        if ($bloodRequest->urgency_level === UrgencyLevel::CRITICAL && $hb !== null && $hb < 7.0) {
            $reasons[] = 'Darurat Kritis · Hb '.number_format($hb, 1).' g/dL';
        } elseif ($bloodRequest->urgency_level === UrgencyLevel::CRITICAL) {
            $reasons[] = 'Kasus Darurat Kritis';
        } elseif ($hb !== null && $hb < 8.0) {
            $reasons[] = 'Anemia Berat (Hb '.number_format($hb, 1).' g/dL)';
        } else {
            $reasons[] = 'Prioritas '.$bloodRequest->urgency_level->label();
        }

        // 3. Geospatial Distance & Same-Region Score (max 20)
        /** @var float|null $distanceKm */
        $distanceKm = $bloodRequest->getAttribute('distance_km');
        $distancePoints = match (true) {
            $distanceKm === null => 2,
            $distanceKm <= 3.0 => 17,
            $distanceKm <= 7.0 => 14,
            $distanceKm <= 15.0 => 11,
            $distanceKm <= 25.0 => 7,
            $distanceKm <= 45.0 => 4,
            default => 2,
        };

        $sameRegion = $donor->region_id !== null
            && $bloodRequest->facility?->region_id !== null
            && (int) $donor->region_id === (int) $bloodRequest->facility->region_id;

        $proximityPoints = min(20, $distancePoints + ($sameRegion ? 3 : 0));

        $distanceLabel = (string) ($bloodRequest->getAttribute('distance_label') ?? '');
        if ($distanceKm !== null && $distanceKm <= 7.0) {
            $reasons[] = "Faskes Dekat ({$distanceLabel})";
        } elseif ($sameRegion && $bloodRequest->facility?->region?->name) {
            $reasons[] = 'Satu Wilayah ('.$bloodRequest->facility->region->name.')';
        } elseif ($distanceKm !== null && $distanceLabel !== '') {
            $reasons[] = "Jarak {$distanceLabel}";
        }

        // 4. Time-to-Need Deadline & Bag Deficit Score (max 10)
        $deadlinePoints = 1;
        $urgentDeadlineReason = null;
        if ($bloodRequest->needed_at !== null) {
            $hoursUntilNeeded = Carbon::now()->diffInHours($bloodRequest->needed_at, false);
            if ($hoursUntilNeeded <= 6) {
                $deadlinePoints = 6;
                $urgentDeadlineReason = 'Tenggat Sangat Mendesak (≤ 6 Jam)';
            } elseif ($hoursUntilNeeded <= 18) {
                $deadlinePoints = 4;
                $urgentDeadlineReason = 'Dibutuhkan ≤ 18 Jam';
            } elseif ($hoursUntilNeeded <= 36) {
                $deadlinePoints = 3;
            }
        }

        $remainingBags = $bloodRequest->remaining_bags;
        $deficitPoints = match (true) {
            $remainingBags >= 3 => 4,
            $remainingBags === 2 => 3,
            default => 2,
        };
        $scarcityPoints = min(10, $deadlinePoints + $deficitPoints);

        if ($urgentDeadlineReason !== null) {
            $reasons[] = $urgentDeadlineReason;
        } elseif ($remainingBags >= 3) {
            $reasons[] = "Defisit {$remainingBags} Kantong";
        }

        $totalScore = min(100, $compatibilityPoints + $clinicalPoints + $proximityPoints + $scarcityPoints);

        if ($totalScore >= 85) {
            $tierKey = 'top_priority';
            $tierLabel = 'Prioritas Utama';
            $pillClass = 'dc-pill-critical';
            $icon = 'bi-lightning-charge-fill';
        } elseif ($totalScore >= 70) {
            $tierKey = 'strong_match';
            $tierLabel = 'Sangat Direkomendasikan';
            $pillClass = 'dc-pill-success';
            $icon = 'bi-stars';
        } else {
            $tierKey = 'compatible_match';
            $tierLabel = 'Rekomendasi Kompatibel';
            $pillClass = 'dc-pill-neutral';
            $icon = 'bi-check2-circle';
        }

        return [
            'eligible' => true,
            'score' => $totalScore,
            'tier_key' => $tierKey,
            'tier_label' => $tierLabel,
            'pill_class' => $pillClass,
            'icon' => $icon,
            'breakdown' => [
                'compatibility' => $compatibilityPoints,
                'clinical' => $clinicalPoints,
                'proximity' => $proximityPoints,
                'scarcity' => $scarcityPoints,
            ],
            'reasons' => array_slice($reasons, 0, 4),
        ];
    }

    /**
     * Filter to serologically compatible active requests, attach `best_match_meta`, and rank deterministically.
     *
     * @param  Collection<int, BloodRequest>  $enrichedRequests
     * @return Collection<int, BloodRequest>
     */
    public function rankForDonor(User $donor, Collection $enrichedRequests): Collection
    {
        $matched = $enrichedRequests
            ->map(function (BloodRequest $bloodRequest) use ($donor): BloodRequest {
                $meta = $this->scoreRequestForDonor($donor, $bloodRequest);
                $bloodRequest->setAttribute('best_match_meta', $meta);

                return $bloodRequest;
            })
            ->filter(fn (BloodRequest $bloodRequest): bool => (bool) ($bloodRequest->getAttribute('best_match_meta')['eligible'] ?? false));

        return $matched->sort(function (BloodRequest $a, BloodRequest $b): int {
            $scoreA = (int) ($a->getAttribute('best_match_meta')['score'] ?? 0);
            $scoreB = (int) ($b->getAttribute('best_match_meta')['score'] ?? 0);

            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA;
            }

            $urgencyA = $this->urgencyRank($a->urgency_level);
            $urgencyB = $this->urgencyRank($b->urgency_level);
            if ($urgencyA !== $urgencyB) {
                return $urgencyA <=> $urgencyB;
            }

            $hbA = $a->hemoglobin_level ?? 99.0;
            $hbB = $b->hemoglobin_level ?? 99.0;
            if ($hbA !== $hbB) {
                return $hbA <=> $hbB;
            }

            $distA = $a->getAttribute('distance_km') ?? 99999.0;
            $distB = $b->getAttribute('distance_km') ?? 99999.0;
            if ($distA !== $distB) {
                return $distA <=> $distB;
            }

            $deadlineA = $a->needed_at?->getTimestamp() ?? PHP_INT_MAX;
            $deadlineB = $b->needed_at?->getTimestamp() ?? PHP_INT_MAX;

            return $deadlineA <=> $deadlineB;
        })->values();
    }

    private function urgencyRank(UrgencyLevel $urgency): int
    {
        return match ($urgency) {
            UrgencyLevel::CRITICAL => 1,
            UrgencyLevel::URGENT => 2,
            UrgencyLevel::STANDARD => 3,
        };
    }
}

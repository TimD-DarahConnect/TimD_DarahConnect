<?php

namespace App\Http\Controllers\DonorDiscovery;

use App\Enums\BloodComponent;
use App\Enums\BloodRequestStatus;
use App\Enums\BloodType;
use App\Enums\Rhesus;
use App\Enums\UrgencyLevel;
use App\Http\Controllers\Controller;
use App\Models\BloodRequest;
use App\Models\Region;
use App\Services\DonorDiscovery\BestMatchRecommendationService;
use App\Services\DonorDiscovery\GeospatialDistanceService;
use App\Services\DonorDiscovery\TransfusionCompatibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DonorDiscoveryController extends Controller
{
    public function __construct(
        private readonly GeospatialDistanceService $geospatialService,
        private readonly TransfusionCompatibilityService $compatibilityService,
        private readonly BestMatchRecommendationService $bestMatchService
    ) {}

    /**
     * Display the active emergency blood request catalog and the personalized Best Match recommendation tab.
     */
    public function index(Request $request): View
    {
        $origin = $this->geospatialService->resolveOrigin($request);
        $user = $request->user();
        $bestMatchReadiness = $this->bestMatchService->evaluateDonorReadiness($user);

        $activeTab = (string) $request->query('tab', 'all');
        if (! in_array($activeTab, ['all', 'best_match'], true)) {
            $activeTab = 'all';
        }

        $sortBy = (string) $request->query('sort', 'urgency_nearest');
        if (! in_array($sortBy, ['urgency_nearest', 'nearest', 'urgency', 'deadline'], true)) {
            $sortBy = 'urgency_nearest';
        }

        $matchMode = (string) $request->query('match_mode', 'compatible');
        if (! in_array($matchMode, ['compatible', 'exact'], true)) {
            $matchMode = 'compatible';
        }

        $bloodTypeFilter = strtoupper(trim((string) $request->query('blood_type', '')));
        if ($bloodTypeFilter !== '' && ! in_array($bloodTypeFilter, BloodType::values(), true)) {
            $bloodTypeFilter = '';
        }

        $rhesusFilter = trim((string) $request->query('rhesus', ''));
        if ($rhesusFilter !== '' && ! in_array($rhesusFilter, Rhesus::values(), true)) {
            $rhesusFilter = '';
        }

        $componentFilter = strtoupper(trim((string) $request->query('component', '')));
        if ($componentFilter !== '' && ! in_array($componentFilter, BloodComponent::values(), true)) {
            $componentFilter = '';
        }

        $rawRegionId = $request->query('region_id');
        $selectedRegion = null;
        $regionIdFilter = null;
        if (is_numeric($rawRegionId) && (int) $rawRegionId > 0) {
            $selectedRegion = Region::find((int) $rawRegionId);
            $regionIdFilter = $selectedRegion?->id;
        }

        $searchQuery = trim((string) $request->query('q', ''));

        // Live global count of active requests catalog & personalized Best Match count for segmented tab badges
        $allActiveRequestsCount = BloodRequest::query()
            ->active()
            ->whereColumn('bags_fulfilled', '<', 'bags_needed')
            ->count();

        $bestMatchTotalCount = 0;
        if ($bestMatchReadiness['is_ready'] && $user !== null) {
            $bestMatchCountQuery = BloodRequest::query()
                ->active()
                ->whereColumn('bags_fulfilled', '<', 'bags_needed');

            $this->compatibilityService->applyDonorCompatibilityScope(
                $bestMatchCountQuery,
                $user->blood_type?->value,
                $user->rhesus?->value
            );

            $bestMatchTotalCount = $bestMatchCountQuery->count();
        }

        if ($activeTab === 'best_match') {
            if (! $bestMatchReadiness['is_ready'] || $user === null) {
                $sortedItems = collect();
                $compatibilityReachSummary = null;
            } else {
                $bestMatchQuery = BloodRequest::with(['facility.region'])
                    ->active()
                    ->whereColumn('bags_fulfilled', '<', 'bags_needed');

                $this->compatibilityService->applyDonorCompatibilityScope(
                    $bestMatchQuery,
                    $user->blood_type?->value,
                    $user->rhesus?->value,
                    $componentFilter ?: null
                );

                if ($componentFilter !== '') {
                    $bestMatchQuery->where('component_type', $componentFilter);
                }

                if ($regionIdFilter !== null) {
                    $bestMatchQuery->whereHas('facility', function (Builder $facilityQuery) use ($regionIdFilter): void {
                        $facilityQuery->where('region_id', $regionIdFilter);
                    });
                }

                if ($searchQuery !== '') {
                    $this->applyKeywordSearch($bestMatchQuery, $searchQuery);
                }

                $enrichedForDonor = $bestMatchQuery->get()->map(function (BloodRequest $bloodRequest) use ($origin, $user): BloodRequest {
                    $enriched = $this->enrichWithGeospatialMetrics($bloodRequest, $origin['latitude'], $origin['longitude']);
                    $enriched->setAttribute(
                        'compatibility_meta',
                        $this->compatibilityService->evaluateForRequest($user->blood_type, $user->rhesus, $bloodRequest)
                    );

                    return $enriched;
                });

                $sortedItems = $this->bestMatchService->rankForDonor($user, $enrichedForDonor);
                $compatibilityReachSummary = $this->compatibilityService->summarizeDonorReach(
                    $user->blood_type,
                    $user->rhesus,
                    $componentFilter ?: null
                );
            }
        } else {
            $baseQuery = BloodRequest::with(['facility.region'])
                ->active()
                ->whereColumn('bags_fulfilled', '<', 'bags_needed');

            if ($componentFilter !== '') {
                $baseQuery->where('component_type', $componentFilter);
            }

            if ($regionIdFilter !== null) {
                $baseQuery->whereHas('facility', function (Builder $facilityQuery) use ($regionIdFilter): void {
                    $facilityQuery->where('region_id', $regionIdFilter);
                });
            }

            if ($bloodTypeFilter !== '' || $rhesusFilter !== '') {
                if ($matchMode === 'compatible') {
                    $this->compatibilityService->applyDonorCompatibilityScope(
                        $baseQuery,
                        $bloodTypeFilter ?: null,
                        $rhesusFilter ?: null,
                        $componentFilter ?: null
                    );
                } else {
                    if ($bloodTypeFilter !== '') {
                        $baseQuery->where('blood_type', $bloodTypeFilter);
                    }
                    if ($rhesusFilter !== '') {
                        $baseQuery->where('rhesus', $rhesusFilter);
                    }
                }
            }

            if ($searchQuery !== '') {
                $this->applyKeywordSearch($baseQuery, $searchQuery);
            }

            $referenceDonorAbo = null;
            $referenceDonorRhesus = null;

            if ($matchMode === 'compatible' && $bloodTypeFilter !== '' && $rhesusFilter !== '') {
                $referenceDonorAbo = $bloodTypeFilter;
                $referenceDonorRhesus = $rhesusFilter;
            } elseif ($user !== null && $user->isDonor() && $user->blood_type !== null && $user->rhesus !== null) {
                $referenceDonorAbo = $user->blood_type->value;
                $referenceDonorRhesus = $user->rhesus->value;
            }

            $items = $baseQuery->get()->map(function (BloodRequest $bloodRequest) use (
                $origin,
                $referenceDonorAbo,
                $referenceDonorRhesus,
                $user,
                $bestMatchReadiness,
                $bloodTypeFilter,
                $rhesusFilter
            ): BloodRequest {
                $enriched = $this->enrichWithGeospatialMetrics($bloodRequest, $origin['latitude'], $origin['longitude']);

                if ($referenceDonorAbo !== null && $referenceDonorRhesus !== null) {
                    $enriched->setAttribute(
                        'compatibility_meta',
                        $this->compatibilityService->evaluateForRequest($referenceDonorAbo, $referenceDonorRhesus, $bloodRequest)
                    );
                }

                if ($bestMatchReadiness['is_ready'] && $user !== null && $bloodTypeFilter === '' && $rhesusFilter === '') {
                    $enriched->setAttribute('best_match_meta', $this->bestMatchService->scoreRequestForDonor($user, $enriched));
                }

                return $enriched;
            });

            $sortedItems = $this->sortCatalog($items, $sortBy)->values();

            $compatibilityReachSummary = ($matchMode === 'compatible' && ($bloodTypeFilter !== '' || $rhesusFilter !== ''))
                ? $this->compatibilityService->summarizeDonorReach(
                    $bloodTypeFilter ?: null,
                    $rhesusFilter ?: null,
                    $componentFilter ?: null
                )
                : null;
        }

        $summary = [
            'active_cases' => $sortedItems->count(),
            'critical_cases' => $sortedItems->filter(
                fn (BloodRequest $r): bool => $r->urgency_level === UrgencyLevel::CRITICAL
            )->count(),
            'remaining_bags' => (int) $sortedItems->sum(fn (BloodRequest $r): int => $r->remaining_bags),
            'nearest_distance_label' => $sortedItems->isNotEmpty()
                ? $this->geospatialService->formatDistance(
                    $sortedItems->min(fn (BloodRequest $r): ?float => $r->getAttribute('distance_km'))
                )
                : '-',
            'top_match_score' => $activeTab === 'best_match' && $sortedItems->isNotEmpty()
                ? (int) ($sortedItems->first()?->getAttribute('best_match_meta')['score'] ?? 0)
                : null,
        ];

        $mapMarkers = $sortedItems
            ->filter(fn (BloodRequest $r): bool => $r->facility?->latitude !== null && $r->facility?->longitude !== null)
            ->map(function (BloodRequest $r) use ($origin): array {
                $bestMatchMeta = $r->getAttribute('best_match_meta');

                return [
                    'id' => $r->id,
                    'code' => $r->request_code,
                    'blood_group' => $r->blood_group_label,
                    'component' => $r->component_type->value,
                    'remaining_bags' => $r->remaining_bags,
                    'urgency' => $r->urgency_level->value,
                    'urgency_label' => $r->urgency_level->label(),
                    'facility_name' => $r->facility->name,
                    'region_name' => $r->facility->region?->name ?? '',
                    'latitude' => (float) $r->facility->latitude,
                    'longitude' => (float) $r->facility->longitude,
                    'distance_label' => $r->getAttribute('distance_label'),
                    'travel_minutes' => $r->getAttribute('estimated_minutes'),
                    'match_score' => is_array($bestMatchMeta) && ($bestMatchMeta['eligible'] ?? false)
                        ? $bestMatchMeta['score']
                        : null,
                    'detail_url' => route('discovery.show', array_filter([
                        'bloodRequest' => $r->id,
                        'lat' => $origin['source'] === 'gps' ? $origin['latitude'] : null,
                        'lng' => $origin['source'] === 'gps' ? $origin['longitude'] : null,
                    ])),
                ];
            })
            ->values();

        $regionsByProvince = Region::query()
            ->orderBy('province_name')
            ->orderBy('name')
            ->get()
            ->groupBy('province_name');

        $hasActiveFilters = ($activeTab === 'all' && ($bloodTypeFilter !== '' || $rhesusFilter !== ''))
            || $componentFilter !== ''
            || $regionIdFilter !== null
            || $searchQuery !== '';

        return view('donor-discovery.index', [
            'activeTab' => $activeTab,
            'bestMatchReadiness' => $bestMatchReadiness,
            'allActiveRequestsCount' => $allActiveRequestsCount,
            'bestMatchTotalCount' => $bestMatchTotalCount,
            'requests' => $sortedItems,
            'origin' => $origin,
            'sortBy' => $sortBy,
            'matchMode' => $matchMode,
            'bloodTypeFilter' => $bloodTypeFilter,
            'rhesusFilter' => $rhesusFilter,
            'componentFilter' => $componentFilter,
            'regionIdFilter' => $regionIdFilter,
            'selectedRegion' => $selectedRegion,
            'searchQuery' => $searchQuery,
            'hasActiveFilters' => $hasActiveFilters,
            'compatibilityReachSummary' => $compatibilityReachSummary,
            'bloodTypes' => BloodType::cases(),
            'rhesusOptions' => Rhesus::cases(),
            'componentOptions' => BloodComponent::cases(),
            'regionsByProvince' => $regionsByProvince,
            'summary' => $summary,
            'mapMarkers' => $mapMarkers,
        ]);
    }

    /**
     * Display privacy-safe clinical requirement details and hospital map navigation for an active request.
     */
    public function show(Request $request, BloodRequest $bloodRequest): View
    {
        $isExpired = $bloodRequest->expires_at !== null && $bloodRequest->expires_at->isPast();

        if ($bloodRequest->status !== BloodRequestStatus::ACTIVE || $isExpired) {
            abort(404, 'Permohonan darah ini sudah tidak aktif atau masa penayangannya telah berakhir.');
        }

        $bloodRequest->load(['facility.region']);
        $origin = $this->geospatialService->resolveOrigin($request);
        $this->enrichWithGeospatialMetrics($bloodRequest, $origin['latitude'], $origin['longitude']);

        $user = $request->user();
        $compatibilityMeta = null;
        $bestMatchMeta = null;
        if ($user !== null && $user->isDonor()) {
            $compatibilityMeta = $this->compatibilityService->evaluateForRequest(
                $user->blood_type,
                $user->rhesus,
                $bloodRequest
            );
            $bloodRequest->setAttribute('compatibility_meta', $compatibilityMeta);

            if ($user->blood_type !== null && $user->rhesus !== null) {
                $bestMatchMeta = $this->bestMatchService->scoreRequestForDonor($user, $bloodRequest);
            }
        }

        $nearbyRequests = BloodRequest::with(['facility.region'])
            ->active()
            ->whereColumn('bags_fulfilled', '<', 'bags_needed')
            ->where('id', '!=', $bloodRequest->id)
            ->take(6)
            ->get()
            ->map(fn (BloodRequest $item): BloodRequest => $this->enrichWithGeospatialMetrics($item, $origin['latitude'], $origin['longitude']))
            ->sortBy(fn (BloodRequest $item): float => $item->getAttribute('distance_km') ?? 99999.0)
            ->take(3)
            ->values();

        return view('donor-discovery.show', [
            'bloodRequest' => $bloodRequest,
            'origin' => $origin,
            'compatibilityMeta' => $compatibilityMeta,
            'bestMatchMeta' => $bestMatchMeta,
            'patientInitials' => $this->maskPatientName($bloodRequest->patient_name),
            'nearbyRequests' => $nearbyRequests,
        ]);
    }

    private function applyKeywordSearch(Builder $query, string $searchQuery): void
    {
        $like = '%'.$searchQuery.'%';

        $query->where(function (Builder $q) use ($like): void {
            $q->where('request_code', 'like', $like)
                ->orWhere('clinical_diagnosis', 'like', $like)
                ->orWhere('component_type', 'like', $like)
                ->orWhereHas('facility', function (Builder $facilityQuery) use ($like): void {
                    $facilityQuery->where('name', 'like', $like)
                        ->orWhere('address', 'like', $like)
                        ->orWhereHas('region', function (Builder $regionQuery) use ($like): void {
                            $regionQuery->where('name', 'like', $like)
                                ->orWhere('province_name', 'like', $like);
                        });
                });
        });
    }

    private function enrichWithGeospatialMetrics(BloodRequest $bloodRequest, float $originLat, float $originLng): BloodRequest
    {
        $facility = $bloodRequest->facility;
        $distanceKm = null;

        if ($facility && $facility->latitude !== null && $facility->longitude !== null) {
            $distanceKm = $this->geospatialService->calculateDistanceKm(
                $originLat,
                $originLng,
                (float) $facility->latitude,
                (float) $facility->longitude
            );
        }

        $bloodRequest->setAttribute('distance_km', $distanceKm);
        $bloodRequest->setAttribute('distance_label', $this->geospatialService->formatDistance($distanceKm));
        $bloodRequest->setAttribute('estimated_minutes', $this->geospatialService->estimateTravelMinutes($distanceKm));
        $bloodRequest->setAttribute('masked_patient_name', $this->maskPatientName($bloodRequest->patient_name));

        return $bloodRequest;
    }

    /**
     * @param  Collection<int, BloodRequest>  $items
     * @return Collection<int, BloodRequest>
     */
    private function sortCatalog(Collection $items, string $sortBy): Collection
    {
        return $items->sort(function (BloodRequest $a, BloodRequest $b) use ($sortBy): int {
            $urgencyRankA = $this->urgencyRank($a->urgency_level);
            $urgencyRankB = $this->urgencyRank($b->urgency_level);
            $distA = $a->getAttribute('distance_km') ?? 99999.0;
            $distB = $b->getAttribute('distance_km') ?? 99999.0;
            $hbA = $a->hemoglobin_level ?? 99.0;
            $hbB = $b->hemoglobin_level ?? 99.0;
            $deadlineA = $a->needed_at?->getTimestamp() ?? PHP_INT_MAX;
            $deadlineB = $b->needed_at?->getTimestamp() ?? PHP_INT_MAX;

            return match ($sortBy) {
                'nearest' => ($distA <=> $distB)
                    ?: ($urgencyRankA <=> $urgencyRankB)
                    ?: ($deadlineA <=> $deadlineB),
                'urgency' => ($urgencyRankA <=> $urgencyRankB)
                    ?: ($hbA <=> $hbB)
                    ?: ($deadlineA <=> $deadlineB),
                'deadline' => ($deadlineA <=> $deadlineB)
                    ?: ($urgencyRankA <=> $urgencyRankB)
                    ?: ($distA <=> $distB),
                default => ($urgencyRankA <=> $urgencyRankB)
                    ?: ($distA <=> $distB)
                    ?: ($deadlineA <=> $deadlineB),
            };
        });
    }

    private function urgencyRank(UrgencyLevel $urgency): int
    {
        return match ($urgency) {
            UrgencyLevel::CRITICAL => 1,
            UrgencyLevel::URGENT => 2,
            UrgencyLevel::STANDARD => 3,
        };
    }

    /**
     * Mask patient full name on public/donor catalog to respect clinical privacy while preserving clarity.
     */
    private function maskPatientName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if ($parts === []) {
            return 'Pasien Rawat Inap';
        }

        $honorifics = ['tn.', 'ny.', 'nn.', 'an.', 'sdr.', 'sdri.', 'by.'];

        return collect($parts)->map(function (string $word, int $index) use ($honorifics): string {
            if ($index === 0 && in_array(mb_strtolower($word), $honorifics, true)) {
                return $word;
            }

            if ($index <= 1 && mb_strlen($word) > 2) {
                return mb_substr($word, 0, 2).str_repeat('*', min(4, mb_strlen($word) - 2));
            }

            return mb_substr($word, 0, 1).'.';
        })->implode(' ');
    }
}

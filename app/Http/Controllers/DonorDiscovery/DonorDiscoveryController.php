<?php

namespace App\Http\Controllers\DonorDiscovery;

use App\Enums\BloodRequestStatus;
use App\Enums\UrgencyLevel;
use App\Http\Controllers\Controller;
use App\Models\BloodRequest;
use App\Services\DonorDiscovery\GeospatialDistanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DonorDiscoveryController extends Controller
{
    public function __construct(
        private readonly GeospatialDistanceService $geospatialService
    ) {}

    /**
     * Display the active emergency blood request catalog with urgency & geospatial distance sorting.
     */
    public function index(Request $request): View
    {
        $origin = $this->geospatialService->resolveOrigin($request);

        $sortBy = (string) $request->query('sort', 'urgency_nearest');
        if (! in_array($sortBy, ['urgency_nearest', 'nearest', 'urgency', 'deadline'], true)) {
            $sortBy = 'urgency_nearest';
        }

        $searchQuery = trim((string) $request->query('q', ''));

        $baseQuery = BloodRequest::with(['facility.region'])
            ->active()
            ->whereColumn('bags_fulfilled', '<', 'bags_needed');

        if ($searchQuery !== '') {
            $like = '%'.$searchQuery.'%';
            $baseQuery->where(function (Builder $q) use ($like): void {
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

        $items = $baseQuery->get()->map(function (BloodRequest $bloodRequest) use ($origin): BloodRequest {
            return $this->enrichWithGeospatialMetrics($bloodRequest, $origin['latitude'], $origin['longitude']);
        });

        $sortedItems = $this->sortCatalog($items, $sortBy)->values();

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
        ];

        $mapMarkers = $sortedItems
            ->filter(fn (BloodRequest $r): bool => $r->facility?->latitude !== null && $r->facility?->longitude !== null)
            ->map(fn (BloodRequest $r): array => [
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
                'detail_url' => route('discovery.show', array_filter([
                    'bloodRequest' => $r->id,
                    'lat' => $origin['source'] === 'gps' ? $origin['latitude'] : null,
                    'lng' => $origin['source'] === 'gps' ? $origin['longitude'] : null,
                ])),
            ])
            ->values();

        return view('donor-discovery.index', [
            'requests' => $sortedItems,
            'origin' => $origin,
            'sortBy' => $sortBy,
            'searchQuery' => $searchQuery,
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
            'patientInitials' => $this->maskPatientName($bloodRequest->patient_name),
            'nearbyRequests' => $nearbyRequests,
        ]);
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

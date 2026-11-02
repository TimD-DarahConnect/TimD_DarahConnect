<?php

namespace App\Services\DonorDiscovery;

use App\Models\Region;
use Illuminate\Http\Request;

class GeospatialDistanceService
{
    private const EARTH_RADIUS_KM = 6371.0;

    private const DEFAULT_LATITUDE = -6.9089260;

    private const DEFAULT_LONGITUDE = 107.6213590;

    private const DEFAULT_LABEL = 'Pusat Layanan Kota Bandung';

    /**
     * Resolve the active reference point for geospatial sorting (GPS query -> user profile -> user region -> default).
     *
     * @return array{latitude: float, longitude: float, label: string, source: string}
     */
    public function resolveOrigin(Request $request): array
    {
        $latParam = $request->query('lat');
        $lngParam = $request->query('lng');

        if (is_numeric($latParam) && is_numeric($lngParam)) {
            $lat = (float) $latParam;
            $lng = (float) $lngParam;

            if ($this->isValidCoordinate($lat, $lng)) {
                return [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'label' => 'Titik GPS Perangkat Saat Ini',
                    'source' => 'gps',
                ];
            }
        }

        $user = $request->user();
        if ($user) {
            if ($user->latitude !== null && $user->longitude !== null && $this->isValidCoordinate((float) $user->latitude, (float) $user->longitude)) {
                $user->loadMissing('region');

                return [
                    'latitude' => (float) $user->latitude,
                    'longitude' => (float) $user->longitude,
                    'label' => $user->address ?: ($user->region?->name ?? 'Lokasi Profil Akun'),
                    'source' => 'profile',
                ];
            }

            if ($user->region_id) {
                $region = $user->relationLoaded('region') ? $user->region : Region::find($user->region_id);
                if ($region && $region->latitude !== null && $region->longitude !== null) {
                    return [
                        'latitude' => (float) $region->latitude,
                        'longitude' => (float) $region->longitude,
                        'label' => $region->full_label,
                        'source' => 'region',
                    ];
                }
            }
        }

        return [
            'latitude' => self::DEFAULT_LATITUDE,
            'longitude' => self::DEFAULT_LONGITUDE,
            'label' => self::DEFAULT_LABEL,
            'source' => 'default',
        ];
    }

    /**
     * Calculate great-circle distance in kilometers between two coordinates using the Haversine formula.
     */
    public function calculateDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));

        return round(self::EARTH_RADIUS_KM * $c, 2);
    }

    /**
     * Format distance in clean human-readable Indonesian metric units.
     */
    public function formatDistance(?float $distanceKm): string
    {
        if ($distanceKm === null) {
            return 'Jarak belum diketahui';
        }

        if ($distanceKm < 1.0) {
            $meters = max(50, (int) round($distanceKm * 1000, -1));

            return $meters.' m';
        }

        return number_format($distanceKm, 1).' km';
    }

    /**
     * Estimate urban travel time in minutes (average 26 km/h urban medical corridor + 3 min buffer).
     */
    public function estimateTravelMinutes(?float $distanceKm): ?int
    {
        if ($distanceKm === null) {
            return null;
        }

        return max(3, (int) ceil(($distanceKm / 26.0) * 60) + 3);
    }

    public function isValidCoordinate(float $latitude, float $longitude): bool
    {
        return $latitude >= -90.0
            && $latitude <= 90.0
            && $longitude >= -180.0
            && $longitude <= 180.0;
    }
}

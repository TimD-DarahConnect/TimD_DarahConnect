<?php

namespace App\Enums;

enum DonationScheduleType: string
{
    case UDD_BUILDING = 'udd_building';
    case MOBILE_UNIT = 'mobile_unit';

    public function label(): string
    {
        return match ($this) {
            self::UDD_BUILDING => 'Gedung UDD PMI',
            self::MOBILE_UNIT => 'Mobile Unit / Event Mitra',
        };
    }

    public function pillClass(): string
    {
        return match ($this) {
            self::UDD_BUILDING => 'dc-pill-critical',
            self::MOBILE_UNIT => 'dc-pill-neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::UDD_BUILDING => 'bi-building-fill-plus',
            self::MOBILE_UNIT => 'bi-truck-front-fill',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

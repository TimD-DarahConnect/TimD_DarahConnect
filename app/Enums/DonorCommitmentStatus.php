<?php

namespace App\Enums;

enum DonorCommitmentStatus: string
{
    case COMMITTED = 'committed';
    case ARRIVED = 'arrived';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::COMMITTED => 'Berkomitmen Hadir',
            self::ARRIVED => 'Tiba di Faskes',
            self::COMPLETED => 'Selesai Donor',
            self::CANCELLED => 'Komitmen Dibatalkan',
            self::EXPIRED => 'Melewati Jadwal',
        };
    }

    public function pillClass(): string
    {
        return match ($this) {
            self::COMMITTED => 'dc-pill-warning',
            self::ARRIVED, self::COMPLETED => 'dc-pill-success',
            self::CANCELLED => 'dc-pill-critical',
            self::EXPIRED => 'dc-pill-neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::COMMITTED => 'bi-person-walking',
            self::ARRIVED => 'bi-geo-alt-fill',
            self::COMPLETED => 'bi-heart-pulse-fill',
            self::CANCELLED => 'bi-slash-circle',
            self::EXPIRED => 'bi-clock-history',
        };
    }

    public function isActiveCommitment(): bool
    {
        return in_array($this, [self::COMMITTED, self::ARRIVED], true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

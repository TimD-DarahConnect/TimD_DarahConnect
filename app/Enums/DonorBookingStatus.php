<?php

namespace App\Enums;

enum DonorBookingStatus: string
{
    case PENDING_SCREENING = 'pending_screening';
    case CONFIRMED = 'confirmed';
    case DEFERRED_SCREENING = 'deferred_screening';
    case CANCELLED = 'cancelled';
    case CHECKED_IN = 'checked_in';
    case COMPLETED = 'completed';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_SCREENING => 'Menunggu Skrining Anamnesis',
            self::CONFIRMED => 'Reservasi Terkonfirmasi',
            self::DEFERRED_SCREENING => 'Tunda Sementara (Anamnesis)',
            self::CANCELLED => 'Reservasi Dibatalkan',
            self::CHECKED_IN => 'Hadir di Lokasi',
            self::COMPLETED => 'Selesai Donor',
            self::EXPIRED => 'Jadwal Terlewat',
        };
    }

    public function pillClass(): string
    {
        return match ($this) {
            self::CONFIRMED, self::CHECKED_IN, self::COMPLETED => 'dc-pill-success',
            self::PENDING_SCREENING, self::DEFERRED_SCREENING => 'dc-pill-warning',
            self::CANCELLED => 'dc-pill-critical',
            self::EXPIRED => 'dc-pill-neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING_SCREENING => 'bi-clipboard2-pulse',
            self::CONFIRMED => 'bi-calendar2-check-fill',
            self::DEFERRED_SCREENING => 'bi-info-circle-fill',
            self::CANCELLED => 'bi-slash-circle',
            self::CHECKED_IN => 'bi-qr-code-scan',
            self::COMPLETED => 'bi-heart-pulse-fill',
            self::EXPIRED => 'bi-clock-history',
        };
    }

    public function holdsSessionQuota(): bool
    {
        return in_array($this, [
            self::CONFIRMED,
            self::CHECKED_IN,
            self::COMPLETED,
        ], true);
    }

    public function isActiveReservation(): bool
    {
        return in_array($this, [
            self::PENDING_SCREENING,
            self::CONFIRMED,
        ], true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

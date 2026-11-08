<?php

namespace App\Enums;

enum VerificationDecision: string
{
    case APPROVED = 'approved';
    case NEED_REVISION = 'need_revision';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::APPROVED => 'Disetujui & Dipublikasikan',
            self::NEED_REVISION => 'Diminta Perbaikan Data',
            self::REJECTED => 'Ditolak Medis',
        };
    }

    public function pillClass(): string
    {
        return match ($this) {
            self::APPROVED => 'dc-pill-success',
            self::NEED_REVISION => 'dc-pill-warning',
            self::REJECTED => 'dc-pill-critical',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::APPROVED => 'bi-patch-check-fill',
            self::NEED_REVISION => 'bi-arrow-return-left',
            self::REJECTED => 'bi-x-octagon-fill',
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

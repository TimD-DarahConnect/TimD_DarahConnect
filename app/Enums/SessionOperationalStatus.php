<?php

namespace App\Enums;

enum SessionOperationalStatus: string
{
    case OPEN = 'open';
    case PAUSED = 'paused';
    case CLOSED_EMERGENCY = 'closed_emergency';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Dibuka Normal',
            self::PAUSED => 'Jeda Sementara',
            self::CLOSED_EMERGENCY => 'Ditutup Kendala Teknis',
        };
    }

    public function pillClass(): string
    {
        return match ($this) {
            self::OPEN => 'dc-pill-success',
            self::PAUSED => 'dc-pill-warning',
            self::CLOSED_EMERGENCY => 'dc-pill-critical',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::OPEN => 'bi-check-circle-fill',
            self::PAUSED => 'bi-pause-circle-fill',
            self::CLOSED_EMERGENCY => 'bi-exclamation-triangle-fill',
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

<?php

namespace App\Models;

use App\Enums\SessionOperationalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DonationSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'donation_schedule_id',
        'session_label',
        'start_time',
        'end_time',
        'quota_total',
        'quota_booked',
        'operational_status',
        'operational_note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quota_total' => 'integer',
            'quota_booked' => 'integer',
            'operational_status' => SessionOperationalStatus::class,
            'sort_order' => 'integer',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DonationSchedule::class, 'donation_schedule_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(DonorBooking::class, 'donation_session_id');
    }

    public function getTimeRangeLabelAttribute(): string
    {
        return substr((string) $this->start_time, 0, 5).' – '.substr((string) $this->end_time, 0, 5).' WIB';
    }

    public function getRemainingQuotaAttribute(): int
    {
        return max(0, (int) $this->quota_total - (int) $this->quota_booked);
    }

    public function getOccupancyPercentageAttribute(): int
    {
        if ($this->quota_total <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->quota_booked / $this->quota_total) * 100));
    }

    public function isBookable(): bool
    {
        return $this->operational_status === SessionOperationalStatus::OPEN
            && $this->remaining_quota > 0;
    }
}


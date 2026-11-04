<?php

namespace App\Models;

use App\Enums\DonationScheduleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DonationSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_code',
        'facility_id',
        'region_id',
        'created_by',
        'schedule_type',
        'title',
        'partner_institution',
        'service_date',
        'opening_time',
        'closing_time',
        'venue_name',
        'venue_address',
        'latitude',
        'longitude',
        'target_bags',
        'publication_status',
        'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $schedule): void {
            if (empty($schedule->schedule_code)) {
                $schedule->schedule_code = self::generateUniqueScheduleCode();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'schedule_type' => DonationScheduleType::class,
            'service_date' => 'date',
            'latitude' => 'float',
            'longitude' => 'float',
            'target_bags' => 'integer',
        ];
    }

    public static function generateUniqueScheduleCode(): string
    {
        do {
            $code = 'DC-SCH-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        } while (self::where('schedule_code', $code)->exists());

        return $code;
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(DonationSession::class)->orderBy('sort_order')->orderBy('start_time');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(DonorBooking::class, 'donation_schedule_id');
    }

    public function scopeUddBuilding(Builder $query): Builder
    {
        return $query->where('schedule_type', DonationScheduleType::UDD_BUILDING->value);
    }

    public function scopeMobileUnit(Builder $query): Builder
    {
        return $query->where('schedule_type', DonationScheduleType::MOBILE_UNIT->value);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('publication_status', 'published');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('service_date', '>=', today());
    }

    public function getOperatingHoursLabelAttribute(): string
    {
        return substr((string) $this->opening_time, 0, 5).' – '.substr((string) $this->closing_time, 0, 5).' WIB';
    }

    public function getTotalQuotaAttribute(): int
    {
        return (int) $this->sessions->sum('quota_total');
    }

    public function getTotalBookedAttribute(): int
    {
        return (int) $this->sessions->sum('quota_booked');
    }

    public function getRemainingQuotaAttribute(): int
    {
        return max(0, $this->total_quota - $this->total_booked);
    }

    public function getOccupancyPercentageAttribute(): int
    {
        if ($this->total_quota <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->total_booked / $this->total_quota) * 100));
    }
}


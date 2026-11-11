<?php

namespace App\Models;

use App\Enums\DonorBookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class DonorBooking extends Model
{
    use HasFactory;

    public const MAX_RESCHEDULE_COUNT = 2;

    protected $fillable = [
        'booking_code',
        'qr_token',
        'donor_id',
        'donation_schedule_id',
        'donation_session_id',
        'status',
        'donation_type',
        'donor_notes',
        'screening_passed',
        'screening_completed_at',
        'screening_summary_note',
        'confirmed_at',
        'rescheduled_from_session_id',
        'reschedule_count',
        'cancelled_at',
        'cancellation_reason',
        'checked_in_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $booking): void {
            if (empty($booking->booking_code)) {
                $booking->booking_code = self::generateUniqueBookingCode();
            }

            if (empty($booking->qr_token)) {
                $booking->qr_token = self::generateFreshQrToken();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => DonorBookingStatus::class,
            'screening_passed' => 'boolean',
            'screening_completed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'reschedule_count' => 'integer',
            'cancelled_at' => 'datetime',
            'checked_in_at' => 'datetime',
        ];
    }

    public static function generateUniqueBookingCode(): string
    {
        do {
            $code = 'DC-BKG-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        } while (self::where('booking_code', $code)->exists());

        return $code;
    }

    public static function generateFreshQrToken(): string
    {
        return hash('sha256', Str::uuid()->toString().'|'.microtime(true));
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'donor_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DonationSchedule::class, 'donation_schedule_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(DonationSession::class, 'donation_session_id');
    }

    public function previousSession(): BelongsTo
    {
        return $this->belongsTo(DonationSession::class, 'rescheduled_from_session_id');
    }

    public function screening(): HasOne
    {
        return $this->hasOne(BookingScreening::class, 'donor_booking_id');
    }

    public function requiresSelfScreening(): bool
    {
        return $this->status === DonorBookingStatus::PENDING_SCREENING;
    }

    public function scopeForDonor(Builder $query, int $donorId): Builder
    {
        return $query->where('donor_id', $donorId);
    }

    public function scopeActiveReservation(Builder $query): Builder
    {
        return $query->whereIn('status', [
            DonorBookingStatus::PENDING_SCREENING->value,
            DonorBookingStatus::CONFIRMED->value,
        ]);
    }

    public function isActiveReservation(): bool
    {
        return $this->status instanceof DonorBookingStatus
            && $this->status->isActiveReservation();
    }

    public function hasDigitalTicket(): bool
    {
        return in_array($this->status, [
            DonorBookingStatus::CONFIRMED,
            DonorBookingStatus::CHECKED_IN,
            DonorBookingStatus::COMPLETED,
        ], true) && (bool) $this->screening_passed;
    }

    public function canBeRescheduled(): bool
    {
        if (! $this->isActiveReservation()) {
            return false;
        }

        if ((int) $this->reschedule_count >= self::MAX_RESCHEDULE_COUNT) {
            return false;
        }

        if ($this->schedule?->service_date !== null && $this->schedule->service_date->lt(today())) {
            return false;
        }

        return true;
    }

    public function canBeCancelled(): bool
    {
        return $this->isActiveReservation();
    }

    public function getRemainingReschedulesAttribute(): int
    {
        return max(0, self::MAX_RESCHEDULE_COUNT - (int) $this->reschedule_count);
    }

    public function getShortVerificationHashAttribute(): string
    {
        return strtoupper(substr((string) $this->qr_token, 0, 8));
    }
}

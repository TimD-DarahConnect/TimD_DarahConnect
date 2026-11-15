<?php

namespace App\Models;

use App\Enums\BloodRequestStatus;
use App\Enums\DonorCommitmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DonorCommitment extends Model
{
    use HasFactory;

    protected $fillable = [
        'pledge_code',
        'blood_request_id',
        'donor_id',
        'status',
        'bags_pledged',
        'estimated_arrival_at',
        'health_readiness_confirmed',
        'pledge_notes',
        'arrived_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'meeting_point',
        'building_floor',
        'companion_alias',
        'meeting_instructions',
        'coordination_code',
        'meeting_point_updated_at',
        'meeting_point_confirmed_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $commitment): void {
            if (empty($commitment->pledge_code)) {
                $commitment->pledge_code = self::generateUniquePledgeCode();
            }

            if (empty($commitment->coordination_code)) {
                $commitment->coordination_code = Str::upper(Str::random(6));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => DonorCommitmentStatus::class,
            'bags_pledged' => 'integer',
            'estimated_arrival_at' => 'datetime',
            'health_readiness_confirmed' => 'boolean',
            'arrived_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meeting_point_updated_at' => 'datetime',
            'meeting_point_confirmed_at' => 'datetime',
        ];
    }

    public static function generateUniquePledgeCode(): string
    {
        do {
            $code = 'DC-PLG-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        } while (self::where('pledge_code', $code)->exists());

        return $code;
    }

    public function bloodRequest(): BelongsTo
    {
        return $this->belongsTo(BloodRequest::class);
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'donor_id');
    }

    public function coordinationMessages(): HasMany
    {
        return $this->hasMany(CoordinationMessage::class, 'donor_commitment_id')->orderBy('created_at');
    }

    public function scopeActiveCommitment(Builder $query): Builder
    {
        return $query->whereIn('status', [
            DonorCommitmentStatus::COMMITTED->value,
            DonorCommitmentStatus::ARRIVED->value,
        ]);
    }

    public function scopeForDonor(Builder $query, int $donorId): Builder
    {
        return $query->where('donor_id', $donorId);
    }

    public function isActiveCommitment(): bool
    {
        return $this->status instanceof DonorCommitmentStatus
            && $this->status->isActiveCommitment();
    }

    public function canConfirmArrival(): bool
    {
        return $this->status === DonorCommitmentStatus::COMMITTED;
    }

    public function canBeCancelledByDonor(): bool
    {
        return in_array($this->status, [
            DonorCommitmentStatus::COMMITTED,
            DonorCommitmentStatus::ARRIVED,
        ], true);
    }

    public function canCoordinateActively(): bool
    {
        if (! $this->isActiveCommitment()) {
            return false;
        }

        $requestStatus = $this->bloodRequest?->status;
        if ($requestStatus !== null && $requestStatus !== BloodRequestStatus::ACTIVE) {
            return false;
        }

        return true;
    }

    public function getMaskedDonorAliasAttribute(): string
    {
        $name = trim((string) ($this->donor?->name ?? ''));
        if ($name === '') {
            return 'Relawan Pendonor';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $masked = collect($parts)->map(function (string $word, int $idx): string {
            if ($idx === 0 && mb_strlen($word) > 2) {
                return mb_substr($word, 0, 2).str_repeat('*', min(4, mb_strlen($word) - 2));
            }

            return mb_substr($word, 0, 1).'.';
        })->implode(' ');

        return 'Relawan '.$masked;
    }
}

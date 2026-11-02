<?php

namespace App\Models;

use App\Enums\BloodComponent;
use App\Enums\BloodRequestStatus;
use App\Enums\BloodType;
use App\Enums\Rhesus;
use App\Enums\UrgencyLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BloodRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_code',
        'requester_id',
        'facility_id',
        'patient_name',
        'patient_age',
        'patient_gender',
        'patient_relationship',
        'medical_record_number',
        'ward_room',
        'clinical_diagnosis',
        'hemoglobin_level',
        'blood_type',
        'rhesus',
        'component_type',
        'bags_needed',
        'bags_fulfilled',
        'urgency_level',
        'needed_at',
        'additional_notes',
        'doctor_name',
        'referral_letter_path',
        'referral_letter_original_name',
        'referral_letter_mime',
        'referral_letter_size',
        'status',
        'submitted_at',
        'verified_by',
        'verified_at',
        'verification_notes',
        'expires_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $bloodRequest): void {
            if (empty($bloodRequest->request_code)) {
                $bloodRequest->request_code = self::generateUniqueCode();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'patient_age' => 'integer',
            'hemoglobin_level' => 'float',
            'blood_type' => BloodType::class,
            'rhesus' => Rhesus::class,
            'component_type' => BloodComponent::class,
            'bags_needed' => 'integer',
            'bags_fulfilled' => 'integer',
            'urgency_level' => UrgencyLevel::class,
            'needed_at' => 'datetime',
            'referral_letter_size' => 'integer',
            'status' => BloodRequestStatus::class,
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = 'DC-REQ-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        } while (self::where('request_code', $code)->exists());

        return $code;
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function commitments(): HasMany
    {
        return $this->hasMany(DonorCommitment::class);
    }

    public function scopeForRequester(Builder $query, int $userId): Builder
    {
        return $query->where('requester_id', $userId);
    }

    public function scopePendingVerification(Builder $query): Builder
    {
        return $query->where('status', BloodRequestStatus::PENDING_VERIFICATION->value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', BloodRequestStatus::ACTIVE->value)
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeOrderByUrgency(Builder $query): Builder
    {
        return $query->orderByRaw(
            "CASE urgency_level WHEN 'critical' THEN 1 WHEN 'urgent' THEN 2 ELSE 3 END ASC"
        );
    }

    public function getBloodGroupLabelAttribute(): string
    {
        $bt = $this->blood_type instanceof BloodType ? $this->blood_type->value : (string) $this->blood_type;
        $rh = $this->rhesus instanceof Rhesus ? $this->rhesus->value : (string) $this->rhesus;

        return $bt.$rh;
    }

    public function getRemainingBagsAttribute(): int
    {
        return max(0, (int) $this->bags_needed - (int) $this->bags_fulfilled);
    }

    public function getFulfillmentPercentageAttribute(): int
    {
        if ($this->bags_needed <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->bags_fulfilled / $this->bags_needed) * 100));
    }

    public function getHemoglobinSeverityLabelAttribute(): ?string
    {
        if ($this->hemoglobin_level === null) {
            return null;
        }

        return match (true) {
            $this->hemoglobin_level < 7.0 => 'Anemia Berat / Kritis',
            $this->hemoglobin_level < 10.0 => 'Anemia Sedang',
            default => 'Indikasi Perioperatif / Pemeliharaan',
        };
    }

    public function hasReferralLetter(): bool
    {
        return ! empty($this->referral_letter_path);
    }

    public function isEditableByRequester(): bool
    {
        return $this->status instanceof BloodRequestStatus
            && $this->status->isEditableByRequester();
    }

    public function isCancellableByRequester(): bool
    {
        return $this->status instanceof BloodRequestStatus
            && $this->status->isCancellableByRequester();
    }
}

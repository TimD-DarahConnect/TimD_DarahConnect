<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingScreening extends Model
{
    use HasFactory;

    protected $fillable = [
        'donor_booking_id',
        'donor_id',
        'total_questions',
        'passed_count',
        'flagged_count',
        'is_eligible',
        'responses',
        'flagged_reasons',
        'consent_confirmed_at',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'total_questions' => 'integer',
            'passed_count' => 'integer',
            'flagged_count' => 'integer',
            'is_eligible' => 'boolean',
            'responses' => 'array',
            'flagged_reasons' => 'array',
            'consent_confirmed_at' => 'datetime',
            'evaluated_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(DonorBooking::class, 'donor_booking_id');
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'donor_id');
    }
}

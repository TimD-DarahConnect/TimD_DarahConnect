<?php

namespace App\Models;

use App\Enums\VerificationDecision;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'blood_request_id',
        'reviewer_id',
        'decision',
        'previous_status',
        'resulting_status',
        'expires_at',
        'notes',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decision' => VerificationDecision::class,
            'expires_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function bloodRequest(): BelongsTo
    {
        return $this->belongsTo(BloodRequest::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoordinationMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'donor_commitment_id',
        'sender_id',
        'sender_role',
        'message_type',
        'preset_key',
        'body',
        'contained_masked_contact',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'contained_masked_contact' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function donorCommitment(): BelongsTo
    {
        return $this->belongsTo(DonorCommitment::class, 'donor_commitment_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function messageTypeLabel(): string
    {
        return match ($this->message_type) {
            'meeting_point_update' => 'Pembaruan Titik Temu',
            'arrival_check' => 'Konfirmasi Titik / Loket',
            'status_update' => 'Status Perjalanan & Faskes',
            default => 'Pesan Koordinasi',
        };
    }
}

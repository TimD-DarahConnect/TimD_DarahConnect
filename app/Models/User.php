<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\BloodType;
use App\Enums\Rhesus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'phone',
        'password',
        'region_id',
        'facility_id',
        'blood_type',
        'rhesus',
        'gender',
        'birth_date',
        'weight_kg',
        'last_donated_at',
        'address',
        'latitude',
        'longitude',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'blood_type' => BloodType::class,
            'rhesus' => Rhesus::class,
            'birth_date' => 'date',
            'weight_kg' => 'integer',
            'last_donated_at' => 'date',
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function bloodRequests(): HasMany
    {
        return $this->hasMany(BloodRequest::class, 'requester_id');
    }

    public function donorCommitments(): HasMany
    {
        return $this->hasMany(DonorCommitment::class, 'donor_id');
    }

    public function hasRole(UserRole|string ...$roles): bool
    {
        $current = $this->role instanceof UserRole ? $this->role->value : (string) $this->role;

        foreach ($roles as $role) {
            $target = $role instanceof UserRole ? $role->value : $role;
            if ($current === $target) {
                return true;
            }
        }

        return false;
    }

    public function isSeeker(): bool
    {
        return $this->hasRole(UserRole::PENCARI_DARAH);
    }

    public function isDonor(): bool
    {
        return $this->hasRole(UserRole::PENDONOR);
    }

    public function isUddStaff(): bool
    {
        return $this->hasRole(UserRole::PETUGAS_UDD);
    }

    public function isHospitalStaff(): bool
    {
        return $this->hasRole(UserRole::PETUGAS_RS);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::ADMIN);
    }

    public function getBloodGroupLabelAttribute(): ?string
    {
        if (! $this->blood_type || ! $this->rhesus) {
            return null;
        }

        return $this->blood_type->value.$this->rhesus->value;
    }
}

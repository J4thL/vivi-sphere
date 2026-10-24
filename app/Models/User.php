<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'stylist_id',
        'branch_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'disabled_at' => 'datetime',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function stylist()
    {
        return $this->belongsTo(Stylist::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /** Admins reach every branch; everyone else only the one they belong to. */
    public function canAccessBranch($branchId): bool
    {
        return $this->isAdmin() || ($this->branch_id !== null && (int) $this->branch_id === (int) $branchId);
    }

    public function client()
    {
        return $this->hasOne(Client::class);
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['admin', 'manager']);
    }

    public function isStylist(): bool
    {
        return $this->role === 'stylist';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }
}

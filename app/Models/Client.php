<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'phone', 'notes', 'visits', 'spend', 'last_visit_at'];

    // Staff-only: a client (or a stylist) must never receive these through the API unless a manager endpoint opts in.
    protected $hidden = ['notes'];

    protected function casts(): array
    {
        return [
            'spend' => 'decimal:2',
            'last_visit_at' => 'datetime',
        ];
    }

    /** Privacy-safe label for public display: "Elena Marsh" -> "Elena M." */
    public function getPublicNameAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) < 2) {
            return $parts[0] ?? 'Client';
        }

        return $parts[0].' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}

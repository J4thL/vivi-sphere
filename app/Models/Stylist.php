<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stylist extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'role', 'photo_url', 'bio', 'active', 'branch_id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }
}

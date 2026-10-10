<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'price', 'duration_minutes', 'photo_url'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = ['name', 'address', 'phone', 'map_query', 'open_time', 'close_time', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function stylists()
    {
        return $this->hasMany(Stylist::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}

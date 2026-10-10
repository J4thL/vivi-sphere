<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaitlistEntry extends Model
{
    protected $fillable = ['client_id', 'client_name', 'service_id', 'stylist_id', 'preferred_date', 'status'];

    protected function casts(): array
    {
        return ['preferred_date' => 'date'];
    }

    public function client() { return $this->belongsTo(Client::class); }
    public function service() { return $this->belongsTo(Service::class); }
    public function stylist() { return $this->belongsTo(Stylist::class); }
}

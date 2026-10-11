<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'stylist_id', 'service_id', 'scheduled_at', 'status', 'price',
    ];

    protected function casts(): array
    {
        return [
            // Plain "Y-m-d H:i:s" (salon local time). The default ISO format ends in "Z", which wrongly
            // labels local time as UTC and is not what the dashboard parses.
            'scheduled_at' => 'datetime:Y-m-d H:i:s',
            'price' => 'decimal:2',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function stylist()
    {
        return $this->belongsTo(Stylist::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'stylist_id', 'service_id', 'duration_minutes', 'scheduled_at', 'status', 'price',
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

    /**
     * How long this booking lasts: the length saved when it was made, so later edits to a service's
     * duration don't stretch or shrink existing appointments. Older rows without one use the service's.
     */
    public function minutes(): int
    {
        return (int) ($this->duration_minutes ?? $this->service->duration_minutes ?? 45);
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

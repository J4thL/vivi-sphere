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

    /**
     * What this service costs, how long it takes and whether it is offered at a branch: the branch's
     * own setting where an admin has made one, otherwise the service's normal values.
     */
    public function termsFor(?int $branchId): object
    {
        $override = $branchId ? BranchService::where('branch_id', $branchId)->where('service_id', $this->id)->first() : null;

        return (object) [
            'price' => $override?->price ?? $this->price,
            'duration_minutes' => (int) ($override?->duration_minutes ?? $this->duration_minutes),
            'available' => $override ? (bool) $override->available : true,
        ];
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}

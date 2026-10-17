<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A branch's own price / length / availability for one service. Missing or NULL = the service's normal value. */
class BranchService extends Model
{
    protected $table = 'branch_service';

    protected $fillable = ['branch_id', 'service_id', 'price', 'duration_minutes', 'available'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'available' => 'boolean'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'category', 'qty', 'reorder_level', 'unit_cost', 'branch_id'];

    protected function casts(): array
    {
        return ['unit_cost' => 'decimal:2'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function getLowStockAttribute(): bool
    {
        return $this->qty <= $this->reorder_level;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Convenience: AuditLog::record($request->user(), 'service.price_updated', $service, ['old' => 65, 'new' => 70]); */
    public static function record($user, string $action, $subject = null, array $meta = []): self
    {
        return static::create([
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'meta' => $meta,
        ]);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['changes' => 'array', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Add a line to the audit log. Never stops the work it is recording. */
    public static function record(string $action, ?Model $subject = null, ?string $description = null, ?array $changes = null): void
    {
        try {
            static::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'description' => mb_substr($description ?? (method_exists($subject, 'auditLabel') ? $subject->auditLabel() : ''), 0, 500),
                'changes' => $changes,
                'ip' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

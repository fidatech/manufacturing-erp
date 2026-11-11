<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/** Writes who created, changed or deleted a record to the audit log. */
trait Audited
{
    public static function bootAudited(): void
    {
        static::created(fn ($model) => AuditLog::record('created', $model));
        static::updated(function ($model) {
            $changes = [];
            foreach ($model->getChanges() as $field => $new) {
                if (in_array($field, ['updated_at', 'password', 'remember_token', 'avg_cost'], true)) {
                    continue;
                }
                $changes[$field] = ['from' => $model->getOriginal($field), 'to' => $new];
            }
            if ($changes) {
                AuditLog::record('updated', $model, null, $changes);
            }
        });
        static::deleted(fn ($model) => AuditLog::record('deleted', $model));
    }

    /** Short name shown in the audit log. */
    public function auditLabel(): string
    {
        return (string) ($this->number ?? $this->name ?? $this->title ?? $this->code ?? $this->getKey());
    }
}

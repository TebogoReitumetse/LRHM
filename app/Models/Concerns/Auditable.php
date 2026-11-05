<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Automatically records create / update / delete events in the audit trail.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLog::record('created', $model, $model->auditDescription('created'), null, $model->auditFilter($model->getAttributes()));
        });

        static::updated(function ($model) {
            $changes = $model->auditFilter($model->getChanges());
            unset($changes['updated_at']);
            if (! $changes) {
                return;
            }
            $old = array_intersect_key($model->auditFilter($model->getOriginal()), $changes);
            AuditLog::record('updated', $model, $model->auditDescription('updated'), $old, $changes);
        });

        static::deleted(function ($model) {
            AuditLog::record('deleted', $model, $model->auditDescription('deleted'), $model->auditFilter($model->getOriginal()));
        });
    }

    protected function auditFilter(array $values): array
    {
        $hidden = array_merge(['password', 'remember_token', 'created_at'], $this->auditExclude ?? []);

        return collect($values)->except($hidden)
            ->map(fn ($v) => $v instanceof \BackedEnum ? $v->value : ($v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v))
            ->all();
    }

    protected function auditDescription(string $event): string
    {
        return class_basename($this).' #'.$this->getKey().' '.$event;
    }
}

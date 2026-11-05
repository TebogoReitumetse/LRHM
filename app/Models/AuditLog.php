<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault(['name' => 'System']);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Write an audit entry. */
    public static function record(string $event, ?Model $subject = null, ?string $description = null, ?array $old = null, ?array $new = null): self
    {
        $request = app()->runningInConsole() ? null : Request::instance();

        return static::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'description' => $description ? mb_substr($description, 0, 255) : null,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : 'console',
        ]);
    }

    public function subjectLabel(): string
    {
        return match ($this->auditable_type) {
            LeaveApplication::class => 'Leave application',
            Contract::class => 'Contract',
            AbsenceRecord::class => 'Absence record',
            User::class => 'User',
            ContractDocument::class => 'Contract document',
            LeaveDocument::class => 'Leave document',
            null => '—',
            default => class_basename($this->auditable_type),
        };
    }
}

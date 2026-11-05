<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApplicationAction extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['from_status' => LeaveStatus::class, 'to_status' => LeaveStatus::class, 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault(['name' => 'System']);
    }

    public function label(): string
    {
        return match ($this->action) {
            'created' => 'Draft created',
            'submitted' => 'Application submitted',
            'resubmitted' => 'Resubmitted with additional information',
            'review_started' => 'Manager review started',
            'returned' => 'Returned for additional information',
            'forwarded' => 'Reviewed and forwarded to the Director',
            'approved' => 'Approved by the Director',
            'declined' => 'Declined by the Director',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }
}

<?php

namespace App\Models;

use App\Enums\AbsenceClassification;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceRecord extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public const TYPES = ['absence' => 'Absence', 'late_arrival' => 'Late arrival'];

    public const FOLLOW_UP = ['open' => 'Open', 'in_progress' => 'In progress', 'closed' => 'Closed'];

    /** Outcomes management may record after considering the circumstances. */
    public const OUTCOMES = [
        'authorised' => 'Authorised / explained',
        'unauthorised' => 'Unauthorised (management decision)',
        'no_further_action' => 'No further action',
        'refer_to_policy' => 'Refer to company policy process',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'classification' => AbsenceClassification::class, 'reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withDefault(['name' => '—']);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

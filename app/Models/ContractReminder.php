<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractReminder extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['scheduled_for' => 'date', 'generated_at' => 'datetime'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function label(): string
    {
        if (str_starts_with($this->reminder_key, 'reminder_')) {
            return substr($this->reminder_key, 9).'-day reminder';
        }
        if ($this->reminder_key === 'expiry') {
            return 'Expiry notification';
        }
        if (str_starts_with($this->reminder_key, 'follow_up')) {
            return 'Follow-up (unresolved after expiry)';
        }

        return $this->reminder_key;
    }
}

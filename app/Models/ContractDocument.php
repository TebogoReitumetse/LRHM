<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractDocument extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public const TYPES = [
        'signed_contract' => 'Signed contract',
        'renewal' => 'Renewal agreement',
        'extension' => 'Extension',
        'addendum' => 'Addendum',
        'other' => 'Other supporting document',
    ];

    protected function casts(): array
    {
        return ['is_current' => 'boolean'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withDefault(['name' => '—']);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->document_type] ?? $this->document_type;
    }
}

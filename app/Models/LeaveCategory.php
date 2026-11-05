<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LeaveCategory extends Model
{
    protected $fillable = ['name', 'description', 'requires_document', 'requires_reason', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['requires_document' => 'boolean', 'requires_reason' => 'boolean', 'is_active' => 'boolean'];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}

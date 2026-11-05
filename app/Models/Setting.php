<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'contract_reminder_days' => '90,60,30',
        'contract_due_to_expire_days' => '90',
        'contract_followup_interval_days' => '7',
        'contract_notify_channels' => 'mail,database',
    ];

    public static function get(string $key, $default = null): ?string
    {
        $all = Cache::rememberForever('settings.all', fn () => static::pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }

    /** Reminder intervals in days before expiry, largest first. */
    public static function reminderDays(): array
    {
        return collect(explode(',', (string) static::get('contract_reminder_days')))
            ->map(fn ($d) => (int) trim($d))->filter(fn ($d) => $d > 0)->unique()->sortDesc()->values()->all();
    }
}

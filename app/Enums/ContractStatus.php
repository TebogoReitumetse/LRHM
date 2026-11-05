<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Draft = 'draft';
    case AwaitingSignature = 'awaiting_signature';
    case Active = 'active';
    case DueToExpire = 'due_to_expire';
    case Expired = 'expired';
    case Renewed = 'renewed';
    case Ended = 'ended';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::AwaitingSignature => 'Awaiting Signature',
            self::Active => 'Active',
            self::DueToExpire => 'Due to Expire',
            self::Expired => 'Expired',
            self::Renewed => 'Renewed',
            self::Ended => 'Ended',
            self::Superseded => 'Superseded',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-700 ring-gray-300',
            self::AwaitingSignature => 'bg-sky-50 text-sky-700 ring-sky-200',
            self::Active => 'bg-accent-50 text-accent-800 ring-accent-200',
            self::DueToExpire => 'bg-brand-50 text-brand-800 ring-brand-200',
            self::Expired => 'bg-red-50 text-red-700 ring-red-200',
            self::Renewed => 'bg-teal-50 text-teal-700 ring-teal-200',
            self::Ended => 'bg-slate-100 text-slate-700 ring-slate-300',
            self::Superseded => 'bg-purple-50 text-purple-700 ring-purple-200',
        };
    }

    /** Statuses the system monitors for expiry. */
    public static function monitored(): array
    {
        return [self::Active, self::DueToExpire, self::Expired];
    }

    /** Statuses a manager may set manually from the form. */
    public static function manualOptions(): array
    {
        return collect([self::Draft, self::AwaitingSignature, self::Active])
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}

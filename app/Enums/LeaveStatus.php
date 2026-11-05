<?php

namespace App\Enums;

enum LeaveStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderManagerReview = 'under_manager_review';
    case ReturnedForInformation = 'returned_for_information';
    case PendingDirectorApproval = 'pending_director_approval';
    case Approved = 'approved';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::UnderManagerReview => 'Under Manager Review',
            self::ReturnedForInformation => 'Returned for Information',
            self::PendingDirectorApproval => 'Pending Director Approval',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
        };
    }

    /** Tailwind classes for the status badge. */
    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-700 ring-gray-300',
            self::Submitted => 'bg-sky-50 text-sky-700 ring-sky-200',
            self::UnderManagerReview => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
            self::ReturnedForInformation => 'bg-amber-50 text-amber-800 ring-amber-300',
            self::PendingDirectorApproval => 'bg-brand-50 text-brand-800 ring-brand-200',
            self::Approved => 'bg-accent-50 text-accent-800 ring-accent-200',
            self::Declined => 'bg-red-50 text-red-700 ring-red-200',
        };
    }

    /** The employee may still edit the application. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::ReturnedForInformation], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Declined], true);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}

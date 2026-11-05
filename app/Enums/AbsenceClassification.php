<?php

namespace App\Enums;

enum AbsenceClassification: string
{
    case OnApprovedLeave = 'on_approved_leave';
    case ReportedSick = 'reported_sick';
    case PendingExplanation = 'pending_explanation';
    case OtherReason = 'other_reason';
    case ReviewedClassified = 'reviewed_classified';

    public function label(): string
    {
        return match ($this) {
            self::OnApprovedLeave => 'On approved leave',
            self::ReportedSick => 'Reported sick',
            self::PendingExplanation => 'Absent – pending explanation',
            self::OtherReason => 'Other recorded reason',
            self::ReviewedClassified => 'Reviewed & classified by management',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::OnApprovedLeave => 'bg-accent-50 text-accent-800 ring-accent-200',
            self::ReportedSick => 'bg-sky-50 text-sky-700 ring-sky-200',
            self::PendingExplanation => 'bg-amber-50 text-amber-800 ring-amber-300',
            self::OtherReason => 'bg-gray-100 text-gray-700 ring-gray-300',
            self::ReviewedClassified => 'bg-purple-50 text-purple-700 ring-purple-200',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}

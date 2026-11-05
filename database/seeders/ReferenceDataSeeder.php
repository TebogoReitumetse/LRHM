<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Department;
use App\Models\LeaveCategory;
use App\Models\PublicHoliday;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Management', 'Software Development', 'Network & Infrastructure', 'Technical Support', 'Finance & Administration', 'Sales & Marketing'] as $name) {
            Department::firstOrCreate(['name' => $name]);
        }

        // Provisional categories based on the BCEA. Must be confirmed against
        // LivityICT's approved leave policy and existing paper form.
        $categories = [
            ['Annual Leave', 'Paid annual leave.', false, false],
            ['Sick Leave', 'Illness or injury. A medical certificate is required in line with company policy.', true, true],
            ['Family Responsibility Leave', 'Birth or illness of a child, or death of a close family member. Proof may be requested.', false, true],
            ['Maternity Leave', 'Maternity leave. Supporting documentation required.', true, false],
            ['Parental Leave', 'Parental, adoption or commissioning parental leave.', true, false],
            ['Study Leave', 'Examinations or approved study commitments.', true, true],
            ['Unpaid Leave', 'Leave without pay, subject to Director approval.', false, true],
            ['Other / Special Leave', 'Any other leave approved under company policy.', false, true],
        ];
        foreach ($categories as $i => [$name, $desc, $doc, $reason]) {
            LeaveCategory::firstOrCreate(['name' => $name], [
                'description' => $desc, 'requires_document' => $doc, 'requires_reason' => $reason, 'sort_order' => $i + 1,
            ]);
        }

        // South African public holidays (Sunday holidays move to the Monday).
        $holidays = [
            '2026-01-01' => "New Year's Day", '2026-03-21' => 'Human Rights Day', '2026-04-03' => 'Good Friday',
            '2026-04-06' => 'Family Day', '2026-04-27' => 'Freedom Day', '2026-05-01' => "Workers' Day",
            '2026-06-16' => 'Youth Day', '2026-08-10' => "National Women's Day (observed)", '2026-09-24' => 'Heritage Day',
            '2026-12-16' => 'Day of Reconciliation', '2026-12-25' => 'Christmas Day', '2026-12-26' => 'Day of Goodwill',
            '2027-01-01' => "New Year's Day", '2027-03-22' => 'Human Rights Day (observed)', '2027-03-26' => 'Good Friday',
            '2027-03-29' => 'Family Day', '2027-04-27' => 'Freedom Day', '2027-05-01' => "Workers' Day",
            '2027-06-16' => 'Youth Day', '2027-08-09' => "National Women's Day", '2027-09-24' => 'Heritage Day',
            '2027-12-16' => 'Day of Reconciliation', '2027-12-25' => 'Christmas Day', '2027-12-27' => 'Day of Goodwill (observed)',
        ];
        foreach ($holidays as $date => $name) {
            PublicHoliday::firstOrCreate(['date' => $date], ['name' => $name]);
        }

        foreach (Setting::DEFAULTS as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        // Initial system administrator. The password must be changed at first login.
        User::firstOrCreate(['username' => 'admin'], [
            'name' => 'System Administrator',
            'employee_number' => 'LV-ADMIN',
            'email' => 'admin@livityict.co.za',
            'role' => Role::Admin,
            'department_id' => Department::where('name', 'Management')->value('id'),
            'job_title' => 'System Administrator',
            'can_view_contract' => false,
            'password' => 'Admin@12345',
            'password_changed_at' => app()->isProduction() ? null : now(),
        ]);
    }
}

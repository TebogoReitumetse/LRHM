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

        // Leave types as listed on the LivityICT paper Leave Application Form.
        // [name, description, document required, must specify type, entitlement days, cycle months, document only after N days]
        $categories = [
            ['Annual Leave', 'Paid annual leave. LivityICT policy: 21 working days per 12-month cycle (BCEA minimum: 15 working days).', false, false, 21, 12, null],
            ['Sick Leave', 'Illness or injury. Attach a medical certificate if more than 2 days. BCEA: 30 days per 36-month cycle.', true, false, 30, 36, 2],
            ['Family Responsibility Leave', 'Birth or illness of a child, or death of a close family member. Proof may be requested. BCEA: 3 days per year.', false, false, 3, 12, null],
            ['Maternity / Paternity Leave', 'Maternity or paternity leave. Supporting documentation may be requested.', false, false, null, null, null],
            ['Unpaid Leave', 'Leave without pay, subject to approval.', false, false, null, null, null],
            ['Other', 'Any other leave. Specify the type of leave.', false, true, null, null, null],
        ];
        foreach ($categories as $i => [$name, $desc, $doc, $specify, $days, $months, $after]) {
            LeaveCategory::firstOrCreate(['name' => $name], [
                'description' => $desc, 'requires_document' => $doc, 'requires_reason' => $specify, 'sort_order' => $i + 1,
                'entitlement_days' => $days, 'entitlement_cycle_months' => $months ?? 12, 'document_after_days' => $after,
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

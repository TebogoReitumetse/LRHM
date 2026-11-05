<?php

namespace Database\Seeders;

use App\Enums\AbsenceClassification;
use App\Enums\ContractStatus;
use App\Enums\LeaveStatus;
use App\Enums\Role;
use App\Models\AbsenceRecord;
use App\Models\Contract;
use App\Models\Department;
use App\Models\LeaveApplication;
use App\Models\LeaveCategory;
use App\Models\User;
use App\Services\ContractService;
use App\Services\LeaveWorkflow;
use App\Services\WorkingDayCalculator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/** Sample data for demonstration and User Acceptance Testing. */
class DemoDataSeeder extends Seeder
{
    public const PASSWORD = 'Password@123';

    public function run(): void
    {
        $dept = Department::pluck('id', 'name');

        $people = [
            ['Thandi Mokoena', 'director', 'LV-001', Role::Director, 'Management', 'Managing Director', 'employee'],
            ['Sipho Ndlovu', 'manager', 'LV-002', Role::Manager, 'Management', 'Operations & HR Manager', 'employee'],
            ['Lerato Dlamini', 'lerato', 'LV-010', Role::Employee, 'Software Development', 'Software Developer', 'employee'],
            ['Kabelo Molefe', 'kabelo', 'LV-011', Role::Employee, 'Network & Infrastructure', 'Network Engineer', 'employee'],
            ['Ayesha Patel', 'ayesha', 'LV-012', Role::Employee, 'Technical Support', 'Support Technician', 'employee'],
            ['Johan van Wyk', 'johan', 'LV-013', Role::Employee, 'Finance & Administration', 'Finance Officer', 'employee'],
            ['Nomsa Khumalo', 'nomsa', 'LV-014', Role::Employee, 'Sales & Marketing', 'Sales Consultant', 'employee'],
            ['Tumelo Sithole', 'tumelo', 'LC-101', Role::Employee, 'Software Development', 'Contract UI Designer', 'contractor'],
            ['Zanele Mahlangu', 'zanele', 'LC-102', Role::Employee, 'Technical Support', 'IT Intern', 'contractor'],
        ];

        $u = [];
        foreach ($people as [$name, $username, $num, $role, $d, $title, $type]) {
            $u[$username] = User::firstOrCreate(['username' => $username], [
                'name' => $name, 'employee_number' => $num, 'role' => $role, 'department_id' => $dept[$d] ?? null,
                'job_title' => $title, 'employment_type' => $type, 'email' => $username.'@livityict.co.za',
                'password' => self::PASSWORD, 'password_changed_at' => now(),
            ]);
        }

        if (LeaveApplication::exists()) {
            return; // Sample records already seeded.
        }

        $this->seedLeave($u);
        $this->seedContracts($u);
        $this->seedAbsences($u);
        Auth::logout();
    }

    private function seedLeave(array $u): void
    {
        $cal = app(WorkingDayCalculator::class);
        $wf = app(LeaveWorkflow::class);
        $cat = LeaveCategory::pluck('id', 'name');
        $today = today();

        $make = function (User $who, string $category, Carbon $start, Carbon $end, ?string $reason = null) use ($cal, $cat) {
            Auth::setUser($who);
            $app = LeaveApplication::create([
                'user_id' => $who->id, 'leave_category_id' => $cat[$category],
                'employee_name' => $who->name, 'employee_number' => $who->employee_number,
                'department' => $who->department?->name, 'job_title' => $who->job_title,
                'start_date' => $start, 'end_date' => $end, 'return_date' => $cal->nextWorkingDay($end),
                'working_days' => $cal->count($start, $end), 'reason' => $reason,
                'declaration_accepted' => true, 'declaration_accepted_at' => now(), 'status' => LeaveStatus::Draft,
            ]);
            $app->logAction('created', null, LeaveStatus::Draft);

            return $app;
        };
        $submit = function (LeaveApplication $a) use ($wf) {
            Auth::setUser($a->user);
            $wf->submit($a);

            return $a->fresh();
        };
        $as = fn (User $x) => Auth::setUser($x);
        $next = fn (int $days) => $today->copy()->addDays($days);
        $wd = fn (Carbon $d) => $d->isWeekend() ? $d->next(Carbon::MONDAY) : $d;

        // Approved, currently on leave.
        $a = $submit($make($u['lerato'], 'Annual Leave', $wd($today->copy()->subDays(2)), $wd($next(4)), 'Family holiday.'));
        $as($u['manager']); $wf->forward($a, $u['manager'], 'approve', 'Cover arranged with the dev team.');
        $as($u['director']); $wf->decide($a->fresh(), $u['director'], true, 'Approved. Enjoy the break.');

        // Approved upcoming.
        $a = $submit($make($u['kabelo'], 'Annual Leave', $wd($next(14)), $wd($next(18))));
        $as($u['manager']); $wf->startReview($a, $u['manager']); $wf->forward($a->fresh(), $u['manager'], 'approve', null);
        $as($u['director']); $wf->decide($a->fresh(), $u['director'], true, null);

        // Pending Director approval.
        $a = $submit($make($u['ayesha'], 'Study Leave', $wd($next(21)), $wd($next(22)), 'Final examinations, CompTIA A+.'));
        $as($u['manager']); $wf->forward($a, $u['manager'], 'approve', 'Exam timetable attached; support roster covered.');

        // Submitted, awaiting Manager.
        $submit($make($u['johan'], 'Family Responsibility Leave', $wd($next(3)), $wd($next(3)), 'Attending to a sick child.'));

        // Under Manager review.
        $a = $submit($make($u['nomsa'], 'Annual Leave', $wd($next(30)), $wd($next(39)), 'Travel to Durban.'));
        $as($u['manager']); $wf->startReview($a, $u['manager']);

        // Returned for information.
        $a = $submit($make($u['kabelo'], 'Sick Leave', $wd($today->copy()->subDays(9)), $wd($today->copy()->subDays(8)), 'Flu.'));
        $as($u['manager']); $wf->returnForInformation($a, $u['manager'], 'Please upload the medical certificate for these two days.');

        // Declined.
        $a = $submit($make($u['lerato'], 'Unpaid Leave', $wd($next(45)), $wd($next(55)), 'Extended personal travel.'));
        $as($u['manager']); $wf->forward($a, $u['manager'], 'decline', 'Overlaps with the client go-live; not recommended.');
        $as($u['director']); $wf->decide($a->fresh(), $u['director'], false, 'Declined due to the project go-live. Please consider dates after the release.');

        // Historic approved leave for reports.
        foreach ([[$u['ayesha'], 'Annual Leave', 70], [$u['johan'], 'Sick Leave', 50], [$u['nomsa'], 'Annual Leave', 100], [$u['manager'], 'Annual Leave', 120]] as [$who, $c, $ago]) {
            $s = $wd($today->copy()->subDays($ago));
            $a = $submit($make($who, $c, $s, $wd($s->copy()->addDays(2)), $c === 'Sick Leave' ? 'Stomach bug.' : null));
            if ($a->status === LeaveStatus::Submitted) {
                $as($u['manager']); $wf->forward($a, $u['manager'], 'approve', null);
            }
            $as($u['director']); $wf->decide($a->fresh(), $u['director'], true, null);
        }

        // A private draft.
        $make($u['tumelo'], 'Annual Leave', $wd($next(60)), $wd($next(62)));
    }

    private function seedContracts(array $u): void
    {
        $svc = app(ContractService::class);
        Auth::setUser($u['manager']);
        $today = today();

        $rows = [
            // [user, type, start, end]
            [$u['tumelo'], 'contractor', $today->copy()->subMonths(11), $today->copy()->addDays(25)],
            [$u['zanele'], 'internship', $today->copy()->subMonths(10), $today->copy()->addDays(55)],
            [$u['ayesha'], 'fixed_term', $today->copy()->subMonths(9), $today->copy()->addDays(85)],
            [$u['nomsa'], 'fixed_term', $today->copy()->subMonths(4), $today->copy()->addMonths(8)],
            [$u['johan'], 'fixed_term', $today->copy()->subMonths(12), $today->copy()->subDays(5)],
            [$u['kabelo'], 'fixed_term', $today->copy()->subMonths(6), $today->copy()->endOfMonth()],
        ];

        foreach ($rows as [$who, $type, $start, $end]) {
            $c = Contract::create([
                'reference' => $svc->nextReference(), 'user_id' => $who->id, 'full_name' => $who->name,
                'employee_number' => $who->employee_number, 'job_title' => $who->job_title, 'department_id' => $who->department_id,
                'contract_type' => $type, 'start_date' => $start, 'end_date' => $end,
                'date_signed' => $start->copy()->subDays(7), 'status' => ContractStatus::Active, 'dates_verified' => true,
                'created_by' => $u['manager']->id,
            ]);
            $c->update(['status' => $svc->monitoredStatusFor($c)]);
        }

        // A renewal history example for Lerato.
        $old = Contract::create([
            'reference' => $svc->nextReference(), 'user_id' => $u['lerato']->id, 'full_name' => $u['lerato']->name,
            'employee_number' => $u['lerato']->employee_number, 'job_title' => 'Junior Software Developer',
            'department_id' => $u['lerato']->department_id, 'contract_type' => 'fixed_term',
            'start_date' => $today->copy()->subMonths(18), 'end_date' => $today->copy()->subMonths(6)->subDay(),
            'date_signed' => $today->copy()->subMonths(18)->subDays(10), 'status' => ContractStatus::Expired,
            'dates_verified' => true, 'created_by' => $u['manager']->id,
        ]);
        $svc->renew($old, [
            'renewal_kind' => 'renewal', 'contract_type' => 'fixed_term', 'job_title' => 'Software Developer',
            'start_date' => $today->copy()->subMonths(6)->toDateString(), 'end_date' => $today->copy()->addMonths(6)->toDateString(),
            'date_signed' => $today->copy()->subMonths(6)->subDays(5)->toDateString(),
            'decided_by_name' => 'Thandi Mokoena (Director)', 'decision_date' => $today->copy()->subMonths(6)->subDays(14)->toDateString(),
            'notes' => 'Renewed for 12 months with promotion to Software Developer.',
        ], null, $u['manager']);

        $svc->sendReminders();
    }

    private function seedAbsences(array $u): void
    {
        Auth::setUser($u['manager']);
        $today = today();
        $prev = fn (int $n) => $today->copy()->subWeekdays($n);

        AbsenceRecord::create([
            'user_id' => $u['nomsa']->id, 'date' => $prev(1), 'record_type' => 'absence',
            'classification' => AbsenceClassification::PendingExplanation, 'follow_up_status' => 'open',
            'management_comment' => 'Did not report for duty; no call received. Awaiting explanation.', 'recorded_by' => $u['manager']->id,
        ]);
        AbsenceRecord::create([
            'user_id' => $u['johan']->id, 'date' => $prev(3), 'record_type' => 'late_arrival', 'arrival_time' => '09:45',
            'classification' => AbsenceClassification::OtherReason, 'reason' => 'Taxi strike on the route.',
            'follow_up_status' => 'closed', 'recorded_by' => $u['manager']->id,
        ]);
        AbsenceRecord::create([
            'user_id' => $u['ayesha']->id, 'date' => $prev(2), 'record_type' => 'absence',
            'classification' => AbsenceClassification::ReportedSick, 'reason' => 'Phoned in sick at 07:30.',
            'follow_up_status' => 'in_progress', 'management_comment' => 'Reminded to submit a sick leave application.', 'recorded_by' => $u['manager']->id,
        ]);
    }
}

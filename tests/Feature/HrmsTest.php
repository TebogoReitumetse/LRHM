<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Enums\LeaveStatus;
use App\Models\AbsenceRecord;
use App\Models\Contract;
use App\Models\LeaveApplication;
use App\Models\LeaveCategory;
use App\Models\User;
use App\Notifications\ContractExpiryNotification;
use App\Notifications\LeaveApplicationNotification;
use App\Services\ContractService;
use App\Services\WorkingDayCalculator;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HrmsTest extends TestCase
{
    use RefreshDatabase;

    /** A 1x1 PNG, standing in for a drawn signature. */
    private const SIG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    private function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    public function test_every_page_loads_for_each_role(): void
    {
        $pages = [
            'admin' => ['/', '/leave', '/leave/create', '/admin/users', '/admin/users/create', '/admin/departments',
                '/admin/leave-categories', '/admin/holidays', '/admin/settings', '/admin/backups', '/admin/audit', '/profile', '/notifications'],
            'manager' => ['/', '/leave', '/leave/create', '/approvals', '/register', '/reports', '/absences', '/absences/create',
                '/contracts/dashboard', '/contracts', '/contracts/create', '/admin/users', '/admin/audit', '/notifications',
                '/reports/run?type=monthly&month='.now()->format('Y-m'), '/reports/run?type=department',
                '/reports/run?type=status&status_group=approved', '/register/export/print', '/register/export/csv',
                '/register/export/pdf', '/contracts/export/csv', '/contracts/export/pdf'],
            'director' => ['/', '/approvals', '/register', '/reports', '/absences', '/admin/audit'],
            'lerato' => ['/', '/leave', '/leave/create', '/my-contract', '/notifications', '/profile'],
        ];

        foreach ($pages as $username => $urls) {
            $this->actingAs($this->user($username));
            foreach ($urls as $url) {
                $this->get($url)->assertOk();
            }
        }

        // Detail pages
        $this->actingAs($this->user('manager'));
        foreach (LeaveApplication::submittedOnly()->get() as $a) {
            $this->get(route('leave.show', $a))->assertOk();
            $this->get(route('leave.print', $a))->assertOk();
        }
        foreach (Contract::all() as $c) {
            $this->get(route('contracts.show', $c))->assertOk();
            $this->get(route('contracts.edit', $c))->assertStatus(in_array($c->status, [ContractStatus::Renewed, ContractStatus::Superseded, ContractStatus::Ended], true) ? 403 : 200);
        }
        $this->get(route('contracts.renew', Contract::where('status', 'due_to_expire')->first()))->assertOk();
        foreach (AbsenceRecord::all() as $r) {
            $this->get(route('absences.show', $r))->assertOk();
            $this->get(route('absences.edit', $r))->assertOk();
        }
    }

    public function test_role_based_access_is_enforced(): void
    {
        $lerato = $this->user('lerato');
        $other = LeaveApplication::where('user_id', '!=', $lerato->id)->submittedOnly()->first();

        $this->actingAs($lerato);
        $this->get(route('leave.show', $other))->assertForbidden();
        $this->get('/register')->assertForbidden();
        $this->get('/contracts')->assertForbidden();
        $this->get('/absences')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->get(route('contracts.show', Contract::where('user_id', '!=', $lerato->id)->first()))->assertForbidden();

        // Director cannot access the contract module in Phase 2.
        $this->actingAs($this->user('director'));
        $this->get('/contracts')->assertForbidden();
        $this->get('/admin/settings')->assertForbidden();

        // Admin cannot read confidential leave records.
        $this->actingAs($this->user('admin'));
        $this->get(route('leave.show', $other))->assertForbidden();
        $this->get('/register')->assertForbidden();

        // Drafts are private to the employee.
        $draft = LeaveApplication::status(LeaveStatus::Draft)->first();
        $this->actingAs($this->user('manager'));
        $this->get(route('leave.show', $draft))->assertForbidden();
    }

    public function test_full_leave_workflow(): void
    {
        Notification::fake();
        $emp = $this->user('ayesha');
        $manager = $this->user('manager');
        $director = $this->user('director');
        $annual = LeaveCategory::where('name', 'Annual Leave')->first();

        // Mon 7 Dec 2026 – Fri 18 Dec 2026, includes Day of Reconciliation (Wed 16 Dec): 9 working days.
        $this->actingAs($emp)->post(route('leave.store'), [
            'leave_category_id' => $annual->id, 'start_date' => '2026-12-07', 'end_date' => '2026-12-18',
            'return_date' => '2026-12-21', 'employee_signature' => self::SIG, 'employee_signed_on' => '2026-11-10', 'action' => 'submit',
        ])->assertRedirect();

        $app = LeaveApplication::where('user_id', $emp->id)->latest('id')->first();
        $this->assertEquals(9, (float) $app->working_days);
        $this->assertSame(LeaveStatus::Submitted, $app->status);
        $this->assertNotNull($app->reference);
        $this->assertLeaveEmailSent($manager, 'submitted');
        $this->assertLeaveEmailSent($director, 'submitted_for_director');

        // Employee cannot approve; Director cannot act before the Manager forwards.
        $this->post(route('leave.decide', $app), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($director)->post(route('leave.decide', $app), ['decision' => 'approve', 'approver_signature' => self::SIG, 'approver_name' => 'X'])->assertSessionHasErrors('status');

        // Manager returns for information.
        $this->actingAs($manager)->post(route('leave.return', $app), ['comment' => 'Add contact details'])->assertRedirect();
        $this->assertSame(LeaveStatus::ReturnedForInformation, $app->fresh()->status);
        $this->assertNull($app->fresh()->employee_signature); // must sign again

        // Resubmitting without signing again is not allowed.
        $this->actingAs($emp)->put(route('leave.update', $app), [
            'leave_category_id' => $annual->id, 'start_date' => '2026-12-07', 'end_date' => '2026-12-18',
            'return_date' => '2026-12-21', 'action' => 'submit',
        ])->assertSessionHasErrors('employee_signature');

        // Employee resubmits.
        $this->actingAs($emp)->put(route('leave.update', $app), [
            'leave_category_id' => $annual->id, 'start_date' => '2026-12-07', 'end_date' => '2026-12-18',
            'return_date' => '2026-12-21', 'employee_signature' => self::SIG, 'action' => 'submit',
        ])->assertRedirect();
        $this->assertSame(LeaveStatus::Submitted, $app->fresh()->status);

        // Manager must sign the Manager/Owner Signature when forwarding.
        $this->actingAs($manager)->post(route('leave.forward', $app), ['recommendation' => 'approve', 'comment' => 'OK'])
            ->assertSessionHasErrors(['approver_signature', 'approver_name']);
        $this->actingAs($manager)->post(route('leave.forward', $app), [
            'recommendation' => 'approve', 'comment' => 'OK', 'approver_signature' => self::SIG, 'approver_name' => 'Thabo Manager',
        ])->assertRedirect();
        $this->assertSame(LeaveStatus::PendingDirectorApproval, $app->fresh()->status);
        $this->actingAs($manager)->post(route('leave.decide', $app), ['decision' => 'approve'])->assertForbidden();

        // Director must sign before approving; the Manager's signature stays on the form.
        $this->actingAs($director)->post(route('leave.decide', $app), ['decision' => 'approve', 'comment' => 'Enjoy'])
            ->assertSessionHasErrors('director_signature');
        $this->assertSame(LeaveStatus::PendingDirectorApproval, $app->fresh()->status);
        $this->actingAs($director)->post(route('leave.decide', $app), ['decision' => 'approve', 'comment' => 'Enjoy', 'director_signature' => self::SIG])
            ->assertSessionHasNoErrors()->assertRedirect();

        $app->refresh();
        $this->assertSame(LeaveStatus::Approved, $app->status);
        $this->assertSame('Thabo Manager', $app->approver_name);
        $this->assertSame(self::SIG, $app->approver_signature);
        $this->assertSame(self::SIG, $app->director_signature);
        $this->get(route('leave.print', $app))->assertOk()->assertSee('LEAVE APPLICATION FORM')->assertSee('Thabo Manager');
        $this->assertEquals($director->id, $app->director_id);
        $this->assertNotNull($app->decided_at);
        $this->assertGreaterThanOrEqual(6, $app->actions()->count());
        $this->assertLeaveEmailSent($emp, 'approved');
    }

    public function test_signatures_are_saved_for_reuse(): void
    {
        $emp = $this->user('lerato');
        $manager = $this->user('manager');
        $director = $this->user('director');
        Notification::fake();
        $annual = LeaveCategory::where('name', 'Annual Leave')->first();
        $managerSig = str_replace('ggg', 'ggh', self::SIG);
        $directorSig = str_replace('ggg', 'ggi', self::SIG);

        $this->actingAs($emp)->post(route('leave.store'), [
            'leave_category_id' => $annual->id, 'start_date' => '2027-05-03', 'end_date' => '2027-05-04',
            'return_date' => '2027-05-05', 'employee_signature' => self::SIG, 'action' => 'submit',
        ])->assertSessionHasNoErrors();
        $app = LeaveApplication::where('user_id', $emp->id)->latest('id')->first();
        $this->assertSame(self::SIG, $emp->fresh()->signature);

        $this->actingAs($manager)->post(route('leave.forward', $app), [
            'recommendation' => 'approve', 'approver_signature' => $managerSig, 'approver_name' => $manager->name,
        ])->assertSessionHasNoErrors();
        $this->assertSame($managerSig, $manager->fresh()->signature);

        $this->actingAs($director)->post(route('leave.decide', $app), ['decision' => 'approve'])
            ->assertSessionHasErrors('director_signature');
        $this->assertSame(LeaveStatus::PendingDirectorApproval, $app->fresh()->status);
        $this->actingAs($director)->post(route('leave.decide', $app), ['decision' => 'approve', 'director_signature' => $directorSig])
            ->assertSessionHasNoErrors();
        $this->assertSame($directorSig, $app->fresh()->director_signature);
        $this->assertSame($managerSig, $app->fresh()->approver_signature);
        $this->assertSame($directorSig, $director->fresh()->signature);

        // The saved signature pre-fills the next form and can be removed from the profile.
        $this->actingAs($emp)->get(route('leave.create'))->assertOk()->assertSee(substr(self::SIG, 22), false);
        $this->put(route('profile.signature'), ['signature' => ''])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull($emp->fresh()->signature);
        $this->put(route('profile.signature'), ['signature' => 'not-a-png'])->assertSessionHasErrors('signature');
    }

    private function assertLeaveEmailSent(User $user, string $event): void
    {
        Notification::assertSentTo($user, LeaveApplicationNotification::class,
            fn (LeaveApplicationNotification $n, array $channels) => $n->event === $event && in_array('mail', $channels, true));
    }

    public function test_sick_leave_needs_certificate_only_for_more_than_two_days(): void
    {
        $emp = $this->user('johan');
        $sick = LeaveCategory::where('name', 'Sick Leave')->first();
        // Mon 1 – Wed 3 Feb 2027: 3 working days.
        $data = ['leave_category_id' => $sick->id, 'start_date' => '2027-02-01', 'end_date' => '2027-02-03',
            'return_date' => '2027-02-04', 'action' => 'submit'];

        $this->actingAs($emp)->post(route('leave.store'), $data)->assertSessionHasErrors(['employee_signature', 'documents']);
        $this->assertSame(LeaveStatus::Draft, LeaveApplication::where('user_id', $emp->id)->latest('id')->first()->status);

        // 2 days: no certificate needed.
        $this->post(route('leave.store'), ['start_date' => '2027-02-08', 'end_date' => '2027-02-09', 'return_date' => '2027-02-10',
            'employee_signature' => self::SIG] + $data)->assertSessionHasNoErrors();
        $this->assertSame(LeaveStatus::Submitted, LeaveApplication::where('user_id', $emp->id)->latest('id')->first()->status);

        // More than 2 days with a certificate.
        $this->post(route('leave.store'), ['start_date' => '2027-02-15', 'end_date' => '2027-02-17', 'return_date' => '2027-02-18',
            'employee_signature' => self::SIG, 'documents' => [UploadedFile::fake()->create('medical.pdf', 100, 'application/pdf')]] + $data)
            ->assertSessionHasNoErrors();
        $app = LeaveApplication::where('user_id', $emp->id)->latest('id')->first();
        $this->assertSame(LeaveStatus::Submitted, $app->status);
        $this->get(route('leave.document', [$app, $app->documents->first()]))->assertOk();
    }

    public function test_paper_form_fields(): void
    {
        $emp = $this->user('lerato');
        $emp->update(['id_number' => '9104075789082']);
        $other = LeaveCategory::where('name', 'Other')->first();

        // The form lists exactly the leave types on the paper form.
        $this->assertSame(['Annual Leave', 'Sick Leave', 'Family Responsibility Leave', 'Maternity / Paternity Leave', 'Unpaid Leave', 'Other'],
            LeaveCategory::active()->pluck('name')->all());
        $this->actingAs($emp)->get(route('leave.create'))->assertOk()
            ->assertSee('9104075789082')->assertSee('I confirm that the above information is correct', false)
            ->assertSee('Attach medical certificate if more than 2 days')
            ->assertSee(LeaveCategory::FORM_NOTES['Maternity / Paternity Leave'])
            ->assertSee(LeaveCategory::FORM_NOTES[LeaveCategory::UNPAID])
            ->assertSee(LeaveCategory::FORM_NOTES['Other'])
            ->assertViewHas('balanceMap', fn (array $map) => $map[LeaveCategory::where('name', 'Maternity / Paternity Leave')->value('id')] == [
                'tracked' => false, 'entitlements' => LeaveCategory::UNTRACKED_ENTITLEMENTS['Maternity / Paternity Leave'],
                'taken' => 0, 'pending' => 0, 'cycle' => now()->startOfYear()->format('d M Y').' – '.now()->endOfYear()->format('d M Y'),
            ]);

        $data = ['leave_category_id' => $other->id, 'start_date' => '2027-03-01', 'end_date' => '2027-03-01',
            'return_date' => '2027-03-02', 'employee_signature' => self::SIG, 'action' => 'submit'];
        $this->post(route('leave.store'), $data)->assertSessionHasErrors('other_leave_type');
        $this->post(route('leave.store'), $data + ['other_leave_type' => 'Study leave'])->assertSessionHasNoErrors();

        $app = LeaveApplication::where('user_id', $emp->id)->latest('id')->first();
        $this->assertSame('9104075789082', $app->id_number);
        $this->assertSame('Other: Study leave', $app->leaveTypeLabel());
        $this->assertTrue($app->declaration_accepted);
        $this->assertNotNull($app->employee_signed_on);

        // A signature that is not a PNG image is rejected.
        $this->post(route('leave.store'), ['employee_signature' => 'javascript:alert(1)', 'start_date' => '2027-03-08',
            'end_date' => '2027-03-08', 'return_date' => '2027-03-09', 'other_leave_type' => 'x'] + $data)
            ->assertSessionHasErrors('employee_signature');
    }

    public function test_id_number_entered_on_leave_form_is_remembered(): void
    {
        $emp = $this->user('lerato');
        $emp->update(['id_number' => null]);
        $annual = LeaveCategory::where('name', 'Annual Leave')->first();
        $data = ['leave_category_id' => $annual->id, 'start_date' => '2027-04-05', 'end_date' => '2027-04-05',
            'return_date' => '2027-04-06', 'employee_signature' => self::SIG];

        $this->actingAs($emp)->post(route('leave.store'), $data + ['id_number' => 'abc!'])->assertSessionHasErrors('id_number');
        $this->post(route('leave.store'), $data + ['id_number' => '9104075789082'])->assertSessionHasNoErrors();

        $this->assertSame('9104075789082', $emp->fresh()->id_number);
        $this->assertSame('9104075789082', LeaveApplication::where('user_id', $emp->id)->latest('id')->first()->id_number);
        // The next application is pre-filled from the profile.
        $this->get(route('leave.create'))->assertOk()->assertSee('9104075789082');
    }

    public function test_user_id_number_is_saved(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Tebogo Gopane', 'username' => 'tebogo', 'employee_number' => 'LV-020', 'id_number' => '9104075789082',
            'role' => 'employee', 'employment_type' => 'employee', 'is_active' => 1,
        ])->assertRedirect();
        $u = User::where('username', 'tebogo')->first();
        $this->assertSame('9104075789082', $u->id_number);
        $this->get(route('admin.users.edit', $u))->assertOk()->assertSee('9104075789082');
        $this->get(route('admin.users.index', ['search' => '91040757']))->assertOk()->assertSee('Tebogo Gopane');
    }

    public function test_working_day_calculation(): void
    {
        $cal = app(WorkingDayCalculator::class);
        $this->assertSame(5, $cal->count(Carbon::parse('2026-11-09'), Carbon::parse('2026-11-15'))); // Mon–Sun
        $this->assertSame(5, $cal->count(Carbon::parse('2026-12-24'), Carbon::parse('2026-12-31'))); // excludes Christmas (Fri) + weekend
        $this->assertSame('2026-12-28', $cal->nextWorkingDay(Carbon::parse('2026-12-24'))->toDateString());
    }

    public function test_contract_duration_and_reminders(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-11-05 08:00'));
        $this->assertSame('12 months', Contract::formatDuration(Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31')));
        $this->assertSame('6 months, 15 days', Contract::formatDuration(Carbon::parse('2026-01-01'), Carbon::parse('2026-07-15')));

        $manager = $this->user('manager');
        $emp = $this->user('nomsa');
        $svc = app(ContractService::class);

        $this->actingAs($manager)->post(route('contracts.store'), [
            'user_id' => $emp->id, 'full_name' => $emp->name, 'employee_number' => $emp->employee_number,
            'contract_type' => 'fixed_term', 'start_date' => '2026-02-04', 'end_date' => '2027-02-03',
            'status' => 'active', 'dates_verified' => 1,
            'signed_contract' => UploadedFile::fake()->create('contract.pdf', 50, 'application/pdf'),
        ])->assertRedirect();
        $c = Contract::latest('id')->first();
        $this->assertSame(ContractStatus::DueToExpire, $c->status); // 90 days left
        $this->assertSame('12 months', $c->durationLabel());

        $svc->sendReminders();
        $this->assertTrue($c->reminders()->where('reminder_key', 'reminder_90')->where('status', 'sent')->exists());
        Notification::assertSentTo($manager, ContractExpiryNotification::class);

        $this->travelTo(Carbon::parse('2026-12-05 08:00'));
        $svc->sendReminders();
        $this->assertTrue($c->reminders()->where('reminder_key', 'reminder_60')->exists());
        $this->travelTo(Carbon::parse('2027-01-04 08:00'));
        $svc->sendReminders();
        $this->assertTrue($c->reminders()->where('reminder_key', 'reminder_30')->exists());
        $svc->sendReminders(); // no duplicates
        $this->assertSame(1, $c->reminders()->where('reminder_key', 'reminder_30')->count());

        $this->travelTo(Carbon::parse('2027-02-03 08:00'));
        $svc->sendReminders();
        $this->assertTrue($c->reminders()->where('reminder_key', 'expiry')->exists());

        $this->travelTo(Carbon::parse('2027-02-10 08:00'));
        $svc->sendReminders();
        $this->assertSame(ContractStatus::Expired, $c->fresh()->status);
        $this->assertTrue($c->reminders()->where('reminder_key', 'like', 'follow_up%')->exists());

        // The employee can view their own contract, but not edit it.
        $this->actingAs($emp)->get(route('contracts.show', $c))->assertOk();
        $this->get(route('contracts.edit', $c))->assertForbidden();

        // Renewal keeps history.
        $this->actingAs($manager)->post(route('contracts.renew', $c), [
            'renewal_kind' => 'renewal', 'contract_type' => 'fixed_term', 'start_date' => '2027-02-04', 'end_date' => '2028-02-03',
            'decided_by_name' => 'Thandi Mokoena', 'decision_date' => '2027-02-09', 'dates_verified' => 1,
        ])->assertRedirect();
        $this->assertSame(ContractStatus::Renewed, $c->fresh()->status);
        $new = Contract::where('previous_contract_id', $c->id)->first();
        $this->assertNotNull($new);
        $this->assertSame(1, $c->documents()->count());
        $this->get(route('contracts.show', $new))->assertOk()->assertSee($c->reference);
    }

    public function test_login_and_forced_password_change(): void
    {
        $this->post('/login', ['username' => 'lerato', 'password' => 'wrong'])->assertSessionHasErrors('username');
        $this->post('/login', ['username' => 'lerato', 'password' => 'Password@123'])->assertRedirect('/');

        auth()->logout();
        $u = User::factory()->create(['password_changed_at' => null, 'password' => 'Temp@Pass123']);
        $this->post('/login', ['username' => $u->username, 'password' => 'Temp@Pass123'])->assertRedirect(route('profile.edit'));
        $this->get('/')->assertRedirect(route('profile.edit'));

        $u->update(['is_active' => false]);
        $this->actingAs($u->fresh())->get('/leave')->assertRedirect(route('login'));
    }
}

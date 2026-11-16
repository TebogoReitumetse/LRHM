<?php

namespace Tests\Feature;

use App\Enums\LeaveStatus;
use App\Models\LeaveApplication;
use App\Models\LeaveCategory;
use App\Models\User;
use App\Services\LeaveBalance;
use App\Services\LeaveBalanceService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-11-09');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function newEmployee(array $attrs = []): User
    {
        return User::factory()->create($attrs + [
            'username' => 'bal'.uniqid(), 'employee_number' => 'T-'.uniqid(), 'role' => 'employee', 'employment_type' => 'employee', 'is_active' => true,
        ]);
    }

    private function leave(User $u, string $category, string $start, float $days, LeaveStatus $status): LeaveApplication
    {
        return LeaveApplication::create([
            'user_id' => $u->id, 'leave_category_id' => LeaveCategory::where('name', $category)->value('id'),
            'employee_name' => $u->name, 'employee_number' => $u->employee_number,
            'start_date' => $start, 'end_date' => $start, 'return_date' => $start, 'working_days' => $days, 'status' => $status,
        ]);
    }

    public function test_default_entitlements_are_seeded(): void
    {
        $this->assertSame(21.0, LeaveCategory::where('name', 'Annual Leave')->first()->entitlement_days);
        $this->assertSame(36, LeaveCategory::where('name', 'Sick Leave')->first()->entitlement_cycle_months);
        $this->assertSame(3.0, LeaveCategory::where('name', 'Family Responsibility Leave')->first()->entitlement_days);
    }

    public function test_taken_pending_and_available_are_calculated_for_the_current_cycle(): void
    {
        $u = $this->newEmployee(['employment_start_date' => '2024-03-15']);
        $this->leave($u, 'Annual Leave', '2026-04-01', 5, LeaveStatus::Approved);
        $this->leave($u, 'Annual Leave', '2026-12-01', 2, LeaveStatus::Approved);            // approved, still to come
        $this->leave($u, 'Annual Leave', '2026-12-10', 3, LeaveStatus::Submitted);           // pending
        $this->leave($u, 'Annual Leave', '2026-12-20', 4, LeaveStatus::Declined);            // ignored
        $this->leave($u, 'Annual Leave', '2026-12-22', 4, LeaveStatus::Draft);               // ignored
        $this->leave($u, 'Annual Leave', '2026-02-01', 6, LeaveStatus::Approved);            // previous cycle

        $b = app(LeaveBalanceService::class)->forUser($u)->first(fn ($b) => $b->category->name === 'Annual Leave');

        $this->assertSame('2026-03-15', $b->cycleStart->toDateString());
        $this->assertSame('2027-03-14', $b->cycleEnd->toDateString());
        $this->assertSame(7.0, $b->taken);
        $this->assertSame(2.0, $b->scheduled);
        $this->assertSame(3.0, $b->pending);
        $this->assertSame(14.0, $b->available());
        $this->assertSame(11.0, $b->availableAfterPending());
    }

    /** Submits the leave form for $user and returns the saved application. */
    private function apply(User $user, string $category, string $start, string $end, string $return): LeaveApplication
    {
        $this->actingAs($user)->post(route('leave.store'), [
            'leave_category_id' => LeaveCategory::where('name', $category)->value('id'),
            'start_date' => $start, 'end_date' => $end, 'return_date' => $return,
            'employee_signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
            'action' => 'submit',
        ])->assertSessionHasNoErrors();

        return LeaveApplication::where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    private function balance(User $user, string $category): LeaveBalance
    {
        return app(LeaveBalanceService::class)->forUser($user)->first(fn ($b) => $b->category->name === $category);
    }

    public function test_annual_leave_beyond_the_balance_is_recorded_as_unpaid_leave(): void
    {
        $u = $this->newEmployee(['employment_start_date' => '2026-01-01']);
        $this->leave($u, 'Annual Leave', '2026-03-02', 18, LeaveStatus::Approved);

        // Mon 23 – Fri 27 Nov 2026: 5 working days, only 3 annual leave days left.
        $app = $this->apply($u, 'Annual Leave', '2026-11-23', '2026-11-27', '2026-11-30');

        $this->assertSame(LeaveStatus::Submitted, $app->status);
        $this->assertEquals(5, (float) $app->working_days);
        $this->assertEquals(2, (float) $app->unpaid_days);
        $this->assertSame('3 Annual Leave + 2 Unpaid Leave', $app->daysSplitLabel());

        $annual = $this->balance($u, 'Annual Leave');
        $this->assertSame(3.0, $annual->pending);
        $this->assertSame(0.0, $annual->availableAfterPending());
        $this->assertSame(2.0, $this->balance($u, 'Unpaid Leave')->pending);

        $this->get(route('leave.show', $app))->assertOk()->assertSee('3 Annual Leave + 2 Unpaid Leave');
    }

    public function test_other_tracked_leave_types_also_move_extra_days_to_unpaid_leave(): void
    {
        $u = $this->newEmployee(['employment_start_date' => '2026-01-01']);

        // Mon 23 – Thu 26 Nov 2026: 4 days of Family Responsibility Leave, entitlement is 3.
        $app = $this->apply($u, 'Family Responsibility Leave', '2026-11-23', '2026-11-26', '2026-11-27');

        $this->assertEquals(1, (float) $app->unpaid_days);
        $this->assertSame(3.0, $this->balance($u, 'Family Responsibility Leave')->pending);
        $this->assertSame(1.0, $this->balance($u, 'Unpaid Leave')->pending);
    }

    public function test_leave_within_the_balance_or_without_an_entitlement_has_no_unpaid_days(): void
    {
        $u = $this->newEmployee(['employment_start_date' => '2026-01-01']);

        $this->assertEquals(0, (float) $this->apply($u, 'Annual Leave', '2026-11-23', '2026-11-27', '2026-11-30')->unpaid_days);
        $this->assertEquals(0, (float) $this->apply($u, 'Unpaid Leave', '2026-12-01', '2026-12-04', '2026-12-07')->unpaid_days);
        $this->assertSame(4.0, $this->balance($u, 'Unpaid Leave')->pending);
    }

    public function test_unpaid_days_are_recalculated_when_the_application_is_submitted(): void
    {
        $u = $this->newEmployee(['employment_start_date' => '2026-01-01']);
        $this->leave($u, 'Annual Leave', '2026-03-02', 18, LeaveStatus::Approved);
        $earlier = $this->leave($u, 'Annual Leave', '2026-10-01', 3, LeaveStatus::Submitted);

        // Saved as a draft while 3 days are reserved by the earlier application: all 5 days unpaid.
        $this->actingAs($u)->post(route('leave.store'), [
            'leave_category_id' => LeaveCategory::where('name', 'Annual Leave')->value('id'),
            'start_date' => '2026-11-23', 'end_date' => '2026-11-27', 'return_date' => '2026-11-30',
            'employee_signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
        ])->assertSessionHasNoErrors();
        $draft = LeaveApplication::where('user_id', $u->id)->latest('id')->firstOrFail();
        $this->assertEquals(5, (float) $draft->unpaid_days);

        // The earlier application is declined, freeing 3 days before this one is submitted.
        $earlier->update(['status' => LeaveStatus::Declined]);
        $this->post(route('leave.submit', $draft))->assertSessionHasNoErrors();
        $this->assertEquals(2, (float) $draft->fresh()->unpaid_days);
    }

    public function test_cycles_follow_the_employment_start_date(): void
    {
        $svc = app(LeaveBalanceService::class);
        $on = CarbonImmutable::parse('2026-11-09');

        $u = new User(['employment_start_date' => '2025-11-09']);
        $this->assertSame(['2026-11-09', '2027-11-08'], array_map(fn ($d) => $d->toDateString(), $svc->cycle($u, 12, $on)));
        $this->assertSame(['2025-11-09', '2028-11-08'], array_map(fn ($d) => $d->toDateString(), $svc->cycle($u, 36, $on)));

        $u = new User(['employment_start_date' => '2025-11-10']);
        $this->assertSame(['2025-11-10', '2026-11-09'], array_map(fn ($d) => $d->toDateString(), $svc->cycle($u, 12, $on)));
    }

    public function test_contractors_have_no_entitlement_unless_enabled(): void
    {
        $c = $this->newEmployee(['employment_type' => 'contractor']);
        $this->assertTrue(app(LeaveBalanceService::class)->forUser($c)->isEmpty());

        LeaveCategory::where('name', 'Annual Leave')->update(['entitlement_for_contractors' => true]);
        $this->assertSame(21.0, app(LeaveBalanceService::class)->forUser($c)->first()->entitlement);
    }

    public function test_balance_pages_respect_roles(): void
    {
        $lerato = User::where('username', 'lerato')->first();

        $this->actingAs($lerato)->get('/balances')->assertForbidden();
        $this->actingAs($lerato)->get('/')->assertOk()->assertSee('My leave balances')->assertSee('Your leave rights under the Basic Conditions of Employment Act');

        foreach (['manager', 'director', 'admin'] as $name) {
            $this->actingAs(User::where('username', $name)->first())->get('/balances')->assertOk()->assertSee('Lerato');
        }
        $this->actingAs(User::where('username', 'manager')->first())->get('/balances/export')->assertOk();

        // Admin sees the balance but not the individual (confidential) applications.
        $this->actingAs(User::where('username', 'admin')->first())->get(route('balances.show', $lerato))
            ->assertOk()->assertDontSee('Leave counted in the current cycles');
        $this->actingAs(User::where('username', 'manager')->first())->get(route('balances.show', $lerato))
            ->assertOk()->assertSee('Leave counted in the current cycles');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\LeaveStatus;
use App\Models\LeaveApplication;
use App\Models\LeaveCategory;
use App\Models\User;
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
            'username' => 'bal'.uniqid(), 'employee_number' => 'T-'.uniqid(), 'role' => 'employee', 'employment_type' => 'employee',
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

    public function test_bcea_defaults_are_seeded(): void
    {
        $this->assertSame(15.0, LeaveCategory::where('name', 'Annual Leave')->first()->entitlement_days);
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
        $this->assertSame(8.0, $b->available());
        $this->assertSame(5.0, $b->availableAfterPending());
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
        $this->assertSame(15.0, app(LeaveBalanceService::class)->forUser($c)->first()->entitlement);
    }

    public function test_balance_pages_respect_roles(): void
    {
        $lerato = User::where('username', 'lerato')->first();

        $this->actingAs($lerato)->get('/balances')->assertForbidden();
        $this->actingAs($lerato)->get('/')->assertOk()->assertSee('My leave balances');

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

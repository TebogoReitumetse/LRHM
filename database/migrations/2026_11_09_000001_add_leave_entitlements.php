<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Leave cycles run from this date (BCEA s20 / s22). If empty, cycles start on 1 January.
            $table->date('employment_start_date')->nullable()->after('employment_type');
        });

        Schema::table('leave_categories', function (Blueprint $table) {
            // Working days allowed per cycle. Null = no balance is tracked (only days taken are shown).
            $table->decimal('entitlement_days', 5, 1)->nullable()->after('requires_reason');
            $table->unsignedSmallInteger('entitlement_cycle_months')->default(12)->after('entitlement_days');
            $table->boolean('entitlement_for_contractors')->default(false)->after('entitlement_cycle_months');
        });

        // BCEA minimums for a 5-day working week.
        $defaults = [
            'Annual Leave' => [15, 12],                // 21 consecutive days = 15 working days per 12-month cycle (s20)
            'Sick Leave' => [30, 36],                  // 30 days per 36-month cycle (s22)
            'Family Responsibility Leave' => [3, 12],  // 3 days per year (s27)
        ];
        foreach ($defaults as $name => [$days, $months]) {
            DB::table('leave_categories')->where('name', $name)
                ->update(['entitlement_days' => $days, 'entitlement_cycle_months' => $months]);
        }
    }

    public function down(): void
    {
        Schema::table('leave_categories', function (Blueprint $table) {
            $table->dropColumn(['entitlement_days', 'entitlement_cycle_months', 'entitlement_for_contractors']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('employment_start_date');
        });
    }
};

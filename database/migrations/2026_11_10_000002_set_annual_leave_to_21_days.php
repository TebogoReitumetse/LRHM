<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * LivityICT policy: 21 working days of annual leave per 12-month cycle
 * (above the BCEA minimum of 15 working days for a 5-day week).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('leave_categories')->where('name', 'Annual Leave')->update([
            'entitlement_days' => 21,
            'description' => 'Paid annual leave. LivityICT policy: 21 working days per 12-month cycle (BCEA minimum: 15 working days).',
        ]);
    }

    public function down(): void
    {
        DB::table('leave_categories')->where('name', 'Annual Leave')->update([
            'entitlement_days' => 15,
            'description' => 'Paid annual leave. BCEA minimum: 21 consecutive days (15 working days) per 12-month cycle.',
        ]);
    }
};

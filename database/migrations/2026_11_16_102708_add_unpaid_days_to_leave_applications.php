<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Days of an application that go beyond the employee's available balance for the
 * chosen leave type. They are counted as Unpaid Leave instead of the chosen type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->decimal('unpaid_days', 5, 1)->default(0)->after('working_days');
        });
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn('unpaid_days');
        });
    }
};

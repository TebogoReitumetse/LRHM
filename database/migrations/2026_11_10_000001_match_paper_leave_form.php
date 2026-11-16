<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns the online leave application with the LivityICT paper Leave Application Form:
 * employee ID number, "Other (specify)", employee signature and date, and the
 * Office Use Only block (decline reason, Manager/Owner signature, print name).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('id_number', 20)->nullable()->after('employee_number');
        });

        Schema::table('leave_categories', function (Blueprint $table) {
            // e.g. Sick Leave: a medical certificate is only required for more than 2 days.
            $table->unsignedSmallInteger('document_after_days')->nullable()->after('requires_document');
        });

        Schema::table('leave_applications', function (Blueprint $table) {
            $table->string('id_number', 20)->nullable()->after('employee_number');
            $table->string('other_leave_type', 150)->nullable()->after('leave_category_id');
            $table->mediumText('employee_signature')->nullable()->after('declaration_accepted_at');
            $table->date('employee_signed_on')->nullable()->after('employee_signature');
            $table->mediumText('approver_signature')->nullable()->after('director_comment');
            $table->string('approver_name', 150)->nullable()->after('approver_signature');
        });

        // Leave types on the paper form, in the same order. "Reason required" now means
        // "employee must specify the type", which only applies to Other.
        $rename = ['Maternity Leave' => 'Maternity / Paternity Leave', 'Other / Special Leave' => 'Other'];
        foreach ($rename as $from => $to) {
            if (! DB::table('leave_categories')->where('name', $to)->exists()) {
                DB::table('leave_categories')->where('name', $from)->update(['name' => $to]);
            }
        }

        DB::table('leave_categories')->update(['requires_reason' => false]);
        $paper = [
            'Annual Leave' => [1, []],
            'Sick Leave' => [2, ['requires_document' => true, 'document_after_days' => 2,
                'description' => 'Illness or injury. Attach a medical certificate if more than 2 days. BCEA: 30 days per 36-month cycle.']],
            'Family Responsibility Leave' => [3, []],
            'Maternity / Paternity Leave' => [4, ['requires_document' => false, 'description' => 'Maternity or paternity leave. Supporting documentation may be requested.']],
            'Unpaid Leave' => [5, []],
            'Other' => [6, ['requires_reason' => true, 'requires_document' => false,
                'description' => 'Any other leave. Specify the type of leave.']],
        ];
        foreach ($paper as $name => [$order, $extra]) {
            DB::table('leave_categories')->where('name', $name)->update(['sort_order' => $order, 'is_active' => true] + $extra);
        }

        // Types not on the paper form stay for history but can no longer be selected.
        DB::table('leave_categories')->whereNotIn('name', array_keys($paper))->update(['is_active' => false]);
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn(['id_number', 'other_leave_type', 'employee_signature', 'employee_signed_on', 'approver_signature', 'approver_name']);
        });
        Schema::table('leave_categories', function (Blueprint $table) {
            $table->dropColumn('document_after_days');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('id_number');
        });
    }
};

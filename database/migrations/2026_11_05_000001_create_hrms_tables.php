<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
            $table->string('employee_number')->unique()->after('username');
            $table->foreignId('department_id')->nullable()->after('employee_number')->constrained()->nullOnDelete();
            $table->string('job_title')->nullable()->after('department_id');
            $table->string('phone')->nullable()->after('email');
            // employee | manager | director | admin
            $table->string('role', 20)->default('employee')->index()->after('job_title');
            // employee | contractor
            $table->string('employment_type', 20)->default('employee')->after('role');
            $table->boolean('can_view_contract')->default(true)->after('employment_type');
            $table->boolean('is_active')->default(true)->after('can_view_contract');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->string('email')->nullable()->change();
        });

        Schema::create('leave_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('requires_document')->default(false);
            $table->boolean('requires_reason')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique()->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_category_id')->constrained()->restrictOnDelete();
            // Snapshot of the employee details at time of application
            $table->string('employee_name');
            $table->string('employee_number');
            $table->string('department')->nullable();
            $table->string('job_title')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->date('return_date');
            $table->decimal('working_days', 5, 1);
            $table->text('reason')->nullable();
            $table->string('contact_while_away')->nullable();
            $table->boolean('declaration_accepted')->default(false);
            $table->timestamp('declaration_accepted_at')->nullable();
            $table->string('status', 40)->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            // Manager review
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('manager_recommendation', 20)->nullable();
            $table->text('manager_comment')->nullable();
            $table->timestamp('manager_reviewed_at')->nullable();
            // Returned for information
            $table->text('return_reason')->nullable();
            $table->timestamp('returned_at')->nullable();
            // Director decision
            $table->foreignId('director_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('director_comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('leave_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('leave_application_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('absence_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            // absence | late_arrival
            $table->string('record_type', 30);
            $table->time('arrival_time')->nullable();
            // on_approved_leave | reported_sick | pending_explanation | other_reason | reviewed_classified
            $table->string('classification', 40)->default('pending_explanation');
            // Only filled in by management after review (e.g. authorised / unauthorised)
            $table->string('management_outcome', 40)->nullable();
            $table->text('reason')->nullable();
            $table->text('management_comment')->nullable();
            // open | in_progress | closed
            $table->string('follow_up_status', 20)->default('open');
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'date']);
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique()->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('employee_number');
            $table->string('job_title')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contract_type', 40);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('date_signed')->nullable();
            $table->date('commencement_date')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->boolean('dates_verified')->default(false);
            // renew | not_renew | null
            $table->string('renewal_decision', 20)->nullable();
            $table->string('renewal_decided_by_name')->nullable();
            $table->foreignId('renewal_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('renewal_decision_date')->nullable();
            $table->text('renewal_notes')->nullable();
            $table->date('ended_on')->nullable();
            $table->foreignId('previous_contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('end_date');
        });

        Schema::create('contract_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            // signed_contract | renewal | extension | addendum | other
            $table->string('document_type', 30);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('replaces_id')->nullable()->constrained('contract_documents')->nullOnDelete();
            $table->boolean('is_current')->default(true);
            $table->timestamps();
        });

        Schema::create('contract_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            // e.g. reminder_90, reminder_60, reminder_30, expiry, follow_up
            $table->string('reminder_key', 40);
            $table->date('scheduled_for');
            $table->timestamp('generated_at')->nullable();
            // generated | sent | failed
            $table->string('status', 20)->default('generated');
            $table->string('channels')->nullable();
            $table->unsignedInteger('recipients')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['contract_id', 'reminder_key']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 60);
            $table->nullableMorphs('auditable');
            $table->string('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'audit_logs', 'settings', 'contract_reminders', 'contract_documents', 'contracts',
            'absence_records', 'leave_application_actions', 'leave_documents', 'leave_applications',
            'public_holidays', 'leave_categories'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['username', 'employee_number', 'job_title', 'phone', 'role', 'employment_type',
                'can_view_contract', 'is_active', 'last_login_at', 'password_changed_at']);
        });
        Schema::dropIfExists('departments');
    }
};

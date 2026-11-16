<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Director signs every approval (separately from the Manager/Owner signature), and each
 * user's last signature is kept on their profile so it can be reused on the next form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->mediumText('director_signature')->nullable()->after('director_comment');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->mediumText('signature')->nullable()->after('id_number');
        });
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn('director_signature');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('signature');
        });
    }
};

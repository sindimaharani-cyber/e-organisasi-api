<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (!Schema::hasColumn('members', 'phone')) {
                $table
                    ->string('phone', 30)
                    ->nullable();
            }

            if (!Schema::hasColumn('members', 'study_program')) {
                $table
                    ->string('study_program', 150)
                    ->nullable();
            }

            if (!Schema::hasColumn('members', 'generation')) {
                $table
                    ->string('generation', 20)
                    ->nullable();
            }

            if (!Schema::hasColumn('members', 'position')) {
                $table
                    ->string('position', 150)
                    ->nullable();
            }

            if (!Schema::hasColumn('members', 'division')) {
                $table
                    ->string('division', 150)
                    ->nullable();
            }

            if (!Schema::hasColumn('members', 'service_period')) {
                $table
                    ->string('service_period', 100)
                    ->nullable();
            }

            if (!Schema::hasColumn('members', 'address')) {
                $table
                    ->text('address')
                    ->nullable();
            }

            if (!Schema::hasColumn('members', 'profile_photo')) {
                $table
                    ->string('profile_photo')
                    ->nullable();
            }

            if (!Schema::hasColumn('members', 'member_status')) {
                $table
                    ->string('member_status', 20)
                    ->default('active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('members', 'phone')) {
                $columns[] = 'phone';
            }

            if (Schema::hasColumn('members', 'study_program')) {
                $columns[] = 'study_program';
            }

            if (Schema::hasColumn('members', 'generation')) {
                $columns[] = 'generation';
            }

            if (Schema::hasColumn('members', 'position')) {
                $columns[] = 'position';
            }

            if (Schema::hasColumn('members', 'division')) {
                $columns[] = 'division';
            }

            if (Schema::hasColumn('members', 'service_period')) {
                $columns[] = 'service_period';
            }

            if (Schema::hasColumn('members', 'address')) {
                $columns[] = 'address';
            }

            if (Schema::hasColumn('members', 'profile_photo')) {
                $columns[] = 'profile_photo';
            }

            if (Schema::hasColumn('members', 'member_status')) {
                $columns[] = 'member_status';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
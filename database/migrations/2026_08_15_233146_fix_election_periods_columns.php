<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('election_periods', function (Blueprint $table) {

            if (!Schema::hasColumn('election_periods', 'description')) {
                $table->text('description')
                    ->nullable()
                    ->after('title');
            }

            if (!Schema::hasColumn('election_periods', 'start_date')) {
                $table->date('start_date')
                    ->nullable()
                    ->after('description');
            }

            if (!Schema::hasColumn('election_periods', 'end_date')) {
                $table->date('end_date')
                    ->nullable()
                    ->after('start_date');
            }

            if (!Schema::hasColumn('election_periods', 'status')) {
                $table->string('status', 30)
                    ->default('draft')
                    ->after('end_date');
            }

            if (!Schema::hasColumn('election_periods', 'created_by')) {
                $table->unsignedBigInteger('created_by')
                    ->nullable()
                    ->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('election_periods', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('election_periods', 'description')) {
                $columns[] = 'description';
            }

            if (Schema::hasColumn('election_periods', 'start_date')) {
                $columns[] = 'start_date';
            }

            if (Schema::hasColumn('election_periods', 'end_date')) {
                $columns[] = 'end_date';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
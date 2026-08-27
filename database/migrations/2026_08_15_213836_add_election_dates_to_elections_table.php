<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elections', function (Blueprint $table) {
            if (!Schema::hasColumn('elections', 'start_date')) {
                $table->dateTime('start_date')
                    ->nullable()
                    ->after('description');
            }

            if (!Schema::hasColumn('elections', 'end_date')) {
                $table->dateTime('end_date')
                    ->nullable()
                    ->after('start_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('elections', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('elections', 'start_date')) {
                $columns[] = 'start_date';
            }

            if (Schema::hasColumn('elections', 'end_date')) {
                $columns[] = 'end_date';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
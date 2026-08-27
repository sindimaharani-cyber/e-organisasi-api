<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elections', function (Blueprint $table) {
            if (!Schema::hasColumn('elections', 'result_published')) {
                $table->boolean('result_published')
                    ->default(false)
                    ->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('elections', function (Blueprint $table) {
            if (Schema::hasColumn('elections', 'result_published')) {
                $table->dropColumn('result_published');
            }
        });
    }
};
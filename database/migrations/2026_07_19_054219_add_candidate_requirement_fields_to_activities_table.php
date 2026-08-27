<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->boolean('is_candidate_requirement')
                ->default(false)
                ->after('status');

            $table->unsignedInteger('candidate_requirement_order')
                ->nullable()
                ->after('is_candidate_requirement');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn([
                'is_candidate_requirement',
                'candidate_requirement_order',
            ]);
        });
    }
};
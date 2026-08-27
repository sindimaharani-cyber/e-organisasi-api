<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('organization_profiles', 'photo_path')) {
                $table->string('photo_path')
                    ->nullable();
            }

            if (!Schema::hasColumn('organization_profiles', 'history')) {
                $table->text('history')
                    ->nullable();
            }

            if (!Schema::hasColumn('organization_profiles', 'vision')) {
                $table->text('vision')
                    ->nullable();
            }

            if (!Schema::hasColumn('organization_profiles', 'mission')) {
                $table->text('mission')
                    ->nullable();
            }

            if (!Schema::hasColumn('organization_profiles', 'goals')) {
                $table->text('goals')
                    ->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('organization_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'photo_path',
                'history',
                'vision',
                'mission',
                'goals',
            ]);
        });
    }
};
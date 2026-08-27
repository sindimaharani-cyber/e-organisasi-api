<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_registrations')) {
            return;
        }

        Schema::create('activity_registrations', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('activity_id')
                ->constrained('activities')
                ->cascadeOnDelete();

            $table
                ->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table
                ->string('status', 30)
                ->default('registered');

            $table
                ->timestamp('registered_at')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'activity_id',
                'user_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'activity_registrations'
        );
    }
};
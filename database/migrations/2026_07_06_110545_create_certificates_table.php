<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('certificates')) {
            return;
        }

        Schema::create('certificates', function (Blueprint $table) {
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
                ->foreignId('issued_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table
                ->string('certificate_number', 100)
                ->unique();

            $table
                ->string('title', 200);

            $table
                ->text('description')
                ->nullable();

            $table
                ->string('file_path')
                ->nullable();

            $table
                ->timestamp('issued_at')
                ->nullable();

            $table
                ->string('status', 30)
                ->default('issued');

            $table->timestamps();

            $table->unique([
                'activity_id',
                'user_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
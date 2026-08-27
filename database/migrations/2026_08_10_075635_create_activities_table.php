<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activities')) {
            return;
        }

        Schema::create('activities', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title', 200);

            $table->text('description')->nullable();

            $table->date('activity_date');

            $table->time('start_time')->nullable();

            $table->time('end_time')->nullable();

            $table->string('location', 200)->nullable();

            $table
                ->string('target_role', 30)
                ->default('semua');

            $table
                ->unsignedInteger('quota')
                ->nullable();

            $table
                ->string('status', 30)
                ->default('perencanaan');

            $table
                ->boolean('registration_open')
                ->default(true);

            $table->timestamps();

            $table->index('activity_date');
            $table->index('status');
            $table->index('target_role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
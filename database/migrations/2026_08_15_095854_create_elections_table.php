<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title', 200);

            $table->text('description')
                ->nullable();

            $table->dateTime('start_date');

            $table->dateTime('end_date');

            $table->enum('status', [
                'draft',
                'open',
                'closed',
            ])->default('draft');

            $table->boolean('result_published')
                ->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections');
    }
};
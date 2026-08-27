<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'election_voters',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('election_id')
                    ->constrained('elections')
                    ->cascadeOnDelete();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->boolean('eligible')
                    ->default(true);

                $table->timestamps();

                $table->unique([
                    'election_id',
                    'user_id',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'election_voters'
        );
    }
};
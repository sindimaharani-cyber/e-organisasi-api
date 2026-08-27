<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('elections')) {
            Schema::create('elections', function (Blueprint $table) {
                $table->id();

                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('title', 200);
                $table->text('description')->nullable();

                $table->date('start_date');
                $table->date('end_date');

                $table->string('status', 30)
                    ->default('draft');

                $table->timestamps();
            });

            return;
        }

        Schema::table('elections', function (Blueprint $table) {
            if (!Schema::hasColumn('elections', 'created_by')) {
                $table->unsignedBigInteger('created_by')
                    ->nullable();
            }

            if (!Schema::hasColumn('elections', 'title')) {
                $table->string('title', 200)
                    ->nullable();
            }

            if (!Schema::hasColumn('elections', 'description')) {
                $table->text('description')
                    ->nullable();
            }

            if (!Schema::hasColumn('elections', 'start_date')) {
                $table->date('start_date')
                    ->nullable();
            }

            if (!Schema::hasColumn('elections', 'end_date')) {
                $table->date('end_date')
                    ->nullable();
            }

            if (!Schema::hasColumn('elections', 'status')) {
                $table->string('status', 30)
                    ->default('draft');
            }
        });
    }

    public function down(): void
    {
        // Sengaja tidak drop tabel agar data tidak terhapus.
    }
};
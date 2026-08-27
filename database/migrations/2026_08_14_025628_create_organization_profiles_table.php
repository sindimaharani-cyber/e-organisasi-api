<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('organization_profiles')) {
            Schema::create('organization_profiles', function (Blueprint $table) {
                $table->id();

                $table->string('name', 150);

                $table->text('description')
                    ->nullable();

                $table->text('history')
                    ->nullable();

                $table->text('vision')
                    ->nullable();

                $table->text('mission')
                    ->nullable();

                $table->text('goals')
                    ->nullable();

                $table->string('photo_path')
                    ->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_profiles');
    }
};
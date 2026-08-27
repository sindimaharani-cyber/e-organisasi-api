<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_officers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')
                ->constrained('members')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('position', 100);
            $table->string('division', 100)->nullable();
            $table->string('management_period', 30);

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->enum('status', [
                'active',
                'inactive',
            ])->default('active');

            $table->timestamps();

            $table->index([
                'management_period',
                'status',
            ]);

            $table->unique([
                'member_id',
                'management_period',
            ], 'officer_member_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_officers');
    }
};
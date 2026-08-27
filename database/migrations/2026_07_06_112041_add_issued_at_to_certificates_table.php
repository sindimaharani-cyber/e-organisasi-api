<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            if (!Schema::hasColumn('certificates', 'certificate_file')) {
                $table->string('certificate_file')->nullable();
            }

            if (!Schema::hasColumn('certificates', 'status')) {
                $table->string('status')->default('available');
            }

            if (!Schema::hasColumn('certificates', 'issued_at')) {
                $table->timestamp('issued_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            if (Schema::hasColumn('certificates', 'issued_at')) {
                $table->dropColumn('issued_at');
            }

            if (Schema::hasColumn('certificates', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('certificates', 'certificate_file')) {
                $table->dropColumn('certificate_file');
            }
        });
    }
};
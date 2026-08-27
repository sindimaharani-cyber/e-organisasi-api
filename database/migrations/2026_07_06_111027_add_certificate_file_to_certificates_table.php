<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {

            if (!Schema::hasColumn('certificates', 'issued_by')) {
                $table
                    ->foreignId('issued_by')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('certificates', 'certificate_number')) {
                $table
                    ->string('certificate_number', 100)
                    ->nullable()
                    ->unique()
                    ->after('issued_by');
            }

            if (!Schema::hasColumn('certificates', 'title')) {
                $table
                    ->string('title', 200)
                    ->nullable()
                    ->after('certificate_number');
            }

            if (!Schema::hasColumn('certificates', 'description')) {
                $table
                    ->text('description')
                    ->nullable()
                    ->after('title');
            }

            if (!Schema::hasColumn('certificates', 'file_path')) {
                $table
                    ->string('file_path')
                    ->nullable()
                    ->after('description');
            }

            if (!Schema::hasColumn('certificates', 'issued_at')) {
                $table
                    ->timestamp('issued_at')
                    ->nullable()
                    ->after('file_path');
            }

            if (!Schema::hasColumn('certificates', 'status')) {
                $table
                    ->string('status', 30)
                    ->default('issued')
                    ->after('issued_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {

            if (Schema::hasColumn('certificates', 'issued_by')) {
                $table->dropForeign(['issued_by']);
                $table->dropColumn('issued_by');
            }

            if (Schema::hasColumn('certificates', 'certificate_number')) {
                $table->dropColumn('certificate_number');
            }

            if (Schema::hasColumn('certificates', 'title')) {
                $table->dropColumn('title');
            }

            if (Schema::hasColumn('certificates', 'description')) {
                $table->dropColumn('description');
            }

            if (Schema::hasColumn('certificates', 'file_path')) {
                $table->dropColumn('file_path');
            }

            if (Schema::hasColumn('certificates', 'issued_at')) {
                $table->dropColumn('issued_at');
            }

            if (Schema::hasColumn('certificates', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
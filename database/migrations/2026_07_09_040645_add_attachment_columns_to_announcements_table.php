<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (!Schema::hasColumn('announcements', 'file_path')) {
                $table->string('file_path')->nullable()->after('published_at');
            }

            if (!Schema::hasColumn('announcements', 'file_name')) {
                $table->string('file_name')->nullable()->after('file_path');
            }

            if (!Schema::hasColumn('announcements', 'file_type')) {
                $table->string('file_type', 30)->nullable()->after('file_name');
            }

            if (!Schema::hasColumn('announcements', 'file_size')) {
                $table->unsignedBigInteger('file_size')->nullable()->after('file_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (Schema::hasColumn('announcements', 'file_size')) {
                $table->dropColumn('file_size');
            }

            if (Schema::hasColumn('announcements', 'file_type')) {
                $table->dropColumn('file_type');
            }

            if (Schema::hasColumn('announcements', 'file_name')) {
                $table->dropColumn('file_name');
            }

            if (Schema::hasColumn('announcements', 'file_path')) {
                $table->dropColumn('file_path');
            }
        });
    }
};
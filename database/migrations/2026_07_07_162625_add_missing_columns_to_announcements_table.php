<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (!Schema::hasColumn('announcements', 'category')) {
                $table->string('category', 50)->default('Informasi')->after('content');
            }

            if (!Schema::hasColumn('announcements', 'author')) {
                $table->string('author', 100)->default('Admin HIMATIF')->after('category');
            }

            if (!Schema::hasColumn('announcements', 'archived')) {
                $table->boolean('archived')->default(false)->after('author');
            }

            if (!Schema::hasColumn('announcements', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (Schema::hasColumn('announcements', 'archived')) {
                $table->dropColumn('archived');
            }

            if (Schema::hasColumn('announcements', 'author')) {
                $table->dropColumn('author');
            }

            if (Schema::hasColumn('announcements', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
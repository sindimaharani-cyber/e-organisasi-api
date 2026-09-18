<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (!Schema::hasColumn('activities', 'certificate_template_path')) {
                $table->string('certificate_template_path')->nullable()->after('poster');
            }

            if (!Schema::hasColumn('activities', 'certificate_name_x')) {
                $table->integer('certificate_name_x')->default(400)->after('certificate_template_path');
            }

            if (!Schema::hasColumn('activities', 'certificate_name_y')) {
                $table->integer('certificate_name_y')->default(300)->after('certificate_name_x');
            }

            if (!Schema::hasColumn('activities', 'certificate_font_size')) {
                $table->integer('certificate_font_size')->default(24)->after('certificate_name_y');
            }

            if (!Schema::hasColumn('activities', 'certificate_font_color')) {
                $table->string('certificate_font_color', 20)->default('#000000')->after('certificate_font_size');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $columns = [
                'certificate_template_path',
                'certificate_name_x',
                'certificate_name_y',
                'certificate_font_size',
                'certificate_font_color',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('activities', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | ISSUED BY
        |--------------------------------------------------------------------------
        */

        if (!Schema::hasColumn('certificates', 'issued_by')) {
            Schema::table('certificates', function (Blueprint $table) {
                $table
                    ->unsignedBigInteger('issued_by')
                    ->nullable()
                    ->after('user_id');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | CERTIFICATE NUMBER
        |--------------------------------------------------------------------------
        */

        if (!Schema::hasColumn('certificates', 'certificate_number')) {
            Schema::table('certificates', function (Blueprint $table) {
                $table
                    ->string('certificate_number', 100)
                    ->nullable()
                    ->after('issued_by');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | TITLE
        |--------------------------------------------------------------------------
        */

        if (!Schema::hasColumn('certificates', 'title')) {
            Schema::table('certificates', function (Blueprint $table) {
                $table
                    ->string('title', 200)
                    ->nullable()
                    ->after('certificate_number');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | DESCRIPTION
        |--------------------------------------------------------------------------
        */

        if (!Schema::hasColumn('certificates', 'description')) {
            Schema::table('certificates', function (Blueprint $table) {
                $table
                    ->text('description')
                    ->nullable()
                    ->after('title');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | FILE PATH
        |--------------------------------------------------------------------------
        |
        | Menyimpan:
        | certificates/sertifikat-HIMATIF-XXXXX.png
        |
        */

        if (!Schema::hasColumn('certificates', 'file_path')) {
            Schema::table('certificates', function (Blueprint $table) {
                $table
                    ->string('file_path', 255)
                    ->nullable()
                    ->after('description');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | ISSUED AT
        |--------------------------------------------------------------------------
        */

        if (!Schema::hasColumn('certificates', 'issued_at')) {
            Schema::table('certificates', function (Blueprint $table) {
                $table
                    ->timestamp('issued_at')
                    ->nullable()
                    ->after('file_path');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        if (!Schema::hasColumn('certificates', 'status')) {
            Schema::table('certificates', function (Blueprint $table) {
                $table
                    ->string('status', 30)
                    ->default('issued')
                    ->after('issued_at');
            });
        }
    }

    public function down(): void
    {
        /*
         * Tidak menghapus kolom pada rollback untuk mencegah
         * hilangnya data sertifikat yang sudah diterbitkan.
         */
    }
};
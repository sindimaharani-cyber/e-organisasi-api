<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | certificates.member_id
        |--------------------------------------------------------------------------
        |
        | Sistem lama menggunakan member_id.
        | Sistem baru menggunakan user_id.
        |
        | Agar insert sertifikat baru tidak gagal, member_id dibuat nullable.
        |
        */

        if (Schema::hasColumn('certificates', 'member_id')) {
            DB::statement(
                'ALTER TABLE certificates MODIFY member_id BIGINT UNSIGNED NULL'
            );
        }
    }

    public function down(): void
    {
        /*
         * Jangan dikembalikan ke NOT NULL karena record baru
         * mungkin memang tidak mempunyai member_id.
         */
    }
};
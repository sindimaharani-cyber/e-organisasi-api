<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Samakan data role lama.
         *
         * ketua/pengurus -> pengurus
         * anggota        -> mahasiswa
         */
        DB::table('users')
            ->whereIn('role', ['ketua', 'pengurus'])
            ->update([
                'role' => 'pengurus',
            ]);

        DB::table('users')
            ->where('role', 'anggota')
            ->update([
                'role' => 'mahasiswa',
            ]);

        /*
         * Ubah ENUM menjadi tiga role final.
         */
        DB::statement("
            ALTER TABLE users
            MODIFY role ENUM(
                'admin',
                'pengurus',
                'mahasiswa'
            ) NOT NULL DEFAULT 'mahasiswa'
        ");

        /*
         * Pastikan status akun konsisten.
         */
        DB::statement("
            ALTER TABLE users
            MODIFY status ENUM(
                'active',
                'inactive',
                'suspended'
            ) NOT NULL DEFAULT 'active'
        ");
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'mahasiswa')
            ->update([
                'role' => 'anggota',
            ]);

        DB::table('users')
            ->where('role', 'pengurus')
            ->update([
                'role' => 'ketua',
            ]);

        DB::statement("
            ALTER TABLE users
            MODIFY role ENUM(
                'admin',
                'ketua',
                'anggota'
            ) NOT NULL DEFAULT 'anggota'
        ");

        DB::statement("
            ALTER TABLE users
            MODIFY status ENUM(
                'active',
                'inactive'
            ) NOT NULL DEFAULT 'active'
        ");
    }
};
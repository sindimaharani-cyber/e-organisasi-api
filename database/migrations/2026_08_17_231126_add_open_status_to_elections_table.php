<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE elections
            MODIFY COLUMN status
            ENUM(
                'draft',
                'registration',
                'verification',
                'voting',
                'open',
                'closed'
            )
            NOT NULL
            DEFAULT 'draft'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /*
         * Jika migration di-rollback dan ada data
         * berstatus open, ubah dulu menjadi voting
         * agar ENUM lama dapat dipasang kembali.
         */
        DB::table('elections')
            ->where('status', 'open')
            ->update([
                'status' => 'voting',
            ]);

        DB::statement("
            ALTER TABLE elections
            MODIFY COLUMN status
            ENUM(
                'draft',
                'registration',
                'verification',
                'voting',
                'closed'
            )
            NOT NULL
            DEFAULT 'draft'
        ");
    }
};
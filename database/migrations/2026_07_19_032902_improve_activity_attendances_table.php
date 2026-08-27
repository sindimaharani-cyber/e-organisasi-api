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
         * Bersihkan status lama apabila ada nilai di luar aturan baru.
         */
        DB::table('activity_attendances')
            ->whereNotIn('attendance_status', [
                'present',
                'absent',
                'permission',
            ])
            ->update([
                'attendance_status' => 'present',
            ]);

        DB::statement("
            ALTER TABLE activity_attendances
            MODIFY attendance_status ENUM(
                'present',
                'absent',
                'permission'
            ) NOT NULL DEFAULT 'present'
        ");

        Schema::table('activity_attendances', function (Blueprint $table) {
            if (!Schema::hasColumn(
                'activity_attendances',
                'verified_by'
            )) {
                $table->unsignedBigInteger('verified_by')
                    ->nullable()
                    ->after('attended_at');

                $table->foreign('verified_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn(
                'activity_attendances',
                'notes'
            )) {
                $table->string('notes', 255)
                    ->nullable()
                    ->after('verified_by');
            }
        });

        /*
         * Mencegah satu mahasiswa memiliki dua absensi
         * pada kegiatan yang sama.
         */
        Schema::table('activity_attendances', function (Blueprint $table) {
            $table->unique(
                ['activity_id', 'user_id'],
                'attendance_activity_user_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('activity_attendances', function (Blueprint $table) {
            $table->dropUnique(
                'attendance_activity_user_unique'
            );

            if (Schema::hasColumn(
                'activity_attendances',
                'verified_by'
            )) {
                $table->dropForeign([
                    'verified_by',
                ]);
            }

            $table->dropColumn([
                'verified_by',
                'notes',
            ]);
        });

        DB::statement("
            ALTER TABLE activity_attendances
            MODIFY attendance_status VARCHAR(255)
            NOT NULL DEFAULT 'present'
        ");
    }
};
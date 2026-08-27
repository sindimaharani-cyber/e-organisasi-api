<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'candidate_applications',
            function (Blueprint $table) {
                $table->id();

                /*
                |--------------------------------------------------------------------------
                | PEMILIHAN
                |--------------------------------------------------------------------------
                */

                $table->foreignId('election_id')
                    ->constrained('elections')
                    ->cascadeOnDelete();

                /*
                |--------------------------------------------------------------------------
                | USER YANG MENDAFTAR
                |--------------------------------------------------------------------------
                */

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                /*
                |--------------------------------------------------------------------------
                | DATA MEMBER
                |--------------------------------------------------------------------------
                */

                $table->foreignId('member_id')
                    ->constrained('members')
                    ->cascadeOnDelete();

                /*
                |--------------------------------------------------------------------------
                | VISI MISI
                |--------------------------------------------------------------------------
                */

                $table->text('vision');

                $table->text('mission');

                /*
                |--------------------------------------------------------------------------
                | FOTO
                |--------------------------------------------------------------------------
                */

                $table->string('photo_path')
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | STATUS
                |--------------------------------------------------------------------------
                |
                | pending  = menunggu verifikasi
                | approved = diterima
                | rejected = ditolak
                |
                */

                $table->string(
                    'status',
                    20
                )->default('pending');

                /*
                |--------------------------------------------------------------------------
                | ALASAN PENOLAKAN
                |--------------------------------------------------------------------------
                */

                $table->text(
                    'rejection_reason'
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | ADMIN/PENGURUS YANG MEMERIKSA
                |--------------------------------------------------------------------------
                */

                $table->foreignId(
                    'reviewed_by'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp(
                    'reviewed_at'
                )->nullable();

                $table->timestamps();

                /*
                |--------------------------------------------------------------------------
                | SATU USER SATU PENGAJUAN PER PEMILIHAN
                |--------------------------------------------------------------------------
                */

                $table->unique([
                    'election_id',
                    'user_id',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'candidate_applications'
        );
    }
};
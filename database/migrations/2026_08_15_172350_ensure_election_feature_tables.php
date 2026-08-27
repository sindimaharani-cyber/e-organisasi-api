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
        | ELECTION PERIODS
        |--------------------------------------------------------------------------
        */

        if (!Schema::hasTable('election_periods')) {
            Schema::create('election_periods', function (Blueprint $table) {
                $table->id();

                $table->string('title', 150);

                $table->text('description')
                    ->nullable();

                $table->dateTime('voting_start');

                $table->dateTime('voting_end');

                $table->enum('status', [
                    'draft',
                    'active',
                    'finished',
                    'cancelled',
                ])->default('draft');

                $table->boolean('result_published')
                    ->default(false);

                $table->unsignedBigInteger('created_by')
                    ->nullable();

                $table->timestamps();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | ELECTION CANDIDATES
        |--------------------------------------------------------------------------
        */

        if (!Schema::hasTable('election_candidates')) {
            Schema::create('election_candidates', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('election_id');

                $table->unsignedBigInteger('member_id');

                $table->unsignedInteger('candidate_number');

                $table->text('vision')
                    ->nullable();

                $table->text('mission')
                    ->nullable();

                $table->string('photo_path')
                    ->nullable();

                $table->enum('status', [
                    'active',
                    'inactive',
                ])->default('active');

                $table->timestamps();

                $table->unique([
                    'election_id',
                    'member_id',
                ]);

                $table->unique([
                    'election_id',
                    'candidate_number',
                ]);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | ELIGIBLE VOTERS
        |--------------------------------------------------------------------------
        */

        if (!Schema::hasTable('eligible_voters')) {
            Schema::create('eligible_voters', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('election_id');

                $table->unsignedBigInteger('member_id');

                $table->enum('eligibility_status', [
                    'eligible',
                    'not_eligible',
                ])->default('eligible');

                $table->timestamp('verified_at')
                    ->nullable();

                $table->timestamps();

                $table->unique([
                    'election_id',
                    'member_id',
                ]);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | VOTES
        |--------------------------------------------------------------------------
        */

        if (!Schema::hasTable('votes')) {
            Schema::create('votes', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('election_id');

                $table->unsignedBigInteger('candidate_id');

                $table->unsignedBigInteger('voter_member_id');

                $table->dateTime('voted_at')
                    ->nullable();

                $table->timestamps();

                /*
                 * PENTING:
                 * satu anggota hanya boleh memilih satu kali
                 * pada satu periode pemilihan.
                 */
                $table->unique([
                    'election_id',
                    'voter_member_id',
                ]);
            });
        }
    }

    public function down(): void
    {
        /*
         * Sengaja tidak drop tabel karena sebagian tabel
         * kemungkinan sudah berisi data.
         */
    }
};
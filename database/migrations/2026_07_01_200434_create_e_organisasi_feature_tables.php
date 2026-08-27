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
        | USERS - Login dan Hak Akses
        |--------------------------------------------------------------------------
        */
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['admin', 'ketua', 'pengurus', 'anggota'])
                    ->default('anggota')
                    ->after('password');
            }

            if (!Schema::hasColumn('users', 'status')) {
                $table->enum('status', ['active', 'inactive'])
                    ->default('active')
                    ->after('role');
            }
        });

        /*
        |--------------------------------------------------------------------------
        | PROFIL ORGANISASI
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('organization_profiles')) {
            Schema::create('organization_profiles', function (Blueprint $table) {
                $table->id();
                $table->string('organization_name', 150);
                $table->string('short_name', 50)->nullable();
                $table->string('logo')->nullable();
                $table->text('description')->nullable();
                $table->string('vision')->nullable();
                $table->text('mission')->nullable();
                $table->string('address')->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('instagram')->nullable();
                $table->timestamps();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | DATA ANGGOTA
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('members')) {
            Schema::create('members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 150);
                $table->string('nim', 50)->nullable()->unique();
                $table->string('email')->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('study_program', 100)->default('Teknik Informatika');
                $table->string('generation', 20)->nullable();
                $table->string('position', 100)->nullable();
                $table->enum('member_status', ['active', 'inactive', 'alumni'])->default('active');
                $table->timestamps();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | STRUKTUR ORGANISASI
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('organization_structures')) {
            Schema::create('organization_structures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
                $table->string('position_name', 150);
                $table->string('division', 150)->nullable();
                $table->string('period', 50)->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | PENGUMUMAN
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title', 200);
                $table->text('content');
                $table->enum('status', ['draft', 'published'])->default('published');
                $table->dateTime('published_at')->nullable();
                $table->timestamps();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | KEGIATAN DAN KALENDER KEGIATAN
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('activities')) {
            Schema::create('activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->dateTime('start_datetime');
                $table->dateTime('end_datetime')->nullable();
                $table->string('location', 200)->nullable();
                $table->integer('quota')->nullable();
                $table->enum('status', ['perencanaan', 'berjalan', 'selesai', 'dibatalkan'])
                    ->default('perencanaan');
                $table->timestamps();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | PENDAFTARAN KEGIATAN
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('activity_registrations')) {
            Schema::create('activity_registrations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
                $table->enum('registration_status', ['registered', 'cancelled'])->default('registered');
                $table->dateTime('registered_at')->nullable();
                $table->timestamps();

                $table->unique(['activity_id', 'member_id']);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | ABSENSI KEGIATAN
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('attendances')) {
            Schema::create('attendances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
                $table->dateTime('attendance_time')->nullable();
                $table->enum('attendance_status', ['present', 'absent', 'late'])->default('present');
                $table->string('proof')->nullable();
                $table->timestamps();

                $table->unique(['activity_id', 'member_id']);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | SERTIFIKAT DIGITAL
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('certificates')) {
            Schema::create('certificates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
                $table->string('certificate_number', 100)->nullable()->unique();
                $table->string('file_path')->nullable();
                $table->date('issued_date')->nullable();
                $table->timestamps();

                $table->unique(['activity_id', 'member_id']);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | DOKUMENTASI DAN ARSIP
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('archives')) {
            Schema::create('archives', function (Blueprint $table) {
                $table->id();
                $table->foreignId('activity_id')->nullable()->constrained('activities')->nullOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('file_path')->nullable();
                $table->enum('archive_type', ['document', 'photo', 'video', 'other'])->default('document');
                $table->timestamps();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | KAS ORGANISASI
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('cash_transactions')) {
            Schema::create('cash_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('type', ['in', 'out']);
                $table->integer('amount');
                $table->string('description', 200)->nullable();
                $table->date('transaction_date');
                $table->timestamps();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | JOBDESK / TUGAS PENGURUS
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('jobdesks')) {
            Schema::create('jobdesks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assigned_to')->nullable()->constrained('members')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->date('deadline')->nullable();
                $table->enum('status', ['belum', 'proses', 'selesai'])->default('belum');
                $table->timestamps();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | PEMILIHAN KETUA HIMPUNAN
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('elections')) {
            Schema::create('elections', function (Blueprint $table) {
                $table->id();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->dateTime('registration_start')->nullable();
                $table->dateTime('registration_end')->nullable();
                $table->dateTime('voting_start')->nullable();
                $table->dateTime('voting_end')->nullable();
                $table->decimal('minimum_gpa', 3, 2)->default(3.00);
                $table->enum('status', ['draft', 'registration', 'verification', 'voting', 'closed'])
                    ->default('draft');
                $table->timestamps();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | KANDIDAT PEMILIHAN
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('candidates')) {
            Schema::create('candidates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('election_id')->constrained('elections')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
                $table->decimal('gpa', 3, 2)->nullable();
                $table->text('vision')->nullable();
                $table->text('mission')->nullable();
                $table->enum('verification_status', ['pending', 'approved', 'rejected'])
                    ->default('pending');
                $table->text('verification_note')->nullable();
                $table->timestamps();

                $table->unique(['election_id', 'member_id']);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | BERKAS KANDIDAT
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('candidate_documents')) {
            Schema::create('candidate_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
                $table->string('document_name', 150);
                $table->string('file_path');
                $table->enum('status', ['pending', 'valid', 'invalid'])->default('pending');
                $table->timestamps();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | VOTING ONLINE
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('votes')) {
            Schema::create('votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('election_id')->constrained('elections')->cascadeOnDelete();
                $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
                $table->foreignId('voter_member_id')->constrained('members')->cascadeOnDelete();
                $table->dateTime('voted_at')->nullable();
                $table->timestamps();

                $table->unique(['election_id', 'voter_member_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
        Schema::dropIfExists('candidate_documents');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('elections');
        Schema::dropIfExists('jobdesks');
        Schema::dropIfExists('cash_transactions');
        Schema::dropIfExists('archives');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('activity_registrations');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('organization_structures');
        Schema::dropIfExists('members');
        Schema::dropIfExists('organization_profiles');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
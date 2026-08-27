<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EOrganisasiSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        DB::table('votes')->truncate();
        DB::table('candidate_documents')->truncate();
        DB::table('candidates')->truncate();
        DB::table('elections')->truncate();
        DB::table('jobdesks')->truncate();
        DB::table('cash_transactions')->truncate();
        DB::table('archives')->truncate();
        DB::table('certificates')->truncate();
        DB::table('attendances')->truncate();
        DB::table('activity_registrations')->truncate();
        DB::table('activities')->truncate();
        DB::table('announcements')->truncate();
        DB::table('organization_structures')->truncate();
        DB::table('members')->truncate();
        DB::table('organization_profiles')->truncate();
        DB::table('users')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $adminId = DB::table('users')->insertGetId([
            'name' => 'Sindi Maharani',
            'email' => 'admin@himatifuir.ac.id',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ketuaId = DB::table('users')->insertGetId([
            'name' => 'Rizky Ramadhan',
            'email' => 'ketua@himatifuir.ac.id',
            'password' => Hash::make('password'),
            'role' => 'ketua',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('organization_profiles')->insert([
            'organization_name' => 'Himpunan Mahasiswa Teknik Informatika Universitas Islam Riau',
            'short_name' => 'HIMATIF UIR',
            'description' => 'HIMATIF UIR merupakan organisasi mahasiswa di lingkungan Program Studi Teknik Informatika Universitas Islam Riau.',
            'vision' => 'Menjadi organisasi mahasiswa yang aktif, inovatif, dan berkontribusi dalam pengembangan potensi mahasiswa Teknik Informatika.',
            'mission' => 'Meningkatkan kualitas anggota, memperkuat solidaritas, serta mendukung kegiatan akademik dan nonakademik mahasiswa.',
            'address' => 'Universitas Islam Riau',
            'email' => 'himatif@uir.ac.id',
            'phone' => '081234567890',
            'instagram' => '@himatifuir',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $member1 = DB::table('members')->insertGetId([
            'user_id' => $adminId,
            'name' => 'Sindi Maharani',
            'nim' => '223510001',
            'email' => 'admin@himatifuir.ac.id',
            'phone' => '081234567801',
            'study_program' => 'Teknik Informatika',
            'generation' => '2022',
            'position' => 'Admin',
            'member_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $member2 = DB::table('members')->insertGetId([
            'user_id' => $ketuaId,
            'name' => 'Rizky Ramadhan',
            'nim' => '223510002',
            'email' => 'ketua@himatifuir.ac.id',
            'phone' => '081234567802',
            'study_program' => 'Teknik Informatika',
            'generation' => '2022',
            'position' => 'Ketua Himpunan',
            'member_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $member3 = DB::table('members')->insertGetId([
            'name' => 'Aldi Saputra',
            'nim' => '223510003',
            'email' => 'aldi@himatifuir.ac.id',
            'phone' => '081234567803',
            'study_program' => 'Teknik Informatika',
            'generation' => '2022',
            'position' => 'Sekretaris',
            'member_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $member4 = DB::table('members')->insertGetId([
            'name' => 'Rina Safitri',
            'nim' => '223510004',
            'email' => 'rina@himatifuir.ac.id',
            'phone' => '081234567804',
            'study_program' => 'Teknik Informatika',
            'generation' => '2022',
            'position' => 'Bendahara',
            'member_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $member5 = DB::table('members')->insertGetId([
            'name' => 'Dewi Anggraini',
            'nim' => '223510005',
            'email' => 'dewi@himatifuir.ac.id',
            'phone' => '081234567805',
            'study_program' => 'Teknik Informatika',
            'generation' => '2023',
            'position' => 'Anggota',
            'member_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $member6 = DB::table('members')->insertGetId([
            'name' => 'Fajar Maulana',
            'nim' => '223510006',
            'email' => 'fajar@himatifuir.ac.id',
            'phone' => '081234567806',
            'study_program' => 'Teknik Informatika',
            'generation' => '2023',
            'position' => 'Anggota',
            'member_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('organization_structures')->insert([
            [
                'member_id' => $member2,
                'position_name' => 'Ketua Himpunan',
                'division' => 'Pengurus Inti',
                'period' => '2026',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'member_id' => $member3,
                'position_name' => 'Sekretaris',
                'division' => 'Pengurus Inti',
                'period' => '2026',
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'member_id' => $member4,
                'position_name' => 'Bendahara',
                'division' => 'Pengurus Inti',
                'period' => '2026',
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('announcements')->insert([
            [
                'created_by' => $adminId,
                'title' => 'Informasi Rapat Pengurus',
                'content' => 'Seluruh pengurus HIMATIF UIR diharapkan hadir pada rapat koordinasi kegiatan.',
                'status' => 'published',
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'created_by' => $adminId,
                'title' => 'Pendaftaran Workshop Flutter',
                'content' => 'Pendaftaran Workshop Flutter Mobile telah dibuka untuk seluruh anggota HIMATIF UIR.',
                'status' => 'published',
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $activity1 = DB::table('activities')->insertGetId([
            'created_by' => $adminId,
            'title' => 'Workshop Flutter Mobile',
            'description' => 'Kegiatan pelatihan dasar pengembangan aplikasi mobile menggunakan Flutter.',
            'start_datetime' => '2026-02-10 09:00:00',
            'end_datetime' => '2026-02-10 15:00:00',
            'location' => 'Lab Komputer 1',
            'quota' => 50,
            'status' => 'perencanaan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $activity2 = DB::table('activities')->insertGetId([
            'created_by' => $adminId,
            'title' => 'Seminar AI & Machine Learning',
            'description' => 'Seminar pengenalan kecerdasan buatan dan machine learning bagi mahasiswa.',
            'start_datetime' => '2026-02-15 13:00:00',
            'end_datetime' => '2026-02-15 17:00:00',
            'location' => 'Auditorium Kampus',
            'quota' => 150,
            'status' => 'perencanaan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $activity3 = DB::table('activities')->insertGetId([
            'created_by' => $adminId,
            'title' => 'Rapat Pengurus HIMATIF',
            'description' => 'Rapat rutin pengurus untuk membahas program kerja organisasi.',
            'start_datetime' => '2026-02-20 10:00:00',
            'end_datetime' => '2026-02-20 12:00:00',
            'location' => 'Sekretariat HIMATIF',
            'quota' => 30,
            'status' => 'berjalan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('activity_registrations')->insert([
            [
                'activity_id' => $activity1,
                'member_id' => $member1,
                'registration_status' => 'registered',
                'registered_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'activity_id' => $activity1,
                'member_id' => $member2,
                'registration_status' => 'registered',
                'registered_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('attendances')->insert([
            [
                'activity_id' => $activity3,
                'member_id' => $member2,
                'attendance_time' => now(),
                'attendance_status' => 'present',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'activity_id' => $activity3,
                'member_id' => $member3,
                'attendance_time' => now(),
                'attendance_status' => 'present',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('cash_transactions')->insert([
            [
                'created_by' => $adminId,
                'type' => 'in',
                'amount' => 5000000,
                'description' => 'Saldo awal kas organisasi',
                'transaction_date' => '2026-02-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'created_by' => $adminId,
                'type' => 'out',
                'amount' => 1000000,
                'description' => 'Biaya perlengkapan kegiatan',
                'transaction_date' => '2026-02-05',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('jobdesks')->insert([
            [
                'assigned_to' => $member3,
                'created_by' => $adminId,
                'title' => 'Membuat proposal kegiatan',
                'description' => 'Menyusun proposal Workshop Flutter Mobile.',
                'deadline' => '2026-02-05',
                'status' => 'selesai',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'assigned_to' => $member4,
                'created_by' => $adminId,
                'title' => 'Menyusun anggaran kegiatan',
                'description' => 'Membuat rincian kebutuhan dana kegiatan.',
                'deadline' => '2026-02-07',
                'status' => 'proses',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'assigned_to' => $member5,
                'created_by' => $adminId,
                'title' => 'Membuat desain publikasi',
                'description' => 'Membuat poster kegiatan untuk media sosial.',
                'deadline' => '2026-02-08',
                'status' => 'belum',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'assigned_to' => $member6,
                'created_by' => $adminId,
                'title' => 'Menghubungi pemateri',
                'description' => 'Menghubungi narasumber kegiatan.',
                'deadline' => '2026-02-09',
                'status' => 'belum',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('certificates')->insert([
            [
                'activity_id' => $activity1,
                'member_id' => $member1,
                'certificate_number' => 'CERT-HIMATIF-001',
                'file_path' => 'certificates/cert-himatif-001.pdf',
                'issued_date' => '2026-02-11',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('archives')->insert([
            [
                'activity_id' => $activity1,
                'uploaded_by' => $adminId,
                'title' => 'Dokumentasi Workshop Flutter',
                'description' => 'Dokumentasi kegiatan Workshop Flutter Mobile.',
                'file_path' => 'archives/workshop-flutter.jpg',
                'archive_type' => 'photo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $electionId = DB::table('elections')->insertGetId([
            'title' => 'Pemilihan Ketua HIMATIF UIR Periode 2026/2027',
            'description' => 'Pemilihan Ketua Himpunan Mahasiswa Teknik Informatika Universitas Islam Riau.',
            'registration_start' => '2026-03-01 08:00:00',
            'registration_end' => '2026-03-07 23:59:00',
            'voting_start' => '2026-03-15 08:00:00',
            'voting_end' => '2026-03-15 18:00:00',
            'minimum_gpa' => 3.00,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $candidateId = DB::table('candidates')->insertGetId([
            'election_id' => $electionId,
            'member_id' => $member2,
            'gpa' => 3.65,
            'vision' => 'Mewujudkan HIMATIF UIR yang aktif, inovatif, dan kolaboratif.',
            'mission' => 'Mengembangkan program kerja yang bermanfaat bagi anggota dan lingkungan kampus.',
            'verification_status' => 'approved',
            'verification_note' => 'Berkas persyaratan lengkap dan valid.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('candidate_documents')->insert([
            [
                'candidate_id' => $candidateId,
                'document_name' => 'Transkrip Nilai',
                'file_path' => 'candidate_documents/transkrip-rizky.pdf',
                'status' => 'valid',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'candidate_id' => $candidateId,
                'document_name' => 'Surat Keterangan Mahasiswa Aktif',
                'file_path' => 'candidate_documents/surat-aktif-rizky.pdf',
                'status' => 'valid',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
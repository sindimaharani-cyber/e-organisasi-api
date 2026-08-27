<?php

namespace Database\Seeders;

use App\Models\Announcement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Announcement::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        Announcement::create([
            'created_by' => null,
            'title' => 'Rapat Koordinasi Pengurus',
            'content' => 'Rapat koordinasi pengurus HIMATIF akan dilaksanakan hari ini pukul 16.00 WIB di Ruang Rapat HIMATIF.',
            'category' => 'Rapat',
            'author' => 'Sindi Maharani',
            'archived' => false,
            'status' => 'published',
            'published_at' => now(),
        ]);

        Announcement::create([
            'created_by' => null,
            'title' => 'Seminar AI & Machine Learning',
            'content' => 'Pendaftaran Seminar AI & Machine Learning telah dibuka. Seluruh mahasiswa Teknik Informatika diharapkan dapat berpartisipasi.',
            'category' => 'Acara',
            'author' => 'Budi Santoso',
            'archived' => false,
            'status' => 'published',
            'published_at' => now(),
        ]);

        Announcement::create([
            'created_by' => null,
            'title' => 'Laporan Keuangan Januari',
            'content' => 'Laporan keuangan bulan Januari telah tersedia dan dapat dilihat pada menu Keuangan.',
            'category' => 'Keuangan',
            'author' => 'Siti Rahma',
            'archived' => false,
            'status' => 'published',
            'published_at' => now(),
        ]);

        Announcement::create([
            'created_by' => null,
            'title' => 'Maintenance Sistem',
            'content' => 'Sistem akan mengalami maintenance pada malam hari. Beberapa fitur mungkin tidak dapat diakses sementara.',
            'category' => 'Penting',
            'author' => 'Admin HIMATIF',
            'archived' => true,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
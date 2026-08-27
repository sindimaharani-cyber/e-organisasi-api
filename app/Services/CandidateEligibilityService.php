<?php

namespace App\Services;

use App\Models\Election;
use App\Models\Member;

class CandidateEligibilityService
{
    /**
     * Mengecek kelayakan anggota
     * untuk menjadi calon Bupati.
     */
    public function evaluate(
        Member $member,
        Election $election
    ): array {
        /*
        |--------------------------------------------------------------------------
        | TAHUN SEKARANG
        |--------------------------------------------------------------------------
        */

        $currentYear = (int) now()->year;

        /*
        |--------------------------------------------------------------------------
        | 1. CEK STATUS ANGGOTA
        |--------------------------------------------------------------------------
        |
        | Anggota harus berstatus:
        |
        | aktif
        | atau
        | active
        |
        */

        $memberStatus = strtolower(
            trim(
                (string) $member->member_status
            )
        );

        $active = in_array(
            $memberStatus,
            [
                'aktif',
                'active',
            ],
            true
        );

        /*
        |--------------------------------------------------------------------------
        | 2. CEK ANGKATAN
        |--------------------------------------------------------------------------
        |
        | Untuk versi MVP:
        |
        | mahasiswa minimal sudah 2 tahun
        | dan maksimal 4 tahun dari tahun angkatan.
        |
        | Contoh jika tahun sekarang 2026:
        |
        | Angkatan 2022 -> 4 tahun -> LAYAK
        | Angkatan 2023 -> 3 tahun -> LAYAK
        | Angkatan 2024 -> 2 tahun -> LAYAK
        | Angkatan 2025 -> 1 tahun -> TIDAK LAYAK
        |
        */

        $generation = null;

        if (
            $member->generation !== null &&
            is_numeric($member->generation)
        ) {
            $generation = (int) $member->generation;
        }

        $studyYear = null;

        if ($generation !== null) {
            $studyYear =
                $currentYear - $generation;
        }

        $generationEligible =
            $studyYear !== null &&
            $studyYear >= 2 &&
            $studyYear <= 4;

        /*
        |--------------------------------------------------------------------------
        | 3. CEK IPK
        |--------------------------------------------------------------------------
        |
        | minimum_gpa:
        | batas minimum IPK dari pemilihan.
        |
        | gpa:
        | IPK mahasiswa.
        |
        */

        $minimumGpa =
            $election->minimum_gpa !== null
                ? (float) $election->minimum_gpa
                : 3.00;

        $gpa =
            $member->gpa !== null
                ? (float) $member->gpa
                : null;

        $gpaEligible =
            $gpa !== null &&
            $gpa >= $minimumGpa;

        /*
        |--------------------------------------------------------------------------
        | 4. SERTIFIKAT
        |--------------------------------------------------------------------------
        |
        | Untuk sekarang belum menjadi syarat wajib
        | karena data kegiatan/sertifikat belum lengkap.
        |
        */

        $certificateRequirementEnabled =
            false;

        /*
        |--------------------------------------------------------------------------
        | 5. HASIL AKHIR
        |--------------------------------------------------------------------------
        |
        | Mahasiswa dinyatakan layak jika:
        |
        | - anggota aktif
        | - angkatan memenuhi
        | - IPK memenuhi
        |
        */

        $eligible =
            $active &&
            $generationEligible &&
            $gpaEligible;

        /*
        |--------------------------------------------------------------------------
        | DETAIL PERSYARATAN
        |--------------------------------------------------------------------------
        */

        $requirements = [
            [
                'key' =>
                    'member_status',

                'label' =>
                    'Status Anggota',

                'value' =>
                    $member->member_status,

                'required' =>
                    'Aktif',

                'passed' =>
                    $active,
            ],

            [
                'key' =>
                    'generation',

                'label' =>
                    'Angkatan',

                'value' =>
                    $generation,

                'required' =>
                    ($currentYear - 4)
                    . ' - '
                    . ($currentYear - 2),

                'passed' =>
                    $generationEligible,
            ],

            [
                'key' =>
                    'gpa',

                'label' =>
                    'IPK',

                'value' =>
                    $gpa,

                'required' =>
                    $minimumGpa,

                'passed' =>
                    $gpaEligible,
            ],

            [
                'key' =>
                    'certificate',

                'label' =>
                    'Sertifikat Kegiatan',

                'value' =>
                    null,

                'required' =>
                    null,

                /*
                 * Karena belum diberlakukan,
                 * dianggap tidak menghambat kelayakan.
                 */
                'passed' =>
                    true,

                'enabled' =>
                    $certificateRequirementEnabled,

                'message' =>
                    'Sertifikat kegiatan belum menjadi persyaratan pada versi saat ini.',
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | ALASAN TIDAK MEMENUHI SYARAT
        |--------------------------------------------------------------------------
        */

        $reasons = [];

        /*
         * Status anggota.
         */
        if (!$active) {
            $reasons[] =
                'Status keanggotaan belum aktif.';
        }

        /*
         * Angkatan.
         */
        if (!$generationEligible) {
            if ($generation === null) {
                $reasons[] =
                    'Data angkatan belum tersedia.';
            } else {
                $reasons[] =
                    'Angkatan tidak berada pada rentang yang memenuhi persyaratan.';
            }
        }

        /*
         * IPK.
         */
        if (!$gpaEligible) {
            if ($gpa === null) {
                $reasons[] =
                    'Data IPK belum tersedia.';
            } else {
                $reasons[] =
                    'IPK belum memenuhi batas minimum '
                    . number_format(
                        $minimumGpa,
                        2
                    )
                    . '.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return [
            /*
             * true / false
             */
            'eligible' =>
                $eligible,

            /*
             * status string.
             */
            'status' =>
                $eligible
                    ? 'eligible'
                    : 'not_eligible',

            /*
             * Data mahasiswa.
             */
            'member' => [
                'id' =>
                    $member->id,

                'user_id' =>
                    $member->user_id,

                'name' =>
                    $member->name,

                'nim' =>
                    $member->nim,

                'study_program' =>
                    $member->study_program,

                'generation' =>
                    $generation,

                'gpa' =>
                    $gpa,

                'member_status' =>
                    $member->member_status,
            ],

            /*
             * Data pemilihan.
             */
            'election' => [
                'id' =>
                    $election->id,

                'title' =>
                    $election->title,

                'status' =>
                    $election->status,

                'minimum_gpa' =>
                    $minimumGpa,
            ],

            /*
             * Detail hasil setiap syarat.
             */
            'requirements' =>
                $requirements,

            /*
             * Alasan tidak layak.
             */
            'reasons' =>
                $reasons,

            /*
             * Mahasiswa hanya boleh mengajukan
             * kandidat apabila:
             *
             * 1. memenuhi syarat
             * 2. pemilihan masih draft
             */
            'can_apply' =>
                $eligible &&
                strtolower(
                    trim(
                        (string) $election->status
                    )
                ) === 'draft',
        ];
    }
}
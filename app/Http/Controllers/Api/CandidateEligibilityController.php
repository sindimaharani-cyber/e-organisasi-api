<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\Member;
use App\Services\CandidateEligibilityService;
use Illuminate\Http\Request;

class CandidateEligibilityController extends Controller
{
    protected CandidateEligibilityService $eligibilityService;

    public function __construct(
        CandidateEligibilityService $eligibilityService
    ) {
        $this->eligibilityService = $eligibilityService;
    }

    /*
    |--------------------------------------------------------------------------
    | CEK KELAYAKAN MAHASISWA YANG SEDANG LOGIN
    |--------------------------------------------------------------------------
    |
    | Endpoint nanti:
    |
    | GET /api/student/elections/{electionId}/eligibility
    |
    */

    public function myEligibility(
        Request $request,
        int $electionId
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna belum login.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | CARI PEMILIHAN
        |--------------------------------------------------------------------------
        */

        $election = Election::query()
            ->find($electionId);

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' => 'Pemilihan tidak ditemukan.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | CARI DATA MEMBER
        |--------------------------------------------------------------------------
        |
        | Member dicari berdasarkan user_id akun yang login.
        |
        */

        $member = Member::query()
            ->where(
                'user_id',
                $user->id
            )
            ->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Data keanggotaan Anda tidak ditemukan.',

                'data' => [
                    'eligible' => false,
                    'can_apply' => false,
                    'reasons' => [
                        'Data keanggotaan tidak ditemukan.',
                    ],
                ],
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | PROSES SELEKSI SISTEM
        |--------------------------------------------------------------------------
        */

        $result =
            $this->eligibilityService
                ->evaluate(
                    $member,
                    $election
                );

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,

            'message' =>
                $result['eligible']
                    ? 'Anda memenuhi persyaratan sebagai calon Bupati.'
                    : 'Anda belum memenuhi persyaratan sebagai calon Bupati.',

            'data' => $result,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HASIL SELEKSI SEMUA ANGGOTA
    |--------------------------------------------------------------------------
    |
    | Untuk admin dan pengurus.
    |
    | Endpoint nanti:
    |
    | GET
    | /api/admin/elections/{electionId}/candidate-eligibility
    |
    | GET
    | /api/officer/elections/{electionId}/candidate-eligibility
    |
    */

    public function index(
        int $electionId
    ) {
        /*
        |--------------------------------------------------------------------------
        | CARI PEMILIHAN
        |--------------------------------------------------------------------------
        */

        $election = Election::query()
            ->find($electionId);

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' => 'Pemilihan tidak ditemukan.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | AMBIL SEMUA ANGGOTA
        |--------------------------------------------------------------------------
        */

        $members = Member::query()
            ->orderBy(
                'generation',
                'asc'
            )
            ->orderBy(
                'name',
                'asc'
            )
            ->get();

        $results = [];

        /*
        |--------------------------------------------------------------------------
        | CEK SATU PER SATU
        |--------------------------------------------------------------------------
        */

        foreach ($members as $member) {
            $results[] =
                $this->eligibilityService
                    ->evaluate(
                        $member,
                        $election
                    );
        }

        /*
        |--------------------------------------------------------------------------
        | HITUNG HASIL
        |--------------------------------------------------------------------------
        */

        $eligibleCount =
            collect($results)
                ->where(
                    'eligible',
                    true
                )
                ->count();

        $notEligibleCount =
            count($results)
            - $eligibleCount;

        /*
        |--------------------------------------------------------------------------
        | RESPONSE ADMIN/PENGURUS
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,

            'message' =>
                'Hasil seleksi kelayakan kandidat berhasil dimuat.',

            'data' => [
                /*
                 * Informasi pemilihan.
                 */
                'election' => [
                    'id' =>
                        $election->id,

                    'title' =>
                        $election->title,

                    'status' =>
                        $election->status,

                    'minimum_gpa' =>
                        $election->minimum_gpa
                            !== null
                            ? (float) $election->minimum_gpa
                            : 3.00,
                ],

                /*
                 * Ringkasan.
                 */
                'summary' => [
                    'total_members' =>
                        count($results),

                    'eligible' =>
                        $eligibleCount,

                    'not_eligible' =>
                        $notEligibleCount,
                ],

                /*
                 * Detail semua anggota.
                 */
                'members' =>
                    $results,
            ],
        ]);
    }
}
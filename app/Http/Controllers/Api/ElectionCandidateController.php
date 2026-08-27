<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\ElectionCandidate;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ElectionCandidateController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | DAFTAR KANDIDAT
    |--------------------------------------------------------------------------
    */

    public function index(
        int $electionId
    ): JsonResponse {
        $election =
            Election::query()
                ->find($electionId);

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pemilihan tidak ditemukan.',
            ], 404);
        }

        $candidates =
            ElectionCandidate::query()
                ->where(
                    'election_id',
                    $electionId
                )
                ->withCount('votes')
                ->orderBy(
                    'candidate_number'
                )
                ->get();

        return response()->json([
            'success' => true,
            'message' =>
                'Data kandidat berhasil diambil.',
            'data' => $candidates,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TAMBAH KANDIDAT
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request,
        int $electionId
    ): JsonResponse {
        $election =
            Election::query()
                ->find($electionId);

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pemilihan tidak ditemukan.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | HANYA SAAT DRAFT
        |--------------------------------------------------------------------------
        */

        if (
            strtolower(
                trim(
                    (string)
                    $election->status
                )
            ) !== 'draft'
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kandidat hanya dapat ditambahkan saat pemilihan masih dalam tahap persiapan.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDASI
        |--------------------------------------------------------------------------
        */

        $validated =
            $request->validate([
                'member_id' => [
                    'required',
                    'integer',
                    'exists:members,id',
                ],

                'candidate_number' => [
                    'required',
                    'integer',
                    'min:1',

                    Rule::unique(
                        'election_candidates',
                        'candidate_number'
                    )->where(
                        fn ($query) =>
                            $query->where(
                                'election_id',
                                $electionId
                            )
                    ),
                ],

                'vision' => [
                    'required',
                    'string',
                    'max:5000',
                ],

                'mission' => [
                    'required',
                    'string',
                    'max:5000',
                ],
            ]);

        /*
        |--------------------------------------------------------------------------
        | AMBIL MEMBER
        |--------------------------------------------------------------------------
        */

        $member =
            Member::query()
                ->find(
                    $validated[
                        'member_id'
                    ]
                );

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anggota tidak ditemukan.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | CEK MEMBER AKTIF
        |--------------------------------------------------------------------------
        */

        $memberStatus =
            strtolower(
                trim(
                    (string) (
                        $member
                            ->member_status
                        ?? ''
                    )
                )
            );

        if (
            $memberStatus !== '' &&
            !in_array(
                $memberStatus,
                [
                    'active',
                    'aktif',
                ],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Hanya anggota aktif yang dapat dijadikan kandidat.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CEK NIM
        |--------------------------------------------------------------------------
        */

        $nim =
            trim(
                (string) (
                    $member->nim
                    ?? ''
                )
            );

        if ($nim === '') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Data anggota belum memiliki NIM.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CEK SUDAH MENJADI KANDIDAT
        |--------------------------------------------------------------------------
        |
        | election_candidates belum mempunyai member_id.
        | Jadi sementara kita gunakan NIM sebagai identitas unik kandidat.
        |
        */

        $exists =
            ElectionCandidate::query()
                ->where(
                    'election_id',
                    $electionId
                )
                ->where(
                    'nim',
                    $nim
                )
                ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anggota tersebut sudah menjadi kandidat pada pemilihan ini.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | SIMPAN
        |--------------------------------------------------------------------------
        */

        $candidate =
            ElectionCandidate::query()
                ->create([
                    'election_id' =>
                        $electionId,

                    'candidate_number' =>
                        $validated[
                            'candidate_number'
                        ],

                    'name' =>
                        trim(
                            (string) (
                                $member->name
                                ?? 'Kandidat'
                            )
                        ),

                    'nim' =>
                        $nim,

                    'study_program' =>
                        $member->study_program
                        ? trim(
                            (string)
                            $member
                                ->study_program
                        )
                        : null,

                    'vision' =>
                        trim(
                            $validated[
                                'vision'
                            ]
                        ),

                    'mission' =>
                        trim(
                            $validated[
                                'mission'
                            ]
                        ),

                    'photo_path' =>
                        $member
                            ->profile_photo
                        ?: null,
                ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Kandidat berhasil ditambahkan.',
            'data' =>
                $candidate->fresh(),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE KANDIDAT
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        int $electionId,
        int $candidateId
    ): JsonResponse {
        $election =
            Election::query()
                ->find($electionId);

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pemilihan tidak ditemukan.',
            ], 404);
        }

        if (
            strtolower(
                trim(
                    (string)
                    $election->status
                )
            ) !== 'draft'
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kandidat tidak dapat diedit setelah voting dibuka.',
            ], 422);
        }

        $candidate =
            ElectionCandidate::query()
                ->where(
                    'election_id',
                    $electionId
                )
                ->where(
                    'id',
                    $candidateId
                )
                ->first();

        if (!$candidate) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kandidat tidak ditemukan.',
            ], 404);
        }

        $validated =
            $request->validate([
                'candidate_number' => [
                    'required',
                    'integer',
                    'min:1',

                    Rule::unique(
                        'election_candidates',
                        'candidate_number'
                    )
                        ->where(
                            fn ($query) =>
                                $query->where(
                                    'election_id',
                                    $electionId
                                )
                        )
                        ->ignore(
                            $candidateId
                        ),
                ],

                'vision' => [
                    'required',
                    'string',
                    'max:5000',
                ],

                'mission' => [
                    'required',
                    'string',
                    'max:5000',
                ],
            ]);

        $candidate->update([
            'candidate_number' =>
                $validated[
                    'candidate_number'
                ],

            'vision' =>
                trim(
                    $validated[
                        'vision'
                    ]
                ),

            'mission' =>
                trim(
                    $validated[
                        'mission'
                    ]
                ),
        ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Kandidat berhasil diperbarui.',
            'data' =>
                $candidate->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HAPUS KANDIDAT
    |--------------------------------------------------------------------------
    */

    public function destroy(
        int $electionId,
        int $candidateId
    ): JsonResponse {
        $election =
            Election::query()
                ->find($electionId);

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pemilihan tidak ditemukan.',
            ], 404);
        }

        if (
            strtolower(
                trim(
                    (string)
                    $election->status
                )
            ) !== 'draft'
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kandidat tidak dapat dihapus setelah voting dibuka.',
            ], 422);
        }

        $candidate =
            ElectionCandidate::query()
                ->where(
                    'election_id',
                    $electionId
                )
                ->where(
                    'id',
                    $candidateId
                )
                ->first();

        if (!$candidate) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kandidat tidak ditemukan.',
            ], 404);
        }

        if (
            $candidate
                ->votes()
                ->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kandidat sudah memiliki suara dan tidak dapat dihapus.',
            ], 422);
        }

        $candidate->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Kandidat berhasil dihapus.',
        ]);
    }
}
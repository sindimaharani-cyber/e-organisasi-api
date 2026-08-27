<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\ElectionCandidate;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateController extends Controller
{
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
                ->with(
                    'user:id,name,email'
                )
                ->orderBy(
                    'candidate_number'
                )
                ->get();

        return response()->json([
            'success' => true,
            'data' => $candidates,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DAFTAR USER YANG BISA DIJADIKAN KANDIDAT
    |--------------------------------------------------------------------------
    */

    public function eligibleMembers(
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

        $alreadyCandidateIds =
            ElectionCandidate::query()
                ->where(
                    'election_id',
                    $electionId
                )
                ->pluck(
                    'user_id'
                );

        $users =
            User::query()
                ->whereIn(
                    'role',
                    [
                        'mahasiswa',
                        'pengurus',
                    ]
                )
                ->where(
                    'status',
                    'active'
                )
                ->whereNotIn(
                    'id',
                    $alreadyCandidateIds
                )
                ->orderBy(
                    'name'
                )
                ->get([
                    'id',
                    'name',
                    'email',
                    'role',
                ]);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

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

        if ($election->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kandidat hanya dapat ditambahkan ketika pemilihan masih draft.',
            ], 422);
        }

        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'candidate_number' => [
                'required',
                'integer',
                'min:1',
            ],

            'vision' => [
                'required',
                'string',
            ],

            'mission' => [
                'required',
                'string',
            ],
        ]);

        $duplicateUser =
            ElectionCandidate::query()
                ->where(
                    'election_id',
                    $electionId
                )
                ->where(
                    'user_id',
                    $validated['user_id']
                )
                ->exists();

        if ($duplicateUser) {
            return response()->json([
                'success' => false,
                'message' =>
                    'User sudah terdaftar sebagai kandidat.',
            ], 422);
        }

        $duplicateNumber =
            ElectionCandidate::query()
                ->where(
                    'election_id',
                    $electionId
                )
                ->where(
                    'candidate_number',
                    $validated[
                        'candidate_number'
                    ]
                )
                ->exists();

        if ($duplicateNumber) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Nomor kandidat sudah digunakan.',
            ], 422);
        }

        $user =
            User::query()
                ->findOrFail(
                    $validated['user_id']
                );

        $nim = null;

        /*
         * Kalau User punya relasi member,
         * coba ambil NIM.
         */
        try {
            if (
                method_exists(
                    $user,
                    'member'
                ) &&
                $user->member
            ) {
                $nim =
                    $user->member->nim
                    ?? null;
            }
        } catch (\Throwable $e) {
            $nim = null;
        }

        $candidate =
            ElectionCandidate::query()
                ->create([
                    'election_id' =>
                        $electionId,

                    'user_id' =>
                        $user->id,

                    'candidate_number' =>
                        $validated[
                            'candidate_number'
                        ],

                    'name' =>
                        $user->name,

                    'nim' =>
                        $nim,

                    'vision' =>
                        trim(
                            $validated['vision']
                        ),

                    'mission' =>
                        trim(
                            $validated['mission']
                        ),

                    'status' =>
                        'active',
                ]);

        return response()->json([
            'success' => true,

            'message' =>
                'Kandidat berhasil ditambahkan.',

            'data' =>
                $candidate,
        ], 201);
    }

    public function update(
        Request $request,
        int $candidateId
    ): JsonResponse {
        $candidate =
            ElectionCandidate::query()
                ->find($candidateId);

        if (!$candidate) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kandidat tidak ditemukan.',
            ], 404);
        }

        if (
            $candidate->election->status
            !== 'draft'
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kandidat tidak dapat diedit setelah voting dibuka.',
            ], 422);
        }

        $validated = $request->validate([
            'candidate_number' => [
                'required',
                'integer',
                'min:1',
            ],

            'vision' => [
                'required',
                'string',
            ],

            'mission' => [
                'required',
                'string',
            ],

            'status' => [
                'nullable',
                'in:active,inactive',
            ],
        ]);

        $numberExists =
            ElectionCandidate::query()
                ->where(
                    'election_id',
                    $candidate->election_id
                )
                ->where(
                    'candidate_number',
                    $validated[
                        'candidate_number'
                    ]
                )
                ->where(
                    'id',
                    '!=',
                    $candidate->id
                )
                ->exists();

        if ($numberExists) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Nomor kandidat sudah digunakan.',
            ], 422);
        }

        $candidate->update([
            'candidate_number' =>
                $validated[
                    'candidate_number'
                ],

            'vision' =>
                trim(
                    $validated['vision']
                ),

            'mission' =>
                trim(
                    $validated['mission']
                ),

            'status' =>
                $validated['status']
                ?? $candidate->status,
        ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Kandidat berhasil diperbarui.',
            'data' =>
                $candidate->fresh(),
        ]);
    }

    public function destroy(
        int $candidateId
    ): JsonResponse {
        $candidate =
            ElectionCandidate::query()
                ->find($candidateId);

        if (!$candidate) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kandidat tidak ditemukan.',
            ], 404);
        }

        if (
            $candidate->election->status
            !== 'draft'
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kandidat tidak dapat dihapus setelah voting dibuka.',
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
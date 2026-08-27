<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\ElectionCandidate;
use App\Models\ElectionVote;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VotingController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | STATUS VOTING USER
    |--------------------------------------------------------------------------
    */

    public function status(
        Request $request,
        int $electionId
    ): JsonResponse {
        $election = Election::query()
            ->find($electionId);

        if (!$election) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Pemilihan tidak ditemukan.',
            ], 404);
        }

        $vote = ElectionVote::query()
            ->with(
                'candidate:id,candidate_number,name'
            )
            ->where(
                'election_id',
                $electionId
            )
            ->where(
                'user_id',
                $request->user()->id
            )
            ->first();

        return response()->json([
            'success' => true,

            'data' => [
                'has_voted' =>
                    $vote !== null,

                'vote' =>
                    $vote,

                'is_voting_open' =>
                    $election->is_voting_open,

                'phase' =>
                    $election->phase,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | VOTE
    |--------------------------------------------------------------------------
    */

    public function vote(
        Request $request,
        int $electionId
    ): JsonResponse {
        $request->validate([
            'candidate_id' => [
                'required',
                'integer',
            ],
        ]);

        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | ROLE HARUS MAHASISWA
        |--------------------------------------------------------------------------
        */

        if (
            strtolower(
                trim(
                    (string) $user->role
                )
            ) !== 'mahasiswa'
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Hanya mahasiswa yang dapat melakukan voting.',
            ], 403);
        }

        $election = Election::query()
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
        | CEK PEMILIHAN SUDAH DIPUBLISH
        |--------------------------------------------------------------------------
        */

        if ($election->status !== 'published') {
            return response()->json([
                'success' => false,

                'message' =>
                    'Pemilihan belum dipublikasikan.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CEK WAKTU
        |--------------------------------------------------------------------------
        */

        if (!$election->is_voting_open) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Voting belum dimulai atau sudah berakhir.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CEK FILTER PEMILIH
        |--------------------------------------------------------------------------
        */

        if (
            !$this->eligible(
                $user,
                $election
            )
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Anda tidak termasuk pemilih yang memenuhi syarat.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | CEK KANDIDAT
        |--------------------------------------------------------------------------
        */

        $candidate =
            ElectionCandidate::query()
                ->where(
                    'id',
                    $request->candidate_id
                )
                ->where(
                    'election_id',
                    $electionId
                )
                ->first();

        if (!$candidate) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Kandidat tidak ditemukan pada pemilihan ini.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CEK DOUBLE VOTE SEBELUM TRANSACTION
        |--------------------------------------------------------------------------
        */

        $alreadyVoted =
            ElectionVote::query()
                ->where(
                    'election_id',
                    $electionId
                )
                ->where(
                    'user_id',
                    $user->id
                )
                ->exists();

        if ($alreadyVoted) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Anda sudah memberikan suara pada pemilihan ini.',
            ], 409);
        }

        try {
            $vote = DB::transaction(
                function () use (
                    $electionId,
                    $candidate,
                    $user
                ) {
                    /*
                     * Lock untuk menekan race condition.
                     */

                    $existing =
                        ElectionVote::query()
                            ->where(
                                'election_id',
                                $electionId
                            )
                            ->where(
                                'user_id',
                                $user->id
                            )
                            ->lockForUpdate()
                            ->first();

                    if ($existing) {
                        return null;
                    }

                    return ElectionVote::query()
                        ->create([
                            'election_id' =>
                                $electionId,

                            'candidate_id' =>
                                $candidate->id,

                            'user_id' =>
                                $user->id,

                            'voted_at' =>
                                now(),
                        ]);
                }
            );

            if (!$vote) {
                return response()->json([
                    'success' => false,

                    'message' =>
                        'Anda sudah memberikan suara pada pemilihan ini.',
                ], 409);
            }

            return response()->json([
                'success' => true,

                'message' =>
                    'Suara Anda berhasil disimpan.',

                'data' => [
                    'vote_id' =>
                        $vote->id,

                    'voted_at' =>
                        $vote->voted_at,

                    'candidate' => [
                        'id' =>
                            $candidate->id,

                        'number' =>
                            $candidate
                                ->candidate_number,

                        'name' =>
                            $candidate->name,
                    ],
                ],
            ], 201);
        } catch (QueryException $error) {
            /*
             * Unique constraint tetap menjadi perlindungan terakhir
             * terhadap double vote.
             */

            if (
                in_array(
                    (string) $error->getCode(),
                    [
                        '23000',
                        '23505',
                    ],
                    true
                )
            ) {
                return response()->json([
                    'success' => false,

                    'message' =>
                        'Anda sudah memberikan suara pada pemilihan ini.',
                ], 409);
            }

            throw $error;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RESULTS
    |--------------------------------------------------------------------------
    */

    public function results(
        Request $request,
        int $electionId
    ): JsonResponse {
        $election = Election::query()
            ->withCount('votes')
            ->find($electionId);

        if (!$election) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Pemilihan tidak ditemukan.',
            ], 404);
        }

        $role = strtolower(
            trim(
                (string) (
                    $request->user()->role
                    ?? ''
                )
            )
        );

        $manager = in_array(
            $role,
            [
                'admin',
                'pengurus',
            ],
            true
        );

        /*
         * Mahasiswa tidak bisa melihat hasil jika dinonaktifkan.
         */

        if (
            !$manager &&
            !$election->show_results
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Hasil pemilihan belum dapat ditampilkan.',
            ], 403);
        }

        $totalVotes =
            $election->votes_count;

        $candidates =
            ElectionCandidate::query()
                ->where(
                    'election_id',
                    $electionId
                )
                ->withCount('votes')
                ->orderByDesc('votes_count')
                ->orderBy('candidate_number')
                ->get();

        $resultData =
            $candidates->map(
                function (
                    ElectionCandidate $candidate
                ) use ($totalVotes) {
                    $percentage =
                        $totalVotes > 0
                            ? round(
                                (
                                    $candidate
                                        ->votes_count
                                    /
                                    $totalVotes
                                )
                                * 100,
                                2
                            )
                            : 0;

                    return [
                        'candidate_id' =>
                            $candidate->id,

                        'candidate_number' =>
                            $candidate
                                ->candidate_number,

                        'name' =>
                            $candidate->name,

                        'nim' =>
                            $candidate->nim,

                        'photo_url' =>
                            $candidate->photo_url,

                        'votes' =>
                            $candidate
                                ->votes_count,

                        'percentage' =>
                            $percentage,
                    ];
                }
            );

        /*
        |--------------------------------------------------------------------------
        | KETUA TERPILIH
        |--------------------------------------------------------------------------
        */

        $winner = null;
        $isTie = false;

        if (
            $election->phase === 'completed' &&
            $totalVotes > 0 &&
            $resultData->isNotEmpty()
        ) {
            $highest =
                $resultData
                    ->first()['votes'];

            $topCandidates =
                $resultData
                    ->where(
                        'votes',
                        $highest
                    )
                    ->values();

            if ($topCandidates->count() === 1) {
                $winner =
                    $topCandidates->first();
            } else {
                $isTie = true;
            }
        }

        return response()->json([
            'success' => true,

            'message' =>
                'Hasil pemilihan berhasil diambil.',

            'data' => [
                'election' => [
                    'id' =>
                        $election->id,

                    'title' =>
                        $election->title,

                    'phase' =>
                        $election->phase,

                    'start_at' =>
                        $election->start_at,

                    'end_at' =>
                        $election->end_at,
                ],

                'total_votes' =>
                    $totalVotes,

                'candidates' =>
                    $resultData,

                'is_tie' =>
                    $isTie,

                'winner' =>
                    $winner,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | FILTER PEMILIH
    |--------------------------------------------------------------------------
    */

    private function eligible(
        $user,
        Election $election
    ): bool {
        if (
            $election->voter_scope
            === 'all_students'
        ) {
            return true;
        }

        $studyProgram =
            $user->study_program
            ?? $user->program_studi
            ?? data_get(
                $user,
                'member.study_program'
            )
            ?? data_get(
                $user,
                'member.program_studi'
            );

        if (!$studyProgram) {
            return false;
        }

        return strtolower(
            trim(
                (string) $studyProgram
            )
        ) === strtolower(
            trim(
                (string) $election->voter_value
            )
        );
    }
}
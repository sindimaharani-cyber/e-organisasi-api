<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\ElectionCandidate;
use App\Models\ElectionVote;
use App\Models\ElectionVoter;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ElectionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | DAFTAR PEMILIHAN
    |--------------------------------------------------------------------------
    */

    public function index(): JsonResponse
    {
        try {
            $elections = Election::query()
                ->withCount([
                    'candidates',
                    'voters',
                    'votes',
                ])
                ->orderByDesc('id')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data pemilihan berhasil diambil.',
                'data' => $elections,
            ]);
        } catch (\Throwable $error) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Gagal mengambil data pemilihan: '
                    . $error->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PEMILIHAN AKTIF
    |--------------------------------------------------------------------------
    */

    public function active(): JsonResponse
    {
        try {
            $elections = Election::query()
                ->where('status', 'open')
                ->withCount([
                    'candidates',
                    'voters',
                    'votes',
                ])
                ->orderByDesc('id')
                ->get();

            return response()->json([
                'success' => true,
                'message' =>
                    'Data pemilihan aktif berhasil diambil.',
                'data' => $elections,
            ]);
        } catch (\Throwable $error) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Gagal mengambil pemilihan aktif: '
                    . $error->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL PEMILIHAN
    |--------------------------------------------------------------------------
    */

    public function show(int $id): JsonResponse
    {
        $election = Election::query()
            ->withCount([
                'candidates',
                'voters',
                'votes',
            ])
            ->find($id);

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' => 'Pemilihan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Detail pemilihan berhasil diambil.',
            'data' => $election,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TAMBAH PEMILIHAN
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User belum login.',
            ], 401);
        }

        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:200',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'start_date' => [
                'required',
                'date',
            ],

            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],
        ]);

        $election = Election::query()->create([
            'created_by' =>
                $user->id,

            'title' =>
                trim(
                    $validated['title']
                ),

            'description' =>
                isset($validated['description'])
                    ? trim(
                        $validated['description']
                    )
                    : null,

            'start_date' =>
                $validated['start_date'],

            'end_date' =>
                $validated['end_date'],

            'registration_start' =>
                $validated['start_date'],

            'registration_end' =>
                $validated['end_date'],

            'voting_start' =>
                $validated['start_date'],

            'voting_end' =>
                $validated['end_date'],

            'status' =>
                'draft',

            'result_published' =>
                false,
        ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Pemilihan berhasil dibuat.',
            'data' =>
                $election->fresh(),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE PEMILIHAN
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        $election = Election::query()
            ->find($id);

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
                    'Pemilihan hanya dapat diedit saat masih dalam tahap persiapan.',
            ], 422);
        }

        $validated = $request->validate([
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:200',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'start_date' => [
                'sometimes',
                'required',
                'date',
            ],

            'end_date' => [
                'sometimes',
                'required',
                'date',
            ],
        ]);

        $startDate = array_key_exists(
            'start_date',
            $validated
        )
            ? Carbon::parse(
                $validated['start_date']
            )
            : Carbon::parse(
                $election->start_date
            );

        $endDate = array_key_exists(
            'end_date',
            $validated
        )
            ? Carbon::parse(
                $validated['end_date']
            )
            : Carbon::parse(
                $election->end_date
            );

        if ($endDate->lt($startDate)) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            ], 422);
        }

        if (
            array_key_exists(
                'title',
                $validated
            )
        ) {
            $election->title =
                trim(
                    $validated['title']
                );
        }

        if (
            array_key_exists(
                'description',
                $validated
            )
        ) {
            $election->description =
                $validated['description'] !== null
                    ? trim(
                        $validated['description']
                    )
                    : null;
        }

        if (
            array_key_exists(
                'start_date',
                $validated
            )
        ) {
            $election->start_date =
                $validated['start_date'];

            $election->registration_start =
                $validated['start_date'];

            $election->voting_start =
                $validated['start_date'];
        }

        if (
            array_key_exists(
                'end_date',
                $validated
            )
        ) {
            $election->end_date =
                $validated['end_date'];

            $election->registration_end =
                $validated['end_date'];

            $election->voting_end =
                $validated['end_date'];
        }

        $election->save();

        return response()->json([
            'success' => true,
            'message' =>
                'Pemilihan berhasil diperbarui.',
            'data' =>
                $election->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HAPUS PEMILIHAN
    |--------------------------------------------------------------------------
    */

    public function destroy(
        int $id
    ): JsonResponse {
        $election = Election::query()
            ->find($id);

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
                    'Pemilihan yang sudah dibuka tidak dapat dihapus.',
            ], 422);
        }

        if (
            $election
                ->votes()
                ->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pemilihan sudah memiliki suara dan tidak dapat dihapus.',
            ], 422);
        }

        DB::transaction(
            function () use ($election) {
                ElectionVoter::query()
                    ->where(
                        'election_id',
                        $election->id
                    )
                    ->delete();

                ElectionCandidate::query()
                    ->where(
                        'election_id',
                        $election->id
                    )
                    ->delete();

                $election->delete();
            }
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Pemilihan berhasil dihapus.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE STATUS
    |--------------------------------------------------------------------------
    */

    public function updateStatus(
        Request $request,
        int $id
    ): JsonResponse {
        $election = Election::query()
            ->find($id);

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pemilihan tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'in:draft,open,closed',
            ],
        ]);

        $status =
            $validated['status'];

        if ($status === 'open') {
            return $this->openVoting(
                $id
            );
        }

        if ($status === 'closed') {
            return $this->closeVoting(
                $id
            );
        }

        if (
            $election
                ->votes()
                ->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Status tidak dapat dikembalikan ke draft karena sudah ada suara.',
            ], 422);
        }

        $election->status =
            'draft';

        $election->result_published =
            false;

        $election->save();

        return response()->json([
            'success' => true,
            'message' =>
                'Status pemilihan berhasil diperbarui.',
            'data' =>
                $election->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SYNC PEMILIH
    |--------------------------------------------------------------------------
    |
    | ATURAN FINAL:
    |
    | Pemilih adalah user yang:
    |
    | 1. Memiliki data Member
    | 2. Member berstatus active / aktif
    | 3. User berstatus active
    | 4. Role mahasiswa / pengurus / officer
    |
    | Jadi pengurus yang merupakan anggota aktif
    | tetap mendapatkan hak suara.
    |
    */

    public function syncVoters(
        int $id
    ): JsonResponse {
        $election = Election::query()
            ->find($id);

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
                    'Daftar pemilih hanya dapat disinkronkan sebelum voting dibuka.',
            ], 422);
        }

        try {
            $synced =
                $this->syncActiveMemberVoters(
                    $election
                );

            $eligibleVoters =
                ElectionVoter::query()
                    ->where(
                        'election_id',
                        $election->id
                    )
                    ->where(
                        'is_eligible',
                        true
                    )
                    ->count();

            return response()->json([
                'success' => true,

                'message' =>
                    'Daftar pemilih berhasil disinkronkan dari seluruh anggota HIMATIF aktif.',

                'data' => [
                    'synced_count' =>
                        $synced,

                    'eligible_voters' =>
                        $eligibleVoters,
                ],
            ]);
        } catch (\Throwable $error) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Gagal menyinkronkan pemilih: '
                    . $error->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DAFTAR PEMILIH
    |--------------------------------------------------------------------------
    */

    public function voters(
        int $id
    ): JsonResponse {
        $election = Election::query()
            ->find($id);

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pemilihan tidak ditemukan.',
            ], 404);
        }

        /*
         * Admin/pengurus hanya melihat
         * status pemilih.
         *
         * TIDAK memperlihatkan siapa
         * memilih kandidat mana.
         */

        $voters = ElectionVoter::query()
            ->where(
                'election_id',
                $id
            )
            ->with([
                'user:id,name,email,role,status',
            ])
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' =>
                'Daftar pemilih berhasil diambil.',
            'data' =>
                $voters,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | BUKA VOTING
    |--------------------------------------------------------------------------
    |
    | Saat admin membuka voting:
    |
    | 1. Minimal 2 kandidat
    | 2. Sistem otomatis sinkronisasi seluruh anggota aktif
    | 3. Minimal 1 pemilih aktif
    | 4. Status menjadi open
    |
    */

    public function openVoting(
        int $id
    ): JsonResponse {
        $election = Election::query()
            ->find($id);

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pemilihan tidak ditemukan.',
            ], 404);
        }

        if ($election->status === 'closed') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pemilihan sudah ditutup.',
            ], 422);
        }

        if ($election->status === 'open') {
            return response()->json([
                'success' => true,
                'message' =>
                    'Voting sudah dibuka.',
                'data' =>
                    $election,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | MINIMAL 2 KANDIDAT
        |--------------------------------------------------------------------------
        */

        $candidateCount =
            ElectionCandidate::query()
                ->where(
                    'election_id',
                    $id
                )
                ->count();

        if ($candidateCount < 2) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Minimal harus ada 2 kandidat sebelum voting dibuka.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | AUTO SYNC PEMILIH
        |--------------------------------------------------------------------------
        |
        | Admin tidak perlu lagi wajib menekan
        | tombol Sync sebelum membuka voting.
        |
        */

        try {
            $synced =
                $this->syncActiveMemberVoters(
                    $election
                );
        } catch (\Throwable $error) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Voting tidak dapat dibuka karena sinkronisasi pemilih gagal: '
                    . $error->getMessage(),
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | CEK PEMILIH
        |--------------------------------------------------------------------------
        */

        $voterCount =
            ElectionVoter::query()
                ->where(
                    'election_id',
                    $id
                )
                ->where(
                    'is_eligible',
                    true
                )
                ->count();

        if ($voterCount < 1) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Tidak ada anggota HIMATIF aktif yang dapat menjadi pemilih.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | BUKA
        |--------------------------------------------------------------------------
        */

        $election->status =
            'open';

        $election->result_published =
            false;

        $election->save();

        return response()->json([
            'success' => true,

            'message' =>
                'Voting berhasil dibuka. Seluruh anggota HIMATIF aktif telah disinkronkan sebagai pemilih.',

            'data' =>
                $election->fresh(),

            'synced_voters' =>
                $synced,

            'eligible_voters' =>
                $voterCount,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TUTUP VOTING
    |--------------------------------------------------------------------------
    */

    public function closeVoting(
        int $id
    ): JsonResponse {
        $election = Election::query()
            ->find($id);

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pemilihan tidak ditemukan.',
            ], 404);
        }

        if ($election->status === 'closed') {
            return response()->json([
                'success' => true,
                'message' =>
                    'Voting sudah ditutup.',
                'data' =>
                    $election,
            ]);
        }

        if ($election->status !== 'open') {
            return response()->json([
                'success' => false,

                'message' =>
                    'Voting belum dibuka.',
            ], 422);
        }

        $election->status =
            'closed';

        /*
         * Saat voting ditutup,
         * hasil langsung dipublikasikan.
         */

        $election->result_published =
            true;

        $election->save();

        return response()->json([
            'success' => true,

            'message' =>
                'Voting berhasil ditutup.',

            'data' =>
                $election->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS VOTING USER
    |--------------------------------------------------------------------------
    */

    public function votingStatus(
        Request $request,
        int $id
    ): JsonResponse {
        $election = Election::query()
            ->find($id);

        if (!$election) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Pemilihan tidak ditemukan.',
            ], 404);
        }

        $user =
            $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,

                'message' =>
                    'User belum login.',
            ], 401);
        }

        $voter =
            ElectionVoter::query()
                ->where(
                    'election_id',
                    $id
                )
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();

        /*
         * Selain record voter,
         * cek juga apakah user masih
         * merupakan anggota aktif.
         */

        $activeMember =
            $this->isActiveVotingMember(
                $user
            );

        $eligible =
            $voter !== null &&
            (bool) $voter->is_eligible &&
            $activeMember;

        $hasVoted =
            $voter !== null &&
            (bool) $voter->has_voted;

        $canVote =
            $election->status === 'open' &&
            $eligible &&
            !$hasVoted;

        return response()->json([
            'success' => true,

            'data' => [
                'eligible' =>
                    $eligible,

                'is_eligible' =>
                    $eligible,

                'has_voted' =>
                    $hasVoted,

                'already_voted' =>
                    $hasVoted,

                'can_vote' =>
                    $canVote,

                'status' =>
                    $election->status,

                'result_published' =>
                    (bool)
                    $election->result_published,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ALIAS MY STATUS
    |--------------------------------------------------------------------------
    */

    public function myVotingStatus(
        Request $request,
        int $id
    ): JsonResponse {
        return $this->votingStatus(
            $request,
            $id
        );
    }

    /*
    |--------------------------------------------------------------------------
    | VOTE
    |--------------------------------------------------------------------------
    |
    | ATURAN:
    |
    | - User aktif
    | - Anggota HIMATIF aktif
    | - Mahasiswa / Pengurus
    | - Terdaftar sebagai voter
    | - Belum pernah voting
    | - Kandidat harus berasal dari election yang sama
    |
    */

    public function vote(
        Request $request,
        int $id
    ): JsonResponse {
        $validated = $request->validate([
            'candidate_id' => [
                'required',
                'integer',
                'exists:election_candidates,id',
            ],
        ]);

        $user =
            $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,

                'message' =>
                    'User belum login.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | USER HARUS ANGGOTA AKTIF
        |--------------------------------------------------------------------------
        */

        if (
            !$this->isActiveVotingMember(
                $user
            )
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Hanya anggota HIMATIF aktif yang dapat memberikan suara.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | PEMILIHAN
        |--------------------------------------------------------------------------
        */

        $election = Election::query()
            ->find($id);

        if (!$election) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Pemilihan tidak ditemukan.',
            ], 404);
        }

        if ($election->status !== 'open') {
            return response()->json([
                'success' => false,

                'message' =>
                    'Voting belum dibuka atau sudah ditutup.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | KANDIDAT HARUS DARI ELECTION YANG SAMA
        |--------------------------------------------------------------------------
        */

        $candidate =
            ElectionCandidate::query()
                ->where(
                    'id',
                    $validated['candidate_id']
                )
                ->where(
                    'election_id',
                    $id
                )
                ->first();

        if (!$candidate) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Kandidat tidak ditemukan pada pemilihan ini.',
            ], 422);
        }

        try {
            $result = DB::transaction(
                function () use (
                    $id,
                    $user,
                    $candidate
                ) {
                    /*
                    |--------------------------------------------------------------------------
                    | LOCK VOTER
                    |--------------------------------------------------------------------------
                    |
                    | Mencegah double vote saat
                    | dua request masuk bersamaan.
                    |
                    */

                    $voter =
                        ElectionVoter::query()
                            ->where(
                                'election_id',
                                $id
                            )
                            ->where(
                                'user_id',
                                $user->id
                            )
                            ->lockForUpdate()
                            ->first();

                    if (!$voter) {
                        return [
                            'error' =>
                                'not_registered',
                        ];
                    }

                    if (
                        !$voter->is_eligible
                    ) {
                        return [
                            'error' =>
                                'not_eligible',
                        ];
                    }

                    if (
                        $voter->has_voted
                    ) {
                        return [
                            'error' =>
                                'already_voted',
                        ];
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | CEK TABEL VOTE
                    |--------------------------------------------------------------------------
                    |
                    | Perlindungan lapis kedua
                    | untuk aturan satu user satu suara.
                    |
                    */

                    $alreadyVote =
                        ElectionVote::query()
                            ->where(
                                'election_id',
                                $id
                            )
                            ->where(
                                'user_id',
                                $user->id
                            )
                            ->exists();

                    if ($alreadyVote) {
                        return [
                            'error' =>
                                'already_voted',
                        ];
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | SIMPAN SUARA
                    |--------------------------------------------------------------------------
                    */

                    $vote =
                        ElectionVote::query()
                            ->create([
                                'election_id' =>
                                    $id,

                                'candidate_id' =>
                                    $candidate->id,

                                'user_id' =>
                                    $user->id,

                                'voted_at' =>
                                    now(),
                            ]);

                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE VOTER
                    |--------------------------------------------------------------------------
                    */

                    $voter->update([
                        'has_voted' =>
                            true,

                        'voted_at' =>
                            now(),
                    ]);

                    return [
                        'vote' =>
                            $vote,
                    ];
                }
            );

            /*
            |--------------------------------------------------------------------------
            | ERROR BUSINESS RULE
            |--------------------------------------------------------------------------
            */

            if (isset($result['error'])) {
                return match (
                    $result['error']
                ) {
                    'not_registered' =>
                        response()->json([
                            'success' => false,

                            'message' =>
                                'Anda belum terdaftar sebagai pemilih pada pemilihan ini.',
                        ], 403),

                    'not_eligible' =>
                        response()->json([
                            'success' => false,

                            'message' =>
                                'Anda tidak memiliki hak suara pada pemilihan ini.',
                        ], 403),

                    default =>
                        response()->json([
                            'success' => false,

                            'message' =>
                                'Anda sudah memberikan suara pada pemilihan ini.',
                        ], 409),
                };
            }

            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,

                'message' =>
                    'Suara berhasil disimpan.',

                /*
                 * Tidak mengembalikan candidate_id.
                 *
                 * Pilihan user tetap privat.
                 */
                'data' => [
                    'vote_id' =>
                        $result['vote']->id,

                    'voted_at' =>
                        $result['vote']->voted_at,
                ],
            ], 201);
        } catch (
            QueryException $error
        ) {
            /*
             * Jika DB memiliki unique constraint:
             *
             * election_id + user_id
             *
             * maka ini menjadi perlindungan
             * tambahan terhadap double vote.
             */

            if (
                in_array(
                    (string)
                    $error->getCode(),
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
    | HASIL PEMILIHAN
    |--------------------------------------------------------------------------
    */

    public function results(
        Request $request,
        int $id
    ): JsonResponse {
        $election =
            Election::query()
                ->withCount([
                    'votes',
                    'voters',
                ])
                ->find($id);

        if (!$election) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Pemilihan tidak ditemukan.',
            ], 404);
        }

        $role =
            strtolower(
                trim(
                    (string) (
                        $request
                            ->user()
                            ?->role
                        ?? ''
                    )
                )
            );

        $manager =
            $role === 'admin';

        /*
         * Admin dapat melihat hasil
         * untuk monitoring.
         *
         * Pengurus dan mahasiswa hanya
         * dapat melihat setelah hasil
         * dipublikasikan.
         */

        if (
            !$manager &&
            !$election->result_published
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Hasil pemilihan belum dipublikasikan.',
            ], 403);
        }

        $totalVotes =
            (int)
            $election->votes_count;

        /*
        |--------------------------------------------------------------------------
        | HASIL PER KANDIDAT
        |--------------------------------------------------------------------------
        */

        $candidates =
            ElectionCandidate::query()
                ->where(
                    'election_id',
                    $id
                )
                ->withCount('votes')
                ->orderByDesc(
                    'votes_count'
                )
                ->orderBy(
                    'candidate_number'
                )
                ->get();

        $results =
            $candidates
                ->map(
                    function (
                        ElectionCandidate $candidate
                    ) use (
                        $totalVotes
                    ) {
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
                            'id' =>
                                $candidate->id,

                            'candidate_id' =>
                                $candidate->id,

                            'candidate_number' =>
                                $candidate->candidate_number,

                            'name' =>
                                $candidate->name,

                            'nim' =>
                                $candidate->nim,

                            'study_program' =>
                                $candidate->study_program,

                            'vision' =>
                                $candidate->vision,

                            'mission' =>
                                $candidate->mission,

                            'photo_path' =>
                                $candidate->photo_path,

                            'photo_url' =>
                                $candidate->photo_url,

                            'votes' =>
                                (int)
                                $candidate->votes_count,

                            'vote_count' =>
                                (int)
                                $candidate->votes_count,

                            'votes_count' =>
                                (int)
                                $candidate->votes_count,

                            'percentage' =>
                                $percentage,
                        ];
                    }
                )
                ->values();

        /*
        |--------------------------------------------------------------------------
        | PEMENANG
        |--------------------------------------------------------------------------
        */

        $winner =
            null;

        $isTie =
            false;

        if (
            $election->status === 'closed' &&
            $totalVotes > 0 &&
            $results->isNotEmpty()
        ) {
            $highest =
                (int)
                $results
                    ->first()['votes'];

            $top =
                $results
                    ->where(
                        'votes',
                        $highest
                    )
                    ->values();

            if ($top->count() === 1) {
                $winner =
                    $top->first();
            } else {
                $isTie =
                    true;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | PARTISIPASI PEMILIH
        |--------------------------------------------------------------------------
        */

        $eligibleVoters =
            ElectionVoter::query()
                ->where(
                    'election_id',
                    $id
                )
                ->where(
                    'is_eligible',
                    true
                )
                ->count();

        $turnoutPercentage =
            $eligibleVoters > 0
                ? round(
                    (
                        $totalVotes
                        /
                        $eligibleVoters
                    )
                    * 100,
                    2
                )
                : 0;

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

                    'description' =>
                        $election->description,

                    'status' =>
                        $election->status,

                    'result_published' =>
                        (bool)
                        $election->result_published,

                    'start_date' =>
                        $election->start_date,

                    'end_date' =>
                        $election->end_date,
                ],

                'total_votes' =>
                    $totalVotes,

                'total_voters' =>
                    $eligibleVoters,

                'turnout_percentage' =>
                    $turnoutPercentage,

                /*
                 * Kompatibel Flutter lama dan baru.
                 */
                'results' =>
                    $results,

                'candidates' =>
                    $results,

                'winner' =>
                    $winner,

                'is_tie' =>
                    $isTie,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE: AMBIL USER ID ANGGOTA AKTIF
    |--------------------------------------------------------------------------
    |
    | Voting berdasarkan data MEMBER,
    | bukan sekadar role User.
    |
    */

    private function activeVotingUserIds()
    {
        return Member::query()
            ->whereNotNull(
                'user_id'
            )
            ->where(
                function ($query) {
                    $query
                        ->where(
                            'member_status',
                            'active'
                        )
                        ->orWhere(
                            'member_status',
                            'aktif'
                        );
                }
            )
            ->whereHas(
                'user',
                function ($query) {
                    $query
                        ->where(
                            'status',
                            'active'
                        )
                        ->whereIn(
                            'role',
                            [
                                'mahasiswa',
                                'pengurus',
                                'officer',
                            ]
                        );
                }
            )
            ->pluck(
                'user_id'
            )
            ->filter()
            ->map(
                fn ($id) =>
                    (int) $id
            )
            ->unique()
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE: SYNC ANGGOTA AKTIF → ELECTION VOTER
    |--------------------------------------------------------------------------
    */

    private function syncActiveMemberVoters(
        Election $election
    ): int {
        $userIds =
            $this->activeVotingUserIds();

        return DB::transaction(
            function () use (
                $election,
                $userIds
            ) {
                /*
                |--------------------------------------------------------------------------
                | NONAKTIFKAN YANG SUDAH TIDAK LAYAK
                |--------------------------------------------------------------------------
                |
                | Jangan dihapus.
                |
                | Jika sebelumnya pernah terdaftar,
                | tetapi member sekarang inactive,
                | cukup is_eligible = false.
                |
                */

                $existingQuery =
                    ElectionVoter::query()
                        ->where(
                            'election_id',
                            $election->id
                        );

                if ($userIds->isEmpty()) {
                    $existingQuery->update([
                        'is_eligible' =>
                            false,
                    ]);
                } else {
                    $existingQuery
                        ->whereNotIn(
                            'user_id',
                            $userIds->all()
                        )
                        ->update([
                            'is_eligible' =>
                                false,
                        ]);
                }

                /*
                |--------------------------------------------------------------------------
                | TAMBAHKAN / AKTIFKAN PEMILIH
                |--------------------------------------------------------------------------
                */

                $synced =
                    0;

                foreach (
                    $userIds
                    as $userId
                ) {
                    $voter =
                        ElectionVoter::query()
                            ->firstOrNew([
                                'election_id' =>
                                    $election->id,

                                'user_id' =>
                                    $userId,
                            ]);

                    /*
                     * Anggota aktif memiliki
                     * hak pilih.
                     */

                    $voter->is_eligible =
                        true;

                    /*
                     * Record baru:
                     * belum pernah voting.
                     *
                     * Record lama:
                     * jangan reset has_voted.
                     */

                    if (!$voter->exists) {
                        $voter->has_voted =
                            false;

                        $voter->voted_at =
                            null;
                    }

                    $voter->save();

                    $synced++;
                }

                return $synced;
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE: CEK USER ADALAH ANGGOTA AKTIF
    |--------------------------------------------------------------------------
    */

    private function isActiveVotingMember(
        User $user
    ): bool {
        /*
        |--------------------------------------------------------------------------
        | USER HARUS AKTIF
        |--------------------------------------------------------------------------
        */

        if (
            strtolower(
                trim(
                    (string)
                    $user->status
                )
            ) !== 'active'
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | ROLE YANG DAPAT MENJADI ANGGOTA PEMILIH
        |--------------------------------------------------------------------------
        */

        $role =
            strtolower(
                trim(
                    (string)
                    $user->role
                )
            );

        if (
            !in_array(
                $role,
                [
                    'mahasiswa',
                    'pengurus',
                    'officer',
                ],
                true
            )
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | MEMBER HARUS AKTIF
        |--------------------------------------------------------------------------
        */

        return Member::query()
            ->where(
                'user_id',
                $user->id
            )
            ->where(
                function ($query) {
                    $query
                        ->where(
                            'member_status',
                            'active'
                        )
                        ->orWhere(
                            'member_status',
                            'aktif'
                        );
                }
            )
            ->exists();
    }
}
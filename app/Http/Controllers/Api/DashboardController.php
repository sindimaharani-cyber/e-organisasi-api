<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    /**
     * Dashboard lama.
     *
     * Dipertahankan sementara agar route /api/dashboard
     * tidak rusak apabila masih dipakai frontend lama.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Dashboard API E-Organisasi aktif.',
            'data' => [
                'message' => 'Gunakan dashboard berdasarkan role.',
            ],
        ]);
    }

    /**
     * ================================================================
     * DASHBOARD ADMIN
     * ================================================================
     *
     * Menampilkan:
     * - Total anggota
     * - Total mahasiswa aktif
     * - Total kegiatan
     * - Kegiatan aktif
     * - Total kas
     * - Kegiatan terbaru
     * - Pengumuman terbaru
     */
    public function admin(Request $request): JsonResponse
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | TOTAL ANGGOTA
        |--------------------------------------------------------------------------
        */

        $totalMembers = Schema::hasTable('members')
            ? DB::table('members')->count()
            : 0;

        /*
        |--------------------------------------------------------------------------
        | TOTAL MAHASISWA AKTIF
        |--------------------------------------------------------------------------
        */

        $activeStudents = Schema::hasTable('users')
            ? DB::table('users')
                ->where('role', 'mahasiswa')
                ->where('status', 'active')
                ->count()
            : 0;

        /*
        |--------------------------------------------------------------------------
        | TOTAL PENGURUS AKTIF
        |--------------------------------------------------------------------------
        */

        $activeOfficers = Schema::hasTable('users')
            ? DB::table('users')
                ->where('role', 'pengurus')
                ->where('status', 'active')
                ->count()
            : 0;

        /*
        |--------------------------------------------------------------------------
        | TOTAL KEGIATAN
        |--------------------------------------------------------------------------
        */

        $totalActivities = Schema::hasTable('activities')
            ? DB::table('activities')->count()
            : 0;

        /*
        |--------------------------------------------------------------------------
        | KEGIATAN AKTIF
        |--------------------------------------------------------------------------
        */

        $activeActivities = $this->countActiveActivities();

        /*
        |--------------------------------------------------------------------------
        | PENGUMUMAN TERBARU
        |--------------------------------------------------------------------------
        */

        $latestAnnouncement = $this->getLatestAnnouncement();

        /*
        |--------------------------------------------------------------------------
        | RESPONSE ADMIN
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Dashboard Admin berhasil diambil.',
            'data' => [
                'role' => 'admin',

                'user' => $this->formatUser($user),

                'summary' => [
                    'total_members' => $totalMembers,
                    'active_students' => $activeStudents,
                    'active_officers' => $activeOfficers,
                    'total_activities' => $totalActivities,
                    'active_activities' => $activeActivities,
                    'cash_balance' => $this->getCashBalance(),
                ],

                'recent_activities' =>
                    $this->getRecentActivities(),

                'latest_announcement' =>
                    $latestAnnouncement,

                'election_summary' =>
                    $this->getElectionSummary(),
            ],
        ]);
    }

    /**
     * ================================================================
     * DASHBOARD PENGURUS
     * ================================================================
     *
     * Menampilkan:
     * - Profil pengurus
     * - Kegiatan yang dibuat
     * - Kegiatan aktif
     * - Jumlah peserta
     * - Kehadiran
     * - Sertifikat
     * - Kegiatan terbaru
     * - Pengumuman terbaru
     */
    public function officer(Request $request): JsonResponse
    {
        $user = $request->user();

        $member = Schema::hasTable('members')
            ? DB::table('members')
                ->where('user_id', $user->id)
                ->first()
            : null;

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Data anggota untuk akun Pengurus tidak ditemukan.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | DATA KEPENGURUSAN
        |--------------------------------------------------------------------------
        */

        $officer = null;

        if (Schema::hasTable('organization_officers')) {
            $officerQuery =
                DB::table('organization_officers')
                    ->where('member_id', $member->id);

            if (
                Schema::hasColumn(
                    'organization_officers',
                    'status'
                )
            ) {
                $officerQuery->where('status', 'active');
            }

            $officer = $officerQuery->first();
        }

        /*
        |--------------------------------------------------------------------------
        | KEGIATAN YANG DIBUAT
        |--------------------------------------------------------------------------
        */

        $createdActivities = 0;

        if (Schema::hasTable('activities')) {
            if (
                Schema::hasColumn(
                    'activities',
                    'created_by'
                )
            ) {
                $createdActivities =
                    DB::table('activities')
                        ->where('created_by', $user->id)
                        ->count();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | KEGIATAN AKTIF
        |--------------------------------------------------------------------------
        */

        $activeActivities =
            $this->countActiveActivitiesForRole(
                'pengurus'
            );

        /*
        |--------------------------------------------------------------------------
        | TOTAL PESERTA
        |--------------------------------------------------------------------------
        */

        $totalParticipants =
            Schema::hasTable('activity_registrations')
                ? DB::table('activity_registrations')
                    ->count()
                : 0;

        /*
        |--------------------------------------------------------------------------
        | ABSENSI TERVERIFIKASI
        |--------------------------------------------------------------------------
        */

        $verifiedAttendances = 0;

        if (Schema::hasTable('activity_attendances')) {
            $query =
                DB::table('activity_attendances');

            if (
                Schema::hasColumn(
                    'activity_attendances',
                    'attendance_status'
                )
            ) {
                $query->where(
                    'attendance_status',
                    'present'
                );
            }

            if (
                Schema::hasColumn(
                    'activity_attendances',
                    'verified_by'
                )
            ) {
                $query->where(
                    'verified_by',
                    $user->id
                );
            }

            $verifiedAttendances =
                $query->count();
        }

        /*
        |--------------------------------------------------------------------------
        | ABSENSI TERTUNDA
        |--------------------------------------------------------------------------
        */

        $pendingAttendances =
            $this->countPendingAttendances();

        /*
        |--------------------------------------------------------------------------
        | SERTIFIKAT
        |--------------------------------------------------------------------------
        */

        $certificateCount =
            Schema::hasTable('certificates')
                ? DB::table('certificates')->count()
                : 0;

        return response()->json([
            'success' => true,
            'message' =>
                'Dashboard Pengurus berhasil diambil.',

            'data' => [
                'role' => 'pengurus',

                'user' =>
                    $this->formatUser($user),

                'member' =>
                    $this->formatMember($member),

                'officer' =>
                    $this->formatOfficer($officer),

                'summary' => [
                    'created_activities' =>
                        $createdActivities,

                    'active_activities' =>
                        $activeActivities,

                    'total_participants' =>
                        $totalParticipants,

                    'verified_attendances' =>
                        $verifiedAttendances,

                    'pending_attendances' =>
                        $pendingAttendances,

                    'certificate_count' =>
                        $certificateCount,
                ],

                'recent_activities' =>
                    $this->getRecentActivitiesForRole(
                        'pengurus'
                    ),

                'latest_announcement' =>
                    $this->getLatestAnnouncement(),
            ],
        ]);
    }

    /**
     * ================================================================
     * DASHBOARD MAHASISWA
     * ================================================================
     *
     * Menampilkan:
     * - Nama
     * - NPM/NIM
     * - Kegiatan aktif
     * - Kegiatan yang didaftarkan
     * - Kehadiran
     * - Sertifikat
     * - Voting
     * - Pengumuman
     */
    public function student(Request $request): JsonResponse
    {
        $user = $request->user();

        $member = Schema::hasTable('members')
            ? DB::table('members')
                ->where('user_id', $user->id)
                ->first()
            : null;

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Data mahasiswa tidak ditemukan.',
            ], 404);
        }

        if (
            isset($member->member_status) &&
            $member->member_status !== 'active'
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Mahasiswa tidak memiliki status aktif.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | KEGIATAN AKTIF
        |--------------------------------------------------------------------------
        */

        $activeActivities =
            $this->countActiveActivitiesForRole(
                'mahasiswa'
            );

        /*
        |--------------------------------------------------------------------------
        | KEGIATAN TERDAFTAR
        |--------------------------------------------------------------------------
        */

        $registeredActivities =
            $this->countStudentRegistrations(
                $user->id,
                $member->id
            );

        /*
        |--------------------------------------------------------------------------
        | KEGIATAN YANG SUDAH DIHADIRI
        |--------------------------------------------------------------------------
        */

        $attendedActivities =
            $this->countStudentAttendances(
                $user->id,
                $member->id
            );

        /*
        |--------------------------------------------------------------------------
        | SERTIFIKAT
        |--------------------------------------------------------------------------
        */

        $certificateCount =
            $this->countStudentCertificates(
                $user->id,
                $member->id
            );

        /*
        |--------------------------------------------------------------------------
        | KEGIATAN TERSEDIA
        |--------------------------------------------------------------------------
        */

        $availableActivities =
            $this->getAvailableActivitiesForStudent(
                $user->id,
                $member->id
            );

        /*
        |--------------------------------------------------------------------------
        | KEGIATAN SAYA
        |--------------------------------------------------------------------------
        */

        $myActivities =
            $this->getStudentActivities(
                $user->id,
                $member->id
            );

        return response()->json([
            'success' => true,
            'message' =>
                'Dashboard Mahasiswa berhasil diambil.',

            'data' => [
                'role' => 'mahasiswa',

                'user' =>
                    $this->formatUser($user),

                'student' =>
                    $this->formatMember($member),

                'summary' => [
                    'active_activities' =>
                        $activeActivities,

                    'registered_activities' =>
                        $registeredActivities,

                    'attended_activities' =>
                        $attendedActivities,

                    'not_attended_activities' =>
                        max(
                            0,
                            $registeredActivities -
                            $attendedActivities
                        ),

                    'certificate_count' =>
                        $certificateCount,

                    'available_activities' =>
                        $availableActivities->count(),
                ],

                'available_activities' =>
                    $availableActivities,

                'my_activities' =>
                    $myActivities,

                'latest_announcement' =>
                    $this->getLatestAnnouncement(),

                'active_election' =>
                    $this->getActiveElection(),
            ],
        ]);
    }

    /**
     * ================================================================
     * HELPER: HITUNG KEGIATAN AKTIF
     * ================================================================
     */
    private function countActiveActivities(): int
    {
        if (!Schema::hasTable('activities')) {
            return 0;
        }

        $query = DB::table('activities');

        if (
            Schema::hasColumn(
                'activities',
                'status'
            )
        ) {
            $query->whereIn('status', [
                'perencanaan',
                'berjalan',
                'aktif',
                'terjadwal',
            ]);
        }

        return $query->count();
    }

    /**
     * Kegiatan aktif berdasarkan target role.
     */
    private function countActiveActivitiesForRole(
        string $role
    ): int {
        if (!Schema::hasTable('activities')) {
            return 0;
        }

        $query = DB::table('activities');

        if (
            Schema::hasColumn(
                'activities',
                'status'
            )
        ) {
            $query->whereIn('status', [
                'perencanaan',
                'berjalan',
                'aktif',
                'terjadwal',
            ]);
        }

        if (
            Schema::hasColumn(
                'activities',
                'target_role'
            )
        ) {
            $query->whereIn(
                'target_role',
                [
                    $role,
                    'semua',
                ]
            );
        }

        return $query->count();
    }

    /**
     * ================================================================
     * HELPER: KEGIATAN TERBARU
     * ================================================================
     */
    private function getRecentActivities()
    {
        if (!Schema::hasTable('activities')) {
            return collect();
        }

        $query = DB::table('activities');

        $this->applyActivityOrdering($query);

        return $query
            ->limit(5)
            ->get()
            ->map(
                fn ($activity) =>
                    $this->formatActivity(
                        $activity
                    )
            );
    }

    /**
     * Kegiatan terbaru berdasarkan role.
     */
    private function getRecentActivitiesForRole(
        string $role
    ) {
        if (!Schema::hasTable('activities')) {
            return collect();
        }

        $query = DB::table('activities');

        if (
            Schema::hasColumn(
                'activities',
                'target_role'
            )
        ) {
            $query->whereIn(
                'target_role',
                [
                    $role,
                    'semua',
                ]
            );
        }

        $this->applyActivityOrdering($query);

        return $query
            ->limit(5)
            ->get()
            ->map(
                fn ($activity) =>
                    $this->formatActivity(
                        $activity
                    )
            );
    }

    /**
     * Pengurutan kegiatan secara aman.
     */
    private function applyActivityOrdering(
        $query
    ): void {
        if (
            Schema::hasColumn(
                'activities',
                'start_datetime'
            )
        ) {
            $query->orderByDesc(
                'start_datetime'
            );

            return;
        }

        if (
            Schema::hasColumn(
                'activities',
                'activity_date'
            )
        ) {
            $query->orderByDesc(
                'activity_date'
            );

            return;
        }

        $query->orderByDesc('id');
    }

    /**
     * ================================================================
     * HELPER: PENGUMUMAN TERBARU
     * ================================================================
     */
    private function getLatestAnnouncement()
    {
        if (!Schema::hasTable('announcements')) {
            return null;
        }

        $query = DB::table('announcements');

        if (
            Schema::hasColumn(
                'announcements',
                'status'
            )
        ) {
            $query->where(
                'status',
                'published'
            );
        }

        if (
            Schema::hasColumn(
                'announcements',
                'published_at'
            )
        ) {
            $query->orderByDesc(
                'published_at'
            );
        } elseif (
            Schema::hasColumn(
                'announcements',
                'created_at'
            )
        ) {
            $query->orderByDesc(
                'created_at'
            );
        } else {
            $query->orderByDesc('id');
        }

        return $query->first();
    }

    /**
     * ================================================================
     * HELPER: SALDO KAS
     * ================================================================
     *
     * Mendukung:
     * in / out
     * income / expense
     * masuk / keluar
     */
    private function getCashBalance(): int|float
    {
        if (!Schema::hasTable('cash_transactions')) {
            return 0;
        }

        if (
            !Schema::hasColumn(
                'cash_transactions',
                'type'
            ) ||
            !Schema::hasColumn(
                'cash_transactions',
                'amount'
            )
        ) {
            return 0;
        }

        $cashIn = DB::table('cash_transactions')
            ->whereIn('type', [
                'in',
                'income',
                'masuk',
            ])
            ->sum('amount');

        $cashOut = DB::table('cash_transactions')
            ->whereIn('type', [
                'out',
                'expense',
                'keluar',
            ])
            ->sum('amount');

        return $cashIn - $cashOut;
    }

    /**
     * ================================================================
     * HELPER: ABSENSI TERTUNDA
     * ================================================================
     */
    private function countPendingAttendances(): int
    {
        if (
            !Schema::hasTable(
                'activity_registrations'
            )
        ) {
            return 0;
        }

        if (
            !Schema::hasTable(
                'activity_attendances'
            )
        ) {
            return DB::table(
                'activity_registrations'
            )->count();
        }

        if (
            !Schema::hasColumn(
                'activity_registrations',
                'activity_id'
            )
        ) {
            return 0;
        }

        $registrationUserColumn = null;
        $attendanceUserColumn = null;

        if (
            Schema::hasColumn(
                'activity_registrations',
                'user_id'
            )
        ) {
            $registrationUserColumn =
                'user_id';
        } elseif (
            Schema::hasColumn(
                'activity_registrations',
                'member_id'
            )
        ) {
            $registrationUserColumn =
                'member_id';
        }

        if (
            Schema::hasColumn(
                'activity_attendances',
                'user_id'
            )
        ) {
            $attendanceUserColumn =
                'user_id';
        } elseif (
            Schema::hasColumn(
                'activity_attendances',
                'member_id'
            )
        ) {
            $attendanceUserColumn =
                'member_id';
        }

        if (
            !$registrationUserColumn ||
            !$attendanceUserColumn
        ) {
            return 0;
        }

        return DB::table(
            'activity_registrations as registrations'
        )
            ->leftJoin(
                'activity_attendances as attendances',
                function ($join) use (
                    $registrationUserColumn,
                    $attendanceUserColumn
                ) {
                    $join->on(
                        'attendances.activity_id',
                        '=',
                        'registrations.activity_id'
                    );

                    $join->on(
                        "attendances.$attendanceUserColumn",
                        '=',
                        "registrations.$registrationUserColumn"
                    );
                }
            )
            ->whereNull('attendances.id')
            ->count();
    }

    /**
     * ================================================================
     * HELPER: REGISTRASI MAHASISWA
     * ================================================================
     */
    private function countStudentRegistrations(
        int $userId,
        int $memberId
    ): int {
        if (
            !Schema::hasTable(
                'activity_registrations'
            )
        ) {
            return 0;
        }

        $query = DB::table(
            'activity_registrations'
        );

        if (
            Schema::hasColumn(
                'activity_registrations',
                'user_id'
            )
        ) {
            return $query
                ->where('user_id', $userId)
                ->count();
        }

        if (
            Schema::hasColumn(
                'activity_registrations',
                'member_id'
            )
        ) {
            return $query
                ->where('member_id', $memberId)
                ->count();
        }

        return 0;
    }

    /**
     * ================================================================
     * HELPER: KEHADIRAN MAHASISWA
     * ================================================================
     */
    private function countStudentAttendances(
        int $userId,
        int $memberId
    ): int {
        if (
            !Schema::hasTable(
                'activity_attendances'
            )
        ) {
            return 0;
        }

        $query = DB::table(
            'activity_attendances'
        );

        if (
            Schema::hasColumn(
                'activity_attendances',
                'attendance_status'
            )
        ) {
            $query->where(
                'attendance_status',
                'present'
            );
        }

        if (
            Schema::hasColumn(
                'activity_attendances',
                'user_id'
            )
        ) {
            $query->where(
                'user_id',
                $userId
            );
        } elseif (
            Schema::hasColumn(
                'activity_attendances',
                'member_id'
            )
        ) {
            $query->where(
                'member_id',
                $memberId
            );
        } else {
            return 0;
        }

        return $query->count();
    }

    /**
     * ================================================================
     * HELPER: SERTIFIKAT MAHASISWA
     * ================================================================
     */
    private function countStudentCertificates(
        int $userId,
        int $memberId
    ): int {
        if (!Schema::hasTable('certificates')) {
            return 0;
        }

        $query = DB::table('certificates');

        if (
            Schema::hasColumn(
                'certificates',
                'user_id'
            )
        ) {
            return $query
                ->where('user_id', $userId)
                ->count();
        }

        if (
            Schema::hasColumn(
                'certificates',
                'member_id'
            )
        ) {
            return $query
                ->where('member_id', $memberId)
                ->count();
        }

        return 0;
    }

    /**
     * ================================================================
     * HELPER: KEGIATAN TERSEDIA MAHASISWA
     * ================================================================
     */
    private function getAvailableActivitiesForStudent(
        int $userId,
        int $memberId
    ) {
        if (!Schema::hasTable('activities')) {
            return collect();
        }

        $query = DB::table('activities');

        if (
            Schema::hasColumn(
                'activities',
                'status'
            )
        ) {
            $query->whereIn('status', [
                'perencanaan',
                'berjalan',
                'aktif',
                'terjadwal',
            ]);
        }

        if (
            Schema::hasColumn(
                'activities',
                'target_role'
            )
        ) {
            $query->whereIn(
                'target_role',
                [
                    'mahasiswa',
                    'semua',
                ]
            );
        }

        if (
            Schema::hasTable(
                'activity_registrations'
            )
        ) {
            if (
                Schema::hasColumn(
                    'activity_registrations',
                    'user_id'
                )
            ) {
                $query->whereNotExists(
                    function ($subQuery) use (
                        $userId
                    ) {
                        $subQuery
                            ->select(DB::raw(1))
                            ->from(
                                'activity_registrations'
                            )
                            ->whereColumn(
                                'activity_registrations.activity_id',
                                'activities.id'
                            )
                            ->where(
                                'activity_registrations.user_id',
                                $userId
                            );
                    }
                );
            } elseif (
                Schema::hasColumn(
                    'activity_registrations',
                    'member_id'
                )
            ) {
                $query->whereNotExists(
                    function ($subQuery) use (
                        $memberId
                    ) {
                        $subQuery
                            ->select(DB::raw(1))
                            ->from(
                                'activity_registrations'
                            )
                            ->whereColumn(
                                'activity_registrations.activity_id',
                                'activities.id'
                            )
                            ->where(
                                'activity_registrations.member_id',
                                $memberId
                            );
                    }
                );
            }
        }

        $this->applyActivityOrdering(
            $query
        );

        return $query
            ->limit(10)
            ->get()
            ->map(
                fn ($activity) =>
                    $this->formatActivity(
                        $activity
                    )
            );
    }

    /**
     * ================================================================
     * HELPER: KEGIATAN SAYA
     * ================================================================
     */
    private function getStudentActivities(
        int $userId,
        int $memberId
    ) {
        if (
            !Schema::hasTable(
                'activity_registrations'
            ) ||
            !Schema::hasTable(
                'activities'
            )
        ) {
            return collect();
        }

        $query = DB::table(
            'activity_registrations as registrations'
        )
            ->join(
                'activities',
                'activities.id',
                '=',
                'registrations.activity_id'
            );

        if (
            Schema::hasColumn(
                'activity_registrations',
                'user_id'
            )
        ) {
            $query->where(
                'registrations.user_id',
                $userId
            );
        } elseif (
            Schema::hasColumn(
                'activity_registrations',
                'member_id'
            )
        ) {
            $query->where(
                'registrations.member_id',
                $memberId
            );
        } else {
            return collect();
        }

        $activities =
            $query
                ->select('activities.*')
                ->orderByDesc(
                    'activities.id'
                )
                ->limit(10)
                ->get();

        return $activities->map(
            fn ($activity) =>
                $this->formatActivity(
                    $activity
                )
        );
    }

    /**
     * ================================================================
     * HELPER: ELECTION
     * ================================================================
     */
    private function getActiveElection()
    {
        if (Schema::hasTable('elections')) {
            $query = DB::table('elections');

            if (
                Schema::hasColumn(
                    'elections',
                    'status'
                )
            ) {
                $query->whereIn('status', [
                    'active',
                    'open',
                    'voting',
                ]);
            }

            return $query
                ->orderByDesc('id')
                ->first();
        }

        if (
            Schema::hasTable(
                'election_periods'
            )
        ) {
            $query =
                DB::table('election_periods');

            if (
                Schema::hasColumn(
                    'election_periods',
                    'status'
                )
            ) {
                $query->whereIn('status', [
                    'active',
                    'open',
                    'voting',
                ]);
            }

            return $query
                ->orderByDesc('id')
                ->first();
        }

        return null;
    }

    /**
     * Ringkasan voting Admin.
     */
    private function getElectionSummary(): array
    {
        $activeElection =
            $this->getActiveElection();

        if (!$activeElection) {
            return [
                'status' =>
                    'Belum ada pemilihan aktif',

                'candidate_count' => 0,
                'eligible_voter_count' => 0,
                'vote_count' => 0,
            ];
        }

        $electionId =
            $activeElection->id ?? null;

        $candidateCount = 0;
        $eligibleVoterCount = 0;
        $voteCount = 0;

        if (
            $electionId &&
            Schema::hasTable('candidates')
        ) {
            $query =
                DB::table('candidates');

            if (
                Schema::hasColumn(
                    'candidates',
                    'election_id'
                )
            ) {
                $query->where(
                    'election_id',
                    $electionId
                );
            }

            $candidateCount =
                $query->count();
        }

        if (
            $electionId &&
            Schema::hasTable('eligible_voters')
        ) {
            $query =
                DB::table('eligible_voters');

            if (
                Schema::hasColumn(
                    'eligible_voters',
                    'election_id'
                )
            ) {
                $query->where(
                    'election_id',
                    $electionId
                );
            }

            $eligibleVoterCount =
                $query->count();
        }

        if (
            $electionId &&
            Schema::hasTable('votes')
        ) {
            $query =
                DB::table('votes');

            if (
                Schema::hasColumn(
                    'votes',
                    'election_id'
                )
            ) {
                $query->where(
                    'election_id',
                    $electionId
                );
            }

            $voteCount =
                $query->count();
        }

        return [
            'status' =>
                $activeElection->status
                ?? 'active',

            'candidate_count' =>
                $candidateCount,

            'eligible_voter_count' =>
                $eligibleVoterCount,

            'vote_count' =>
                $voteCount,
        ];
    }

    /**
     * ================================================================
     * FORMAT USER
     * ================================================================
     */
    private function formatUser(
        object $user
    ): array {
        return [
            'id' =>
                $user->id ?? null,

            'name' =>
                $user->name ?? null,

            'email' =>
                $user->email ?? null,

            'role' =>
                $user->role ?? null,

            'status' =>
                $user->status ?? null,
        ];
    }

    /**
     * ================================================================
     * FORMAT MEMBER
     * ================================================================
     */
    private function formatMember(
        ?object $member
    ): ?array {
        if (!$member) {
            return null;
        }

        return [
            'id' =>
                $member->id ?? null,

            'user_id' =>
                $member->user_id ?? null,

            'name' =>
                $member->name ?? null,

            'nim' =>
                $member->nim ?? null,

            'email' =>
                $member->email ?? null,

            'phone' =>
                $member->phone ?? null,

            'study_program' =>
                $member->study_program ?? null,

            'generation' =>
                $member->generation ?? null,

            'position' =>
                $member->position ?? null,

            'division' =>
                $member->division ?? null,

            'member_status' =>
                $member->member_status ?? null,

            'profile_photo' =>
                $member->profile_photo ?? null,
        ];
    }

    /**
     * ================================================================
     * FORMAT OFFICER
     * ================================================================
     */
    private function formatOfficer(
        ?object $officer
    ): ?array {
        if (!$officer) {
            return null;
        }

        return [
            'id' =>
                $officer->id ?? null,

            'member_id' =>
                $officer->member_id ?? null,

            'position' =>
                $officer->position ?? null,

            'division' =>
                $officer->division ?? null,

            'management_period' =>
                $officer->management_period ?? null,

            'status' =>
                $officer->status ?? null,
        ];
    }

    /**
     * ================================================================
     * FORMAT ACTIVITY
     * ================================================================
     */
    private function formatActivity(
        object $activity
    ): array {
        return [
            'id' =>
                $activity->id ?? null,

            'title' =>
                $activity->title ?? '-',

            'description' =>
                $activity->description ?? null,

            'activity_date' =>
                $this->extractActivityDate(
                    $activity
                ),

            'start_time' =>
                $this->extractStartTime(
                    $activity
                ),

            'end_time' =>
                $this->extractEndTime(
                    $activity
                ),

            'location' =>
                $activity->location ?? '-',

            'status' =>
                $activity->status ?? '-',

            'target_role' =>
                $activity->target_role ?? null,
        ];
    }

    private function extractActivityDate(
        object $activity
    ): ?string {
        try {
            if (
                !empty(
                    $activity->activity_date
                )
            ) {
                return Carbon::parse(
                    $activity->activity_date
                )->format('Y-m-d');
            }

            if (
                !empty(
                    $activity->start_datetime
                )
            ) {
                return Carbon::parse(
                    $activity->start_datetime
                )->format('Y-m-d');
            }
        } catch (\Throwable $error) {
            return null;
        }

        return null;
    }

    private function extractStartTime(
        object $activity
    ): ?string {
        try {
            if (
                !empty(
                    $activity->start_time
                )
            ) {
                return Carbon::parse(
                    $activity->start_time
                )->format('H:i');
            }

            if (
                !empty(
                    $activity->start_datetime
                )
            ) {
                return Carbon::parse(
                    $activity->start_datetime
                )->format('H:i');
            }
        } catch (\Throwable $error) {
            return null;
        }

        return null;
    }

    private function extractEndTime(
        object $activity
    ): ?string {
        try {
            if (
                !empty(
                    $activity->end_time
                )
            ) {
                return Carbon::parse(
                    $activity->end_time
                )->format('H:i');
            }

            if (
                !empty(
                    $activity->end_datetime
                )
            ) {
                return Carbon::parse(
                    $activity->end_datetime
                )->format('H:i');
            }
        } catch (\Throwable $error) {
            return null;
        }

        return null;
    }
}
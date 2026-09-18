<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Throwable;

class RegistrationAttendanceController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | DATA PENDAFTARAN & ABSENSI USER LOGIN
    |--------------------------------------------------------------------------
    |
    | Method lama tetap dipertahankan agar endpoint lama tidak rusak.
    |
    */

    public function index(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum login.',
            ], 401);
        }

        if (
            !Schema::hasTable('activity_registrations') ||
            !Schema::hasTable('activities')
        ) {
            return response()->json([
                'success' => true,
                'message' => 'Data kegiatan berhasil diambil.',
                'data' => [],
            ]);
        }

        $query = DB::table(
            'activity_registrations as registrations'
        )
            ->join(
                'activities',
                'activities.id',
                '=',
                'registrations.activity_id'
            )
            ->where(
                'registrations.user_id',
                $user->id
            );

        if (
            Schema::hasTable('activity_attendances')
        ) {
            $query->leftJoin(
                'activity_attendances as attendances',
                function ($join) use ($user) {
                    $join
                        ->on(
                            'attendances.activity_id',
                            '=',
                            'registrations.activity_id'
                        )
                        ->where(
                            'attendances.user_id',
                            '=',
                            $user->id
                        );
                }
            );
        }

        $selects = [
            'registrations.id as registration_id',
            'registrations.activity_id',
            'activities.title',
        ];

        if (
            Schema::hasColumn(
                'activities',
                'description'
            )
        ) {
            $selects[] =
                'activities.description';
        }

        if (
            Schema::hasColumn(
                'activities',
                'activity_date'
            )
        ) {
            $selects[] =
                'activities.activity_date';
        }

        if (
            Schema::hasColumn(
                'activities',
                'location'
            )
        ) {
            $selects[] =
                'activities.location';
        }

        if (
            Schema::hasColumn(
                'activities',
                'status'
            )
        ) {
            $selects[] =
                'activities.status as activity_status';
        }

        if (
            Schema::hasColumn(
                'activity_registrations',
                'status'
            )
        ) {
            $selects[] =
                'registrations.status as registration_status';
        } elseif (
            Schema::hasColumn(
                'activity_registrations',
                'registration_status'
            )
        ) {
            $selects[] =
                'registrations.registration_status';
        }

        if (
            Schema::hasColumn(
                'activity_registrations',
                'registered_at'
            )
        ) {
            $selects[] =
                'registrations.registered_at';
        }

        if (
            Schema::hasTable(
                'activity_attendances'
            )
        ) {
            $selects[] =
                'attendances.attendance_status';

            $selects[] =
                'attendances.attended_at';

            $selects[] =
                'attendances.notes';
        }

        $data = $query
            ->select($selects)
            ->orderByDesc(
                'registrations.id'
            )
            ->get()
            ->map(
                function ($item) {
                    $status =
                        $item->attendance_status
                        ?? null;

                    return [
                        'registration_id' =>
                            $item->registration_id,

                        'activity_id' =>
                            $item->activity_id,

                        'title' =>
                            $item->title,

                        'description' =>
                            $item->description
                            ?? null,

                        'activity_date' =>
                            $item->activity_date
                            ?? null,

                        'location' =>
                            $item->location
                            ?? null,

                        'activity_status' =>
                            $item->activity_status
                            ?? null,

                        'registration_status' =>
                            $item->registration_status
                            ?? 'registered',

                        'attendance_status' =>
                            $status,

                        'attendance_label' =>
                            $this->attendanceLabel(
                                $status
                            ),

                        'attendance_checked' =>
                            $status !== null,

                        'has_attended' =>
                            $status === 'present',

                        'attended_at' =>
                            $item->attended_at
                            ?? null,

                        'notes' =>
                            $item->notes
                            ?? null,
                    ];
                }
            );

        return response()->json([
            'success' => true,
            'message' =>
                'Data pendaftaran dan absensi berhasil diambil.',
            'data' => $data,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | REGISTRASI LEGACY
    |--------------------------------------------------------------------------
    |
    | Method ini dipertahankan agar route lama tidak error.
    | Sistem utama sekarang boleh tetap menggunakan
    | ActivityRegistrationController.
    |
    */

    public function register(
        Request $request,
        int $activityId
    ): JsonResponse {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum login.',
            ], 401);
        }

        if (
            !Schema::hasTable('activities') ||
            !DB::table('activities')
                ->where('id', $activityId)
                ->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kegiatan tidak ditemukan.',
            ], 404);
        }

        if (
            !Schema::hasTable(
                'activity_registrations'
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Tabel pendaftaran kegiatan tidak ditemukan.',
            ], 500);
        }

        $existing =
            DB::table('activity_registrations')
                ->where(
                    'activity_id',
                    $activityId
                )
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anda sudah terdaftar pada kegiatan ini.',
            ], 422);
        }

        $data = [
            'activity_id' =>
                $activityId,

            'user_id' =>
                $user->id,
        ];

        if (
            Schema::hasColumn(
                'activity_registrations',
                'status'
            )
        ) {
            $data['status'] =
                'registered';
        } elseif (
            Schema::hasColumn(
                'activity_registrations',
                'registration_status'
            )
        ) {
            $data['registration_status'] =
                'registered';
        }

        if (
            Schema::hasColumn(
                'activity_registrations',
                'registered_at'
            )
        ) {
            $data['registered_at'] =
                now();
        }

        if (
            Schema::hasColumn(
                'activity_registrations',
                'created_at'
            )
        ) {
            $data['created_at'] =
                now();
        }

        if (
            Schema::hasColumn(
                'activity_registrations',
                'updated_at'
            )
        ) {
            $data['updated_at'] =
                now();
        }

        $registrationId =
            DB::table(
                'activity_registrations'
            )->insertGetId($data);

        return response()->json([
            'success' => true,
            'message' =>
                'Pendaftaran kegiatan berhasil.',
            'data' => [
                'registration_id' =>
                    $registrationId,

                'activity_id' =>
                    $activityId,

                'user_id' =>
                    $user->id,
            ],
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | DAFTAR PESERTA UNTUK KELOLA ABSENSI
    |--------------------------------------------------------------------------
    |
    | Digunakan Admin / Pengurus.
    |
    | Status:
    |
    | null     = belum dicatat
    | present  = hadir
    | absent   = tidak hadir
    |
    */

    public function attendanceParticipants(
        Request $request,
        int $activityId
    ): JsonResponse {
        $user = $request->user();

        if (!$this->canManageAttendance($user)) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anda tidak memiliki akses mengelola daftar hadir.',
            ], 403);
        }

        if (
            !Schema::hasTable('activities')
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Tabel activities tidak ditemukan.',
            ], 500);
        }

        $activity =
            DB::table('activities')
                ->where(
                    'id',
                    $activityId
                )
                ->first();

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kegiatan tidak ditemukan.',
            ], 404);
        }

        if (
            !Schema::hasTable(
                'activity_registrations'
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Tabel activity_registrations tidak ditemukan.',
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | QUERY PESERTA TERDAFTAR
        |--------------------------------------------------------------------------
        */

        $query = DB::table(
            'activity_registrations as registrations'
        )
            ->join(
                'users',
                'users.id',
                '=',
                'registrations.user_id'
            )
            ->where(
                'registrations.activity_id',
                $activityId
            );

        /*
        |--------------------------------------------------------------------------
        | FILTER STATUS PENDAFTARAN
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasColumn(
                'activity_registrations',
                'status'
            )
        ) {
            $query->where(
                'registrations.status',
                'registered'
            );
        } elseif (
            Schema::hasColumn(
                'activity_registrations',
                'registration_status'
            )
        ) {
            $query->where(
                'registrations.registration_status',
                'registered'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | JOIN MEMBER
        |--------------------------------------------------------------------------
        */

        $hasMemberJoin =
            Schema::hasTable('members') &&
            Schema::hasColumn(
                'members',
                'user_id'
            );

        if ($hasMemberJoin) {
            $query->leftJoin(
                'members',
                'members.user_id',
                '=',
                'users.id'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | JOIN ABSENSI
        |--------------------------------------------------------------------------
        */

        $hasAttendanceTable =
            Schema::hasTable(
                'activity_attendances'
            );

        if ($hasAttendanceTable) {
            $query->leftJoin(
                'activity_attendances as attendances',
                function ($join) {
                    $join
                        ->on(
                            'attendances.activity_id',
                            '=',
                            'registrations.activity_id'
                        )
                        ->on(
                            'attendances.user_id',
                            '=',
                            'registrations.user_id'
                        );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SELECT
        |--------------------------------------------------------------------------
        */

        $query->select([
            'registrations.id as registration_id',
            'registrations.user_id',
            'users.name as user_name',
            'users.email as user_email',
        ]);

        if (
            Schema::hasColumn(
                'activity_registrations',
                'registered_at'
            )
        ) {
            $query->addSelect(
                'registrations.registered_at'
            );
        }

        if ($hasMemberJoin) {
            if (
                Schema::hasColumn(
                    'members',
                    'id'
                )
            ) {
                $query->addSelect(
                    'members.id as member_id'
                );
            }

            if (
                Schema::hasColumn(
                    'members',
                    'name'
                )
            ) {
                $query->addSelect(
                    'members.name as member_name'
                );
            }

            if (
                Schema::hasColumn(
                    'members',
                    'nim'
                )
            ) {
                $query->addSelect(
                    'members.nim'
                );
            }

            if (
                Schema::hasColumn(
                    'members',
                    'email'
                )
            ) {
                $query->addSelect(
                    'members.email as member_email'
                );
            }

            if (
                Schema::hasColumn(
                    'members',
                    'study_program'
                )
            ) {
                $query->addSelect(
                    'members.study_program'
                );
            }

            if (
                Schema::hasColumn(
                    'members',
                    'generation'
                )
            ) {
                $query->addSelect(
                    'members.generation'
                );
            }

            if (
                Schema::hasColumn(
                    'members',
                    'profile_photo'
                )
            ) {
                $query->addSelect(
                    'members.profile_photo'
                );
            }
        }

        if ($hasAttendanceTable) {
            $query->addSelect([
                'attendances.id as attendance_id',
                'attendances.attendance_status',
                'attendances.attended_at',
                'attendances.verified_by',
                'attendances.notes',
            ]);
        }

        $participants =
            $query
                ->orderBy(
                    'users.name'
                )
                ->get()
                ->map(
                    function ($participant) {
                        $status =
                            $participant
                                ->attendance_status
                            ?? null;

                        return [
                            'registration_id' =>
                                $participant
                                    ->registration_id,

                            'user_id' =>
                                $participant
                                    ->user_id,

                            'member_id' =>
                                $participant
                                    ->member_id
                                ?? null,

                            'name' =>
                                $participant
                                    ->member_name
                                ?? $participant
                                    ->user_name
                                ?? '-',

                            'email' =>
                                $participant
                                    ->member_email
                                ?? $participant
                                    ->user_email
                                ?? '-',

                            'nim' =>
                                $participant->nim
                                ?? '-',

                            'study_program' =>
                                $participant
                                    ->study_program
                                ?? null,

                            'generation' =>
                                $participant
                                    ->generation
                                ?? null,

                            'profile_photo' =>
                                $participant
                                    ->profile_photo
                                ?? null,

                            'registered_at' =>
                                $participant
                                    ->registered_at
                                ?? null,

                            'attendance_id' =>
                                $participant
                                    ->attendance_id
                                ?? null,

                            'attendance_status' =>
                                $status,

                            'attendance_label' =>
                                $this
                                    ->attendanceLabel(
                                        $status
                                    ),

                            'attendance_checked' =>
                                $status !== null,

                            'has_attended' =>
                                $status ===
                                'present',

                            'attended_at' =>
                                $participant
                                    ->attended_at
                                ?? null,

                            'verified_by' =>
                                $participant
                                    ->verified_by
                                ?? null,

                            'notes' =>
                                $participant
                                    ->notes
                                ?? null,
                        ];
                    }
                );

        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */

        $total =
            $participants->count();

        $present =
            $participants
                ->where(
                    'attendance_status',
                    'present'
                )
                ->count();

        $absent =
            $participants
                ->where(
                    'attendance_status',
                    'absent'
                )
                ->count();

        $unrecorded =
            $participants
                ->whereNull(
                    'attendance_status'
                )
                ->count();

        return response()->json([
            'success' => true,
            'message' =>
                'Daftar hadir peserta berhasil diambil.',
            'data' => [
                'activity' => [
                    'id' =>
                        $activity->id,

                    'title' =>
                        $activity->title,

                    'activity_date' =>
                        $activity
                            ->activity_date
                        ?? null,

                    'location' =>
                        $activity->location
                        ?? null,

                    'status' =>
                        $activity->status
                        ?? null,
                ],

                'summary' => [
                    'total' =>
                        $total,

                    'present' =>
                        $present,

                    'absent' =>
                        $absent,

                    'unrecorded' =>
                        $unrecorded,
                ],

                'participants' =>
                    $participants->values(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SIMPAN DAFTAR HADIR MASSAL
    |--------------------------------------------------------------------------
    |
    | Payload:
    |
    | {
    |   "attendances": [
    |       {
    |           "user_id": 1,
    |           "attendance_status": "present"
    |       },
    |       {
    |           "user_id": 2,
    |           "attendance_status": "absent"
    |       },
    |       {
    |           "user_id": 3,
    |           "attendance_status": "unrecorded"
    |       }
    |   ]
    | }
    |
    | "unrecorded" tidak disimpan ke database.
    | Jika sebelumnya sudah ada, record attendance dihapus.
    |
    */

    public function saveAttendance(
        Request $request,
        int $activityId
    ): JsonResponse {
        $verifier =
            $request->user();

        if (
            !$this->canManageAttendance(
                $verifier
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anda tidak memiliki akses mengelola daftar hadir.',
            ], 403);
        }

        if (
            !Schema::hasTable('activities') ||
            !DB::table('activities')
                ->where(
                    'id',
                    $activityId
                )
                ->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kegiatan tidak ditemukan.',
            ], 404);
        }

        if (
            !Schema::hasTable(
                'activity_registrations'
            ) ||
            !Schema::hasTable(
                'activity_attendances'
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Tabel pendaftaran atau absensi tidak ditemukan.',
            ], 500);
        }

        $validated =
            $request->validate([
                'attendances' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'attendances.*.user_id' => [
                    'required',
                    'integer',
                ],

                'attendances.*.attendance_status' => [
                    'required',
                    'string',
                    Rule::in([
                        'present',
                        'absent',
                        'unrecorded',
                    ]),
                ],

                'attendances.*.notes' => [
                    'nullable',
                    'string',
                    'max:500',
                ],
            ]);

        $saved = 0;
        $deleted = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach (
                $validated['attendances']
                as $attendance
            ) {
                $userId =
                    (int) $attendance['user_id'];

                $status =
                    $attendance[
                        'attendance_status'
                    ];

                $notes =
                    isset(
                        $attendance['notes']
                    )
                        ? trim(
                            (string)
                            $attendance['notes']
                        )
                        : null;

                /*
                |--------------------------------------------------------------------------
                | CEK PESERTA MEMANG TERDAFTAR
                |--------------------------------------------------------------------------
                */

                $registered =
                    DB::table(
                        'activity_registrations'
                    )
                        ->where(
                            'activity_id',
                            $activityId
                        )
                        ->where(
                            'user_id',
                            $userId
                        )
                        ->exists();

                if (!$registered) {
                    $errors[] = [
                        'user_id' =>
                            $userId,

                        'message' =>
                            'User tidak terdaftar pada kegiatan.',
                    ];

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | BELUM DICATAT
                |--------------------------------------------------------------------------
                */

                if (
                    $status ===
                    'unrecorded'
                ) {
                    $count =
                        DB::table(
                            'activity_attendances'
                        )
                            ->where(
                                'activity_id',
                                $activityId
                            )
                            ->where(
                                'user_id',
                                $userId
                            )
                            ->delete();

                    if ($count > 0) {
                        $deleted++;
                    }

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | HADIR / TIDAK HADIR
                |--------------------------------------------------------------------------
                */

                $data = [
                    'attendance_status' =>
                        $status,

                    'attended_at' =>
                        $status === 'present'
                            ? now()
                            : null,

                    'verified_by' =>
                        $verifier->id,

                    'notes' =>
                        $notes,

                    'updated_at' =>
                        now(),
                ];

                $existing =
                    DB::table(
                        'activity_attendances'
                    )
                        ->where(
                            'activity_id',
                            $activityId
                        )
                        ->where(
                            'user_id',
                            $userId
                        )
                        ->first();

                if ($existing) {
                    DB::table(
                        'activity_attendances'
                    )
                        ->where(
                            'id',
                            $existing->id
                        )
                        ->update($data);
                } else {
                    $data['activity_id'] =
                        $activityId;

                    $data['user_id'] =
                        $userId;

                    $data['created_at'] =
                        now();

                    DB::table(
                        'activity_attendances'
                    )->insert($data);
                }

                $saved++;
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' =>
                    'Gagal menyimpan daftar hadir.',
                'error' =>
                    $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Daftar hadir berhasil disimpan.',
            'data' => [
                'activity_id' =>
                    $activityId,

                'saved_count' =>
                    $saved,

                'reset_count' =>
                    $deleted,

                'error_count' =>
                    count($errors),

                'errors' =>
                    $errors,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ABSENSI SATU PESERTA
    |--------------------------------------------------------------------------
    |
    | Dipertahankan untuk kompatibilitas route lama.
    |
    */

    public function attend(
        Request $request,
        int $activityId
    ): JsonResponse {
        $verifier =
            $request->user();

        if (
            !$this->canManageAttendance(
                $verifier
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Hanya Admin atau Pengurus yang dapat mencatat kehadiran.',
            ], 403);
        }

        $validated =
            $request->validate([
                'user_id' => [
                    'required',
                    'integer',
                ],

                'attendance_status' => [
                    'required',
                    Rule::in([
                        'present',
                        'absent',
                    ]),
                ],

                'notes' => [
                    'nullable',
                    'string',
                    'max:500',
                ],
            ]);

        $registered =
            DB::table(
                'activity_registrations'
            )
                ->where(
                    'activity_id',
                    $activityId
                )
                ->where(
                    'user_id',
                    $validated['user_id']
                )
                ->exists();

        if (!$registered) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Peserta tidak terdaftar pada kegiatan ini.',
            ], 422);
        }

        $status =
            $validated[
                'attendance_status'
            ];

        $data = [
            'attendance_status' =>
                $status,

            'attended_at' =>
                $status === 'present'
                    ? now()
                    : null,

            'verified_by' =>
                $verifier->id,

            'notes' =>
                $validated['notes']
                ?? null,

            'updated_at' =>
                now(),
        ];

        $existing =
            DB::table(
                'activity_attendances'
            )
                ->where(
                    'activity_id',
                    $activityId
                )
                ->where(
                    'user_id',
                    $validated['user_id']
                )
                ->first();

        if ($existing) {
            DB::table(
                'activity_attendances'
            )
                ->where(
                    'id',
                    $existing->id
                )
                ->update($data);
        } else {
            $data['activity_id'] =
                $activityId;

            $data['user_id'] =
                $validated['user_id'];

            $data['created_at'] =
                now();

            DB::table(
                'activity_attendances'
            )->insert($data);
        }

        return response()->json([
            'success' => true,
            'message' =>
                $status === 'present'
                    ? 'Peserta berhasil ditandai hadir.'
                    : 'Peserta berhasil ditandai tidak hadir.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY ATTENDANCE LEGACY
    |--------------------------------------------------------------------------
    |
    | Route lama pernah memakai method ini.
    | Sekarang diarahkan ke method attend().
    |
    */

    public function verifyAttendance(
        Request $request,
        int $activityId
    ): JsonResponse {
        return $this->attend(
            $request,
            $activityId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CEK AKSES
    |--------------------------------------------------------------------------
    */

    private function canManageAttendance(
        mixed $user
    ): bool {
        if (!$user) {
            return false;
        }

        $role =
            strtolower(
                trim(
                    (string) $user->role
                )
            );

        return in_array(
            $role,
            [
                'admin',
                'pengurus',
                'officer',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LABEL STATUS
    |--------------------------------------------------------------------------
    */

    private function attendanceLabel(
        ?string $status
    ): string {
        return match ($status) {
            'present' =>
                'Hadir',

            'absent' =>
                'Tidak Hadir',

            default =>
                'Belum Dicatat',
        };
    }
}
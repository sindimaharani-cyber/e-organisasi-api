<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Activity::query()
            ->with([
                'creator:id,name,email,role',
            ]);

        if ($request->filled('search')) {
            $search = trim(
                $request->input('search')
            );

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where(
                        'title',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'description',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'location',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->input('status')
            );
        }

        if ($request->filled('target_role')) {
            $query->where(
                'target_role',
                $request->input('target_role')
            );
        }

        $user = $request->user();

        if ($user) {
            if ($user->role === 'mahasiswa') {
                $query->whereIn(
                    'target_role',
                    [
                        'mahasiswa',
                        'semua',
                    ]
                );
            }

            if ($user->role === 'pengurus') {
                $query->whereIn(
                    'target_role',
                    [
                        'pengurus',
                        'semua',
                    ]
                );
            }
        }

        /*
         * Tetap gunakan kolom lama jika tersedia.
         */
        if (
            Schema::hasColumn(
                'activities',
                'start_datetime'
            )
        ) {
            $query->orderByDesc(
                'start_datetime'
            );
        } else {
            $query
                ->orderByDesc(
                    'activity_date'
                )
                ->orderByDesc(
                    'start_time'
                );
        }

        $activities = $query->get();

        return response()->json([
            'success' => true,
            'message' =>
                'Data kegiatan berhasil diambil.',
            'data' => $activities,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $activity = Activity::query()
            ->with([
                'creator:id,name,email,role',
            ])
            ->find($id);

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kegiatan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Detail kegiatan berhasil diambil.',
            'data' => $activity,
        ]);
    }

    public function store(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pengguna belum login.',
            ], 401);
        }

        if (
            !in_array(
                $user->role,
                [
                    'admin',
                    'pengurus',
                ],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anda tidak memiliki akses untuk membuat kegiatan.',
            ], 403);
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

            'activity_date' => [
                'required',
                'date',
            ],

            'start_time' => [
                'nullable',
                'date_format:H:i',
            ],

            'end_time' => [
                'nullable',
                'date_format:H:i',
            ],

            'location' => [
                'nullable',
                'string',
                'max:200',
            ],

            'target_role' => [
                'required',
                Rule::in([
                    'mahasiswa',
                    'pengurus',
                    'semua',
                ]),
            ],

            'quota' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'status' => [
                'required',
                Rule::in([
                    'perencanaan',
                    'berjalan',
                    'selesai',
                    'dibatalkan',
                ]),
            ],

            'registration_open' => [
                'nullable',
                'boolean',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | BENTUK START DATETIME
        |--------------------------------------------------------------------------
        */

        $startTime =
            $validated['start_time']
            ?? '00:00';

        $startDateTime = Carbon::createFromFormat(
            'Y-m-d H:i',
            $validated['activity_date']
                . ' '
                . $startTime
        );

        /*
        |--------------------------------------------------------------------------
        | BENTUK END DATETIME
        |--------------------------------------------------------------------------
        */

        $endDateTime = null;

        if (
            !empty(
                $validated['end_time']
            )
        ) {
            $endDateTime = Carbon::createFromFormat(
                'Y-m-d H:i',
                $validated['activity_date']
                    . ' '
                    . $validated['end_time']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDASI JAM
        |--------------------------------------------------------------------------
        */

        if (
            $endDateTime &&
            $endDateTime->lt(
                $startDateTime
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Jam selesai tidak boleh lebih awal dari jam mulai.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | DATA UTAMA
        |--------------------------------------------------------------------------
        */

        $activityData = [
            'created_by' =>
                $user->id,

            'title' =>
                $validated['title'],

            'description' =>
                $validated['description']
                ?? null,

            'activity_date' =>
                $validated['activity_date'],

            'start_time' =>
                $validated['start_time']
                ?? null,

            'end_time' =>
                $validated['end_time']
                ?? null,

            'location' =>
                $validated['location']
                ?? null,

            'target_role' =>
                $validated['target_role'],

            'quota' =>
                $validated['quota']
                ?? null,

            'status' =>
                $validated['status'],

            'registration_open' =>
                $validated['registration_open']
                ?? true,
        ];

        /*
        |--------------------------------------------------------------------------
        | KOMPATIBILITAS DATABASE LAMA
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasColumn(
                'activities',
                'start_datetime'
            )
        ) {
            $activityData['start_datetime'] =
                $startDateTime;
        }

        if (
            Schema::hasColumn(
                'activities',
                'end_datetime'
            )
        ) {
            $activityData['end_datetime'] =
                $endDateTime;
        }

        if (
            Schema::hasColumn(
                'activities',
                'completed_at'
            )
        ) {
            $activityData['completed_at'] =
                $validated['status'] === 'selesai'
                    ? now()
                    : null;
        }

        $activity =
            Activity::query()->create(
                $activityData
            );

        $activity->load(
            'creator:id,name,email,role'
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Kegiatan berhasil ditambahkan.',
            'data' => $activity,
        ], 201);
    }

    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pengguna belum login.',
            ], 401);
        }

        $activity = Activity::query()
            ->find($id);

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kegiatan tidak ditemukan.',
            ], 404);
        }

        if (
            $user->role === 'pengurus' &&
            (int) $activity->created_by !==
            (int) $user->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pengurus hanya dapat mengubah kegiatan yang dibuat sendiri.',
            ], 403);
        }

        if (
            !in_array(
                $user->role,
                [
                    'admin',
                    'pengurus',
                ],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anda tidak memiliki akses.',
            ], 403);
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

            'activity_date' => [
                'required',
                'date',
            ],

            'start_time' => [
                'nullable',
                'date_format:H:i',
            ],

            'end_time' => [
                'nullable',
                'date_format:H:i',
            ],

            'location' => [
                'nullable',
                'string',
                'max:200',
            ],

            'target_role' => [
                'required',
                Rule::in([
                    'mahasiswa',
                    'pengurus',
                    'semua',
                ]),
            ],

            'quota' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'status' => [
                'required',
                Rule::in([
                    'perencanaan',
                    'berjalan',
                    'selesai',
                    'dibatalkan',
                ]),
            ],

            'registration_open' => [
                'required',
                'boolean',
            ],
        ]);

        $startTime =
            $validated['start_time']
            ?? '00:00';

        $startDateTime = Carbon::createFromFormat(
            'Y-m-d H:i',
            $validated['activity_date']
                . ' '
                . $startTime
        );

        $endDateTime = null;

        if (
            !empty(
                $validated['end_time']
            )
        ) {
            $endDateTime = Carbon::createFromFormat(
                'Y-m-d H:i',
                $validated['activity_date']
                    . ' '
                    . $validated['end_time']
            );
        }

        if (
            $endDateTime &&
            $endDateTime->lt(
                $startDateTime
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Jam selesai tidak boleh lebih awal dari jam mulai.',
            ], 422);
        }

        $activityData = [
            'title' =>
                $validated['title'],

            'description' =>
                $validated['description']
                ?? null,

            'activity_date' =>
                $validated['activity_date'],

            'start_time' =>
                $validated['start_time']
                ?? null,

            'end_time' =>
                $validated['end_time']
                ?? null,

            'location' =>
                $validated['location']
                ?? null,

            'target_role' =>
                $validated['target_role'],

            'quota' =>
                $validated['quota']
                ?? null,

            'status' =>
                $validated['status'],

            'registration_open' =>
                $validated[
                    'registration_open'
                ],
        ];

        if (
            Schema::hasColumn(
                'activities',
                'start_datetime'
            )
        ) {
            $activityData['start_datetime'] =
                $startDateTime;
        }

        if (
            Schema::hasColumn(
                'activities',
                'end_datetime'
            )
        ) {
            $activityData['end_datetime'] =
                $endDateTime;
        }

        if (
            Schema::hasColumn(
                'activities',
                'completed_at'
            )
        ) {
            $activityData['completed_at'] =
                $validated['status'] === 'selesai'
                    ? (
                        $activity->completed_at
                        ?? now()
                    )
                    : null;
        }

        $activity->update(
            $activityData
        );

        $activity->load(
            'creator:id,name,email,role'
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Kegiatan berhasil diperbarui.',
            'data' => $activity,
        ]);
    }

    public function updateStatus(
        Request $request,
        int $id
    ): JsonResponse {
        $user = $request->user();

        $activity = Activity::query()
            ->find($id);

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kegiatan tidak ditemukan.',
            ], 404);
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pengguna belum login.',
            ], 401);
        }

        if (
            $user->role === 'pengurus' &&
            (int) $activity->created_by !==
            (int) $user->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pengurus hanya dapat mengubah kegiatan yang dibuat sendiri.',
            ], 403);
        }

        if (
            !in_array(
                $user->role,
                [
                    'admin',
                    'pengurus',
                ],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anda tidak memiliki akses.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'perencanaan',
                    'berjalan',
                    'selesai',
                    'dibatalkan',
                ]),
            ],
        ]);

        $data = [
            'status' =>
                $validated['status'],
        ];

        if (
            Schema::hasColumn(
                'activities',
                'completed_at'
            )
        ) {
            $data['completed_at'] =
                $validated['status'] === 'selesai'
                    ? now()
                    : null;
        }

        $activity->update($data);

        return response()->json([
            'success' => true,
            'message' =>
                'Status kegiatan berhasil diperbarui.',
            'data' => $activity,
        ]);
    }

    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {
        $user = $request->user();

        $activity = Activity::query()
            ->find($id);

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kegiatan tidak ditemukan.',
            ], 404);
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pengguna belum login.',
            ], 401);
        }

        if (
            $user->role === 'pengurus' &&
            (int) $activity->created_by !==
            (int) $user->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pengurus hanya dapat menghapus kegiatan yang dibuat sendiri.',
            ], 403);
        }

        if (
            !in_array(
                $user->role,
                [
                    'admin',
                    'pengurus',
                ],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anda tidak memiliki akses.',
            ], 403);
        }

        $activity->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Kegiatan berhasil dihapus.',
        ]);
    }
}
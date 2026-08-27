<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityRegistrationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | DAFTAR KEGIATAN
    |--------------------------------------------------------------------------
    */

    public function register(
        Request $request,
        int $activityId
    ): JsonResponse {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna belum login.',
            ], 401);
        }

        if ($user->role !== 'mahasiswa') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pendaftaran kegiatan hanya tersedia untuk mahasiswa.',
            ], 403);
        }

        $activity = Activity::query()
            ->find($activityId);

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kegiatan tidak ditemukan.',
            ], 404);
        }

        if (!$activity->registration_open) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pendaftaran kegiatan sudah ditutup.',
            ], 422);
        }

        if (
            !in_array(
                $activity->target_role,
                [
                    'mahasiswa',
                    'semua',
                ],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kegiatan ini tidak ditujukan untuk mahasiswa.',
            ], 403);
        }

        if (
            in_array(
                $activity->status,
                [
                    'selesai',
                    'dibatalkan',
                ],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pendaftaran tidak tersedia untuk kegiatan ini.',
            ], 422);
        }

        $existing =
            ActivityRegistration::query()
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

        if ($activity->quota !== null) {
            $registeredCount =
                ActivityRegistration::query()
                    ->where(
                        'activity_id',
                        $activityId
                    )
                    ->where(
                        'status',
                        'registered'
                    )
                    ->count();

            if (
                $registeredCount >=
                (int) $activity->quota
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Kuota kegiatan sudah penuh.',
                ], 422);
            }
        }

        $registration =
            ActivityRegistration::query()->create([
                'activity_id' =>
                    $activityId,

                'user_id' =>
                    $user->id,

                'status' =>
                    'registered',

                'registered_at' =>
                    now(),
            ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Pendaftaran kegiatan berhasil.',
            'data' => $registration,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | BATALKAN PENDAFTARAN
    |--------------------------------------------------------------------------
    */

    public function cancel(
        Request $request,
        int $activityId
    ): JsonResponse {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pengguna belum login.',
            ], 401);
        }

        $registration =
            ActivityRegistration::query()
                ->where(
                    'activity_id',
                    $activityId
                )
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();

        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pendaftaran kegiatan tidak ditemukan.',
            ], 404);
        }

        $registration->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Pendaftaran kegiatan berhasil dibatalkan.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | KEGIATAN SAYA
    |--------------------------------------------------------------------------
    */

    public function myRegistrations(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        $registrations =
            ActivityRegistration::query()
                ->with([
                    'activity.creator:id,name,email,role',
                ])
                ->where(
                    'user_id',
                    $user->id
                )
                ->latest(
                    'registered_at'
                )
                ->get();

        return response()->json([
            'success' => true,
            'message' =>
                'Data kegiatan saya berhasil diambil.',
            'data' => $registrations,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CEK PENDAFTARAN
    |--------------------------------------------------------------------------
    */

    public function status(
        Request $request,
        int $activityId
    ): JsonResponse {
        $user = $request->user();

        $registration =
            ActivityRegistration::query()
                ->where(
                    'activity_id',
                    $activityId
                )
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();

        return response()->json([
            'success' => true,
            'message' =>
                'Status pendaftaran berhasil diambil.',
            'data' => [
                'registered' =>
                    $registration !== null,

                'registration' =>
                    $registration,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DAFTAR PESERTA
    |--------------------------------------------------------------------------
    */

    public function participants(
        Request $request,
        int $activityId
    ): JsonResponse {
        $user = $request->user();

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

        $activity = Activity::query()
            ->find($activityId);

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kegiatan tidak ditemukan.',
            ], 404);
        }

        $participants =
            ActivityRegistration::query()
                ->with([
                    'user:id,name,email,role,status',
                    'user.member',
                ])
                ->where(
                    'activity_id',
                    $activityId
                )
                ->where(
                    'status',
                    'registered'
                )
                ->orderBy(
                    'registered_at'
                )
                ->get();

        return response()->json([
            'success' => true,
            'message' =>
                'Daftar peserta berhasil diambil.',
            'data' => [
                'activity' =>
                    $activity,

                'total' =>
                    $participants->count(),

                'quota' =>
                    $activity->quota,

                'participants' =>
                    $participants,
            ],
        ]);
    }
}
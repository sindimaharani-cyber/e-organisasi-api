<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityRegistration;
use App\Models\Certificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SERTIFIKAT MAHASISWA LOGIN
    |--------------------------------------------------------------------------
    */

    public function myCertificates(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        $certificates = Certificate::query()
            ->with([
                'activity',
                'issuer:id,name,email',
                'user:id,name,email',
            ])
            ->where(
                'user_id',
                $user->id
            )
            ->where(
                'status',
                'issued'
            )
            ->latest(
                'issued_at'
            )
            ->get();

        return response()->json([
            'success' => true,
            'message' =>
                'Data sertifikat berhasil diambil.',
            'data' => $certificates,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PESERTA + STATUS SERTIFIKAT
    |--------------------------------------------------------------------------
    */

    public function participants(
        Request $request,
        int $activityId
    ): JsonResponse {
        $user = $request->user();

        if (
            !$user ||
            !in_array(
                strtolower(
                    (string) $user->role
                ),
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

        $registrations =
            ActivityRegistration::query()
                ->with([
                    'user',
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

        $participants =
            $registrations->map(
                function (
                    ActivityRegistration $registration
                ) use (
                    $activityId
                ) {
                    $certificate =
                        Certificate::query()
                            ->where(
                                'activity_id',
                                $activityId
                            )
                            ->where(
                                'user_id',
                                $registration->user_id
                            )
                            ->first();

                    return [
                        'registration' =>
                            $registration,

                        'certificate_issued' =>
                            $certificate !== null &&
                            $certificate->status === 'issued',

                        'certificate' =>
                            $certificate,
                    ];
                }
            );

        return response()->json([
            'success' => true,
            'message' =>
                'Data peserta sertifikat berhasil diambil.',
            'data' => [
                'activity' =>
                    $activity,

                'total' =>
                    $participants->count(),

                'participants' =>
                    $participants->values(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TERBITKAN SERTIFIKAT
    |--------------------------------------------------------------------------
    */

    public function issue(
        Request $request,
        int $activityId,
        int $userId
    ): JsonResponse {
        $issuer = $request->user();

        if (
            !$issuer ||
            !in_array(
                strtolower(
                    (string) $issuer->role
                ),
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

        $registration =
            ActivityRegistration::query()
                ->where(
                    'activity_id',
                    $activityId
                )
                ->where(
                    'user_id',
                    $userId
                )
                ->first();

        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Mahasiswa belum terdaftar pada kegiatan ini.',
            ], 422);
        }

        $existing =
            Certificate::query()
                ->where(
                    'activity_id',
                    $activityId
                )
                ->where(
                    'user_id',
                    $userId
                )
                ->first();

        /*
        |--------------------------------------------------------------------------
        | JIKA SUDAH ADA
        |--------------------------------------------------------------------------
        */

        if ($existing) {
            if ($existing->status === 'revoked') {
                $existing->update([
                    'status' =>
                        'issued',

                    'issued_by' =>
                        $issuer->id,

                    'issued_at' =>
                        now(),
                ]);

                $existing->refresh();

                $existing->load([
                    'activity',
                    'user:id,name,email',
                    'issuer:id,name,email',
                ]);

                return response()->json([
                    'success' => true,
                    'message' =>
                        'Sertifikat berhasil diterbitkan kembali.',
                    'data' =>
                        $existing,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' =>
                    'Sertifikat peserta ini sudah diterbitkan.',
                'data' =>
                    $existing,
            ], 422);
        }

        $validated =
            $request->validate([
                'title' => [
                    'nullable',
                    'string',
                    'max:200',
                ],

                'description' => [
                    'nullable',
                    'string',
                ],
            ]);

        /*
        |--------------------------------------------------------------------------
        | NOMOR SERTIFIKAT
        |--------------------------------------------------------------------------
        */

        do {
            $certificateNumber =
                'HIMATIF-' .
                now()->format('Ymd') .
                '-' .
                strtoupper(
                    Str::random(8)
                );

            $exists =
                Certificate::query()
                    ->where(
                        'certificate_number',
                        $certificateNumber
                    )
                    ->exists();
        } while ($exists);

        $certificate =
            Certificate::query()->create([
                'activity_id' =>
                    $activityId,

                'user_id' =>
                    $userId,

                'issued_by' =>
                    $issuer->id,

                'certificate_number' =>
                    $certificateNumber,

                'title' =>
                    $validated['title']
                    ?? 'Sertifikat ' .
                    $activity->title,

                'description' =>
                    $validated['description']
                    ?? 'Sertifikat keikutsertaan pada kegiatan ' .
                    $activity->title,

                'file_path' =>
                    null,

                'issued_at' =>
                    now(),

                'status' =>
                    'issued',
            ]);

        $certificate->load([
            'activity',
            'user:id,name,email',
            'issuer:id,name,email',
        ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Sertifikat berhasil diterbitkan. Silakan upload gambar sertifikat.',
            'data' =>
                $certificate,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | UPLOAD PNG / JPG
    |--------------------------------------------------------------------------
    */

    public function uploadFile(
        Request $request,
        int $certificateId
    ): JsonResponse {
        $user = $request->user();

        if (
            !$user ||
            !in_array(
                strtolower(
                    (string) $user->role
                ),
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

        $certificate =
            Certificate::query()
                ->find(
                    $certificateId
                );

        if (!$certificate) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Sertifikat tidak ditemukan.',
            ], 404);
        }

        $request->validate([
            'certificate_file' => [
                'required',
                'file',
                'image',
                'mimes:png,jpg,jpeg',
                'max:5120',
            ],
        ]);

        $file =
            $request->file(
                'certificate_file'
            );

        if (!$file) {
            return response()->json([
                'success' => false,
                'message' =>
                    'File sertifikat tidak ditemukan.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | HAPUS FILE LAMA
        |--------------------------------------------------------------------------
        */

        if (
            $certificate->file_path &&
            Storage::disk('public')
                ->exists(
                    $certificate->file_path
                )
        ) {
            Storage::disk('public')
                ->delete(
                    $certificate->file_path
                );
        }

        $safeNumber =
            preg_replace(
                '/[^A-Za-z0-9\-]/',
                '-',
                $certificate->certificate_number
                    ?? ('CERT-' . $certificate->id)
            );

        $extension =
            strtolower(
                $file->getClientOriginalExtension()
            );

        $fileName =
            'sertifikat-' .
            $safeNumber .
            '-' .
            time() .
            '.' .
            $extension;

        $path =
            $file->storeAs(
                'certificates',
                $fileName,
                'public'
            );

        $certificate->update([
            'file_path' =>
                $path,
        ]);

        $certificate->refresh();

        $certificate->load([
            'activity',
            'user:id,name,email',
            'issuer:id,name,email',
        ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Gambar sertifikat berhasil diunggah.',
            'data' =>
                $certificate,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CABUT SERTIFIKAT
    |--------------------------------------------------------------------------
    */

    public function revoke(
        Request $request,
        int $certificateId
    ): JsonResponse {
        $user = $request->user();

        if (
            !$user ||
            !in_array(
                strtolower(
                    (string) $user->role
                ),
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

        $certificate =
            Certificate::query()
                ->find(
                    $certificateId
                );

        if (!$certificate) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Sertifikat tidak ditemukan.',
            ], 404);
        }

        $certificate->update([
            'status' =>
                'revoked',
        ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Sertifikat berhasil dicabut.',
            'data' =>
                $certificate,
        ]);
    }
}
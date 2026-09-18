<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityRegistration;
use App\Models\Certificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CertificateController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SERTIFIKAT USER LOGIN
    |--------------------------------------------------------------------------
    */

    public function myCertificates(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum login.',
            ], 401);
        }

        $certificates = Certificate::query()
            ->with([
                'activity',
                'issuer:id,name,email',
                'user:id,name,email',
            ])
            ->where('user_id', $user->id)
            ->where('status', 'issued')
            ->latest('issued_at')
            ->get();

        $data = $certificates->map(function (Certificate $certificate) {
            $item = $certificate->toArray();

            $item['file_url'] = $this->makePublicUrl(
                $certificate->file_path
            );

            return $item;
        });

        return response()->json([
            'success' => true,
            'message' => 'Data sertifikat berhasil diambil.',
            'data' => $data,
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

        if (!$this->canManageCertificate($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses.',
            ], 403);
        }

        $activity = Activity::query()->find($activityId);

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Kegiatan tidak ditemukan.',
            ], 404);
        }

        $registrations = ActivityRegistration::query()
            ->with([
                'user',
                'user.member',
            ])
            ->where('activity_id', $activityId)
            ->where('status', 'registered')
            ->orderBy('registered_at')
            ->get();

        $participants = $registrations->map(
            function (ActivityRegistration $registration) use ($activityId) {
                $certificate = Certificate::query()
                    ->where('activity_id', $activityId)
                    ->where('user_id', $registration->user_id)
                    ->first();

                $attendanceStatus = null;

                if (Schema::hasTable('activity_attendances')) {
                    $attendance = DB::table('activity_attendances')
                        ->where('activity_id', $activityId)
                        ->where('user_id', $registration->user_id)
                        ->first();

                    if ($attendance) {
                        $attendanceStatus =
                            $attendance->attendance_status ?? null;
                    }
                }

                $fileExists = false;
                $certificateData = null;

                if ($certificate) {
                    $certificateData = $certificate->toArray();

                    $certificateData['file_url'] =
                        $this->makePublicUrl(
                            $certificate->file_path
                        );

                    if (
                        is_string($certificate->file_path) &&
                        trim($certificate->file_path) !== ''
                    ) {
                        $fileExists = Storage::disk('public')
                            ->exists($certificate->file_path);
                    }
                }

                return [
                    'registration' => $registration,

                    'attendance_status' =>
                        $attendanceStatus,

                    'has_attended' =>
                        $attendanceStatus === 'present',

                    'eligible_for_certificate' =>
                        $attendanceStatus === 'present',

                    /*
                    |--------------------------------------------------------------------------
                    | HANYA DIANGGAP SUDAH ADA JIKA FILE BENAR-BENAR ADA
                    |--------------------------------------------------------------------------
                    */

                    'certificate_issued' =>
                        $certificate !== null &&
                        $certificate->status === 'issued' &&
                        $fileExists,

                    'certificate' =>
                        $certificateData,
                ];
            }
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Data peserta sertifikat berhasil diambil.',

            'data' => [
                'activity' => $activity,
                'total' => $participants->count(),
                'participants' => $participants->values(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPLOAD TEMPLATE
    |--------------------------------------------------------------------------
    */

    public function uploadTemplate(
        Request $request,
        int $activityId
    ): JsonResponse {
        $user = $request->user();

        if (!$this->canManageCertificate($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses.',
            ], 403);
        }

        $activity = Activity::query()->find($activityId);

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Kegiatan tidak ditemukan.',
            ], 404);
        }

        $request->validate([
            'template' => [
                'nullable',
                'file',
                'image',
                'mimes:png,jpg,jpeg',
                'max:10240',
            ],

            'certificate_template' => [
                'nullable',
                'file',
                'image',
                'mimes:png,jpg,jpeg',
                'max:10240',
            ],

            'certificate_template_file' => [
                'nullable',
                'file',
                'image',
                'mimes:png,jpg,jpeg',
                'max:10240',
            ],
        ]);

        $file =
            $request->file('certificate_template')
            ?? $request->file('certificate_template_file')
            ?? $request->file('template');

        if (!$file) {
            return response()->json([
                'success' => false,
                'message' =>
                    'File template sertifikat wajib dipilih.',
            ], 422);
        }

        $templateColumn = $this->getTemplateColumn();

        if (!$templateColumn) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kolom template sertifikat tidak ditemukan.',
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | HAPUS TEMPLATE LAMA
        |--------------------------------------------------------------------------
        */

        $oldTemplate = $activity->getAttribute($templateColumn);

        if (
            is_string($oldTemplate) &&
            trim($oldTemplate) !== '' &&
            Storage::disk('public')->exists($oldTemplate)
        ) {
            Storage::disk('public')->delete($oldTemplate);
        }

        /*
        |--------------------------------------------------------------------------
        | SIMPAN TEMPLATE
        |--------------------------------------------------------------------------
        */

        $extension = strtolower(
            $file->getClientOriginalExtension()
        );

        $fileName =
            'template-activity-' .
            $activityId .
            '-' .
            time() .
            '.' .
            $extension;

        $path = $file->storeAs(
            'certificates/templates',
            $fileName,
            'public'
        );

        $activity->setAttribute(
            $templateColumn,
            $path
        );

        $activity->save();
        $activity->refresh();

        return response()->json([
            'success' => true,
            'message' =>
                'Template sertifikat berhasil diunggah.',

            'data' => [
                'activity_id' =>
                    $activity->id,

                'activity_title' =>
                    $activity->title,

                'template_column' =>
                    $templateColumn,

                'template_path' =>
                    $path,

                'template_url' =>
                    $this->makePublicUrl($path),

                /*
                |--------------------------------------------------------------------------
                | SETTING POSISI NAMA
                |--------------------------------------------------------------------------
                */

                'certificate_name_x' =>
                    $activity->certificate_name_x ?? null,

                'certificate_name_y' =>
                    $activity->certificate_name_y ?? null,

                'certificate_font_size' =>
                    $activity->certificate_font_size ?? null,

                'certificate_font_color' =>
                    $activity->certificate_font_color ?? null,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE SEMUA PESERTA HADIR
    |--------------------------------------------------------------------------
    */

    public function generateAll(
        Request $request,
        int $activityId
    ): JsonResponse {
        $issuer = $request->user();

        if (!$this->canManageCertificate($issuer)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses.',
            ], 403);
        }

        $activity = Activity::query()->find($activityId);

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Kegiatan tidak ditemukan.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | TEMPLATE
        |--------------------------------------------------------------------------
        */

        $templateColumn = $this->getTemplateColumn();

        if (!$templateColumn) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kolom template sertifikat tidak ditemukan.',
            ], 500);
        }

        $templatePath = $activity->getAttribute($templateColumn);

        if (
            !is_string($templatePath) ||
            trim($templatePath) === ''
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Template sertifikat belum diunggah.',
            ], 422);
        }

        $templatePath = trim($templatePath);

        if (
            !Storage::disk('public')
                ->exists($templatePath)
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'File template sertifikat tidak ditemukan.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | GD / FREETYPE
        |--------------------------------------------------------------------------
        */

        if (
            !function_exists('imagecreatefrompng') ||
            !function_exists('imagecreatefromjpeg') ||
            !function_exists('imagettftext') ||
            !function_exists('imagettfbbox')
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'PHP GD / FreeType belum aktif.',
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | FONT
        |--------------------------------------------------------------------------
        */

        $fontPath = $this->findCertificateFont();

        if (!$fontPath) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Font sertifikat tidak ditemukan.',
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | ABSENSI
        |--------------------------------------------------------------------------
        */

        if (!Schema::hasTable('activity_attendances')) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Tabel activity_attendances belum tersedia.',
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | PESERTA HADIR
        |--------------------------------------------------------------------------
        */

        $participantsQuery = DB::table(
            'activity_attendances as attendances'
        )
            ->join(
                'users',
                'users.id',
                '=',
                'attendances.user_id'
            )
            ->where(
                'attendances.activity_id',
                $activityId
            )
            ->where(
                'attendances.attendance_status',
                'present'
            );

        /*
        |--------------------------------------------------------------------------
        | PASTIKAN TERDAFTAR
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasTable('activity_registrations') &&
            Schema::hasColumn(
                'activity_registrations',
                'activity_id'
            ) &&
            Schema::hasColumn(
                'activity_registrations',
                'user_id'
            )
        ) {
            $participantsQuery->join(
                'activity_registrations as registrations',
                function ($join) {
                    $join
                        ->on(
                            'registrations.activity_id',
                            '=',
                            'attendances.activity_id'
                        )
                        ->on(
                            'registrations.user_id',
                            '=',
                            'attendances.user_id'
                        );
                }
            );

            if (
                Schema::hasColumn(
                    'activity_registrations',
                    'status'
                )
            ) {
                $participantsQuery->where(
                    'registrations.status',
                    'registered'
                );
            }
        }

        $participants = $participantsQuery
            ->select([
                'users.id as user_id',
                'users.name',
                'users.email',
            ])
            ->distinct()
            ->orderBy('users.name')
            ->get();

        if ($participants->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Belum ada peserta dengan status HADIR.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | OUTPUT
        |--------------------------------------------------------------------------
        */

        $outputDirectory =
            'certificates/generated/' .
            $activityId;

        Storage::disk('public')
            ->makeDirectory($outputDirectory);

        $generated = [];
        $errors = [];

        foreach ($participants as $participant) {
            try {
                /*
                |--------------------------------------------------------------------------
                | CARI RECORD LAMA
                |--------------------------------------------------------------------------
                */

                $certificate = Certificate::query()
                    ->where('activity_id', $activityId)
                    ->where(
                        'user_id',
                        $participant->user_id
                    )
                    ->first();

                /*
                |--------------------------------------------------------------------------
                | NOMOR SERTIFIKAT
                |--------------------------------------------------------------------------
                */

                if (
                    $certificate &&
                    !empty($certificate->certificate_number)
                ) {
                    $certificateNumber =
                        $certificate->certificate_number;
                } else {
                    $certificateNumber =
                        $this->generateCertificateNumber();
                }

                /*
                |--------------------------------------------------------------------------
                | NAMA FILE
                |--------------------------------------------------------------------------
                */

                $safeName = Str::slug(
                    (string) $participant->name
                );

                if ($safeName === '') {
                    $safeName =
                        'peserta-' .
                        $participant->user_id;
                }

                $fileName =
                    'sertifikat-' .
                    $participant->user_id .
                    '-' .
                    $safeName .
                    '-' .
                    Str::lower(Str::random(6)) .
                    '.png';

                $newFilePath =
                    $outputDirectory .
                    '/' .
                    $fileName;

                /*
                |--------------------------------------------------------------------------
                | GENERATE
                |--------------------------------------------------------------------------
                */

                $this->renderCertificateImage(
                    activity: $activity,
                    templatePath: $templatePath,
                    outputPath: $newFilePath,
                    participantName:
                        (string) $participant->name,
                    fontPath: $fontPath
                );

                $oldFilePath =
                    $certificate?->file_path;

                /*
                |--------------------------------------------------------------------------
                | UPDATE / CREATE
                |--------------------------------------------------------------------------
                */

                if ($certificate) {
                    $certificate->update([
                        'issued_by' =>
                            $issuer->id,

                        'certificate_number' =>
                            $certificateNumber,

                        'title' =>
                            'Sertifikat ' .
                            $activity->title,

                        'description' =>
                            'Sertifikat keikutsertaan pada kegiatan ' .
                            $activity->title,

                        'file_path' =>
                            $newFilePath,

                        'issued_at' =>
                            now(),

                        'status' =>
                            'issued',
                    ]);
                } else {
                    $certificate = Certificate::query()
                        ->create([
                            'activity_id' =>
                                $activityId,

                            'user_id' =>
                                $participant->user_id,

                            'issued_by' =>
                                $issuer->id,

                            'certificate_number' =>
                                $certificateNumber,

                            'title' =>
                                'Sertifikat ' .
                                $activity->title,

                            'description' =>
                                'Sertifikat keikutsertaan pada kegiatan ' .
                                $activity->title,

                            'file_path' =>
                                $newFilePath,

                            'issued_at' =>
                                now(),

                            'status' =>
                                'issued',
                        ]);
                }

                /*
                |--------------------------------------------------------------------------
                | HAPUS FILE LAMA SETELAH FILE BARU BERHASIL
                |--------------------------------------------------------------------------
                */

                if (
                    is_string($oldFilePath) &&
                    trim($oldFilePath) !== '' &&
                    $oldFilePath !== $newFilePath &&
                    Storage::disk('public')
                        ->exists($oldFilePath)
                ) {
                    Storage::disk('public')
                        ->delete($oldFilePath);
                }

                $certificate->refresh();

                $generated[] = [
                    'certificate_id' =>
                        $certificate->id,

                    'user_id' =>
                        $participant->user_id,

                    'name' =>
                        $participant->name,

                    'email' =>
                        $participant->email,

                    'certificate_number' =>
                        $certificateNumber,

                    'file_path' =>
                        $newFilePath,

                    'file_url' =>
                        $this->makePublicUrl(
                            $newFilePath
                        ),
                ];
            } catch (Throwable $e) {
                $errors[] = [
                    'user_id' =>
                        $participant->user_id,

                    'name' =>
                        $participant->name,

                    'error' =>
                        $e->getMessage(),
                ];
            }
        }

        $success = count($generated) > 0;

        if (
            $success &&
            count($errors) === 0
        ) {
            $message =
                count($generated) .
                ' sertifikat berhasil dibuat.';
        } elseif ($success) {
            $message =
                count($generated) .
                ' sertifikat berhasil dibuat, ' .
                count($errors) .
                ' gagal.';
        } else {
            $message =
                'Tidak ada sertifikat yang berhasil dibuat.';
        }

        return response()->json([
            'success' => $success,
            'message' => $message,

            'data' => [
                'activity_id' =>
                    $activityId,

                'activity_title' =>
                    $activity->title,

                'present_participants' =>
                    $participants->count(),

                'generated_count' =>
                    count($generated),

                'error_count' =>
                    count($errors),

                'certificates' =>
                    $generated,

                'errors' =>
                    $errors,
            ],
        ], $success ? 200 : 500);
    }

    /*
    |--------------------------------------------------------------------------
    | ISSUE MANUAL
    |--------------------------------------------------------------------------
    */

    public function issue(
        Request $request,
        int $activityId,
        int $userId
    ): JsonResponse {
        $issuer = $request->user();

        if (!$this->canManageCertificate($issuer)) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anda tidak memiliki akses.',
            ], 403);
        }

        $activity = Activity::query()->find($activityId);

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Kegiatan tidak ditemukan.',
            ], 404);
        }

        $registration = ActivityRegistration::query()
            ->where('activity_id', $activityId)
            ->where('user_id', $userId)
            ->first();

        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Mahasiswa belum terdaftar.',
            ], 422);
        }

        if (Schema::hasTable('activity_attendances')) {
            $attendance = DB::table(
                'activity_attendances'
            )
                ->where('activity_id', $activityId)
                ->where('user_id', $userId)
                ->first();

            if (
                !$attendance ||
                (
                    $attendance->attendance_status
                    ?? null
                ) !== 'present'
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Sertifikat hanya untuk peserta yang HADIR.',
                ], 422);
            }
        }

        $existing = Certificate::query()
            ->where('activity_id', $activityId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            if ($existing->status === 'revoked') {
                $existing->update([
                    'status' => 'issued',
                    'issued_by' => $issuer->id,
                    'issued_at' => now(),
                ]);

                $existing->refresh();

                return response()->json([
                    'success' => true,
                    'message' =>
                        'Sertifikat berhasil diterbitkan kembali.',
                    'data' =>
                        $this->certificatePayload($existing),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' =>
                    'Sertifikat peserta ini sudah diterbitkan.',
                'data' =>
                    $this->certificatePayload($existing),
            ], 422);
        }

        $validated = $request->validate([
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

        $certificate = Certificate::query()->create([
            'activity_id' =>
                $activityId,

            'user_id' =>
                $userId,

            'issued_by' =>
                $issuer->id,

            'certificate_number' =>
                $this->generateCertificateNumber(),

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
                'Sertifikat diterbitkan. Silakan upload file sertifikat.',
            'data' =>
                $this->certificatePayload($certificate),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | UPLOAD FILE MANUAL
    |--------------------------------------------------------------------------
    */

    public function uploadFile(
        Request $request,
        int $certificateId
    ): JsonResponse {
        $user = $request->user();

        if (!$this->canManageCertificate($user)) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anda tidak memiliki akses.',
            ], 403);
        }

        $certificate = Certificate::query()
            ->find($certificateId);

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

        $file = $request->file('certificate_file');

        if (!$file) {
            return response()->json([
                'success' => false,
                'message' =>
                    'File sertifikat tidak ditemukan.',
            ], 422);
        }

        if (
            is_string($certificate->file_path) &&
            trim($certificate->file_path) !== '' &&
            Storage::disk('public')
                ->exists($certificate->file_path)
        ) {
            Storage::disk('public')
                ->delete($certificate->file_path);
        }

        $safeNumber = preg_replace(
            '/[^A-Za-z0-9\-]/',
            '-',
            $certificate->certificate_number
                ?? (
                    'CERT-' .
                    $certificate->id
                )
        );

        $extension = strtolower(
            $file->getClientOriginalExtension()
        );

        $fileName =
            'sertifikat-' .
            $safeNumber .
            '-' .
            time() .
            '.' .
            $extension;

        $path = $file->storeAs(
            'certificates',
            $fileName,
            'public'
        );

        $certificate->update([
            'file_path' => $path,
        ]);

        $certificate->refresh();

        return response()->json([
            'success' => true,
            'message' =>
                'Gambar sertifikat berhasil diunggah.',
            'data' =>
                $this->certificatePayload($certificate),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | REVOKE
    |--------------------------------------------------------------------------
    */

    public function revoke(
        Request $request,
        int $certificateId
    ): JsonResponse {
        $user = $request->user();

        if (!$this->canManageCertificate($user)) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anda tidak memiliki akses.',
            ], 403);
        }

        $certificate = Certificate::query()
            ->find($certificateId);

        if (!$certificate) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Sertifikat tidak ditemukan.',
            ], 404);
        }

        $certificate->update([
            'status' => 'revoked',
        ]);

        $certificate->refresh();

        return response()->json([
            'success' => true,
            'message' =>
                'Sertifikat berhasil dicabut.',
            'data' =>
                $this->certificatePayload($certificate),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | RENDER SERTIFIKAT
    |--------------------------------------------------------------------------
    |
    | Backend hanya menulis NAMA PESERTA.
    |
    | certificate_name_x:
    | posisi tengah X nama.
    |
    | certificate_name_y:
    | baseline Y nama.
    |
    | certificate_font_size:
    | ukuran font.
    |
    | certificate_font_color:
    | contoh #092C5B.
    |
    | Sistem juga punya fallback otomatis jika nilai database tidak cocok
    | dengan ukuran template.
    |
    */

    private function renderCertificateImage(
        Activity $activity,
        string $templatePath,
        string $outputPath,
        string $participantName,
        string $fontPath
    ): void {
        $absoluteTemplatePath =
            Storage::disk('public')
                ->path($templatePath);

        if (!is_file($absoluteTemplatePath)) {
            throw new RuntimeException(
                'Template sertifikat tidak ditemukan.'
            );
        }

        $imageInfo = @getimagesize(
            $absoluteTemplatePath
        );

        if (!$imageInfo) {
            throw new RuntimeException(
                'Template sertifikat tidak dapat dibaca.'
            );
        }

        $mime = strtolower(
            (string) (
                $imageInfo['mime']
                ?? ''
            )
        );

        if ($mime === 'image/png') {
            $image = @imagecreatefrompng(
                $absoluteTemplatePath
            );
        } elseif (
            $mime === 'image/jpeg' ||
            $mime === 'image/jpg'
        ) {
            $image = @imagecreatefromjpeg(
                $absoluteTemplatePath
            );
        } else {
            throw new RuntimeException(
                'Template harus PNG, JPG, atau JPEG.'
            );
        }

        if (!$image) {
            throw new RuntimeException(
                'Gagal membuka template sertifikat.'
            );
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $width = imagesx($image);
        $height = imagesy($image);

        if (
            $width <= 0 ||
            $height <= 0
        ) {
            imagedestroy($image);

            throw new RuntimeException(
                'Ukuran template tidak valid.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | NAMA
        |--------------------------------------------------------------------------
        */

        $participantName = trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $participantName
            ) ?? $participantName
        );

        if ($participantName === '') {
            $participantName = 'PESERTA';
        }

        if (function_exists('mb_strtoupper')) {
            $participantName = mb_strtoupper(
                $participantName,
                'UTF-8'
            );
        } else {
            $participantName = strtoupper(
                $participantName
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FONT SIZE DARI DATABASE
        |--------------------------------------------------------------------------
        */

        $configuredFontSize = (int) (
            $activity->certificate_font_size
            ?? 0
        );

        if (
            $configuredFontSize <= 0 ||
            $configuredFontSize > 100
        ) {
            $configuredFontSize = (int) round(
                $width * 0.023
            );
        }

        $fontSize = max(
            16,
            min(
                64,
                $configuredFontSize
            )
        );

        /*
        |--------------------------------------------------------------------------
        | CENTER X
        |--------------------------------------------------------------------------
        */

        $configuredX = (int) (
            $activity->certificate_name_x
            ?? 0
        );

        /*
        |--------------------------------------------------------------------------
        | Jika X terlalu jauh dari area tengah, gunakan tengah otomatis.
        |--------------------------------------------------------------------------
        */

        if (
            $configuredX <= 0 ||
            $configuredX < ($width * 0.35) ||
            $configuredX > ($width * 0.65)
        ) {
            $centerX = (int) round(
                $width / 2
            );
        } else {
            $centerX = $configuredX;
        }

        /*
        |--------------------------------------------------------------------------
        | Y DARI DATABASE
        |--------------------------------------------------------------------------
        */

        $configuredY = (int) (
            $activity->certificate_name_y
            ?? 0
        );

        /*
        |--------------------------------------------------------------------------
        | Jika nilai Y terlalu rendah/tinggi terhadap template,
        | gunakan posisi default area "Diberikan kepada".
        |--------------------------------------------------------------------------
        */

        if (
            $configuredY <= 0 ||
            $configuredY < ($height * 0.18) ||
            $configuredY > ($height * 0.45)
        ) {
            $baselineY = (int) round(
                $height * 0.31
            );
        } else {
            $baselineY = $configuredY;
        }

        /*
        |--------------------------------------------------------------------------
        | WARNA
        |--------------------------------------------------------------------------
        */

        $hexColor = (string) (
            $activity->certificate_font_color
            ?? '#092C5B'
        );

        [$red, $green, $blue] =
            $this->hexToRgb(
                $hexColor
            );

        $textColor = imagecolorallocate(
            $image,
            $red,
            $green,
            $blue
        );

        /*
        |--------------------------------------------------------------------------
        | AUTO SHRINK NAMA PANJANG
        |--------------------------------------------------------------------------
        */

        $maxTextWidth =
            $width * 0.48;

        do {
            $box = imagettfbbox(
                $fontSize,
                0,
                $fontPath,
                $participantName
            );

            if ($box === false) {
                imagedestroy($image);

                throw new RuntimeException(
                    'Gagal menghitung ukuran nama.'
                );
            }

            $textWidth = abs(
                $box[2] -
                $box[0]
            );

            if (
                $textWidth > $maxTextWidth &&
                $fontSize > 14
            ) {
                $fontSize--;
            }
        } while (
            $textWidth > $maxTextWidth &&
            $fontSize > 14
        );

        /*
        |--------------------------------------------------------------------------
        | CENTER TEXT AKURAT
        |--------------------------------------------------------------------------
        */

        $box = imagettfbbox(
            $fontSize,
            0,
            $fontPath,
            $participantName
        );

        if ($box === false) {
            imagedestroy($image);

            throw new RuntimeException(
                'Gagal menghitung posisi nama.'
            );
        }

        $textWidth =
            $box[2] -
            $box[0];

        $x = (int) round(
            $centerX -
            ($textWidth / 2) -
            $box[0]
        );

        $writeResult = imagettftext(
            $image,
            $fontSize,
            0,
            $x,
            $baselineY,
            $textColor,
            $fontPath,
            $participantName
        );

        if ($writeResult === false) {
            imagedestroy($image);

            throw new RuntimeException(
                'Gagal menulis nama peserta.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | OUTPUT
        |--------------------------------------------------------------------------
        */

        $absoluteOutputPath =
            Storage::disk('public')
                ->path($outputPath);

        $directory = dirname(
            $absoluteOutputPath
        );

        if (!is_dir($directory)) {
            $created = @mkdir(
                $directory,
                0775,
                true
            );

            if (
                !$created &&
                !is_dir($directory)
            ) {
                imagedestroy($image);

                throw new RuntimeException(
                    'Folder sertifikat tidak dapat dibuat.'
                );
            }
        }

        $saved = imagepng(
            $image,
            $absoluteOutputPath,
            6
        );

        imagedestroy($image);

        if (!$saved) {
            throw new RuntimeException(
                'Gagal menyimpan sertifikat.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ROLE
    |--------------------------------------------------------------------------
    */

    private function canManageCertificate(
        mixed $user
    ): bool {
        if (!$user) {
            return false;
        }

        $role = strtolower(
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
    | KOLOM TEMPLATE
    |--------------------------------------------------------------------------
    */

    private function getTemplateColumn(): ?string
    {
        $columns = [
            'certificate_template_path',
            'certificate_template',
            'certificate_template_file',
            'certificate_template_image',
        ];

        foreach ($columns as $column) {
            if (
                Schema::hasColumn(
                    'activities',
                    $column
                )
            ) {
                return $column;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | NOMOR SERTIFIKAT
    |--------------------------------------------------------------------------
    */

    private function generateCertificateNumber(): string
    {
        do {
            $number =
                'HIMATIF-' .
                now()->format('Ymd') .
                '-' .
                strtoupper(
                    Str::random(8)
                );

            $exists = Certificate::query()
                ->where(
                    'certificate_number',
                    $number
                )
                ->exists();
        } while ($exists);

        return $number;
    }

    /*
    |--------------------------------------------------------------------------
    | FONT
    |--------------------------------------------------------------------------
    */

    private function findCertificateFont(): ?string
    {
        $fonts = [
            public_path(
                'fonts/Poppins-SemiBold.ttf'
            ),

            public_path(
                'fonts/Poppins-Bold.ttf'
            ),

            public_path(
                'fonts/Poppins-Regular.ttf'
            ),

            'C:\\Windows\\Fonts\\arialbd.ttf',
            'C:\\Windows\\Fonts\\arial.ttf',
            'C:\\Windows\\Fonts\\calibrib.ttf',
            'C:\\Windows\\Fonts\\calibri.ttf',
            'C:\\Windows\\Fonts\\segoeuib.ttf',
            'C:\\Windows\\Fonts\\segoeui.ttf',

            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
        ];

        foreach ($fonts as $font) {
            if (
                is_file($font) &&
                is_readable($font)
            ) {
                return $font;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | WARNA HEX
    |--------------------------------------------------------------------------
    */

    private function hexToRgb(
        string $hex
    ): array {
        $hex = trim($hex);

        $hex = ltrim(
            $hex,
            '#'
        );

        if (strlen($hex) === 3) {
            $hex =
                $hex[0] .
                $hex[0] .
                $hex[1] .
                $hex[1] .
                $hex[2] .
                $hex[2];
        }

        if (
            strlen($hex) !== 6 ||
            !ctype_xdigit($hex)
        ) {
            $hex = '092C5B';
        }

        return [
            hexdec(
                substr($hex, 0, 2)
            ),

            hexdec(
                substr($hex, 2, 2)
            ),

            hexdec(
                substr($hex, 4, 2)
            ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | URL FILE PUBLIC
    |--------------------------------------------------------------------------
    |
    | Tidak memakai:
    |
    | Storage::disk('public')->url()
    |
    | sehingga warning Intelephense hilang.
    |
    */

    private function makePublicUrl(
        mixed $path
    ): string {
        if (
            !is_string($path) ||
            trim($path) === ''
        ) {
            return '';
        }

        return '/storage/' .
            ltrim(
                trim($path),
                '/'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | PAYLOAD CERTIFICATE
    |--------------------------------------------------------------------------
    */

    private function certificatePayload(
        Certificate $certificate
    ): array {
        $certificate->loadMissing([
            'activity',
            'user:id,name,email',
            'issuer:id,name,email',
        ]);

        $data = $certificate->toArray();

        $data['file_url'] =
            $this->makePublicUrl(
                $certificate->file_path
            );

        return $data;
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\CandidateApplication;
use App\Models\Election;
use App\Models\ElectionCandidate;
use App\Models\Member;

use App\Services\CandidateEligibilityService;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use Illuminate\Validation\Rule;

class CandidateApplicationController extends Controller
{
    protected CandidateEligibilityService $eligibilityService;

    public function __construct(
        CandidateEligibilityService $eligibilityService
    ) {
        $this->eligibilityService = $eligibilityService;
    }

    /*
    |--------------------------------------------------------------------------
    | DAFTAR SEMUA PENGAJUAN
    |--------------------------------------------------------------------------
    |
    | Untuk admin / pengurus.
    |
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

        $applications =
            CandidateApplication::query()
                ->where(
                    'election_id',
                    $electionId
                )
                ->with([
                    'user:id,name,email,role,status',

                    /*
                     * Ditambahkan generation dan gpa
                     * supaya admin dapat melihat data
                     * yang dipakai untuk seleksi.
                     */
                    'member:id,user_id,name,nim,email,study_program,generation,gpa,member_status,profile_photo',

                    'reviewer:id,name,email',
                ])
                ->orderByRaw(
                    "
                    CASE
                        WHEN status = 'pending'
                            THEN 1
                        WHEN status = 'approved'
                            THEN 2
                        WHEN status = 'rejected'
                            THEN 3
                        ELSE 4
                    END
                    "
                )
                ->orderByDesc('id')
                ->get();

        return response()->json([
            'success' => true,

            'message' =>
                'Data pengajuan kandidat berhasil diambil.',

            'data' =>
                $applications,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS PENGAJUAN SAYA
    |--------------------------------------------------------------------------
    */

    public function myApplication(
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

        $application =
            CandidateApplication::query()
                ->where(
                    'election_id',
                    $electionId
                )
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->with([
                    'member:id,user_id,name,nim,email,study_program,generation,gpa,member_status,profile_photo',
                ])
                ->first();

        return response()->json([
            'success' => true,

            'message' =>
                'Status pengajuan berhasil diambil.',

            'data' => [
                'has_application' =>
                    $application !== null,

                'application' =>
                    $application,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | MAHASISWA MENDAFTAR
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request,
        int $electionId
    ): JsonResponse {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | HARUS MAHASISWA
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
                    'Hanya mahasiswa yang dapat mengajukan diri sebagai calon Bupati.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | USER HARUS AKTIF
        |--------------------------------------------------------------------------
        */

        if (
            strtolower(
                trim(
                    (string) $user->status
                )
            ) !== 'active'
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Akun Anda belum aktif.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | PEMILIHAN
        |--------------------------------------------------------------------------
        */

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
                    (string) $election->status
                )
            ) !== 'draft'
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Pendaftaran calon Bupati sudah ditutup.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CEK MASA PENDAFTARAN
        |--------------------------------------------------------------------------
        */

        if (
            $election->registration_start &&
            now()->lt(
                $election->registration_start
            )
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Pendaftaran calon Bupati belum dibuka.',
            ], 422);
        }

        if (
            $election->registration_end &&
            now()->gt(
                $election->registration_end
            )
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Pendaftaran calon Bupati sudah berakhir.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CARI MEMBER BERDASARKAN USER LOGIN
        |--------------------------------------------------------------------------
        */

        $member =
            Member::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();

        if (!$member) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Data keanggotaan Anda tidak ditemukan.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | MEMBER HARUS AKTIF
        |--------------------------------------------------------------------------
        */

        $memberStatus =
            strtolower(
                trim(
                    (string) (
                        $member->member_status
                        ?? ''
                    )
                )
            );

        if (
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
                    'Hanya anggota HIMATIF aktif yang dapat mendaftar sebagai calon Bupati.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CEK NIM
        |--------------------------------------------------------------------------
        */

        if (
            trim(
                (string) $member->nim
            ) === ''
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Data NIM Anda belum tersedia.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | SELEKSI KELAYAKAN OLEH SISTEM
        |--------------------------------------------------------------------------
        |
        | Sistem mengecek:
        |
        | 1. Status anggota
        | 2. Angkatan
        | 3. IPK
        | 4. Sertifikat (belum diberlakukan)
        |
        */

        $eligibility =
            $this->eligibilityService
                ->evaluate(
                    $member,
                    $election
                );

        /*
        |--------------------------------------------------------------------------
        | TIDAK MEMENUHI PERSYARATAN
        |--------------------------------------------------------------------------
        */

        if (
            $eligibility['eligible'] !== true
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Anda belum memenuhi persyaratan sebagai calon Bupati.',

                /*
                 * Contoh:
                 *
                 * [
                 *   "IPK belum memenuhi batas minimum 3.00."
                 * ]
                 */
                'reasons' =>
                    $eligibility['reasons'],

                /*
                 * Detail ditampilkan Flutter
                 * untuk menunjukkan syarat
                 * yang lolos / gagal.
                 */
                'eligibility' =>
                    $eligibility,
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CEK CAN APPLY
        |--------------------------------------------------------------------------
        */

        if (
            $eligibility['can_apply'] !== true
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Anda belum dapat mengajukan diri pada pemilihan ini.',

                'eligibility' =>
                    $eligibility,
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CEK SUDAH MENJADI KANDIDAT RESMI
        |--------------------------------------------------------------------------
        */

        $alreadyCandidate =
            ElectionCandidate::query()
                ->where(
                    'election_id',
                    $electionId
                )
                ->where(
                    'nim',
                    $member->nim
                )
                ->exists();

        if ($alreadyCandidate) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Anda sudah terdaftar sebagai calon Bupati resmi.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CEK PENGAJUAN LAMA
        |--------------------------------------------------------------------------
        */

        $existing =
            CandidateApplication::query()
                ->where(
                    'election_id',
                    $electionId
                )
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();

        if (
            $existing &&
            $existing->status ===
                'pending'
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Pengajuan Anda masih menunggu verifikasi.',
            ], 422);
        }

        if (
            $existing &&
            $existing->status ===
                'approved'
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Pengajuan Anda sudah disetujui.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDASI FORM
        |--------------------------------------------------------------------------
        */

        $validated =
            $request->validate([
                'vision' => [
                    'required',
                    'string',
                    'min:10',
                    'max:5000',
                ],

                'mission' => [
                    'required',
                    'string',
                    'min:10',
                    'max:5000',
                ],

                'photo' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:3072',
                ],
            ]);

        /*
        |--------------------------------------------------------------------------
        | UPLOAD FOTO
        |--------------------------------------------------------------------------
        */

        $photoPath = null;

        if (
            $request->hasFile(
                'photo'
            )
        ) {
            $photoPath =
                $request
                    ->file('photo')
                    ->store(
                        'candidate-applications',
                        'public'
                    );
        }

        /*
        |--------------------------------------------------------------------------
        | DAFTAR ULANG SETELAH DITOLAK
        |--------------------------------------------------------------------------
        */

        if (
            $existing &&
            $existing->status ===
                'rejected'
        ) {
            if (
                $photoPath &&
                $existing->photo_path
            ) {
                Storage::disk(
                    'public'
                )->delete(
                    $existing->photo_path
                );
            }

            $existing->update([
                'member_id' =>
                    $member->id,

                'vision' =>
                    trim(
                        $validated['vision']
                    ),

                'mission' =>
                    trim(
                        $validated['mission']
                    ),

                'photo_path' =>
                    $photoPath
                    ?? $existing->photo_path,

                'status' =>
                    'pending',

                'rejection_reason' =>
                    null,

                'reviewed_by' =>
                    null,

                'reviewed_at' =>
                    null,
            ]);

            return response()->json([
                'success' => true,

                'message' =>
                    'Pengajuan calon Bupati berhasil dikirim ulang.',

                'eligibility' =>
                    $eligibility,

                'data' =>
                    $existing->fresh(),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */

        $application =
            CandidateApplication::query()
                ->create([
                    'election_id' =>
                        $electionId,

                    'user_id' =>
                        $user->id,

                    'member_id' =>
                        $member->id,

                    'vision' =>
                        trim(
                            $validated['vision']
                        ),

                    'mission' =>
                        trim(
                            $validated['mission']
                        ),

                    'photo_path' =>
                        $photoPath,

                    'status' =>
                        'pending',
                ]);

        return response()->json([
            'success' => true,

            'message' =>
                'Pengajuan calon Bupati berhasil dikirim dan menunggu verifikasi.',

            'eligibility' =>
                $eligibility,

            'data' =>
                $application,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE PENGAJUAN
    |--------------------------------------------------------------------------
    |
    | Hanya boleh selama pending.
    |
    */

    public function update(
        Request $request,
        int $electionId
    ): JsonResponse {
        $user =
            $request->user();

        $application =
            CandidateApplication::query()
                ->where(
                    'election_id',
                    $electionId
                )
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();

        if (!$application) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Pengajuan calon Bupati tidak ditemukan.',
            ], 404);
        }

        if (
            $application->status !==
            'pending'
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Pengajuan yang sudah diproses tidak dapat diedit.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CEK ULANG KELAYAKAN
        |--------------------------------------------------------------------------
        |
        | Jika data mahasiswa berubah saat pengajuan pending,
        | sistem tetap melindungi proses.
        |
        */

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

        $member =
            Member::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Data keanggotaan tidak ditemukan.',
            ], 422);
        }

        $eligibility =
            $this->eligibilityService
                ->evaluate(
                    $member,
                    $election
                );

        if (
            $eligibility['eligible'] !== true
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Pengajuan tidak dapat diperbarui karena Anda tidak lagi memenuhi persyaratan calon Bupati.',

                'reasons' =>
                    $eligibility['reasons'],

                'eligibility' =>
                    $eligibility,
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDASI
        |--------------------------------------------------------------------------
        */

        $validated =
            $request->validate([
                'vision' => [
                    'required',
                    'string',
                    'min:10',
                    'max:5000',
                ],

                'mission' => [
                    'required',
                    'string',
                    'min:10',
                    'max:5000',
                ],

                'photo' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:3072',
                ],
            ]);

        $photoPath =
            $application->photo_path;

        if (
            $request->hasFile(
                'photo'
            )
        ) {
            if ($photoPath) {
                Storage::disk(
                    'public'
                )->delete(
                    $photoPath
                );
            }

            $photoPath =
                $request
                    ->file('photo')
                    ->store(
                        'candidate-applications',
                        'public'
                    );
        }

        $application->update([
            'vision' =>
                trim(
                    $validated['vision']
                ),

            'mission' =>
                trim(
                    $validated['mission']
                ),

            'photo_path' =>
                $photoPath,
        ]);

        return response()->json([
            'success' => true,

            'message' =>
                'Pengajuan calon Bupati berhasil diperbarui.',

            'eligibility' =>
                $eligibility,

            'data' =>
                $application->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | BATALKAN PENGAJUAN
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        int $electionId
    ): JsonResponse {
        $application =
            CandidateApplication::query()
                ->where(
                    'election_id',
                    $electionId
                )
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->first();

        if (!$application) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Pengajuan calon Bupati tidak ditemukan.',
            ], 404);
        }

        if (
            $application->status !==
            'pending'
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Pengajuan yang sudah diproses tidak dapat dibatalkan.',
            ], 422);
        }

        if (
            $application->photo_path
        ) {
            Storage::disk(
                'public'
            )->delete(
                $application->photo_path
            );
        }

        $application->delete();

        return response()->json([
            'success' => true,

            'message' =>
                'Pengajuan calon Bupati berhasil dibatalkan.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE
    |--------------------------------------------------------------------------
    */

    public function approve(
        Request $request,
        int $electionId,
        int $applicationId
    ): JsonResponse {
        $reviewer =
            $request->user();

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
                    (string) $election->status
                )
            ) !== 'draft'
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Calon Bupati tidak dapat disetujui setelah voting dibuka.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | ADMIN/PENGURUS MENENTUKAN NOMOR URUT
        |--------------------------------------------------------------------------
        */

        $validated =
            $request->validate([
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
            ]);

        try {
            $candidate =
                DB::transaction(
                    function () use (
                        $election,
                        $electionId,
                        $applicationId,
                        $reviewer,
                        $validated
                    ) {
                        $application =
                            CandidateApplication::query()
                                ->where(
                                    'election_id',
                                    $electionId
                                )
                                ->where(
                                    'id',
                                    $applicationId
                                )
                                ->lockForUpdate()
                                ->first();

                        if (!$application) {
                            throw new \RuntimeException(
                                'APPLICATION_NOT_FOUND'
                            );
                        }

                        if (
                            $application->status !==
                            'pending'
                        ) {
                            throw new \RuntimeException(
                                'APPLICATION_PROCESSED'
                            );
                        }

                        $member =
                            Member::query()
                                ->find(
                                    $application->member_id
                                );

                        if (!$member) {
                            throw new \RuntimeException(
                                'MEMBER_NOT_FOUND'
                            );
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | CEK ULANG KELAYAKAN
                        |--------------------------------------------------------------------------
                        |
                        | Penting:
                        |
                        | Walaupun mahasiswa sebelumnya pernah layak,
                        | admin hanya boleh menyetujui jika saat ini
                        | mahasiswa tetap memenuhi persyaratan.
                        |
                        */

                        $eligibility =
                            $this->eligibilityService
                                ->evaluate(
                                    $member,
                                    $election
                                );

                        if (
                            $eligibility['eligible'] !== true
                        ) {
                            throw new \RuntimeException(
                                'NOT_ELIGIBLE'
                            );
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | CEK DUPLIKAT BERDASARKAN NIM
                        |--------------------------------------------------------------------------
                        */

                        $exists =
                            ElectionCandidate::query()
                                ->where(
                                    'election_id',
                                    $electionId
                                )
                                ->where(
                                    'nim',
                                    $member->nim
                                )
                                ->exists();

                        if ($exists) {
                            throw new \RuntimeException(
                                'ALREADY_CANDIDATE'
                            );
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | BUAT KANDIDAT RESMI
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
                                        $member->name,

                                    'nim' =>
                                        $member->nim,

                                    'study_program' =>
                                        $member->study_program,

                                    'vision' =>
                                        $application->vision,

                                    'mission' =>
                                        $application->mission,

                                    /*
                                     * Prioritas:
                                     *
                                     * 1. foto pengajuan
                                     * 2. foto profil anggota
                                     */
                                    'photo_path' =>
                                        $application->photo_path
                                        ?: $member->profile_photo,
                                ]);

                        /*
                        |--------------------------------------------------------------------------
                        | UPDATE APPLICATION
                        |--------------------------------------------------------------------------
                        */

                        $application->update([
                            'status' =>
                                'approved',

                            'rejection_reason' =>
                                null,

                            'reviewed_by' =>
                                $reviewer->id,

                            'reviewed_at' =>
                                now(),
                        ]);

                        return $candidate;
                    }
                );

            return response()->json([
                'success' => true,

                'message' =>
                    'Pengajuan calon Bupati berhasil disetujui.',

                'data' =>
                    $candidate,
            ]);
        } catch (
            \RuntimeException $error
        ) {
            return match (
                $error->getMessage()
            ) {
                'APPLICATION_NOT_FOUND' =>
                    response()->json([
                        'success' => false,
                        'message' =>
                            'Pengajuan calon Bupati tidak ditemukan.',
                    ], 404),

                'APPLICATION_PROCESSED' =>
                    response()->json([
                        'success' => false,
                        'message' =>
                            'Pengajuan calon Bupati sudah diproses.',
                    ], 422),

                'MEMBER_NOT_FOUND' =>
                    response()->json([
                        'success' => false,
                        'message' =>
                            'Data anggota tidak ditemukan.',
                    ], 422),

                /*
                 * Baru:
                 * tidak bisa approve jika tidak layak.
                 */
                'NOT_ELIGIBLE' =>
                    response()->json([
                        'success' => false,
                        'message' =>
                            'Pengajuan tidak dapat disetujui karena mahasiswa tidak lagi memenuhi persyaratan calon Bupati.',
                    ], 422),

                'ALREADY_CANDIDATE' =>
                    response()->json([
                        'success' => false,
                        'message' =>
                            'Mahasiswa tersebut sudah menjadi calon Bupati resmi.',
                    ], 422),

                default =>
                    throw $error,
            };
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT
    |--------------------------------------------------------------------------
    */

    public function reject(
        Request $request,
        int $electionId,
        int $applicationId
    ): JsonResponse {
        $validated =
            $request->validate([
                'rejection_reason' => [
                    'required',
                    'string',
                    'min:5',
                    'max:2000',
                ],
            ]);

        $application =
            CandidateApplication::query()
                ->where(
                    'election_id',
                    $electionId
                )
                ->where(
                    'id',
                    $applicationId
                )
                ->first();

        if (!$application) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Pengajuan calon Bupati tidak ditemukan.',
            ], 404);
        }

        if (
            $application->status !==
            'pending'
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Pengajuan calon Bupati sudah diproses.',
            ], 422);
        }

        $application->update([
            'status' =>
                'rejected',

            'rejection_reason' =>
                trim(
                    $validated[
                        'rejection_reason'
                    ]
                ),

            'reviewed_by' =>
                $request->user()->id,

            'reviewed_at' =>
                now(),
        ]);

        return response()->json([
            'success' => true,

            'message' =>
                'Pengajuan calon Bupati berhasil ditolak.',

            'data' =>
                $application->fresh([
                    'reviewer:id,name,email',
                ]),
        ]);
    }
}
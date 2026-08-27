<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    /**
     * ================================================================
     * DAFTAR SEMUA ANGGOTA
     * ================================================================
     *
     * Khusus Admin.
     *
     * Mendukung:
     * - pencarian nama / NIM / email
     * - filter status
     * - filter angkatan
     * - filter divisi
     */
    public function index(Request $request): JsonResponse
    {
        $query = Member::query()
            ->with('user')
            ->orderBy('name');

        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = trim(
                $request->input('search')
            );

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where(
                        'name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'nim',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'email',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | FILTER STATUS
        |--------------------------------------------------------------------------
        */

        if ($request->filled('status')) {
            $query->where(
                'member_status',
                $request->input('status')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FILTER ANGKATAN
        |--------------------------------------------------------------------------
        */

        if ($request->filled('generation')) {
            $query->where(
                'generation',
                $request->input('generation')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FILTER DIVISI
        |--------------------------------------------------------------------------
        */

        if ($request->filled('division')) {
            $query->where(
                'division',
                $request->input('division')
            );
        }

        $members = $query->get();

        return response()->json([
            'success' => true,
            'message' =>
                'Data anggota berhasil diambil.',
            'data' => $members,
        ]);
    }

    /**
     * ================================================================
     * DETAIL SATU ANGGOTA
     * ================================================================
     */
    public function show(int $id): JsonResponse
    {
        $member = Member::query()
            ->with('user')
            ->find($id);

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Data anggota tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Detail anggota berhasil diambil.',
            'data' => $member,
        ]);
    }

    /**
     * ================================================================
     * TAMBAH DATA ANGGOTA
     * ================================================================
     *
     * Digunakan jika akun User sudah tersedia,
     * tetapi data Member belum dibuat.
     *
     * Untuk membuat akun baru sekaligus Member,
     * gunakan UserManagementController.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                'unique:members,user_id',
            ],

            'nim' => [
                'required',
                'string',
                'max:50',
                'unique:members,nim',
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'email' => [
                'nullable',
                'email',
                'max:150',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'study_program' => [
                'nullable',
                'string',
                'max:150',
            ],

            'generation' => [
                'nullable',
                'string',
                'max:20',
            ],

            /*
            |--------------------------------------------------------------------------
            | IPK
            |--------------------------------------------------------------------------
            |
            | IPK boleh kosong.
            | Jika diisi harus angka 0.00 - 4.00.
            |
            */

            'gpa' => [
                'nullable',
                'numeric',
                'between:0,4',
            ],

            'position' => [
                'nullable',
                'string',
                'max:150',
            ],

            'division' => [
                'nullable',
                'string',
                'max:150',
            ],

            'service_period' => [
                'nullable',
                'string',
                'max:100',
            ],

            'address' => [
                'nullable',
                'string',
            ],

            'member_status' => [
                'nullable',
                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],
        ]);

        $user = User::query()
            ->find($validated['user_id']);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Akun user tidak ditemukan.',
            ], 404);
        }

        if (
            !in_array(
                $user->role,
                [
                    'pengurus',
                    'mahasiswa',
                ],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Data Member hanya diperuntukkan bagi Pengurus atau Mahasiswa.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $member = Member::query()->create([
                'user_id' =>
                    $validated['user_id'],

                'nim' =>
                    $validated['nim'],

                'name' =>
                    $validated['name'],

                'email' =>
                    $validated['email']
                    ?? $user->email,

                'phone' =>
                    $validated['phone']
                    ?? null,

                'study_program' =>
                    $validated['study_program']
                    ?? null,

                'generation' =>
                    $validated['generation']
                    ?? null,

                /*
                 * Simpan IPK.
                 */
                'gpa' =>
                    $validated['gpa']
                    ?? null,

                'position' =>
                    $validated['position']
                    ?? null,

                'division' =>
                    $validated['division']
                    ?? null,

                'service_period' =>
                    $validated['service_period']
                    ?? null,

                'address' =>
                    $validated['address']
                    ?? null,

                'member_status' =>
                    $validated['member_status']
                    ?? 'active',
            ]);

            DB::commit();

            $member->load('user');

            return response()->json([
                'success' => true,
                'message' =>
                    'Data anggota berhasil ditambahkan.',
                'data' => $member,
            ], 201);
        } catch (\Throwable $error) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' =>
                    'Gagal menambahkan anggota: '
                    . $error->getMessage(),
            ], 500);
        }
    }

    /**
     * ================================================================
     * UPDATE DATA ANGGOTA
     * ================================================================
     */
    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        $member = Member::query()
            ->with('user')
            ->find($id);

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Data anggota tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'nim' => [
                'required',
                'string',
                'max:50',

                Rule::unique(
                    'members',
                    'nim'
                )->ignore(
                    $member->id
                ),
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'email' => [
                'nullable',
                'email',
                'max:150',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'study_program' => [
                'nullable',
                'string',
                'max:150',
            ],

            'generation' => [
                'nullable',
                'string',
                'max:20',
            ],

            /*
            |--------------------------------------------------------------------------
            | IPK
            |--------------------------------------------------------------------------
            */

            'gpa' => [
                'nullable',
                'numeric',
                'between:0,4',
            ],

            'position' => [
                'nullable',
                'string',
                'max:150',
            ],

            'division' => [
                'nullable',
                'string',
                'max:150',
            ],

            'service_period' => [
                'nullable',
                'string',
                'max:100',
            ],

            'address' => [
                'nullable',
                'string',
            ],

            'member_status' => [
                'required',
                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],
        ]);

        DB::beginTransaction();

        try {
            /*
            |--------------------------------------------------------------------------
            | UPDATE MEMBER
            |--------------------------------------------------------------------------
            */

            $member->update([
                'nim' =>
                    $validated['nim'],

                'name' =>
                    $validated['name'],

                'email' =>
                    $validated['email']
                    ?? null,

                'phone' =>
                    $validated['phone']
                    ?? null,

                'study_program' =>
                    $validated['study_program']
                    ?? null,

                'generation' =>
                    $validated['generation']
                    ?? null,

                /*
                 * Update IPK.
                 */
                'gpa' =>
                    $validated['gpa']
                    ?? null,

                'position' =>
                    $validated['position']
                    ?? null,

                'division' =>
                    $validated['division']
                    ?? null,

                'service_period' =>
                    $validated['service_period']
                    ?? null,

                'address' =>
                    $validated['address']
                    ?? null,

                'member_status' =>
                    $validated['member_status'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | SINKRONKAN NAMA DENGAN USER
            |--------------------------------------------------------------------------
            */

            if ($member->user) {
                $member->user->update([
                    'name' =>
                        $validated['name'],
                ]);
            }

            DB::commit();

            $member->refresh();
            $member->load('user');

            return response()->json([
                'success' => true,
                'message' =>
                    'Data anggota berhasil diperbarui.',
                'data' => $member,
            ]);
        } catch (\Throwable $error) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' =>
                    'Gagal memperbarui anggota: '
                    . $error->getMessage(),
            ], 500);
        }
    }

    /**
     * ================================================================
     * HAPUS DATA MEMBER
     * ================================================================
     *
     * Akun User tidak ikut dihapus.
     * Penghapusan akun dilakukan melalui UserManagementController.
     */
    public function destroy(int $id): JsonResponse
    {
        $member = Member::query()
            ->find($id);

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Data anggota tidak ditemukan.',
            ], 404);
        }

        DB::beginTransaction();

        try {
            /*
            |--------------------------------------------------------------------------
            | HAPUS FOTO LAMA
            |--------------------------------------------------------------------------
            */

            if (
                $member->profile_photo &&
                Storage::disk('public')
                    ->exists(
                        $member->profile_photo
                    )
            ) {
                Storage::disk('public')
                    ->delete(
                        $member->profile_photo
                    );
            }

            $member->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' =>
                    'Data anggota berhasil dihapus.',
            ]);
        } catch (\Throwable $error) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' =>
                    'Gagal menghapus anggota: '
                    . $error->getMessage(),
            ], 500);
        }
    }

    /**
     * ================================================================
     * PROFIL AKUN YANG SEDANG LOGIN
     * ================================================================
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pengguna belum login.',
            ], 401);
        }

        $member = Member::query()
            ->where(
                'user_id',
                $user->id
            )
            ->first();

        return response()->json([
            'success' => true,
            'message' =>
                'Profil pengguna berhasil diambil.',

            'data' => [
                'user' => [
                    'id' =>
                        $user->id,

                    'name' =>
                        $user->name,

                    'email' =>
                        $user->email,

                    'role' =>
                        $user->role,

                    'status' =>
                        $user->status,
                ],

                /*
                 * GPA ikut terbaca di sini karena
                 * seluruh object Member dikirim.
                 *
                 * Tetapi mahasiswa tidak dapat
                 * mengubahnya melalui updateProfile().
                 */
                'member' =>
                    $member,
            ],
        ]);
    }

    /**
     * ================================================================
     * UPDATE PROFIL SENDIRI
     * ================================================================
     *
     * Mahasiswa/Pengurus hanya boleh memperbarui
     * data profil pribadi tertentu.
     *
     * Role, status, jabatan, divisi, angkatan,
     * dan IPK tidak boleh diubah sendiri.
     */
    public function updateProfile(
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

        $member = Member::query()
            ->where(
                'user_id',
                $user->id
            )
            ->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Data profil anggota tidak ditemukan.',
            ], 404);
        }

        /*
         * PERHATIKAN:
         *
         * Tidak ada gpa di sini.
         *
         * Jadi mahasiswa/pengurus tidak dapat
         * mengubah IPK miliknya sendiri.
         */
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'address' => [
                'nullable',
                'string',
            ],
        ]);

        DB::beginTransaction();

        try {
            $member->update([
                'name' =>
                    $validated['name'],

                'phone' =>
                    $validated['phone']
                    ?? null,

                'address' =>
                    $validated['address']
                    ?? null,
            ]);

            $user->update([
                'name' =>
                    $validated['name'],
            ]);

            DB::commit();

            $member->refresh();

            return response()->json([
                'success' => true,
                'message' =>
                    'Profil berhasil diperbarui.',

                'data' => [
                    'user' => [
                        'id' =>
                            $user->id,

                        'name' =>
                            $user->name,

                        'email' =>
                            $user->email,

                        'role' =>
                            $user->role,

                        'status' =>
                            $user->status,
                    ],

                    'member' =>
                        $member,
                ],
            ]);
        } catch (\Throwable $error) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' =>
                    'Gagal memperbarui profil: '
                    . $error->getMessage(),
            ], 500);
        }
    }

    /**
     * ================================================================
     * UPLOAD FOTO PROFIL
     * ================================================================
     */
    public function uploadPhoto(
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

        $member = Member::query()
            ->where(
                'user_id',
                $user->id
            )
            ->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Data anggota tidak ditemukan.',
            ], 404);
        }

        $request->validate([
            'photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        try {
            /*
            |--------------------------------------------------------------------------
            | HAPUS FOTO LAMA
            |--------------------------------------------------------------------------
            */

            if (
                $member->profile_photo &&
                Storage::disk('public')
                    ->exists(
                        $member->profile_photo
                    )
            ) {
                Storage::disk('public')
                    ->delete(
                        $member->profile_photo
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | SIMPAN FOTO BARU
            |--------------------------------------------------------------------------
            */

            $path = $request
                ->file('photo')
                ->store(
                    'profile_photos',
                    'public'
                );

            $member->update([
                'profile_photo' =>
                    $path,
            ]);

            return response()->json([
                'success' => true,
                'message' =>
                    'Foto profil berhasil diperbarui.',

                'data' => [
                    'profile_photo' =>
                        $path,

                    'profile_photo_url' =>
                        asset(
                            'storage/' .
                            $path
                        ),
                ],
            ]);
        } catch (\Throwable $error) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Gagal mengunggah foto: '
                    . $error->getMessage(),
            ], 500);
        }
    }

    /**
     * ================================================================
     * AKTIFKAN MEMBER
     * ================================================================
     */
    public function activate(int $id): JsonResponse
    {
        $member = Member::query()
            ->with('user')
            ->find($id);

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anggota tidak ditemukan.',
            ], 404);
        }

        DB::transaction(function () use ($member) {
            $member->update([
                'member_status' =>
                    'active',
            ]);

            if ($member->user) {
                $member->user->update([
                    'status' =>
                        'active',
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' =>
                'Anggota berhasil diaktifkan.',
        ]);
    }

    /**
     * ================================================================
     * NONAKTIFKAN MEMBER
     * ================================================================
     */
    public function deactivate(int $id): JsonResponse
    {
        $member = Member::query()
            ->with('user')
            ->find($id);

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anggota tidak ditemukan.',
            ], 404);
        }

        DB::transaction(function () use ($member) {
            $member->update([
                'member_status' =>
                    'inactive',
            ]);

            if ($member->user) {
                $member->user->update([
                    'status' =>
                        'inactive',
                ]);

                /*
                 * Hapus token supaya akun langsung logout.
                 */
                $member->user
                    ->tokens()
                    ->delete();
            }
        });

        return response()->json([
            'success' => true,
            'message' =>
                'Anggota berhasil dinonaktifkan.',
        ]);
    }
}
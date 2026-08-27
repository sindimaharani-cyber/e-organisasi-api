<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    /**
     * ================================================================
     * DAFTAR USER
     * ================================================================
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with('member')
            ->orderBy('name');

        if ($request->filled('role')) {
            $query->where(
                'role',
                $request->string('role')->toString()
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        if ($request->filled('search')) {
            $search = trim(
                $request->string('search')->toString()
            );

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where(
                        'name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'email',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereHas(
                        'member',
                        function ($memberQuery) use ($search) {
                            $memberQuery
                                ->where(
                                    'nim',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
            });
        }

        $users = $query->get();

        return response()->json([
            'success' => true,
            'message' =>
                'Data user berhasil diambil.',
            'data' => $users,
        ]);
    }

    /**
     * ================================================================
     * DETAIL USER
     * ================================================================
     */
    public function show(int $id): JsonResponse
    {
        $user = User::query()
            ->with('member')
            ->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'User tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Detail user berhasil diambil.',
            'data' => $user,
        ]);
    }

    /**
     * ================================================================
     * TAMBAH USER
     * ================================================================
     */
    public function store(
        Request $request
    ): JsonResponse {
        $validated =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'email' => [
                    'required',
                    'email',
                    'max:150',
                    'unique:users,email',
                ],

                'password' => [
                    'required',
                    'string',
                    'min:6',
                ],

                'role' => [
                    'required',
                    Rule::in([
                        'admin',
                        'pengurus',
                        'mahasiswa',
                    ]),
                ],

                'status' => [
                    'nullable',
                    Rule::in([
                        'active',
                        'inactive',
                    ]),
                ],

                'nim' => [
                    'nullable',
                    'string',
                    'max:50',
                    'unique:members,nim',
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
            ]);

        /*
        |--------------------------------------------------------------------------
        | NIM WAJIB UNTUK MEMBER
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $validated['role'],
                [
                    'pengurus',
                    'mahasiswa',
                ],
                true
            ) &&
            empty($validated['nim'])
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'NIM wajib diisi untuk Pengurus atau Mahasiswa.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            /*
            |--------------------------------------------------------------------------
            | BUAT USER
            |--------------------------------------------------------------------------
            */

            $user =
                User::query()->create([
                    'name' =>
                        $validated['name'],

                    'email' =>
                        $validated['email'],

                    'password' =>
                        Hash::make(
                            $validated['password']
                        ),

                    'role' =>
                        $validated['role'],

                    'status' =>
                        $validated['status']
                        ?? 'active',
                ]);

            /*
            |--------------------------------------------------------------------------
            | BUAT MEMBER
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $validated['role'],
                    [
                        'pengurus',
                        'mahasiswa',
                    ],
                    true
                )
            ) {
                Member::query()->create([
                    'user_id' =>
                        $user->id,

                    'nim' =>
                        $validated['nim'],

                    'name' =>
                        $validated['name'],

                    'email' =>
                        $validated['email'],

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
                     * SIMPAN IPK
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

                    'member_status' =>
                        $validated['status']
                        ?? 'active',
                ]);
            }

            DB::commit();

            $user->load('member');

            return response()->json([
                'success' => true,
                'message' =>
                    'User berhasil dibuat.',
                'data' => $user,
            ], 201);
        } catch (\Throwable $error) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' =>
                    'Gagal membuat user: '
                    . $error->getMessage(),
            ], 500);
        }
    }

    /**
     * ================================================================
     * UPDATE USER
     * ================================================================
     */
    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        $user = User::query()
            ->with('member')
            ->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'User tidak ditemukan.',
            ], 404);
        }

        $validated =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'email' => [
                    'required',
                    'email',
                    'max:150',

                    Rule::unique(
                        'users',
                        'email'
                    )->ignore(
                        $user->id
                    ),
                ],

                'password' => [
                    'nullable',
                    'string',
                    'min:6',
                ],

                'role' => [
                    'required',
                    Rule::in([
                        'admin',
                        'pengurus',
                        'mahasiswa',
                    ]),
                ],

                'status' => [
                    'required',
                    Rule::in([
                        'active',
                        'inactive',
                    ]),
                ],

                'nim' => [
                    'nullable',
                    'string',
                    'max:50',

                    Rule::unique(
                        'members',
                        'nim'
                    )->ignore(
                        $user->member?->id
                    ),
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
            ]);

        if (
            in_array(
                $validated['role'],
                [
                    'pengurus',
                    'mahasiswa',
                ],
                true
            ) &&
            empty($validated['nim'])
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'NIM wajib diisi untuk Pengurus atau Mahasiswa.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            /*
            |--------------------------------------------------------------------------
            | UPDATE USER
            |--------------------------------------------------------------------------
            */

            $user->name =
                $validated['name'];

            $user->email =
                $validated['email'];

            $user->role =
                $validated['role'];

            $user->status =
                $validated['status'];

            if (
                !empty(
                    $validated['password']
                )
            ) {
                $user->password =
                    Hash::make(
                        $validated['password']
                    );
            }

            $user->save();

            /*
            |--------------------------------------------------------------------------
            | UPDATE MEMBER
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $validated['role'],
                    [
                        'pengurus',
                        'mahasiswa',
                    ],
                    true
                )
            ) {
                Member::query()
                    ->updateOrCreate(
                        [
                            'user_id' =>
                                $user->id,
                        ],
                        [
                            'nim' =>
                                $validated['nim'],

                            'name' =>
                                $validated['name'],

                            'email' =>
                                $validated['email'],

                            'phone' =>
                                $validated['phone']
                                ?? null,

                            'study_program' =>
                                $validated[
                                    'study_program'
                                ] ?? null,

                            'generation' =>
                                $validated[
                                    'generation'
                                ] ?? null,

                            /*
                             * UPDATE IPK
                             */
                            'gpa' =>
                                $validated['gpa']
                                ?? null,

                            'position' =>
                                $validated[
                                    'position'
                                ] ?? null,

                            'division' =>
                                $validated[
                                    'division'
                                ] ?? null,

                            'member_status' =>
                                $validated[
                                    'status'
                                ],
                        ]
                    );
            } else {
                /*
                 * Admin tidak memiliki data Member.
                 */
                if ($user->member) {
                    $user->member
                        ->delete();
                }
            }

            DB::commit();

            $user->load('member');

            return response()->json([
                'success' => true,
                'message' =>
                    'Data user berhasil diperbarui.',
                'data' => $user,
            ]);
        } catch (\Throwable $error) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' =>
                    'Gagal memperbarui user: '
                    . $error->getMessage(),
            ], 500);
        }
    }

    /**
     * ================================================================
     * AKTIFKAN USER
     * ================================================================
     */
    public function activate(
        int $id
    ): JsonResponse {
        $user = User::query()
            ->with('member')
            ->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'User tidak ditemukan.',
            ], 404);
        }

        DB::transaction(
            function () use ($user) {
                $user->update([
                    'status' =>
                        'active',
                ]);

                if ($user->member) {
                    $user->member
                        ->update([
                            'member_status' =>
                                'active',
                        ]);
                }
            }
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Akun berhasil diaktifkan.',
        ]);
    }

    /**
     * ================================================================
     * NONAKTIFKAN USER
     * ================================================================
     */
    public function deactivate(
        Request $request,
        int $id
    ): JsonResponse {
        $user = User::query()
            ->with('member')
            ->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'User tidak ditemukan.',
            ], 404);
        }

        if (
            $request->user()?->id ===
            $user->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Admin tidak dapat menonaktifkan akun sendiri.',
            ], 422);
        }

        DB::transaction(
            function () use ($user) {
                $user->update([
                    'status' =>
                        'inactive',
                ]);

                if ($user->member) {
                    $user->member
                        ->update([
                            'member_status' =>
                                'inactive',
                        ]);
                }

                $user->tokens()
                    ->delete();
            }
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Akun berhasil dinonaktifkan.',
        ]);
    }

    /**
     * ================================================================
     * HAPUS USER
     * ================================================================
     */
    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {
        $user = User::query()
            ->with('member')
            ->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'User tidak ditemukan.',
            ], 404);
        }

        if (
            $request->user()?->id ===
            $user->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Admin tidak dapat menghapus akun sendiri.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $user->tokens()
                ->delete();

            if ($user->member) {
                $user->member
                    ->delete();
            }

            $user->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' =>
                    'User berhasil dihapus.',
            ]);
        } catch (\Throwable $error) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' =>
                    'Gagal menghapus user: '
                    . $error->getMessage(),
            ], 500);
        }
    }
}
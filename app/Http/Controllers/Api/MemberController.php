<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    /**
     * ================================================================
     * DAFTAR SEMUA ANGGOTA
     * ================================================================
     */
    public function index(Request $request): JsonResponse
    {
        $query = Member::query()
            ->with('user')
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('nim', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where(
                'member_status',
                $request->input('status')
            );
        }

        if ($request->filled('generation')) {
            $query->where(
                'generation',
                $request->input('generation')
            );
        }

        if ($request->filled('division')) {
            $query->where(
                'division',
                $request->input('division')
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Data anggota berhasil diambil.',
            'data' => $query->get(),
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
                'message' => 'Data anggota tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail anggota berhasil diambil.',
            'data' => $member,
        ]);
    }

    /**
     * ================================================================
     * TAMBAH DATA ANGGOTA
     * ================================================================
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
                'message' => 'Akun user tidak ditemukan.',
            ], 404);
        }

        if (!in_array(
            $user->role,
            [
                'pengurus',
                'mahasiswa',
            ],
            true
        )) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Data Member hanya diperuntukkan bagi Pengurus atau Mahasiswa.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $member = Member::query()->create([
                'user_id' => $validated['user_id'],
                'nim' => $validated['nim'],
                'name' => $validated['name'],
                'email' => $validated['email'] ?? $user->email,
                'phone' => $validated['phone'] ?? null,
                'study_program' =>
                    $validated['study_program'] ?? null,
                'generation' =>
                    $validated['generation'] ?? null,
                'gpa' => $validated['gpa'] ?? null,
                'position' =>
                    $validated['position'] ?? null,
                'division' =>
                    $validated['division'] ?? null,
                'service_period' =>
                    $validated['service_period'] ?? null,
                'address' =>
                    $validated['address'] ?? null,
                'member_status' =>
                    $validated['member_status'] ?? 'active',
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
                    'Gagal menambahkan anggota: ' .
                    $error->getMessage(),
            ], 500);
        }
    }

    /**
     * ================================================================
     * UPDATE DATA ANGGOTA OLEH ADMIN
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
                'message' => 'Data anggota tidak ditemukan.',
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
                )->ignore($member->id),
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
            $member->update([
                'nim' => $validated['nim'],
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'study_program' =>
                    $validated['study_program'] ?? null,
                'generation' =>
                    $validated['generation'] ?? null,
                'gpa' => $validated['gpa'] ?? null,
                'position' =>
                    $validated['position'] ?? null,
                'division' =>
                    $validated['division'] ?? null,
                'service_period' =>
                    $validated['service_period'] ?? null,
                'address' =>
                    $validated['address'] ?? null,
                'member_status' =>
                    $validated['member_status'],
            ]);

            if ($member->user) {
                $member->user->update([
                    'name' => $validated['name'],
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
                    'Gagal memperbarui anggota: ' .
                    $error->getMessage(),
            ], 500);
        }
    }

    /**
     * ================================================================
     * HAPUS DATA MEMBER
     * ================================================================
     */
    public function destroy(int $id): JsonResponse
    {
        $member = Member::query()
            ->find($id);

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Data anggota tidak ditemukan.',
            ], 404);
        }

        DB::beginTransaction();

        try {
            if (
                $member->profile_photo &&
                Storage::disk('public')->exists(
                    $member->profile_photo
                )
            ) {
                Storage::disk('public')->delete(
                    $member->profile_photo
                );
            }

            $member->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data anggota berhasil dihapus.',
            ]);
        } catch (\Throwable $error) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' =>
                    'Gagal menghapus anggota: ' .
                    $error->getMessage(),
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
                'message' => 'Pengguna belum login.',
            ], 401);
        }

        $member = Member::query()
            ->where('user_id', $user->id)
            ->first();

        return response()->json([
            'success' => true,
            'message' =>
                'Profil pengguna berhasil diambil.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                ],
                'member' => $member,
            ],
        ]);
    }

    /**
     * ================================================================
     * UPDATE PROFIL SENDIRI
     * ================================================================
     *
     * Pengurus/Mahasiswa boleh mengubah:
     * - nama
     * - nomor HP
     * - alamat
     * - IPK terbaru
     *
     * Semua role boleh mengganti password.
     *
     * Data berikut tetap tidak boleh diubah sendiri:
     * - email
     * - NIM
     * - role
     * - status
     * - angkatan
     * - jabatan
     * - divisi
     */
    public function updateProfile(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna belum login.',
            ], 401);
        }

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],
            'address' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'gpa' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:0,4',
            ],
            'current_password' => [
                'required_with:password',
                'nullable',
                'string',
            ],
            'password' => [
                'sometimes',
                'required',
                'string',
                'min:8',
                'confirmed',
                'different:current_password',
            ],
        ]);

        $member = Member::query()
            ->where('user_id', $user->id)
            ->first();

        $wantsMemberUpdate =
            $request->has('name') ||
            $request->has('phone') ||
            $request->has('address') ||
            $request->has('gpa');

        if ($wantsMemberUpdate && !$member) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Data profil anggota tidak ditemukan.',
            ], 404);
        }

        if ($request->filled('password')) {
            if (
                !$request->filled('current_password') ||
                !Hash::check(
                    $request->input('current_password'),
                    $user->password
                )
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Password saat ini tidak sesuai.',
                    'errors' => [
                        'current_password' => [
                            'Password saat ini tidak sesuai.',
                        ],
                    ],
                ], 422);
            }
        }

        DB::beginTransaction();

        try {
            if ($member && $wantsMemberUpdate) {
                $memberUpdates = [];

                if ($request->has('name')) {
                    $memberUpdates['name'] =
                        $validated['name'];
                }

                if ($request->has('phone')) {
                    $memberUpdates['phone'] =
                        $validated['phone'] ?? null;
                }

                if ($request->has('address')) {
                    $memberUpdates['address'] =
                        $validated['address'] ?? null;
                }

                if ($request->has('gpa')) {
                    $memberUpdates['gpa'] =
                        $validated['gpa'] ?? null;
                }

                if (!empty($memberUpdates)) {
                    $member->update($memberUpdates);
                }
            }

            if ($request->has('name')) {
                $user->name = $validated['name'];
            }

            if ($request->filled('password')) {
                $user->password = Hash::make(
                    $validated['password']
                );
            }

            if ($user->isDirty()) {
                $user->save();
            }

            DB::commit();

            if ($member) {
                $member->refresh();
            }

            $user->refresh();

            $profileChanged = $wantsMemberUpdate;
            $passwordChanged =
                $request->filled('password');

            if ($profileChanged && $passwordChanged) {
                $message =
                    'Profil dan password berhasil diperbarui.';
            } elseif ($passwordChanged) {
                $message =
                    'Password berhasil diperbarui.';
            } else {
                $message =
                    'Profil berhasil diperbarui.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'status' => $user->status,
                    ],
                    'member' => $member,
                ],
            ]);
        } catch (\Throwable $error) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' =>
                    'Gagal memperbarui profil: ' .
                    $error->getMessage(),
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
                'message' => 'Pengguna belum login.',
            ], 401);
        }

        $member = Member::query()
            ->where('user_id', $user->id)
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
            if (
                $member->profile_photo &&
                Storage::disk('public')->exists(
                    $member->profile_photo
                )
            ) {
                Storage::disk('public')->delete(
                    $member->profile_photo
                );
            }

            $path = $request
                ->file('photo')
                ->store(
                    'profile_photos',
                    'public'
                );

            $member->update([
                'profile_photo' => $path,
            ]);

            return response()->json([
                'success' => true,
                'message' =>
                    'Foto profil berhasil diperbarui.',
                'data' => [
                    'profile_photo' => $path,
                    'profile_photo_url' =>
                        asset('storage/' . $path),
                ],
            ]);
        } catch (\Throwable $error) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Gagal mengunggah foto: ' .
                    $error->getMessage(),
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
                'message' => 'Anggota tidak ditemukan.',
            ], 404);
        }

        DB::transaction(function () use ($member) {
            $member->update([
                'member_status' => 'active',
            ]);

            if ($member->user) {
                $member->user->update([
                    'status' => 'active',
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
                'message' => 'Anggota tidak ditemukan.',
            ], 404);
        }

        DB::transaction(function () use ($member) {
            $member->update([
                'member_status' => 'inactive',
            ]);

            if ($member->user) {
                $member->user->update([
                    'status' => 'inactive',
                ]);

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

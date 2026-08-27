<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login utama aplikasi.
     *
     * Pengguna hanya memasukkan email dan password.
     * Role dibaca otomatis dari tabel users.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
            ],
        ]);

        $email = strtolower(
            trim($validated['email'])
        );

        $user = User::query()
            ->with('member')
            ->whereRaw(
                'LOWER(email) = ?',
                [$email]
            )
            ->first();

        if (
            !$user ||
            !Hash::check(
                $validated['password'],
                $user->password
            )
        ) {
            throw ValidationException::withMessages([
                'email' => [
                    'Email atau password tidak sesuai.',
                ],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Akun Anda sedang dinonaktifkan. Hubungi Admin HIMATIF.',
            ], 403);
        }

        if (!in_array(
            $user->role,
            [
                'admin',
                'pengurus',
                'mahasiswa',
            ],
            true
        )) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Role akun tidak valid.',
            ], 403);
        }

        $member = $user->member;
        $officer = null;

        /*
         * Pengurus wajib memiliki data member.
         */
        if ($user->role === 'pengurus') {
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Data anggota untuk akun Pengurus tidak ditemukan.',
                ], 403);
            }

            if (
                isset($member->member_status) &&
                $member->member_status !== 'active'
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Status anggota Pengurus tidak aktif.',
                ], 403);
            }

            /*
             * Data officer boleh kosong sementara.
             * Login tetap berhasil selama role dan member aktif.
             */
            $officer = DB::table(
                'organization_officers'
            )
                ->where(
                    'member_id',
                    $member->id
                )
                ->where(
                    'status',
                    'active'
                )
                ->first();
        }

        /*
         * Mahasiswa wajib memiliki data member aktif.
         */
        if ($user->role === 'mahasiswa') {
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Data mahasiswa tidak ditemukan.',
                ], 403);
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
        }

        /*
         * Menghapus token lama agar tidak menumpuk.
         */
        $user->tokens()->delete();

        $token = $user
            ->createToken(
                $this->tokenNameByRole(
                    $user->role
                )
            )
            ->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'role' => $user->role,
                'user' => $this->formatUser(
                    user: $user,
                    member: $member,
                    officer: $officer,
                ),
                'member' => $this->formatMember(
                    $member
                ),
                'officer' => $this->formatOfficer(
                    $officer
                ),
            ],
        ]);
    }

    /**
     * Mengambil data akun yang sedang login.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pengguna tidak ditemukan.',
            ], 401);
        }

        $user->load('member');

        $member = $user->member;
        $officer = null;

        if (
            $user->role === 'pengurus' &&
            $member
        ) {
            $officer = DB::table(
                'organization_officers'
            )
                ->where(
                    'member_id',
                    $member->id
                )
                ->where(
                    'status',
                    'active'
                )
                ->first();
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Data pengguna berhasil diambil.',
            'data' => [
                'user' => $this->formatUser(
                    user: $user,
                    member: $member,
                    officer: $officer,
                ),
                'member' => $this->formatMember(
                    $member
                ),
                'officer' => $this->formatOfficer(
                    $officer
                ),
            ],
        ]);
    }

    /**
     * Logout.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $currentToken =
                $user->currentAccessToken();

            if ($currentToken) {
                $currentToken->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    private function tokenNameByRole(
        string $role
    ): string {
        return match ($role) {
            'admin' => 'admin-mobile-token',
            'pengurus' => 'officer-mobile-token',
            'mahasiswa' => 'student-mobile-token',
            default => 'e-organisasi-mobile-token',
        };
    }

    private function formatUser(
        User $user,
        mixed $member = null,
        mixed $officer = null
    ): array {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'member' => $this->formatMember(
                $member
            ),
            'officer' => $this->formatOfficer(
                $officer
            ),
        ];
    }

    private function formatMember(
        mixed $member
    ): ?array {
        if (!$member) {
            return null;
        }

        return [
            'id' =>
                $member->id ?? null,

            'user_id' =>
                $member->user_id ?? null,

            'nim' =>
                $member->nim ?? null,

            'name' =>
                $member->name ?? null,

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

            'service_period' =>
                $member->service_period ?? null,

            'profile_photo' =>
                $member->profile_photo ?? null,

            'address' =>
                $member->address ?? null,

            'member_status' =>
                $member->member_status ?? null,
        ];
    }

    private function formatOfficer(
        mixed $officer
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
}
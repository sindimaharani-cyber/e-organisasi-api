<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Memeriksa autentikasi, status akun, dan role.
     *
     * Contoh route:
     * ->middleware('role:admin')
     *
     * atau:
     * ->middleware('role:admin,pengurus')
     */
    public function handle(
        Request $request,
        Closure $next,
        ...$roles
    ): Response {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anda belum login atau token tidak valid.',
            ], 401);
        }

        if (!$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Akun Anda sedang tidak aktif. Hubungi Admin HIMATIF.',
            ], 403);
        }

        $allowedRoles = $this->normalizeRoles(
            $roles
        );

        if (
            empty($allowedRoles) ||
            !$user->hasRole($allowedRoles)
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anda tidak memiliki akses untuk membuka halaman ini.',
                'required_roles' =>
                    $allowedRoles,
                'current_role' =>
                    $user->role,
            ], 403);
        }

        return $next($request);
    }

    /**
     * Mengubah parameter middleware menjadi daftar role.
     *
     * Mendukung:
     * role:admin
     * role:admin,pengurus
     */
    private function normalizeRoles(
        array $roles
    ): array {
        $normalizedRoles = [];

        foreach ($roles as $roleItem) {
            $items = explode(
                ',',
                (string) $roleItem
            );

            foreach ($items as $role) {
                $normalizedRole = strtolower(
                    trim($role)
                );

                if ($normalizedRole !== '') {
                    $normalizedRoles[] =
                        $normalizedRole;
                }
            }
        }

        return array_values(
            array_unique(
                $normalizedRoles
            )
        );
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrganizationStructureController extends Controller
{
    public function index()
    {
        /*
        |--------------------------------------------------------------------------
        | Sumber data struktur organisasi
        |--------------------------------------------------------------------------
        |
        | members:
        | - id
        | - user_id
        | - name
        | - email
        | - phone
        |
        | organization_officers:
        | - member_id
        | - position
        | - division
        | - management_period
        | - status
        |
        */

        if (
            !Schema::hasTable('members') ||
            !Schema::hasTable('organization_officers')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Tabel data struktur organisasi tidak ditemukan.',
                'data' => [],
            ], 500);
        }

        $membersColumns = Schema::getColumnListing('members');
        $officerColumns = Schema::getColumnListing(
            'organization_officers'
        );

        $query = DB::table('organization_officers as oo')
            ->join(
                'members as m',
                'm.id',
                '=',
                'oo.member_id'
            );

        /*
        |--------------------------------------------------------------------------
        | Ambil data anggota
        |--------------------------------------------------------------------------
        */

        $selects = [
            'oo.id as officer_id',
            'oo.member_id',
            'm.id as member_id_value',
            'm.name',
            'm.email',
            'm.phone',
        ];

        if (in_array('position', $officerColumns, true)) {
            $selects[] = 'oo.position';
        }

        if (in_array('division', $officerColumns, true)) {
            $selects[] = 'oo.division';
        }

        if (
            in_array(
                'management_period',
                $officerColumns,
                true
            )
        ) {
            $selects[] = 'oo.management_period';
        }

        if (in_array('status', $officerColumns, true)) {
            $selects[] = 'oo.status as officer_status';
        }

        if (in_array('member_status', $membersColumns, true)) {
            $selects[] = 'm.member_status';
        }

        $members = $query
            ->select($selects)
            ->orderBy(
                in_array('sort_order', $officerColumns, true)
                    ? 'oo.sort_order'
                    : 'oo.id'
            )
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Hanya struktur kepengurusan aktif
        |--------------------------------------------------------------------------
        |
        | Jika status organization_officers tersedia, ambil data aktif.
        | Jika status tidak tersedia, semua data officer ditampilkan.
        |
        */

        if (in_array('status', $officerColumns, true)) {
            $members = $members->filter(function ($item) {
                $status = strtolower(
                    trim((string) ($item->officer_status ?? ''))
                );

                return $status === ''
                    || in_array(
                        $status,
                        [
                            'aktif',
                            'active',
                            '1',
                            'true',
                        ],
                        true
                    );
            })->values();
        }

        /*
        |--------------------------------------------------------------------------
        | Bentuk response untuk Flutter
        |--------------------------------------------------------------------------
        */

        $data = $members->map(function ($member) {
            $name = trim(
                (string) ($member->name ?? '')
            );

            $email = trim(
                (string) ($member->email ?? '')
            );

            $phone = trim(
                (string) ($member->phone ?? '')
            );

            $position = trim(
                (string) ($member->position ?? '')
            );

            $division = trim(
                (string) ($member->division ?? '')
            );

            $status = trim(
                (string) (
                    $member->officer_status
                    ?? $member->member_status
                    ?? 'aktif'
                )
            );

            return [
                'id' => (int) (
                    $member->member_id_value
                    ?? $member->member_id
                    ?? $member->officer_id
                    ?? 0
                ),

                'name' => $name !== ''
                    ? $name
                    : 'Nama belum tersedia',

                'email' => $email !== ''
                    ? $email
                    : '-',

                'phone' => $phone !== ''
                    ? $phone
                    : '-',

                /*
                 * Role di sini adalah kategori struktur organisasi,
                 * bukan role login pada tabel users.
                 */
                'role' => 'pengurus',

                'position' => $position !== ''
                    ? $position
                    : '-',

                'division' => $division !== ''
                    ? $division
                    : '-',

                'status' => $status !== ''
                    ? $status
                    : 'aktif',

                'management_period' => trim(
                    (string) (
                        $member->management_period ?? ''
                    )
                ),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' =>
                'Data struktur organisasi berhasil diambil',
            'data' => $data,
        ]);
    }
}

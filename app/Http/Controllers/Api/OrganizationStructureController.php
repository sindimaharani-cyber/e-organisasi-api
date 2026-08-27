<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrganizationStructureController extends Controller
{
    public function index()
    {
        $columns = Schema::getColumnListing('users');

        $members = DB::table('users')
            ->orderBy('id')
            ->get()
            ->map(function ($user) use ($columns) {
                $getValue = function ($names, $default = '-') use ($user, $columns) {
                    foreach ($names as $name) {
                        if (in_array($name, $columns) && isset($user->$name)) {
                            return $user->$name;
                        }
                    }

                    return $default;
                };

                return [
                    'id' => $user->id ?? 0,
                    'name' => $getValue(['name', 'nama'], '-'),
                    'email' => $getValue(['email'], '-'),
                    'role' => $getValue(['role'], 'Anggota'),
                    'position' => $getValue(['jabatan', 'position'], $getValue(['role'], 'Anggota')),
                    'division' => $getValue(['bidang', 'division'], '-'),
                    'status' => $getValue(['status'], 'aktif'),
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Data struktur organisasi berhasil diambil',
            'data' => $members,
        ]);
    }
}
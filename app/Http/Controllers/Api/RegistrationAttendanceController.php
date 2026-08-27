<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RegistrationAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->query('user_id', 1);

        $activityColumns = Schema::getColumnListing('activities');

        $activities = DB::table('activities')
            ->orderByDesc('id')
            ->get()
            ->map(function ($activity) use ($activityColumns, $userId) {
                $getValue = function ($names, $default = '') use ($activity, $activityColumns) {
                    foreach ($names as $name) {
                        if (in_array($name, $activityColumns) && isset($activity->$name)) {
                            return $activity->$name;
                        }
                    }

                    return $default;
                };

                $registration = DB::table('activity_registrations')
                    ->where('activity_id', $activity->id)
                    ->where('user_id', $userId)
                    ->first();

                $attendance = DB::table('activity_attendances')
                    ->where('activity_id', $activity->id)
                    ->where('user_id', $userId)
                    ->first();

                return [
                    'id' => $activity->id ?? 0,
                    'title' => $getValue(['title', 'name', 'nama_kegiatan'], '-'),
                    'description' => $getValue(['description', 'deskripsi', 'content'], ''),
                    'activity_date' => $getValue(['activity_date', 'date', 'tanggal'], ''),
                    'start_time' => $getValue(['start_time', 'jam_mulai', 'time_start'], ''),
                    'end_time' => $getValue(['end_time', 'jam_selesai', 'time_end'], ''),
                    'location' => $getValue(['location', 'lokasi', 'place', 'tempat'], '-'),
                    'status' => $getValue(['status'], 'Terjadwal'),
                    'created_at' => $activity->created_at ?? null,
                    'is_registered' => $registration ? true : false,
                    'is_attended' => $attendance ? true : false,
                    'registered_at' => $registration->registered_at ?? null,
                    'attended_at' => $attendance->attended_at ?? null,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Data pendaftaran dan absensi berhasil diambil',
            'data' => $activities,
        ]);
    }

    public function register(Request $request, $activityId)
    {
        $userId = $request->input('user_id', 1);

        $activity = DB::table('activities')->where('id', $activityId)->first();

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Kegiatan tidak ditemukan',
            ], 404);
        }

        $existing = DB::table('activity_registrations')
            ->where('activity_id', $activityId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Anda sudah terdaftar pada kegiatan ini',
            ]);
        }

        DB::table('activity_registrations')->insert([
            'activity_id' => $activityId,
            'user_id' => $userId,
            'status' => 'registered',
            'registered_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran kegiatan berhasil',
        ]);
    }

    public function attend(Request $request, $activityId)
    {
        $userId = $request->input('user_id', 1);

        $activity = DB::table('activities')->where('id', $activityId)->first();

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Kegiatan tidak ditemukan',
            ], 404);
        }

        $registration = DB::table('activity_registrations')
            ->where('activity_id', $activityId)
            ->where('user_id', $userId)
            ->first();

        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'Anda harus mendaftar kegiatan terlebih dahulu sebelum melakukan absensi',
            ], 400);
        }

        $existingAttendance = DB::table('activity_attendances')
            ->where('activity_id', $activityId)
            ->where('user_id', $userId)
            ->first();

        if ($existingAttendance) {
            return response()->json([
                'success' => true,
                'message' => 'Anda sudah melakukan absensi pada kegiatan ini',
            ]);
        }

        DB::table('activity_attendances')->insert([
            'activity_id' => $activityId,
            'user_id' => $userId,
            'attendance_status' => 'present',
            'attended_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Absensi kegiatan berhasil',
        ]);
    }
}
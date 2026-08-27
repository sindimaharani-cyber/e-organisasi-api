<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrganizationProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrganizationProfileController extends Controller
{
    // =========================================================
    // TAMPILKAN PROFIL ORGANISASI
    // =========================================================

    public function show(): JsonResponse
    {
        $profile = OrganizationProfile::query()->first();

        if (!$profile) {
            $profile = OrganizationProfile::query()->create([
                'organization_name' => 'HIMATIF UIR',

                'subtitle' =>
                    'Himpunan Mahasiswa Teknik Informatika '
                    . 'Universitas Islam Riau',

                'history' =>
                    'Himpunan Mahasiswa Teknik Informatika Universitas '
                    . 'Islam Riau merupakan organisasi kemahasiswaan '
                    . 'yang menjadi wadah bagi mahasiswa Teknik Informatika.',

                'vision' =>
                    'Mewujudkan HIMATIF UIR sebagai organisasi mahasiswa '
                    . 'yang aktif, inovatif, profesional, dan berintegritas.',

                'mission' =>
                    'Meningkatkan kualitas anggota, memperkuat solidaritas, '
                    . 'serta mendukung pengembangan akademik dan nonakademik.',

                'goals' =>
                    'Menjadi wadah pengembangan mahasiswa Teknik Informatika '
                    . 'dalam meningkatkan kompetensi, kepemimpinan, '
                    . 'dan kontribusi terhadap lingkungan kampus.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil organisasi berhasil diambil.',
            'data' => $profile,
        ]);
    }

    // =========================================================
    // UPDATE PROFIL ORGANISASI
    // INI METHOD YANG SEKARANG HILANG
    // =========================================================

    public function update(Request $request): JsonResponse
    {
        $profile = OrganizationProfile::query()->first();

        if (!$profile) {
            $profile = OrganizationProfile::query()->create([
                'organization_name' => 'HIMATIF UIR',
            ]);
        }

        $validated = $request->validate([
            'organization_name' => [
                'required',
                'string',
                'max:150',
            ],

            'subtitle' => [
                'nullable',
                'string',
                'max:255',
            ],

            'history' => [
                'nullable',
                'string',
            ],

            'vision' => [
                'nullable',
                'string',
            ],

            'mission' => [
                'nullable',
                'string',
            ],

            'goals' => [
                'nullable',
                'string',
            ],
        ]);

        $profile->update([
            'organization_name' =>
                trim($validated['organization_name']),

            'subtitle' =>
                trim($validated['subtitle'] ?? ''),

            'history' =>
                trim($validated['history'] ?? ''),

            'vision' =>
                trim($validated['vision'] ?? ''),

            'mission' =>
                trim($validated['mission'] ?? ''),

            'goals' =>
                trim($validated['goals'] ?? ''),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profil organisasi berhasil diperbarui.',
            'data' => $profile->fresh(),
        ]);
    }

    // =========================================================
    // UPLOAD FOTO ORGANISASI
    // =========================================================

    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);

        $profile = OrganizationProfile::query()->first();

        if (!$profile) {
            $profile = OrganizationProfile::query()->create([
                'organization_name' => 'HIMATIF UIR',
            ]);
        }

        if (
            $profile->photo_path &&
            Storage::disk('public')->exists($profile->photo_path)
        ) {
            Storage::disk('public')->delete($profile->photo_path);
        }

        $path = $request
            ->file('photo')
            ->store('organization/profile', 'public');

        $profile->update([
            'photo_path' => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Foto organisasi berhasil diunggah.',
            'data' => $profile->fresh(),
        ]);
    }

    // =========================================================
    // HAPUS FOTO
    // =========================================================

    public function deletePhoto(): JsonResponse
    {
        $profile = OrganizationProfile::query()->first();

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profil organisasi belum tersedia.',
            ], 404);
        }

        if (
            $profile->photo_path &&
            Storage::disk('public')->exists($profile->photo_path)
        ) {
            Storage::disk('public')->delete($profile->photo_path);
        }

        $profile->update([
            'photo_path' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Foto organisasi berhasil dihapus.',
            'data' => $profile->fresh(),
        ]);
    }
}
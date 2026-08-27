<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LIST PENGUMUMAN
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        $showArchived = $request->boolean(
            'archived',
            false
        );

        $query = Announcement::query()
            ->where(
                'archived',
                $showArchived
            );

        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->input('search')
            );

            $query->where(
                function ($builder) use ($search) {
                    $builder
                        ->where(
                            'title',
                            'like',
                            '%' . $search . '%'
                        )
                        ->orWhere(
                            'content',
                            'like',
                            '%' . $search . '%'
                        )
                        ->orWhere(
                            'category',
                            'like',
                            '%' . $search . '%'
                        )
                        ->orWhere(
                            'author',
                            'like',
                            '%' . $search . '%'
                        );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FILTER CATEGORY
        |--------------------------------------------------------------------------
        */

        if ($request->filled('category')) {
            $query->where(
                'category',
                $request->input('category')
            );
        }

        $announcements = $query
            ->orderByDesc('created_at')
            ->get()
            ->map(
                function (Announcement $announcement) {
                    return $this->formatAnnouncement(
                        $announcement
                    );
                }
            );

        return response()->json([
            'success' => true,
            'message' =>
                'Data pengumuman berhasil diambil.',
            'data' => $announcements,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL
    |--------------------------------------------------------------------------
    */

    public function show(
        int $id
    ): JsonResponse {
        $announcement = Announcement::query()
            ->find($id);

        if (!$announcement) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pengumuman tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Detail pengumuman berhasil diambil.',
            'data' =>
                $this->formatAnnouncement(
                    $announcement
                ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TAMBAH
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anda belum login.',
            ], 401);
        }

        if (!$this->canManage($user)) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anda tidak memiliki akses menambahkan pengumuman.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:150',
            ],

            'category' => [
                'required',
                'string',
                Rule::in([
                    'Informasi',
                    'Rapat',
                    'Acara',
                    'Kegiatan',
                    'Akademik',
                    'Keuangan',
                    'Penting',
                    'Lainnya',
                ]),
            ],

            'content' => [
                'required',
                'string',
                'max:10000',
            ],
        ]);

        $author = trim(
            (string) (
                $user->name
                ?? $user->nama
                ?? 'Admin HIMATIF'
            )
        );

        if ($author === '') {
            $author = 'Admin HIMATIF';
        }

        $announcement = Announcement::query()
            ->create([
                'title' => trim(
                    $validated['title']
                ),

                'category' => trim(
                    $validated['category']
                ),

                'content' => trim(
                    $validated['content']
                ),

                'author' => $author,

                'archived' => false,
            ]);

        $announcement->refresh();

        return response()->json([
            'success' => true,
            'message' =>
                'Pengumuman berhasil ditambahkan.',
            'data' =>
                $this->formatAnnouncement(
                    $announcement
                ),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        $user = $request->user();

        if (!$this->canManage($user)) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anda tidak memiliki akses.',
            ], 403);
        }

        $announcement = Announcement::query()
            ->find($id);

        if (!$announcement) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pengumuman tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'category' => [
                'sometimes',
                'required',
                Rule::in([
                    'Informasi',
                    'Rapat',
                    'Acara',
                    'Kegiatan',
                    'Akademik',
                    'Keuangan',
                    'Penting',
                    'Lainnya',
                ]),
            ],

            'content' => [
                'sometimes',
                'required',
                'string',
                'max:10000',
            ],
        ]);

        $updateData = [];

        if (isset($validated['title'])) {
            $updateData['title'] = trim(
                $validated['title']
            );
        }

        if (isset($validated['category'])) {
            $updateData['category'] = trim(
                $validated['category']
            );
        }

        if (isset($validated['content'])) {
            $updateData['content'] = trim(
                $validated['content']
            );
        }

        $announcement->update(
            $updateData
        );

        $announcement->refresh();

        return response()->json([
            'success' => true,
            'message' =>
                'Pengumuman berhasil diperbarui.',
            'data' =>
                $this->formatAnnouncement(
                    $announcement
                ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ARSIP
    |--------------------------------------------------------------------------
    */

    public function archive(
        Request $request,
        int $id
    ): JsonResponse {
        $user = $request->user();

        if (!$this->canManage($user)) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anda tidak memiliki akses.',
            ], 403);
        }

        $announcement = Announcement::query()
            ->find($id);

        if (!$announcement) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pengumuman tidak ditemukan.',
            ], 404);
        }

        $announcement->update([
            'archived' => true,
        ]);

        $announcement->refresh();

        return response()->json([
            'success' => true,
            'message' =>
                'Pengumuman berhasil diarsipkan.',
            'data' =>
                $this->formatAnnouncement(
                    $announcement
                ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | RESTORE
    |--------------------------------------------------------------------------
    */

    public function restore(
        Request $request,
        int $id
    ): JsonResponse {
        $user = $request->user();

        if (!$this->canManage($user)) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anda tidak memiliki akses.',
            ], 403);
        }

        $announcement = Announcement::query()
            ->find($id);

        if (!$announcement) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pengumuman tidak ditemukan.',
            ], 404);
        }

        $announcement->update([
            'archived' => false,
        ]);

        $announcement->refresh();

        return response()->json([
            'success' => true,
            'message' =>
                'Pengumuman berhasil dikembalikan.',
            'data' =>
                $this->formatAnnouncement(
                    $announcement
                ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {
        $user = $request->user();

        if (!$this->canManage($user)) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Anda tidak memiliki akses.',
            ], 403);
        }

        $announcement = Announcement::query()
            ->find($id);

        if (!$announcement) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pengumuman tidak ditemukan.',
            ], 404);
        }

        $announcement->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Pengumuman berhasil dihapus.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD
    |--------------------------------------------------------------------------
    */

    public function download(
        int $id
    ): JsonResponse {
        $announcement = Announcement::query()
            ->find($id);

        if (!$announcement) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pengumuman tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => false,
            'message' =>
                'Pengumuman ini tidak memiliki file lampiran.',
        ], 404);
    }

    /*
    |--------------------------------------------------------------------------
    | ROLE CHECK
    |--------------------------------------------------------------------------
    */

    private function canManage(
        $user
    ): bool {
        if (!$user) {
            return false;
        }

        return in_array(
            strtolower(
                trim(
                    (string) $user->role
                )
            ),
            [
                'admin',
                'pengurus',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT RESPONSE
    |--------------------------------------------------------------------------
    */

    private function formatAnnouncement(
        Announcement $announcement
    ): array {
        return [
            'id' =>
                $announcement->id,

            'title' =>
                $announcement->title,

            'content' =>
                $announcement->content,

            'category' =>
                $announcement->category,

            'author' =>
                $announcement->author,

            'archived' =>
                (bool) $announcement->archived,

            /*
            |--------------------------------------------------------------------------
            | ALIAS UNTUK FLUTTER
            |--------------------------------------------------------------------------
            */

            'created_by' =>
                null,

            'creator' => [
                'name' =>
                    $announcement->author,

                'email' =>
                    null,
            ],

            'status' =>
                $announcement->archived
                    ? 'archived'
                    : 'active',

            'published_at' =>
                $announcement->created_at
                    ? $announcement
                        ->created_at
                        ->toISOString()
                    : null,

            'created_at' =>
                $announcement->created_at
                    ? $announcement
                        ->created_at
                        ->toISOString()
                    : null,

            'updated_at' =>
                $announcement->updated_at
                    ? $announcement
                        ->updated_at
                        ->toISOString()
                    : null,
        ];
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrganizationDocument;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class OrganizationDocumentController extends Controller
{
    /**
     * ADMIN + PENGURUS + MAHASISWA
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->canView($request)) {
            return $this->forbidden();
        }

        $request->validate([
            'type' => [
                'nullable',
                Rule::in([
                    'all',
                    'photo',
                    'proposal',
                    'lpj',
                    'notulen',
                    'other',
                ]),
            ],

            'activity_name' => [
                'nullable',
                'string',
                'max:200',
            ],

            'search' => [
                'nullable',
                'string',
                'max:200',
            ],

            'start_date' => [
                'nullable',
                'date',
            ],

            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = OrganizationDocument::query()
            ->with([
                'uploader:id,name,email,role',
            ]);

        if (
            $request->filled('type') &&
            $request->input('type') !== 'all'
        ) {
            $query->where(
                'type',
                $request->input('type')
            );
        }

        if ($request->filled('activity_name')) {
            $activityName = trim(
                (string) $request->input('activity_name')
            );

            $query->where(
                'activity_name',
                'like',
                "%{$activityName}%"
            );
        }

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->input('search')
            );

            $query->where(
                function (Builder $builder) use ($search) {
                    $builder
                        ->where(
                            'title',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'activity_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'description',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'original_name',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        $this->applyDateFilter(
            $query,
            $request
        );

        $perPage = (int) $request->input(
            'per_page',
            20
        );

        $perPage = max(
            1,
            min($perPage, 100)
        );

        $documents = $query
            ->orderByDesc('document_date')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $documents
            ->getCollection()
            ->transform(
                function (
                    OrganizationDocument $document
                ) use ($request) {
                    return $this->formatDocument(
                        $document,
                        $request
                    );
                }
            );

        return response()->json([
            'success' => true,
            'message' => 'Dokumentasi kegiatan berhasil diambil.',
            'data' => $documents,
        ]);
    }

    /**
     * ADMIN + PENGURUS + MAHASISWA
     */
    public function summary(Request $request): JsonResponse
    {
        if (!$this->canView($request)) {
            return $this->forbidden();
        }

        $request->validate([
            'start_date' => [
                'nullable',
                'date',
            ],

            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],
        ]);

        $query = OrganizationDocument::query();

        $this->applyDateFilter(
            $query,
            $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan dokumentasi berhasil diambil.',
            'data' => [
                'total' =>
                    (clone $query)->count(),

                'photos' =>
                    (clone $query)
                        ->where('type', 'photo')
                        ->count(),

                'proposals' =>
                    (clone $query)
                        ->where('type', 'proposal')
                        ->count(),

                'lpj' =>
                    (clone $query)
                        ->where('type', 'lpj')
                        ->count(),

                'notulen' =>
                    (clone $query)
                        ->where('type', 'notulen')
                        ->count(),

                'other' =>
                    (clone $query)
                        ->where('type', 'other')
                        ->count(),

                'total_size' =>
                    (int) (clone $query)
                        ->sum('file_size'),
            ],
        ]);
    }

    /**
     * ADMIN + PENGURUS + MAHASISWA
     */
    public function show(
        Request $request,
        int $id
    ): JsonResponse {
        if (!$this->canView($request)) {
            return $this->forbidden();
        }

        $document =
            OrganizationDocument::with([
                'uploader:id,name,email,role',
            ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail dokumentasi berhasil diambil.',
            'data' => $this->formatDocument(
                $document,
                $request
            ),
        ]);
    }

    /**
     * ADMIN ONLY
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->canManage($request)) {
            return $this->manageForbidden();
        }

        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:200',
            ],

            'type' => [
                'required',
                Rule::in([
                    'photo',
                    'proposal',
                    'lpj',
                    'notulen',
                    'other',
                ]),
            ],

            'activity_name' => [
                'required',
                'string',
                'max:200',
            ],

            'document_date' => [
                'nullable',
                'date',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'file' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png',
                'max:10240',
            ],
        ]);

        $file = $request->file('file');

        if (!$file instanceof UploadedFile) {
            return response()->json([
                'success' => false,
                'message' => 'File dokumentasi tidak ditemukan.',
            ], 422);
        }

        $filePath = $file->store(
            'organization-documents',
            'public'
        );

        try {
            $document = OrganizationDocument::create([
                'uploaded_by' =>
                    $request->user()->id,

                'title' =>
                    $validated['title'],

                'type' =>
                    $validated['type'],

                'activity_name' =>
                    $validated['activity_name'],

                'document_date' =>
                    $validated['document_date'] ?? null,

                'description' =>
                    $validated['description'] ?? null,

                'file_path' =>
                    $filePath,

                'original_name' =>
                    $file->getClientOriginalName(),

                'mime_type' =>
                    $file->getMimeType(),

                'file_size' =>
                    $file->getSize(),
            ]);

            $document->load([
                'uploader:id,name,email,role',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Dokumentasi berhasil ditambahkan.',
                'data' => $this->formatDocument(
                    $document,
                    $request
                ),
            ], 201);
        } catch (Throwable $exception) {
            $this->deleteFile(
                $filePath
            );

            throw $exception;
        }
    }

    /**
     * ADMIN ONLY
     */
    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        if (!$this->canManage($request)) {
            return $this->manageForbidden();
        }

        $document =
            OrganizationDocument::findOrFail($id);

        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:200',
            ],

            'type' => [
                'required',
                Rule::in([
                    'photo',
                    'proposal',
                    'lpj',
                    'notulen',
                    'other',
                ]),
            ],

            'activity_name' => [
                'required',
                'string',
                'max:200',
            ],

            'document_date' => [
                'nullable',
                'date',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'file' => [
                'nullable',
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png',
                'max:10240',
            ],
        ]);

        $updateData = [
            'title' =>
                $validated['title'],

            'type' =>
                $validated['type'],

            'activity_name' =>
                $validated['activity_name'],

            'document_date' =>
                $validated['document_date'] ?? null,

            'description' =>
                $validated['description'] ?? null,
        ];

        $file = $request->file('file');

        if ($file instanceof UploadedFile) {
            $newPath = $file->store(
                'organization-documents',
                'public'
            );

            $oldPath =
                $document->file_path;

            $updateData['file_path'] =
                $newPath;

            $updateData['original_name'] =
                $file->getClientOriginalName();

            $updateData['mime_type'] =
                $file->getMimeType();

            $updateData['file_size'] =
                $file->getSize();

            try {
                $document->update(
                    $updateData
                );

                $this->deleteFile(
                    $oldPath
                );
            } catch (Throwable $exception) {
                $this->deleteFile(
                    $newPath
                );

                throw $exception;
            }
        } else {
            $document->update(
                $updateData
            );
        }

        $document->refresh();

        $document->load([
            'uploader:id,name,email,role',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dokumentasi berhasil diperbarui.',
            'data' => $this->formatDocument(
                $document,
                $request
            ),
        ]);
    }

    /**
     * ADMIN ONLY
     */
    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {
        if (!$this->canManage($request)) {
            return $this->manageForbidden();
        }

        $document =
            OrganizationDocument::findOrFail($id);

        $filePath =
            $document->file_path;

        $document->delete();

        $this->deleteFile(
            $filePath
        );

        return response()->json([
            'success' => true,
            'message' => 'Dokumentasi berhasil dihapus.',
        ]);
    }

    private function applyDateFilter(
        Builder $query,
        Request $request
    ): void {
        if ($request->filled('start_date')) {
            $query->whereDate(
                'document_date',
                '>=',
                $request->input('start_date')
            );
        }

        if ($request->filled('end_date')) {
            $query->whereDate(
                'document_date',
                '<=',
                $request->input('end_date')
            );
        }
    }

    private function deleteFile(
        ?string $filePath
    ): void {
        if (
            $filePath === null ||
            trim($filePath) === ''
        ) {
            return;
        }

        if (
            Storage::disk('public')
                ->exists($filePath)
        ) {
            Storage::disk('public')
                ->delete($filePath);
        }
    }

    /**
     * ADMIN + PENGURUS + MAHASISWA
     */
    private function canView(
        Request $request
    ): bool {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole([
            'admin',
            'pengurus',
            'mahasiswa',
        ]);
    }

    /**
     * ADMIN ONLY
     */
    private function canManage(
        Request $request
    ): bool {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole(
            'admin'
        );
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' =>
                'Anda tidak memiliki akses ke dokumentasi kegiatan.',
        ], 403);
    }

    private function manageForbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' =>
                'Hanya Admin yang dapat mengelola dokumentasi kegiatan.',
        ], 403);
    }

    private function formatDocument(
        OrganizationDocument $document,
        Request $request
    ): array {
        $fileUrl = null;

        if (
            $document->file_path !== null &&
            trim($document->file_path) !== ''
        ) {
            $fileUrl =
                $request->getSchemeAndHttpHost()
                . Storage::url(
                    $document->file_path
                );
        }

        return [
            'id' =>
                $document->id,

            'uploaded_by' =>
                $document->uploaded_by,

            'title' =>
                $document->title,

            'type' =>
                $document->type,

            'activity_name' =>
                $document->activity_name,

            'document_date' =>
                $this->formatDate(
                    $document->document_date
                ),

            'description' =>
                $document->description,

            'file_path' =>
                $document->file_path,

            'file_url' =>
                $fileUrl,

            'original_name' =>
                $document->original_name,

            'mime_type' =>
                $document->mime_type,

            'file_size' =>
                (int) $document->file_size,

            'uploader' =>
                $document->uploader
                    ? [
                        'id' =>
                            $document->uploader->id,

                        'name' =>
                            $document->uploader->name,

                        'email' =>
                            $document->uploader->email,

                        'role' =>
                            $document->uploader->role,
                    ]
                    : null,

            'created_at' =>
                $this->formatDateTime(
                    $document->created_at
                ),

            'updated_at' =>
                $this->formatDateTime(
                    $document->updated_at
                ),
        ];
    }

    private function formatDate(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        try {
            return Carbon::parse(
                (string) $value
            )->format('Y-m-d');
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function formatDateTime(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(
                DATE_ATOM
            );
        }

        try {
            return Carbon::parse(
                (string) $value
            )->format(
                DATE_ATOM
            );
        } catch (Throwable $exception) {
            return null;
        }
    }
}
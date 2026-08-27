<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrganizationLetter;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class OrganizationLetterController extends Controller
{
    /**
     * ADMIN + PENGURUS
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
                    'incoming',
                    'outgoing',
                    'invitation',
                    'assignment',
                    'other',
                ]),
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

        $query = OrganizationLetter::query()
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

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->input('search')
            );

            $query->where(
                function (Builder $builder) use ($search) {
                    $builder
                        ->where(
                            'letter_number',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'subject',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'sender',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'recipient',
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

        $letters = $query
            ->orderByDesc('letter_date')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $letters
            ->getCollection()
            ->transform(
                function (
                    OrganizationLetter $letter
                ) use ($request) {
                    return $this->formatLetter(
                        $letter,
                        $request
                    );
                }
            );

        return response()->json([
            'success' => true,
            'message' => 'Arsip surat berhasil diambil.',
            'data' => $letters,
        ]);
    }

    /**
     * ADMIN + PENGURUS
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

        $query = OrganizationLetter::query();

        $this->applyDateFilter(
            $query,
            $request
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Ringkasan arsip surat berhasil diambil.',
            'data' => [
                'total' =>
                    (clone $query)->count(),

                'incoming' =>
                    (clone $query)
                        ->where('type', 'incoming')
                        ->count(),

                'outgoing' =>
                    (clone $query)
                        ->where('type', 'outgoing')
                        ->count(),

                'invitation' =>
                    (clone $query)
                        ->where('type', 'invitation')
                        ->count(),

                'assignment' =>
                    (clone $query)
                        ->where('type', 'assignment')
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
     * ADMIN + PENGURUS
     */
    public function show(
        Request $request,
        int $id
    ): JsonResponse {
        if (!$this->canView($request)) {
            return $this->forbidden();
        }

        $letter =
            OrganizationLetter::with([
                'uploader:id,name,email,role',
            ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' =>
                'Detail arsip surat berhasil diambil.',
            'data' => $this->formatLetter(
                $letter,
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
            'letter_number' => [
                'nullable',
                'string',
                'max:150',
            ],

            'subject' => [
                'required',
                'string',
                'max:200',
            ],

            'type' => [
                'required',
                Rule::in([
                    'incoming',
                    'outgoing',
                    'invitation',
                    'assignment',
                    'other',
                ]),
            ],

            'letter_date' => [
                'required',
                'date',
            ],

            'sender' => [
                'nullable',
                'string',
                'max:200',
            ],

            'recipient' => [
                'nullable',
                'string',
                'max:200',
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
                'message' => 'File surat tidak ditemukan.',
            ], 422);
        }

        $filePath = $file->store(
            'organization-letters',
            'public'
        );

        try {
            $letter = OrganizationLetter::create([
                'uploaded_by' =>
                    $request->user()->id,

                'letter_number' =>
                    $validated['letter_number'] ?? null,

                'subject' =>
                    $validated['subject'],

                'type' =>
                    $validated['type'],

                'letter_date' =>
                    $validated['letter_date'],

                'sender' =>
                    $validated['sender'] ?? null,

                'recipient' =>
                    $validated['recipient'] ?? null,

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

            $letter->load([
                'uploader:id,name,email,role',
            ]);

            return response()->json([
                'success' => true,
                'message' =>
                    'Arsip surat berhasil ditambahkan.',
                'data' => $this->formatLetter(
                    $letter,
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

        $letter =
            OrganizationLetter::findOrFail($id);

        $validated = $request->validate([
            'letter_number' => [
                'nullable',
                'string',
                'max:150',
            ],

            'subject' => [
                'required',
                'string',
                'max:200',
            ],

            'type' => [
                'required',
                Rule::in([
                    'incoming',
                    'outgoing',
                    'invitation',
                    'assignment',
                    'other',
                ]),
            ],

            'letter_date' => [
                'required',
                'date',
            ],

            'sender' => [
                'nullable',
                'string',
                'max:200',
            ],

            'recipient' => [
                'nullable',
                'string',
                'max:200',
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
            'letter_number' =>
                $validated['letter_number'] ?? null,

            'subject' =>
                $validated['subject'],

            'type' =>
                $validated['type'],

            'letter_date' =>
                $validated['letter_date'],

            'sender' =>
                $validated['sender'] ?? null,

            'recipient' =>
                $validated['recipient'] ?? null,

            'description' =>
                $validated['description'] ?? null,
        ];

        $file = $request->file('file');

        if ($file instanceof UploadedFile) {
            $newPath = $file->store(
                'organization-letters',
                'public'
            );

            $oldPath =
                $letter->file_path;

            $updateData['file_path'] =
                $newPath;

            $updateData['original_name'] =
                $file->getClientOriginalName();

            $updateData['mime_type'] =
                $file->getMimeType();

            $updateData['file_size'] =
                $file->getSize();

            try {
                $letter->update(
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
            $letter->update(
                $updateData
            );
        }

        $letter->refresh();

        $letter->load([
            'uploader:id,name,email,role',
        ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Arsip surat berhasil diperbarui.',
            'data' => $this->formatLetter(
                $letter,
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

        $letter =
            OrganizationLetter::findOrFail($id);

        $filePath =
            $letter->file_path;

        $letter->delete();

        $this->deleteFile(
            $filePath
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Arsip surat berhasil dihapus.',
        ]);
    }

    private function applyDateFilter(
        Builder $query,
        Request $request
    ): void {
        if ($request->filled('start_date')) {
            $query->whereDate(
                'letter_date',
                '>=',
                $request->input('start_date')
            );
        }

        if ($request->filled('end_date')) {
            $query->whereDate(
                'letter_date',
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
     * ADMIN + PENGURUS
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
                'Anda tidak memiliki akses ke arsip surat.',
        ], 403);
    }

    private function manageForbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' =>
                'Hanya Admin yang dapat mengelola arsip surat.',
        ], 403);
    }

    private function formatLetter(
        OrganizationLetter $letter,
        Request $request
    ): array {
        $fileUrl = null;

        if (
            $letter->file_path !== null &&
            trim($letter->file_path) !== ''
        ) {
            $fileUrl =
                $request->getSchemeAndHttpHost()
                . Storage::url(
                    $letter->file_path
                );
        }

        return [
            'id' =>
                $letter->id,

            'uploaded_by' =>
                $letter->uploaded_by,

            'letter_number' =>
                $letter->letter_number,

            'subject' =>
                $letter->subject,

            'type' =>
                $letter->type,

            'letter_date' =>
                $this->formatDate(
                    $letter->letter_date
                ),

            'sender' =>
                $letter->sender,

            'recipient' =>
                $letter->recipient,

            'description' =>
                $letter->description,

            'file_path' =>
                $letter->file_path,

            'file_url' =>
                $fileUrl,

            'original_name' =>
                $letter->original_name,

            'mime_type' =>
                $letter->mime_type,

            'file_size' =>
                (int) $letter->file_size,

            'uploader' =>
                $letter->uploader
                    ? [
                        'id' =>
                            $letter->uploader->id,

                        'name' =>
                            $letter->uploader->name,

                        'email' =>
                            $letter->uploader->email,

                        'role' =>
                            $letter->uploader->role,
                    ]
                    : null,

            'created_at' =>
                $this->formatDateTime(
                    $letter->created_at
                ),

            'updated_at' =>
                $this->formatDateTime(
                    $letter->updated_at
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
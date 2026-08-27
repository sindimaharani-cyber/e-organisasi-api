<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialTransaction;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class FinanceController extends Controller
{
    /**
     * ============================================================
     * GET LIST TRANSAKSI
     * Admin + Pengurus
     * ============================================================
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->canViewFinance($request)) {
            return $this->forbidden();
        }

        $request->validate([
            'type' => [
                'nullable',
                Rule::in([
                    'income',
                    'expense',
                    'all',
                ]),
            ],
            'category' => [
                'nullable',
                'string',
                'max:100',
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

        $query = FinancialTransaction::query()
            ->with([
                'creator:id,name,email,role',
            ]);

        /*
         * Filter jenis transaksi.
         */
        if (
            $request->filled('type') &&
            $request->input('type') !== 'all'
        ) {
            $query->where(
                'type',
                $request->input('type')
            );
        }

        /*
         * Filter kategori.
         */
        if ($request->filled('category')) {
            $query->where(
                'category',
                $request->input('category')
            );
        }

        /*
         * Pencarian.
         */
        if ($request->filled('search')) {
            $search = trim(
                (string) $request->input('search')
            );

            $query->where(function ($q) use ($search) {
                $q->where(
                    'title',
                    'like',
                    "%{$search}%"
                )
                    ->orWhere(
                        'category',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'description',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        /*
         * Filter periode.
         */
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

        $transactions = $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        /*
         * Format response supaya konsisten untuk Flutter.
         */
        $transactions
            ->getCollection()
            ->transform(
                function (
                    FinancialTransaction $transaction
                ) use ($request) {
                    return $this->formatTransaction(
                        $transaction,
                        $request
                    );
                }
            );

        return response()->json([
            'success' => true,
            'message' => 'Data keuangan berhasil diambil.',
            'data' => $transactions,
        ]);
    }

    /**
     * ============================================================
     * RINGKASAN KEUANGAN
     * Admin + Pengurus
     * ============================================================
     */
    public function summary(Request $request): JsonResponse
    {
        if (!$this->canViewFinance($request)) {
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

        $query = FinancialTransaction::query();

        /*
         * Jika Flutter memilih periode,
         * summary juga mengikuti periode tersebut.
         */
        $this->applyDateFilter(
            $query,
            $request
        );

        $totalIncome = (float) (
            (clone $query)
                ->where(
                    'type',
                    'income'
                )
                ->sum('amount')
        );

        $totalExpense = (float) (
            (clone $query)
                ->where(
                    'type',
                    'expense'
                )
                ->sum('amount')
        );

        $balance =
            $totalIncome - $totalExpense;

        $transactionCount =
            (clone $query)->count();

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan keuangan berhasil diambil.',
            'data' => [
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'balance' => $balance,
                'transaction_count' => $transactionCount,
            ],
        ]);
    }

    /**
     * ============================================================
     * DETAIL TRANSAKSI
     * Admin + Pengurus
     * ============================================================
     */
    public function show(
        Request $request,
        int $id
    ): JsonResponse {
        if (!$this->canViewFinance($request)) {
            return $this->forbidden();
        }

        $transaction =
            FinancialTransaction::with([
                'creator:id,name,email,role',
            ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail transaksi berhasil diambil.',
            'data' => $this->formatTransaction(
                $transaction,
                $request
            ),
        ]);
    }

    /**
     * ============================================================
     * TAMBAH TRANSAKSI
     * ADMIN ONLY
     * ============================================================
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->canManageFinance($request)) {
            return $this->manageForbidden();
        }

        $validated = $request->validate([
            'type' => [
                'required',
                Rule::in([
                    'income',
                    'expense',
                ]),
            ],

            'category' => [
                'nullable',
                'string',
                'max:100',
            ],

            'title' => [
                'required',
                'string',
                'max:150',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'transaction_date' => [
                'required',
                'date',
            ],

            'proof_file' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ]);

        $proofPath = null;

        /*
         * Upload bukti transaksi.
         */
        if ($request->hasFile('proof_file')) {
            $proofPath =
                $request
                    ->file('proof_file')
                    ->store(
                        'finance-proofs',
                        'public'
                    );
        }

        try {
            $transaction =
                FinancialTransaction::create([
                    'created_by' =>
                        $request->user()->id,

                    'type' =>
                        $validated['type'],

                    'category' =>
                        $validated['category'] ?? null,

                    'title' =>
                        $validated['title'],

                    'description' =>
                        $validated['description'] ?? null,

                    'amount' =>
                        $validated['amount'],

                    'transaction_date' =>
                        $validated['transaction_date'],

                    'proof_file' =>
                        $proofPath,
                ]);

            $transaction->load([
                'creator:id,name,email,role',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil ditambahkan.',
                'data' => $this->formatTransaction(
                    $transaction,
                    $request
                ),
            ], 201);
        } catch (Throwable $exception) {
            /*
             * Jika database gagal,
             * file yang sudah terupload dibersihkan.
             */
            if (
                $proofPath !== null &&
                Storage::disk('public')->exists(
                    $proofPath
                )
            ) {
                Storage::disk('public')->delete(
                    $proofPath
                );
            }

            throw $exception;
        }
    }

    /**
     * ============================================================
     * UPDATE TRANSAKSI
     * ADMIN ONLY
     * ============================================================
     */
    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        if (!$this->canManageFinance($request)) {
            return $this->manageForbidden();
        }

        $transaction =
            FinancialTransaction::findOrFail($id);

        $validated = $request->validate([
            'type' => [
                'required',
                Rule::in([
                    'income',
                    'expense',
                ]),
            ],

            'category' => [
                'nullable',
                'string',
                'max:100',
            ],

            'title' => [
                'required',
                'string',
                'max:150',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'transaction_date' => [
                'required',
                'date',
            ],

            'proof_file' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],

            'remove_proof' => [
                'nullable',
                'boolean',
            ],
        ]);

        $updateData = [
            'type' =>
                $validated['type'],

            'category' =>
                $validated['category'] ?? null,

            'title' =>
                $validated['title'],

            'description' =>
                $validated['description'] ?? null,

            'amount' =>
                $validated['amount'],

            'transaction_date' =>
                $validated['transaction_date'],
        ];

        /*
         * ========================================================
         * JIKA ADA FILE BUKTI BARU
         * ========================================================
         */
        if ($request->hasFile('proof_file')) {
            /*
             * Hapus file lama.
             */
            $this->deleteProofFile(
                $transaction->proof_file
            );

            /*
             * Simpan file baru.
             */
            $newProofPath =
                $request
                    ->file('proof_file')
                    ->store(
                        'finance-proofs',
                        'public'
                    );

            $updateData['proof_file'] =
                $newProofPath;
        }
        /*
         * ========================================================
         * JIKA ADMIN MEMILIH HAPUS BUKTI
         * ========================================================
         */
        elseif (
            $request->boolean('remove_proof')
        ) {
            $this->deleteProofFile(
                $transaction->proof_file
            );

            $updateData['proof_file'] =
                null;
        }

        $transaction->update(
            $updateData
        );

        $transaction->refresh();

        $transaction->load([
            'creator:id,name,email,role',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil diperbarui.',
            'data' => $this->formatTransaction(
                $transaction,
                $request
            ),
        ]);
    }

    /**
     * ============================================================
     * HAPUS TRANSAKSI
     * ADMIN ONLY
     * ============================================================
     */
    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {
        if (!$this->canManageFinance($request)) {
            return $this->manageForbidden();
        }

        $transaction =
            FinancialTransaction::findOrFail($id);

        /*
         * Hapus bukti transaksi jika ada.
         */
        $this->deleteProofFile(
            $transaction->proof_file
        );

        /*
         * Hapus data database.
         */
        $transaction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil dihapus.',
        ]);
    }

    /**
     * ============================================================
     * FILTER PERIODE
     * ============================================================
     */
    private function applyDateFilter(
        $query,
        Request $request
    ): void {
        if ($request->filled('start_date')) {
            $query->whereDate(
                'transaction_date',
                '>=',
                $request->input('start_date')
            );
        }

        if ($request->filled('end_date')) {
            $query->whereDate(
                'transaction_date',
                '<=',
                $request->input('end_date')
            );
        }
    }

    /**
     * ============================================================
     * HAPUS FILE BUKTI
     * ============================================================
     */
    private function deleteProofFile(
        ?string $proofFile
    ): void {
        if (
            $proofFile === null ||
            trim($proofFile) === ''
        ) {
            return;
        }

        if (
            Storage::disk('public')->exists(
                $proofFile
            )
        ) {
            Storage::disk('public')->delete(
                $proofFile
            );
        }
    }

    /**
     * ============================================================
     * ROLE - LIHAT KEUANGAN
     * ADMIN + PENGURUS
     * ============================================================
     */
    private function canViewFinance(
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
     * ============================================================
     * ROLE - KELOLA KEUANGAN
     * ADMIN ONLY
     * ============================================================
     */
    private function canManageFinance(
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

    /**
     * ============================================================
     * RESPONSE TIDAK PUNYA AKSES
     * ============================================================
     */
    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' =>
                'Anda tidak memiliki akses ke modul keuangan.',
        ], 403);
    }

    /**
     * ============================================================
     * RESPONSE HANYA ADMIN
     * ============================================================
     */
    private function manageForbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' =>
                'Hanya Admin yang dapat mengelola data keuangan.',
        ], 403);
    }

    /**
     * ============================================================
     * FORMAT RESPONSE TRANSAKSI
     * ============================================================
     */
    private function formatTransaction(
        FinancialTransaction $transaction,
        Request $request
    ): array {
        $proofUrl = null;

        /*
         * URL file dibuat berdasarkan host request.
         *
         * Contoh emulator:
         * http://10.0.2.2:8000/storage/finance-proofs/xxx.pdf
         *
         * Jadi Android Emulator dapat membukanya.
         */
        if (
            $transaction->proof_file !== null &&
            trim($transaction->proof_file) !== ''
        ) {
            $proofUrl =
                $request->getSchemeAndHttpHost()
                . Storage::url(
                    $transaction->proof_file
                );
        }

        $creator = null;

        if ($transaction->creator !== null) {
            $creator = [
                'id' =>
                    $transaction->creator->id,

                'name' =>
                    $transaction->creator->name,

                'email' =>
                    $transaction->creator->email,

                'role' =>
                    $transaction->creator->role,
            ];
        }

        return [
            'id' =>
                $transaction->id,

            'created_by' =>
                $transaction->created_by,

            'type' =>
                $transaction->type,

            'category' =>
                $transaction->category,

            'title' =>
                $transaction->title,

            'description' =>
                $transaction->description,

            'amount' =>
                (float) $transaction->amount,

            /*
             * Pakai helper agar VS Code/Intelephense
             * tidak error pada ->format().
             */
            'transaction_date' =>
                $this->formatDateValue(
                    $transaction->transaction_date
                ),

            'proof_file' =>
                $transaction->proof_file,

            'proof_url' =>
                $proofUrl,

            'creator' =>
                $creator,

            'created_at' =>
                $this->formatDateTimeValue(
                    $transaction->created_at
                ),

            'updated_at' =>
                $this->formatDateTimeValue(
                    $transaction->updated_at
                ),
        ];
    }

    /**
     * ============================================================
     * FORMAT DATE
     *
     * Mengatasi warning/error:
     * Undefined method format()
     * ============================================================
     */
    private function formatDateValue(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(
                'Y-m-d'
            );
        }

        try {
            return Carbon::parse(
                (string) $value
            )->format(
                'Y-m-d'
            );
        } catch (Throwable $exception) {
            return null;
        }
    }

    /**
     * ============================================================
     * FORMAT CREATED_AT / UPDATED_AT
     * ============================================================
     */
    private function formatDateTimeValue(
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
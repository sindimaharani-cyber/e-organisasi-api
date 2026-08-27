<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
             * income  = pemasukan
             * expense = pengeluaran
             */
            $table->string('type', 20);

            $table->string('category', 100)
                ->nullable();

            $table->string('title', 150);

            $table->text('description')
                ->nullable();

            $table->decimal(
                'amount',
                15,
                2
            );

            $table->date(
                'transaction_date'
            );

            /*
             * Bukti transaksi:
             * JPG / PNG / PDF
             */
            $table->string('proof_file')
                ->nullable();

            $table->timestamps();

            $table->index('type');
            $table->index('transaction_date');

            $table->index([
                'type',
                'transaction_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'financial_transactions'
        );
    }
};
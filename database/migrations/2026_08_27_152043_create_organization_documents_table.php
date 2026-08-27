<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title', 200);

            // photo, proposal, lpj, notulen, other
            $table->string('type', 30);

            $table->string('activity_name', 200);

            $table->date('document_date')
                ->nullable();

            $table->text('description')
                ->nullable();

            $table->string('file_path');

            $table->string('original_name');

            $table->string('mime_type', 150)
                ->nullable();

            $table->unsignedBigInteger('file_size')
                ->default(0);

            $table->timestamps();

            $table->index('type');
            $table->index('activity_name');
            $table->index('document_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_documents');
    }
};
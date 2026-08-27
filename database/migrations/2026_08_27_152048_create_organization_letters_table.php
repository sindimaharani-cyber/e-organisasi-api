<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_letters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('letter_number', 150)
                ->nullable();

            $table->string('subject', 200);

            // incoming, outgoing, invitation, assignment, other
            $table->string('type', 30);

            $table->date('letter_date');

            $table->string('sender', 200)
                ->nullable();

            $table->string('recipient', 200)
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

            $table->index('letter_number');
            $table->index('type');
            $table->index('letter_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_letters');
    }
};
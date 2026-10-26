<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('note_id')
                ->constrained('notes')
                ->cascadeOnDelete();

            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('file_size');

            $table->timestamps();

            $table->index('note_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_attachments');
    }
};

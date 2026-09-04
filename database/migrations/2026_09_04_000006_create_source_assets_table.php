<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_assets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->unsignedInteger('schema_version');
            $table->string('storage_disk', 120);
            $table->string('storage_path', 500);
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64)->index();
            $table->json('metadata');
            $table->json('provenance');
            $table->timestamp('retrieved_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_assets');
    }
};

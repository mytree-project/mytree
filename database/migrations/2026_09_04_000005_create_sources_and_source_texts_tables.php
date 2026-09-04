<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedInteger('schema_version');
            $table->string('source_type_key', 120);
            $table->unsignedInteger('source_type_schema_version');
            $table->json('metadata');
            $table->timestamps();
        });

        Schema::create('source_texts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_id')->constrained('sources')->cascadeOnDelete();
            $table->unsignedInteger('schema_version');
            $table->string('kind', 32);
            $table->string('language', 35)->nullable();
            $table->text('content');
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['source_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_texts');
        Schema::dropIfExists('sources');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_id')->constrained('sources')->cascadeOnDelete();
            $table->unsignedInteger('schema_version');
            $table->string('kind', 64);
            $table->string('local_key', 255);
            $table->string('role', 120)->nullable();
            $table->text('display_label')->nullable();
            $table->json('raw_data');
            $table->timestamps();

            $table->unique(['source_id', 'local_key']);
            $table->index(['source_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentions');
    }
};

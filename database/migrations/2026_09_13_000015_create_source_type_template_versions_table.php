<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_type_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('template_id');
            $table->unsignedInteger('version');
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->json('compatible_source_types');
            $table->json('default_field_keys');
            $table->boolean('is_active')->default(true);
            $table->string('changed_by')->nullable();
            $table->timestamps();

            $table->unique(['template_id', 'version']);
            $table->index(['is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_type_template_versions');
    }
};

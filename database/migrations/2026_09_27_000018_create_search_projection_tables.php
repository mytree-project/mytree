<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_documents', function (Blueprint $table): void {
            $table->foreignUuid('source_id')->primary()->constrained('sources')->cascadeOnDelete();
            $table->foreignUuid('evidence_state_id')->constrained('evidence_states')->restrictOnDelete();
            $table->uuid('source_revision_id');
            $table->string('source_name')->nullable();
            $table->string('source_type_key');
            $table->char('input_fingerprint', 64);
            $table->char('index_signature', 64);
            $table->boolean('stale')->default(false);
            $table->timestampTz('built_at');

            $table->index(['stale', 'built_at']);
            $table->foreign('source_revision_id')
                ->references('revision_id')
                ->on('source_revisions')
                ->restrictOnDelete();
        });

        Schema::create('search_document_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('source_id')->constrained('search_documents', 'source_id')->cascadeOnDelete();
            $table->string('field');
            $table->text('value');
            $table->string('origin');
            $table->uuid('mention_id')->nullable();
            $table->uuid('claim_id')->nullable();
            $table->uuid('claim_revision_id')->nullable();
            $table->string('language')->nullable();
            $table->string('script')->nullable();
            $table->string('representation_relation')->nullable();
            $table->json('index_forms');

            $table->index(['source_id', 'field']);
            $table->index('claim_revision_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_document_entries');
        Schema::dropIfExists('search_documents');
    }
};

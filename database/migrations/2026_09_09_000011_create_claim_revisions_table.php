<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_id')->constrained('sources')->restrictOnDelete();
            $table->uuid('claim_id');
            $table->unsignedBigInteger('revision_number');
            $table->foreignUuid('subject_mention_revision_id')->constrained('mention_revisions')->restrictOnDelete();
            $table->foreignUuid('object_mention_revision_id')->nullable()->constrained('mention_revisions')->restrictOnDelete();
            $table->unsignedInteger('snapshot_schema_version');
            $table->longText('canonical_payload');
            $table->char('payload_hash', 64);
            $table->timestampTz('recorded_at');
            $table->text('change_note')->nullable();
            $table->string('changed_by')->nullable();

            $table->unique(['source_id', 'claim_id', 'revision_number']);
            $table->index(['source_id', 'claim_id']);
            $table->index(['claim_id', 'payload_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_revisions');
    }
};

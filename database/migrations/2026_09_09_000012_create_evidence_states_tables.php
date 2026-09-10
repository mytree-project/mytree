<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_states', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedInteger('snapshot_schema_version');
            $table->longText('canonical_payload');
            $table->char('payload_hash', 64);
            $table->timestampTz('recorded_at');
            $table->text('change_note')->nullable();
            $table->string('changed_by')->nullable();

            $table->index('payload_hash');
        });

        Schema::create('evidence_state_source_revisions', function (Blueprint $table): void {
            $table->foreignUuid('evidence_state_id')->constrained('evidence_states')->cascadeOnDelete();
            $table->uuid('source_id');
            $table->unsignedBigInteger('revision_number');

            $table->primary(['evidence_state_id', 'source_id']);
            $table->foreign(['source_id', 'revision_number'])
                ->references(['source_id', 'revision_number'])
                ->on('source_revisions')
                ->restrictOnDelete();
        });

        Schema::create('evidence_state_mention_revisions', function (Blueprint $table): void {
            $table->foreignUuid('evidence_state_id')->constrained('evidence_states')->cascadeOnDelete();
            $table->foreignUuid('mention_revision_id')->constrained('mention_revisions')->restrictOnDelete();

            $table->primary(['evidence_state_id', 'mention_revision_id']);
        });

        Schema::create('evidence_state_claim_revisions', function (Blueprint $table): void {
            $table->foreignUuid('evidence_state_id')->constrained('evidence_states')->cascadeOnDelete();
            $table->foreignUuid('claim_revision_id')->constrained('claim_revisions')->restrictOnDelete();

            $table->primary(['evidence_state_id', 'claim_revision_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_state_claim_revisions');
        Schema::dropIfExists('evidence_state_mention_revisions');
        Schema::dropIfExists('evidence_state_source_revisions');
        Schema::dropIfExists('evidence_states');
    }
};

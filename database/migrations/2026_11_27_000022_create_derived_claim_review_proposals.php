<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('derived_claim_proposals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_id')->constrained('sources')->cascadeOnDelete();
            $table->uuid('subject_mention_id');
            $table->string('predicate_key', 120);
            $table->unsignedInteger('predicate_schema_version');
            $table->uuid('object_mention_id')->nullable();
            $table->longText('value_payload')->nullable();
            $table->longText('qualifiers_payload');
            $table->longText('derivation_payload');
            $table->string('rule_id', 160);
            $table->string('output_key', 200);
            $table->char('fingerprint', 64);
            $table->string('status', 32);
            $table->uuid('accepted_claim_id')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'fingerprint'], 'derived_proposal_fingerprint_unique');
            $table->index(['source_id', 'rule_id', 'output_key'], 'derived_proposal_output_index');
            $table->index(['source_id', 'status'], 'derived_proposal_status_index');
            $table->foreign(['source_id', 'subject_mention_id'])
                ->references(['source_id', 'id'])->on('mentions')->restrictOnDelete();
            $table->foreign(['source_id', 'object_mention_id'])
                ->references(['source_id', 'id'])->on('mentions')->restrictOnDelete();
        });

        Schema::create('derived_claim_proposal_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('proposal_id')->constrained('derived_claim_proposals')->cascadeOnDelete();
            $table->string('status', 32);
            $table->unsignedInteger('sequence');
            $table->string('actor', 200);
            $table->uuid('evidence_state_id')->nullable();
            $table->uuid('accepted_claim_id')->nullable();
            $table->timestampTz('recorded_at');
            $table->unique(['proposal_id', 'sequence'], 'derived_proposal_decision_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('derived_claim_proposal_decisions');
        Schema::dropIfExists('derived_claim_proposals');
    }
};

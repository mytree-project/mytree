<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_revisions', function (Blueprint $table): void {
            $table->uuid('revision_id')->nullable()->after('id');
        });

        foreach (DB::table('source_revisions')->select('id')->orderBy('id')->get() as $row) {
            DB::table('source_revisions')
                ->where('id', $row->id)
                ->update(['revision_id' => (string) Str::uuid()]);
        }

        Schema::table('source_revisions', function (Blueprint $table): void {
            $table->unique('revision_id');
        });

        Schema::table('evidence_state_source_revisions', function (Blueprint $table): void {
            $table->uuid('source_revision_id')->nullable();
        });

        $references = DB::table('evidence_state_source_revisions')
            ->select('evidence_state_id', 'source_id', 'revision_number')
            ->get();

        foreach ($references as $reference) {
            $revisionId = DB::table('source_revisions')
                ->where('source_id', $reference->source_id)
                ->where('revision_number', $reference->revision_number)
                ->value('revision_id');

            if (! is_string($revisionId) || $revisionId === '') {
                throw new RuntimeException('Cannot backfill EvidenceState SourceRevision identity.');
            }

            DB::table('evidence_state_source_revisions')
                ->where('evidence_state_id', $reference->evidence_state_id)
                ->where('source_id', $reference->source_id)
                ->update(['source_revision_id' => $revisionId]);
        }

        Schema::table('evidence_state_source_revisions', function (Blueprint $table): void {
            $table->index('source_revision_id');
            $table->foreign('source_revision_id')
                ->references('revision_id')
                ->on('source_revisions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('evidence_state_source_revisions', function (Blueprint $table): void {
            $table->dropForeign(['source_revision_id']);
            $table->dropIndex(['source_revision_id']);
            $table->dropColumn('source_revision_id');
        });

        Schema::table('source_revisions', function (Blueprint $table): void {
            $table->dropUnique(['revision_id']);
            $table->dropColumn('revision_id');
        });
    }
};

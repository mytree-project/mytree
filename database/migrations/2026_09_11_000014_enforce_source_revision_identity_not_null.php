<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('source_revisions')->whereNull('revision_id')->exists()) {
            throw new RuntimeException('Cannot enforce SourceRevision identity while null revision IDs remain.');
        }

        if (DB::table('evidence_state_source_revisions')->whereNull('source_revision_id')->exists()) {
            throw new RuntimeException('Cannot enforce EvidenceState SourceRevision identity while null references remain.');
        }

        Schema::table('source_revisions', function (Blueprint $table): void {
            $table->uuid('revision_id')->nullable(false)->change();
        });

        Schema::table('evidence_state_source_revisions', function (Blueprint $table): void {
            $table->uuid('source_revision_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('evidence_state_source_revisions', function (Blueprint $table): void {
            $table->uuid('source_revision_id')->nullable()->change();
        });

        Schema::table('source_revisions', function (Blueprint $table): void {
            $table->uuid('revision_id')->nullable()->change();
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentions', function (Blueprint $table): void {
            $table->unique(['source_id', 'id']);
        });

        Schema::table('source_assets', function (Blueprint $table): void {
            $table->unique(['source_id', 'id']);
        });

        Schema::create('claims', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_id')->constrained('sources')->cascadeOnDelete();
            $table->unsignedInteger('schema_version');
            $table->uuid('subject_mention_id');
            $table->string('predicate_key', 120);
            $table->unsignedInteger('predicate_schema_version');
            $table->uuid('object_mention_id')->nullable();
            $table->text('value_payload')->nullable();
            $table->text('qualifiers_payload');
            $table->text('raw_text')->nullable();
            $table->text('origin_payload');
            $table->string('transcription_certainty', 64);
            $table->unsignedInteger('transcription_certainty_schema_version');
            $table->string('interpretation_certainty', 64);
            $table->unsignedInteger('interpretation_certainty_schema_version');
            $table->timestamps();

            $table->foreign(['source_id', 'subject_mention_id'])
                ->references(['source_id', 'id'])
                ->on('mentions')
                ->restrictOnDelete();
            $table->foreign(['source_id', 'object_mention_id'])
                ->references(['source_id', 'id'])
                ->on('mentions')
                ->restrictOnDelete();
            $table->unique(['source_id', 'id']);
            $table->index(['source_id', 'subject_mention_id']);
            $table->index(['source_id', 'predicate_key']);
        });

        Schema::create('source_locators', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_id')->constrained('sources')->cascadeOnDelete();
            $table->uuid('claim_id');
            $table->uuid('source_asset_id')->nullable();
            $table->unsignedInteger('schema_version');
            $table->string('locator_type', 64);
            $table->text('value_payload');
            $table->timestamps();

            $table->foreign(['source_id', 'claim_id'])
                ->references(['source_id', 'id'])
                ->on('claims')
                ->cascadeOnDelete();
            $table->foreign(['source_id', 'source_asset_id'])
                ->references(['source_id', 'id'])
                ->on('source_assets')
                ->restrictOnDelete();
            $table->index(['source_id', 'claim_id']);
            $table->index(['source_id', 'locator_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_locators');
        Schema::dropIfExists('claims');

        Schema::table('source_assets', function (Blueprint $table): void {
            $table->dropUnique(['source_id', 'id']);
        });

        Schema::table('mentions', function (Blueprint $table): void {
            $table->dropUnique(['source_id', 'id']);
        });
    }
};

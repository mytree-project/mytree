<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_documents', function (Blueprint $table): void {
            $table->unsignedInteger('source_date_ordinal')->nullable()->index();
        });

        Schema::create('search_document_dates', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('source_id')->constrained('search_documents', 'source_id')->cascadeOnDelete();
            $table->string('field');
            $table->unsignedInteger('lower_ordinal');
            $table->unsignedInteger('upper_ordinal');
            $table->uuid('claim_revision_id')->nullable();

            $table->index(['source_id', 'lower_ordinal', 'upper_ordinal']);
            $table->index('claim_revision_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_document_dates');
        Schema::table('search_documents', function (Blueprint $table): void {
            $table->dropColumn('source_date_ordinal');
        });
    }
};

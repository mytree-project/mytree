<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_variant_datasets', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->longText('payload');
            $table->char('payload_hash', 64);
            $table->unsignedInteger('revision');
            $table->string('changed_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_variant_datasets');
    }
};

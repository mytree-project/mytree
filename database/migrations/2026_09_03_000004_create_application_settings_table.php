<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('section', 80);
            $table->string('key', 120);
            $table->string('value_type', 32);
            $table->unsignedInteger('schema_version');
            $table->text('value');
            $table->unsignedBigInteger('revision')->default(1);
            $table->char('value_hash', 64);
            $table->string('changed_by')->nullable();
            $table->timestamps();

            $table->unique(['section', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_settings');
    }
};

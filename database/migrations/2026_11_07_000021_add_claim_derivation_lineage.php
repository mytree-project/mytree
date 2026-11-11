<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table): void {
            $table->longText('derivation_payload')->nullable();
            $table->string('derivation_rule_id', 160)->nullable();
            $table->string('derivation_output_key', 200)->nullable();

            // Direct Claims have NULL keys and can repeat their predicate.
            // A Source may have only one current Derived Claim per logical rule output.
            $table->unique(
                ['source_id', 'derivation_rule_id', 'derivation_output_key'],
                'claims_derived_rule_output_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table): void {
            $table->dropUnique('claims_derived_rule_output_unique');
            $table->dropColumn([
                'derivation_payload',
                'derivation_rule_id',
                'derivation_output_key',
            ]);
        });
    }
};

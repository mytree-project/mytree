<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Search;

use App\Application\Search\SearchVariantDatasetStore;
use Illuminate\Support\Facades\DB;

final class EloquentSearchVariantDatasetStore implements SearchVariantDatasetStore
{
    public function read(string $datasetId): ?string
    {
        $data = DB::table('search_variant_datasets')->where('id', $datasetId)->value('payload');

        return $data === null ? null : (string) $data;
    }

    public function replace(string $datasetId, string $canonicalJson, ?string $changedBy): void
    {
        DB::transaction(static function () use ($datasetId, $canonicalJson, $changedBy): void {
            $row = DB::table('search_variant_datasets')->where('id', $datasetId)->lockForUpdate()->first();
            $hash = hash('sha256', $canonicalJson);

            if ($row !== null && hash_equals((string) $row->payload_hash, $hash)) {
                return;
            }

            $values = [
                'payload' => $canonicalJson,
                'payload_hash' => $hash,
                'revision' => $row === null ? 1 : ((int) $row->revision) + 1,
                'changed_by' => $changedBy,
                'updated_at' => now(),
            ];
            if ($row === null) {
                DB::table('search_variant_datasets')->insert([
                    'id' => $datasetId,
                    'created_at' => now(),
                    ...$values,
                ]);
            } else {
                DB::table('search_variant_datasets')->where('id', $datasetId)->update($values);
            }
        });
    }
}

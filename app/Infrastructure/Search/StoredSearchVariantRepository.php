<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Application\Search\SearchVariantDatasetStore;
use MyTree\NameProcessing\Contracts\VariantRepositoryInterface;
use MyTree\NameProcessing\Domain\VariantDataset;
use MyTree\NameProcessing\Variants\JsonVariantRepository;
use MyTree\NameProcessing\Variants\VariantDatasetJsonCodec;

final readonly class StoredSearchVariantRepository implements VariantRepositoryInterface
{
    public function __construct(
        private SearchVariantDatasetStore $store,
        private JsonVariantRepository $seed,
        private VariantDatasetJsonCodec $codec,
    ) {}

    public function load(string $datasetId): VariantDataset
    {
        $stored = $this->store->read($datasetId);

        return $stored === null
            ? $this->seed->load($datasetId)
            : $this->codec->decode($stored, $datasetId);
    }
}

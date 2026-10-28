<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Application\Search\SearchNameType;
use App\Application\Search\SearchVariantCandidate;
use App\Application\Search\SearchVariantDatasetStore;
use App\Application\Search\SearchVariantDictionary;
use MyTree\NameProcessing\Contracts\NameNormalizerInterface;
use MyTree\NameProcessing\Contracts\ProfileRepositoryInterface;
use MyTree\NameProcessing\Domain\NameInput;
use MyTree\NameProcessing\Domain\NameType;
use MyTree\NameProcessing\Variants\NameVariantResolver;
use MyTree\NameProcessing\Variants\VariantDatasetJsonCodec;

final readonly class NameProcessingSearchVariantDictionary implements SearchVariantDictionary
{
    public function __construct(
        private StoredSearchVariantRepository $repository,
        private SearchVariantDatasetStore $store,
        private VariantDatasetJsonCodec $codec,
        private ProfileRepositoryInterface $profiles,
        private NameNormalizerInterface $normalizer,
        private string $profileId,
    ) {}

    public function export(): string
    {
        return $this->codec->encode($this->repository->load($this->datasetId()));
    }

    public function import(string $json, ?string $changedBy = null): void
    {
        // Decode and re-encode through the package contract before any persistence mutation.
        $dataset = $this->codec->decode($json, $this->datasetId());
        $this->store->replace($dataset->id, $this->codec->encode($dataset), $changedBy);
    }

    public function variants(string $value, SearchNameType $type): array
    {
        $packageType = match ($type) {
            SearchNameType::GivenName => NameType::GivenName,
            SearchNameType::Surname => NameType::Surname,
            SearchNameType::PlaceName => NameType::PlaceName,
            SearchNameType::Other => NameType::Other,
        };
        $resolver = new NameVariantResolver($this->profiles, $this->repository, $this->normalizer);
        $result = $resolver->resolve(new NameInput($value, type: $packageType), $this->profileId);
        $dataset = $this->repository->load($this->datasetId());
        $signature = hash('sha256', $this->codec->encode($dataset));

        $candidates = [];
        foreach ($result->results as $entry) {
            $groupId = $entry->metadata['group_id'] ?? '';
            $candidates[] = new SearchVariantCandidate(
                value: $entry->value,
                type: $type,
                groupId: is_string($groupId) ? $groupId : '',
                datasetId: $dataset->id,
                datasetVersion: $dataset->version,
                datasetSignature: $signature,
            );
        }

        return $candidates;
    }

    private function datasetId(): string
    {
        $profile = $this->profiles->get('variants', $this->profileId);

        return (string) $profile->config['dataset'];
    }
}

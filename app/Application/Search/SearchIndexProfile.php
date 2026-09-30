<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\Acquisition\PredicateKey;

final readonly class SearchIndexProfile
{
    public const SCHEMA_VERSION = 1;

    /** @var list<PredicateKey> */
    public array $searchablePredicates;

    /** @param list<PredicateKey> $searchablePredicates */
    public function __construct(
        array $searchablePredicates,
        public string $processingSignature,
    ) {
        $this->searchablePredicates = $searchablePredicates;
    }

    public static function m5Foundation(): self
    {
        return new self(
            searchablePredicates: [
                PredicateKey::PersonGivenName,
                PredicateKey::PersonSurname,
                PredicateKey::PlaceName,
            ],
            processingSignature: 'direct-values-v1',
        );
    }

    public function contains(PredicateKey $key): bool
    {
        return in_array($key, $this->searchablePredicates, true);
    }

    public function signature(): string
    {
        $predicates = array_map(
            static fn (PredicateKey $key): string => $key->value,
            $this->searchablePredicates,
        );
        sort($predicates, SORT_STRING);

        return hash('sha256', json_encode([
            'schema_version' => self::SCHEMA_VERSION,
            'searchable_predicates' => $predicates,
            'processing_signature' => $this->processingSignature,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}

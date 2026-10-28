<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Application\Acquisition\CaptureEvidenceStateForSource;
use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Search\SearchMatchType;
use App\Application\Search\SearchRequest;
use App\Application\Search\SearchSources;
use App\Application\Search\SearchVariantDictionary;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MyTree\NameProcessing\Exception\InvalidResourceException;
use Tests\TestCase;

final class MaintainedSearchVariantDictionaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_mapping_affects_the_next_search_without_rebuilding_projection(): void
    {
        $source = $this->sourceWithClaim(PredicateKey::PersonGivenName, 'Peter');
        $search = app(SearchSources::class);
        $dictionary = app(SearchVariantDictionary::class);

        self::assertCount(0, $search->handle(new SearchRequest('Piotr'))->results);

        $dictionary->import($this->dataset([
            $this->group('peter', 'given_name', ['Peter', 'Piotr']),
        ]));

        $page = $search->handle(new SearchRequest('Piotr'));
        self::assertCount(1, $page->results);
        self::assertSame($source, $page->results[0]->sourceId->value);
        self::assertSame(SearchMatchType::DictionaryVariant, $page->results[0]->matchReasons[0]->type);
        self::assertSame('Peter', $page->results[0]->matchReasons[0]->matchedValue);
        self::assertSame('Piotr', $page->results[0]->matchReasons[0]->queryTerm);
        self::assertSame('given-names.v1', $page->results[0]->matchReasons[0]->datasetId);
        self::assertSame('1.1.0', $page->results[0]->matchReasons[0]->datasetVersion);
        self::assertSame(64, strlen((string) $page->results[0]->matchReasons[0]->datasetSignature));
        self::assertSame('peter', $page->results[0]->matchReasons[0]->variantGroupId);
        self::assertNotNull($page->results[0]->matchReasons[0]->claimRevisionId);
    }

    public function test_place_mapping_uses_place_field_and_not_an_unrelated_surname(): void
    {
        $place = $this->sourceWithClaim(PredicateKey::PlaceName, 'Posen');
        $this->sourceWithClaim(PredicateKey::PersonSurname, 'Posen');

        app(SearchVariantDictionary::class)->import($this->dataset([
            $this->group('poznan', 'place_name', ['Poznań', 'Posen']),
        ]));

        $results = app(SearchSources::class)->handle(new SearchRequest('Poznań'))->results;
        self::assertCount(1, $results);
        self::assertSame($place, $results[0]->sourceId->value);
        self::assertSame(SearchMatchType::DictionaryVariant, $results[0]->matchReasons[0]->type);
    }

    public function test_native_export_roundtrips_and_invalid_import_does_not_replace_working_copy(): void
    {
        $dictionary = app(SearchVariantDictionary::class);
        $dictionary->import($this->dataset([
            $this->group('peter', 'given_name', ['Peter', 'Piotr']),
        ]));
        $export = $dictionary->export();
        self::assertStringContainsString('"schema": "mytree.name-variants.v1"', $export);
        $dictionary->import($export);
        self::assertSame($export, $dictionary->export());

        try {
            $dictionary->import('{"schema":"unsupported","id":"given-names.v1","version":"1","groups":[]}');
            self::fail('Malformed dataset must be rejected.');
        } catch (InvalidResourceException) {
            self::assertSame($export, $dictionary->export());
        }
    }

    /** @param list<array<string, mixed>> $groups */
    private function dataset(array $groups): string
    {
        return json_encode([
            'schema' => 'mytree.name-variants.v1',
            'id' => 'given-names.v1',
            'version' => '1.1.0',
            'groups' => $groups,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** @param list<string> $values
     *  @return array<string, mixed>
     */
    private function group(string $id, string $type, array $values): array
    {
        return [
            'id' => $id,
            'variants' => array_map(static fn (string $value): array => [
                'value' => $value,
                'language' => null,
                'script' => null,
                'type' => $type,
                'metadata' => [],
            ], $values),
        ];
    }

    private function sourceWithClaim(PredicateKey $predicate, string $value): string
    {
        $source = app(CreateSource::class)->handle(
            new SourceType('civil.birth'),
            new SourceMetadata(['title' => 'record']),
        );
        $mention = app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: new MentionKind($predicate === PredicateKey::PlaceName ? 'place' : 'person'),
            localKey: 'mention-1',
        );
        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $mention->id,
            predicate: PredicateVocabulary::get($predicate),
            value: new TextClaimValue($value),
        );
        app(CaptureEvidenceStateForSource::class)->capture($source->id);

        return $source->id->value;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\GetEvidenceState;
use App\Application\Acquisition\ListClaimRevisions;
use App\Application\Acquisition\LoadSourceDraft;
use App\Domain\Acquisition\AgeClaimValue;
use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimRevision;
use App\Domain\Acquisition\DateClaimValue;
use App\Domain\Acquisition\EvidenceStateId;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\SourceLinguisticRepresentationRelation;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use App\Filament\Pages\Acquisition\SourceEditor;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class SourceWorkspaceStructuredFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_structured_fields_round_trip_mentions_typed_claims_and_event_claims_through_one_nested_editor(): void
    {
        $source = app(CreateSource::class)->handle(new SourceType('civil.birth'));

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->fillForm([
                'mentions' => [
                    [
                        'id' => null,
                        'kind' => MentionKind::PERSON,
                        'local_key' => 'person.child',
                        'role' => 'child',
                        'display_label' => 'Jan Kowalski',
                        'raw_data_json' => '{"descriptor":"włościanin ze wsi X"}',
                        'claims' => [
                            $this->literalRow(
                                PredicateKey::PersonOccupation->value,
                                'włościanin',
                                effectiveTime: [
                                    'raw' => '1890–1895',
                                    'kind' => 'range',
                                    'from' => '1890',
                                    'to' => '1895',
                                ],
                            ),
                            $this->literalRow(
                                PredicateKey::PersonBirthDate->value,
                                '3 II 1891',
                                expressionKind: 'exact',
                                from: '1891-02-03',
                            ),
                            $this->objectRow(PredicateKey::PersonResidence->value, 'place.birth'),
                        ],
                    ],
                    [
                        'id' => null,
                        'kind' => MentionKind::PERSON,
                        'local_key' => 'person.witness',
                        'role' => 'witness',
                        'display_label' => 'Piotr Nowak',
                        'raw_data_json' => '{}',
                        'claims' => [],
                    ],
                    [
                        'id' => null,
                        'kind' => MentionKind::PLACE,
                        'local_key' => 'place.birth',
                        'role' => null,
                        'display_label' => 'Wieś X',
                        'raw_data_json' => '{}',
                        'claims' => [],
                    ],
                    [
                        'id' => null,
                        'kind' => MentionKind::EVENT,
                        'local_key' => 'event.birth.1',
                        'role' => 'birth',
                        'display_label' => 'Birth record event',
                        'raw_data_json' => '{}',
                        'claims' => [
                            $this->literalRow(
                                PredicateKey::EventDate->value,
                                '3 II 1891',
                                expressionKind: 'exact',
                                from: '1891-02-03',
                            ),
                            $this->objectRow(PredicateKey::EventPlace->value, 'place.birth'),
                            $this->objectRow(PredicateKey::EventChild->value, 'person.child'),
                            $this->objectRow(PredicateKey::EventWitness->value, 'person.witness'),
                        ],
                    ],
                ],
            ], 'evidenceForm')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $draft = app(LoadSourceDraft::class)->handle($source->id);
        self::assertCount(4, $draft->current->mentions);
        self::assertCount(7, $draft->current->claims);

        $child = $this->mentionByLocalKey($draft->current->mentions, 'person.child');
        self::assertSame(['descriptor' => 'włościanin ze wsi X'], $child->rawData->toArray());

        $occupation = $this->claimByPredicate($draft->current->claims, PredicateKey::PersonOccupation);
        self::assertInstanceOf(TextClaimValue::class, $occupation->value);
        self::assertSame('włościanin', $occupation->value->raw());
        self::assertSame('1890–1895', $occupation->qualifiers->effectiveTime?->raw());

        $birthDate = $this->claimByPredicate($draft->current->claims, PredicateKey::PersonBirthDate);
        self::assertInstanceOf(DateClaimValue::class, $birthDate->value);
        self::assertSame('1891-02-03', $birthDate->value->from->toIsoString());

        $event = $this->mentionByLocalKey($draft->current->mentions, 'event.birth.1');
        self::assertSame(MentionKind::EVENT, $event->kind->key);
        self::assertSame(4, count(array_filter(
            $draft->current->claims,
            static fn (Claim $claim): bool => $claim->subjectMentionId->value === $event->id->value,
        )));

        $this->assertDatabaseCount('mention_revisions', 4);
        $this->assertDatabaseCount('claim_revisions', 7);
        $this->assertDatabaseCount('evidence_states', 1);
    }

    public function test_source_recorded_linguistic_forms_round_trip_and_remain_exact_in_history(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->fillForm([
                'mentions' => [[
                    'id' => null,
                    'kind' => MentionKind::PERSON,
                    'local_key' => 'person.child',
                    'role' => 'child',
                    'display_label' => 'Peter Kowalski',
                    'raw_data_json' => '{}',
                    'claims' => [
                        $this->literalRow(
                            PredicateKey::PersonGivenName->value,
                            'Peter',
                            sourceRepresentations: [[
                                'value' => 'Piotr',
                                'language' => 'pl',
                                'script' => 'Latn',
                                'relation' => SourceLinguisticRepresentationRelation::LanguageEquivalent->value,
                                'source_locator_ids' => [],
                            ]],
                        ),
                    ],
                ]],
            ], 'evidenceForm')
            ->call('save')
            ->assertHasNoFormErrors();

        $firstDraft = app(LoadSourceDraft::class)->handle($source->id);
        $person = $this->mentionByLocalKey($firstDraft->current->mentions, 'person.child');
        $claim = $this->claimByPredicate($firstDraft->current->claims, PredicateKey::PersonGivenName);

        self::assertSame('Peter', $claim->value?->raw());
        self::assertCount(1, $claim->sourceLinguisticRepresentations);
        self::assertSame('Piotr', $claim->sourceLinguisticRepresentations[0]->value);
        self::assertSame('pl', $claim->sourceLinguisticRepresentations[0]->language);
        self::assertSame(
            SourceLinguisticRepresentationRelation::LanguageEquivalent,
            $claim->sourceLinguisticRepresentations[0]->relation,
        );

        $firstEvidenceStateId = DB::table('evidence_states')
            ->orderBy('recorded_at')
            ->value('id');
        self::assertIsString($firstEvidenceStateId);

        $revisionCount = count(app(ListClaimRevisions::class)->handle($source->id, $claim->id));
        $evidenceCount = DB::table('evidence_states')->count();

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->call('save')
            ->assertHasNoFormErrors();

        self::assertSame($revisionCount, count(app(ListClaimRevisions::class)->handle($source->id, $claim->id)));
        self::assertSame($evidenceCount, DB::table('evidence_states')->count());

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->fillForm([
                'mentions' => [[
                    'id' => $person->id->value,
                    'kind' => MentionKind::PERSON,
                    'local_key' => 'person.child',
                    'role' => 'child',
                    'display_label' => 'Peter Kowalski',
                    'raw_data_json' => '{}',
                    'claims' => [[
                        ...$this->literalRow(
                            PredicateKey::PersonGivenName->value,
                            'Peter',
                            sourceRepresentations: [[
                                'value' => 'Pierre',
                                'language' => 'fr',
                                'script' => 'Latn',
                                'relation' => SourceLinguisticRepresentationRelation::Translation->value,
                                'source_locator_ids' => [],
                            ]],
                        ),
                        'claim_id' => $claim->id->value,
                    ]],
                ]],
            ], 'evidenceForm')
            ->call('save')
            ->assertHasNoFormErrors();

        $revisions = app(ListClaimRevisions::class)->handle($source->id, $claim->id);
        self::assertCount($revisionCount + 1, $revisions);
        self::assertSame('Piotr', $revisions[0]->reconstruct()->claim->sourceLinguisticRepresentations[0]->value);
        self::assertSame('Pierre', $revisions[count($revisions) - 1]->reconstruct()->claim->sourceLinguisticRepresentations[0]->value);

        $firstEvidence = app(GetEvidenceState::class)->get(new EvidenceStateId($firstEvidenceStateId));
        $firstHistoricalClaim = array_values(array_filter(
            $firstEvidence->claimRevisions,
            static fn (ClaimRevision $revision): bool => $revision->claimId->value === $claim->id->value,
        ));
        self::assertCount(1, $firstHistoricalClaim);
        self::assertSame(
            'Piotr',
            $firstHistoricalClaim[0]->reconstruct()->claim->sourceLinguisticRepresentations[0]->value,
        );

        $current = $this->claimByPredicate(
            app(LoadSourceDraft::class)->handle($source->id)->current->claims,
            PredicateKey::PersonGivenName,
        );
        self::assertSame('Pierre', $current->sourceLinguisticRepresentations[0]->value);
    }

    public function test_surname_place_and_occupation_source_recorded_forms_persist_as_claim_state(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->fillForm([
                'mentions' => [
                    [
                        'id' => null,
                        'kind' => MentionKind::PERSON,
                        'local_key' => 'person.1',
                        'role' => null,
                        'display_label' => 'Johann Schmidt',
                        'raw_data_json' => '{}',
                        'claims' => [
                            $this->literalRow(
                                PredicateKey::PersonSurname->value,
                                'Schmidt',
                                sourceRepresentations: [[
                                    'value' => 'Szmidt',
                                    'language' => 'pl',
                                    'script' => 'Latn',
                                    'relation' => SourceLinguisticRepresentationRelation::LanguageEquivalent->value,
                                    'source_locator_ids' => [],
                                ]],
                            ),
                            $this->literalRow(
                                PredicateKey::PersonOccupation->value,
                                'Arbeiter',
                                sourceRepresentations: [[
                                    'value' => 'robotnik',
                                    'language' => 'pl',
                                    'script' => 'Latn',
                                    'relation' => SourceLinguisticRepresentationRelation::Translation->value,
                                    'source_locator_ids' => [],
                                ]],
                            ),
                        ],
                    ],
                    [
                        'id' => null,
                        'kind' => MentionKind::PLACE,
                        'local_key' => 'place.1',
                        'role' => null,
                        'display_label' => 'Posen',
                        'raw_data_json' => '{}',
                        'claims' => [
                            $this->literalRow(
                                PredicateKey::PlaceName->value,
                                'Posen',
                                sourceRepresentations: [[
                                    'value' => 'Poznań',
                                    'language' => 'pl',
                                    'script' => 'Latn',
                                    'relation' => SourceLinguisticRepresentationRelation::LanguageEquivalent->value,
                                    'source_locator_ids' => [],
                                ]],
                            ),
                        ],
                    ],
                ],
            ], 'evidenceForm')
            ->call('save')
            ->assertHasNoFormErrors();

        $claims = app(LoadSourceDraft::class)->handle($source->id)->current->claims;

        self::assertSame(
            'Szmidt',
            $this->claimByPredicate($claims, PredicateKey::PersonSurname)
                ->sourceLinguisticRepresentations[0]->value,
        );
        self::assertSame(
            'robotnik',
            $this->claimByPredicate($claims, PredicateKey::PersonOccupation)
                ->sourceLinguisticRepresentations[0]->value,
        );
        self::assertSame(
            'Poznań',
            $this->claimByPredicate($claims, PredicateKey::PlaceName)
                ->sourceLinguisticRepresentations[0]->value,
        );
    }

    public function test_repeatable_claims_can_be_removed_inside_one_mention_without_touching_mention_raw_data(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->fillForm([
                'mentions' => [[
                    'id' => null,
                    'kind' => MentionKind::PERSON,
                    'local_key' => 'person.1',
                    'role' => null,
                    'display_label' => 'Person 1',
                    'raw_data_json' => '{"unclassified_descriptor":"однодворец"}',
                    'claims' => [
                        $this->literalRow(PredicateKey::PersonOccupation->value, 'rolnik'),
                        $this->literalRow(PredicateKey::PersonOccupation->value, 'kowal'),
                    ],
                ]],
            ], 'evidenceForm')
            ->call('save')
            ->assertHasNoFormErrors();

        $draft = app(LoadSourceDraft::class)->handle($source->id);
        $occupations = array_values(array_filter(
            $draft->current->claims,
            static fn (Claim $claim): bool => $claim->predicate->key === PredicateKey::PersonOccupation,
        ));
        self::assertCount(2, $occupations);
        $person = $this->mentionByLocalKey($draft->current->mentions, 'person.1');

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->fillForm([
                'mentions' => [[
                    'id' => $person->id->value,
                    'kind' => MentionKind::PERSON,
                    'local_key' => 'person.1',
                    'role' => null,
                    'display_label' => 'Person 1',
                    'raw_data_json' => '{"unclassified_descriptor":"однодворец"}',
                    'claims' => [[
                        ...$this->literalRow(
                            PredicateKey::PersonOccupation->value,
                            $occupations[0]->value?->raw() ?? 'rolnik',
                        ),
                        'claim_id' => $occupations[0]->id->value,
                    ]],
                ]],
            ], 'evidenceForm')
            ->call('save')
            ->assertHasNoFormErrors();

        $afterRemoval = app(LoadSourceDraft::class)->handle($source->id);
        self::assertCount(1, array_filter(
            $afterRemoval->current->claims,
            static fn (Claim $claim): bool => $claim->predicate->key === PredicateKey::PersonOccupation,
        ));
        self::assertSame(
            ['unclassified_descriptor' => 'однодворец'],
            $this->mentionByLocalKey($afterRemoval->current->mentions, 'person.1')->rawData->toArray(),
        );
    }

    public function test_typed_age_editor_round_trips_uncertainty_without_false_precision(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->fillForm([
                'mentions' => [[
                    'id' => null,
                    'kind' => MentionKind::PERSON,
                    'local_key' => 'person.1',
                    'role' => null,
                    'display_label' => null,
                    'raw_data_json' => '{}',
                    'claims' => [[
                        ...$this->literalRow(
                            PredicateKey::PersonAge->value,
                            'około 40 lat',
                            expressionKind: 'approximate',
                            from: '40',
                        ),
                        'age_unit' => 'years',
                    ]],
                ]],
            ], 'evidenceForm')
            ->call('save')
            ->assertHasNoFormErrors();

        $claim = $this->claimByPredicate(
            app(LoadSourceDraft::class)->handle($source->id)->current->claims,
            PredicateKey::PersonAge,
        );
        self::assertInstanceOf(AgeClaimValue::class, $claim->value);
        self::assertSame('approximate', $claim->value->kind->value);
        self::assertSame(40, $claim->value->from);
        self::assertSame('około 40 lat', $claim->value->raw());
    }

    public function test_one_spouse_claim_is_not_duplicated_for_the_object_mention(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->fillForm([
                'mentions' => [
                    [
                        'id' => null,
                        'kind' => MentionKind::PERSON,
                        'local_key' => 'person.jan',
                        'role' => null,
                        'display_label' => 'Jan',
                        'raw_data_json' => '{}',
                        'claims' => [
                            $this->objectRow(PredicateKey::PersonSpouse->value, 'person.anna'),
                        ],
                    ],
                    [
                        'id' => null,
                        'kind' => MentionKind::PERSON,
                        'local_key' => 'person.anna',
                        'role' => null,
                        'display_label' => 'Anna',
                        'raw_data_json' => '{}',
                        'claims' => [],
                    ],
                ],
            ], 'evidenceForm')
            ->call('save')
            ->assertHasNoFormErrors();

        $draft = app(LoadSourceDraft::class)->handle($source->id);
        $spouseClaims = array_values(array_filter(
            $draft->current->claims,
            static fn (Claim $claim): bool => $claim->predicate->key === PredicateKey::PersonSpouse,
        ));

        self::assertCount(1, $spouseClaims);

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->assertSee('Incoming relationship references')
            ->assertSee('Spouse ← Jan');
    }

    /**
     * @param  array{raw: string, kind: string, from: string, to?: string}|null  $effectiveTime
     * @param  list<array<string, mixed>>  $sourceRepresentations
     * @return array<string, mixed>
     */
    private function literalRow(
        string $fieldKey,
        string $raw,
        ?string $expressionKind = null,
        string|int|null $from = null,
        string|int|null $to = null,
        ?array $effectiveTime = null,
        array $sourceRepresentations = [],
    ): array {
        return [
            'claim_id' => null,
            'field_key' => $fieldKey,
            'object_local_key' => null,
            'value_raw' => $raw,
            'expression_kind' => $expressionKind,
            'value_from' => $from,
            'value_to' => $to,
            'age_unit' => null,
            'integer_value' => null,
            'boolean_value' => null,
            'enum_key' => null,
            'source_linguistic_representations' => $sourceRepresentations,
            'effective_time_raw' => $effectiveTime['raw'] ?? null,
            'effective_time_kind' => $effectiveTime['kind'] ?? null,
            'effective_time_from' => $effectiveTime['from'] ?? null,
            'effective_time_to' => $effectiveTime['to'] ?? null,
            'raw_text' => null,
            'transcription_certainty' => 'unspecified',
            'interpretation_certainty' => 'unspecified',
        ];
    }

    /** @return array<string, mixed> */
    private function objectRow(string $fieldKey, string $objectLocalKey): array
    {
        return [
            ...$this->literalRow($fieldKey, 'unused'),
            'value_raw' => null,
            'object_local_key' => $objectLocalKey,
        ];
    }

    /** @param  list<Mention>  $mentions */
    private function mentionByLocalKey(array $mentions, string $localKey): Mention
    {
        foreach ($mentions as $mention) {
            if ($mention->localKey === $localKey) {
                return $mention;
            }
        }

        self::fail(sprintf('Mention "%s" was not found.', $localKey));
    }

    /** @param  list<Claim>  $claims */
    private function claimByPredicate(array $claims, PredicateKey $predicate): Claim
    {
        foreach ($claims as $claim) {
            if ($claim->predicate->key === $predicate) {
                return $claim;
            }
        }

        self::fail(sprintf('Claim "%s" was not found.', $predicate->value));
    }
}

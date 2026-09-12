<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\LoadSourceDraft;
use App\Domain\Acquisition\AgeClaimValue;
use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\DateClaimValue;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use App\Filament\Pages\Acquisition\StructuredFieldsEditor;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class StructuredFieldsEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_structured_fields_round_trip_mentions_typed_claims_effective_time_and_event_context(): void
    {
        $source = app(CreateSource::class)->handle(new SourceType('civil.birth'));

        Livewire::test(StructuredFieldsEditor::class, ['source' => $source->id->value])
            ->fillForm([
                'mentions' => [
                    [
                        'id' => null,
                        'kind' => MentionKind::PERSON,
                        'local_key' => 'person.child',
                        'role' => 'child',
                        'display_label' => 'Jan Kowalski',
                        'raw_data_json' => '{"descriptor":"włościanin ze wsi X"}',
                    ],
                    [
                        'id' => null,
                        'kind' => MentionKind::PERSON,
                        'local_key' => 'person.witness',
                        'role' => 'witness',
                        'display_label' => 'Piotr Nowak',
                        'raw_data_json' => '{}',
                    ],
                    [
                        'id' => null,
                        'kind' => MentionKind::PLACE,
                        'local_key' => 'place.birth',
                        'role' => null,
                        'display_label' => 'Wieś X',
                        'raw_data_json' => '{}',
                    ],
                ],
                'fields' => [
                    $this->literalRow(
                        PredicateKey::PersonOccupation->value,
                        'person.child',
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
                        'person.child',
                        '3 II 1891',
                        expressionKind: 'exact',
                        from: '1891-02-03',
                    ),
                    $this->objectRow(
                        PredicateKey::PersonResidence->value,
                        'person.child',
                        'place.birth',
                    ),
                ],
                'event_contexts' => [
                    [
                        'id' => null,
                        'local_key' => 'event.birth.1',
                        'role' => 'birth',
                        'display_label' => 'Birth record event',
                        'raw_data_json' => '{}',
                        'claims' => [
                            $this->literalRow(
                                PredicateKey::EventDate->value,
                                null,
                                '3 II 1891',
                                expressionKind: 'exact',
                                from: '1891-02-03',
                            ),
                            $this->objectRow(PredicateKey::EventPlace->value, null, 'place.birth'),
                            $this->objectRow(PredicateKey::EventChild->value, null, 'person.child'),
                            $this->objectRow(PredicateKey::EventWitness->value, null, 'person.witness'),
                        ],
                    ],
                ],
            ])
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
        $this->assertDatabaseCount('evidence_states', 2);
    }

    public function test_repeatable_fields_can_coexist_and_one_can_be_removed_without_touching_mention_raw_data(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());

        Livewire::test(StructuredFieldsEditor::class, ['source' => $source->id->value])
            ->fillForm([
                'mentions' => [[
                    'id' => null,
                    'kind' => MentionKind::PERSON,
                    'local_key' => 'person.1',
                    'role' => null,
                    'display_label' => 'Person 1',
                    'raw_data_json' => '{"unclassified_descriptor":"однодворец"}',
                ]],
                'fields' => [
                    $this->literalRow(PredicateKey::PersonOccupation->value, 'person.1', 'rolnik'),
                    $this->literalRow(PredicateKey::PersonOccupation->value, 'person.1', 'kowal'),
                ],
                'event_contexts' => [],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $draft = app(LoadSourceDraft::class)->handle($source->id);
        $occupations = array_values(array_filter(
            $draft->current->claims,
            static fn (Claim $claim): bool => $claim->predicate->key === PredicateKey::PersonOccupation,
        ));
        self::assertCount(2, $occupations);

        Livewire::test(StructuredFieldsEditor::class, ['source' => $source->id->value])
            ->fillForm([
                'fields' => [[
                    'claim_id' => $occupations[0]->id->value,
                    ...$this->literalRow(PredicateKey::PersonOccupation->value, 'person.1', $occupations[0]->value?->raw() ?? 'rolnik'),
                ]],
            ])
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

        Livewire::test(StructuredFieldsEditor::class, ['source' => $source->id->value])
            ->fillForm([
                'mentions' => [[
                    'id' => null,
                    'kind' => MentionKind::PERSON,
                    'local_key' => 'person.1',
                    'role' => null,
                    'display_label' => null,
                    'raw_data_json' => '{}',
                ]],
                'fields' => [[
                    ...$this->literalRow(
                        PredicateKey::PersonAge->value,
                        'person.1',
                        'około 40 lat',
                        expressionKind: 'approximate',
                        from: '40',
                    ),
                    'age_unit' => 'years',
                ]],
                'event_contexts' => [],
            ])
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

    /**
     * @param  array{raw: string, kind: string, from: string, to?: string}|null  $effectiveTime
     * @return array<string, mixed>
     */
    private function literalRow(
        string $fieldKey,
        ?string $subjectLocalKey,
        string $raw,
        ?string $expressionKind = null,
        string|int|null $from = null,
        string|int|null $to = null,
        ?array $effectiveTime = null,
    ): array {
        return [
            'claim_id' => null,
            'field_key' => $fieldKey,
            'subject_local_key' => $subjectLocalKey,
            'object_local_key' => null,
            'value_raw' => $raw,
            'expression_kind' => $expressionKind,
            'value_from' => $from,
            'value_to' => $to,
            'age_unit' => null,
            'integer_value' => null,
            'boolean_value' => null,
            'enum_key' => null,
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
    private function objectRow(string $fieldKey, ?string $subjectLocalKey, string $objectLocalKey): array
    {
        return [
            ...$this->literalRow($fieldKey, $subjectLocalKey, 'unused'),
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

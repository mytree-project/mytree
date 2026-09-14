<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\CreateSourceLocator;
use App\Application\Acquisition\LoadSourceDraft;
use App\Domain\Acquisition\ClaimOrigin;
use App\Domain\Acquisition\ClaimOriginKind;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\QuotedFragmentLocatorValue;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use App\Filament\Pages\Acquisition\SourceEditor;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class M4ConsistencyRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_basic_editor_preserves_existing_claim_origin_and_locator_when_claim_value_changes(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $person = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.subject');
        $claim = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonOccupation),
            value: new TextClaimValue('rolnik'),
            origin: new ClaimOrigin(
                kind: ClaimOriginKind::ProviderObservation,
                providerKey: 'provider-a',
                providerRecordId: 'record-17',
                sourceUrl: 'https://example.test/index/record-17',
                requestContext: ['query' => 'kowalski'],
                parserVersion: '1.0.0',
            ),
        );
        $locator = app(CreateSourceLocator::class)->handle(
            $source->id,
            $claim->id,
            new QuotedFragmentLocatorValue('rolnik'),
        );

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->fillForm([
                'fields' => [[
                    'claim_id' => $claim->id->value,
                    'field_key' => PredicateKey::PersonOccupation->value,
                    'subject_local_key' => 'person.subject',
                    'object_local_key' => null,
                    'value_raw' => 'gospodarz',
                    'expression_kind' => null,
                    'value_from' => null,
                    'value_to' => null,
                    'age_unit' => null,
                    'integer_value' => null,
                    'boolean_value' => null,
                    'enum_key' => null,
                    'effective_time_raw' => null,
                    'effective_time_kind' => null,
                    'effective_time_from' => null,
                    'effective_time_to' => null,
                    'raw_text' => null,
                    'transcription_certainty' => 'unspecified',
                    'interpretation_certainty' => 'unspecified',
                ]],
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $draft = app(LoadSourceDraft::class)->handle($source->id);
        self::assertCount(1, $draft->current->claims);
        self::assertCount(1, $draft->current->locators);

        $updatedClaim = $draft->current->claims[0];
        self::assertSame($claim->id->value, $updatedClaim->id->value);
        self::assertSame('gospodarz', $updatedClaim->value?->raw());
        self::assertSame(ClaimOriginKind::ProviderObservation, $updatedClaim->origin->kind);
        self::assertSame('provider-a', $updatedClaim->origin->providerKey);
        self::assertSame('record-17', $updatedClaim->origin->providerRecordId);

        $preservedLocator = $draft->current->locators[0];
        self::assertSame($locator->id->value, $preservedLocator->id->value);
        self::assertSame($claim->id->value, $preservedLocator->claimId->value);
        self::assertSame('quoted_fragment', $preservedLocator->value->type()->value);
        self::assertSame('rolnik', $preservedLocator->value->toArray()['quote']);
    }
}

<?php

declare(strict_types=1);

use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Settings\Application\ApplicationSettings;
use App\Application\Settings\Application\UpdateApplicationSettings;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\Webpage;
use Tests\TestCase;

function openSourceWorkspaceForBrowserTest(string $sourceId): Webpage|AwaitableWebpage
{
    app(UpdateApplicationSettings::class)->handle(
        new ApplicationSettings(defaultLocale: 'pl'),
        changedBy: null,
    );

    $user = User::factory()->admin()->create([
        'email' => 'browser-admin@example.test',
    ]);

    /** @var TestCase $test */
    $test = test();
    $test->actingAs($user);

    return visit('/admin/acquisition/source?source='.urlencode($sourceId))
        ->assertPresent('[data-source-workspace]')
        ->assertPresent('[data-mentions-claims-editor]');
}

/** @param array<string, mixed> $fields */
function setSourceWorkspaceBrowserRowFields(
    Webpage|AwaitableWebpage $page,
    string $collectionPath,
    string $matchField,
    string $matchValue,
    array $fields,
): void {
    $script = strtr(<<<'JS'
        (async () => {
            const workspace = document.querySelector('[data-source-workspace]');

            if (! workspace) {
                throw new Error('Source workspace root was not found.');
            }

            let root = workspace;

            while (root && ! root.hasAttribute('wire:id')) {
                root = root.parentElement;
            }

            if (! root) {
                throw new Error('Livewire component root for Source workspace was not found.');
            }

            const wire = Livewire.find(root.getAttribute('wire:id'));

            if (! wire || typeof wire['$set'] !== 'function') {
                throw new Error('Livewire Source workspace $set API is unavailable.');
            }

            const collectionPath = __COLLECTION_PATH__;
            const matchField = __MATCH_FIELD__;
            const matchValue = __MATCH_VALUE__;
            const fields = __FIELDS__;
            let collection = wire;

            for (const segment of collectionPath.split('.')) {
                collection = collection?.[segment];
            }

            if (! collection || typeof collection !== 'object') {
                throw new Error(`Livewire collection ${collectionPath} was not found.`);
            }

            const entry = Object.entries(collection).find(([, row]) => row && row[matchField] === matchValue);

            if (! entry) {
                throw new Error(`No ${collectionPath} row matched ${matchField}=${matchValue}.`);
            }

            const rowKey = entry[0];

            for (const [field, value] of Object.entries(fields)) {
                await wire['$set'](`${collectionPath}.${rowKey}.${field}`, value);
            }
        })()
        JS, [
        '__COLLECTION_PATH__' => json_encode($collectionPath, JSON_THROW_ON_ERROR),
        '__MATCH_FIELD__' => json_encode($matchField, JSON_THROW_ON_ERROR),
        '__MATCH_VALUE__' => json_encode($matchValue, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        '__FIELDS__' => json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);

    $page->script($script);
}

function submitSourceWorkspaceBrowserForm(Webpage|AwaitableWebpage $page): Webpage|AwaitableWebpage
{
    return $page->click('form.source-acquisition-form button[type="submit"]');
}

it('routes Mention JSON syntax errors to Mentions and Claims and keeps entered state', function (): void {
    $source = app(CreateSource::class)->handle(SourceType::generic());
    app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::person(),
        localKey: 'person_valentin',
        role: 'declarant',
        displayLabel: 'Valentin Wiśniewski',
    );

    $page = openSourceWorkspaceForBrowserTest($source->id->value);

    setSourceWorkspaceBrowserRowFields(
        $page,
        collectionPath: 'evidenceData.mentions',
        matchField: 'local_key',
        matchValue: 'person_valentin',
        fields: ['raw_data_json' => '{"broken":'],
    );

    $page
        ->assertScript(
            "Array.from(document.querySelectorAll('input')).some((input) => input.value === 'person_valentin')",
            true,
        )
        ->assertScript(
            "Array.from(document.querySelectorAll('textarea')).some((textarea) => textarea.value === '{\"broken\":')",
            true,
        );

    submitSourceWorkspaceBrowserForm($page)
        ->assertSee('Błąd składni JSON w Mention nr 1 (person_valentin).')
        ->assertVisible('[data-source-workspace-save-errors]')
        ->assertScript(
            "document.querySelector('[data-mentions-claims-editor]').classList.contains('source-workspace-error-region')",
            true,
        )
        ->assertScript(
            "document.querySelectorAll('details.source-workspace-details')[0].classList.contains('source-workspace-error-region')",
            false,
        )
        ->assertScript(
            "Array.from(document.querySelectorAll('input')).some((input) => input.value === 'person_valentin')",
            true,
        )
        ->assertScript("document.querySelector('.source-workspace-error-details').open", false)
        ->click('.source-workspace-error-details summary')
        ->assertVisible('.source-workspace-error-details code')
        ->assertScript("document.querySelector('.source-workspace-error-details code').textContent.trim()", 'Syntax error')
        ->assertNoJavaScriptErrors();
});

it('routes Claim subject errors to Mentions and Claims instead of Source details', function (): void {
    $source = app(CreateSource::class)->handle(SourceType::generic());
    $person = app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::person(),
        localKey: 'person_subject',
    );
    app(CreateClaim::class)->handle(
        sourceId: $source->id,
        subjectMentionId: $person->id,
        predicate: PredicateVocabulary::get(PredicateKey::PersonOccupation),
        value: new TextClaimValue('rolnik'),
    );

    $page = openSourceWorkspaceForBrowserTest($source->id->value);

    setSourceWorkspaceBrowserRowFields(
        $page,
        collectionPath: 'evidenceData.fields',
        matchField: 'field_key',
        matchValue: PredicateKey::PersonOccupation->value,
        fields: ['subject_local_key' => 'missing-person'],
    );

    $page
        ->assertScript(
            "Array.from(document.querySelectorAll('input')).some((input) => input.value === 'missing-person')",
            true,
        )
        ->assertScript(
            "Array.from(document.querySelectorAll('input')).some((input) => input.value === 'rolnik')",
            true,
        );

    submitSourceWorkspaceBrowserForm($page)
        ->assertSee('Claim nr 1 odwołuje się do nieistniejącego Mention jako podmiotu.')
        ->assertVisible('[data-source-workspace-save-errors]')
        ->assertScript(
            "document.querySelector('[data-mentions-claims-editor]').classList.contains('source-workspace-error-region')",
            true,
        )
        ->assertScript(
            "document.querySelectorAll('details.source-workspace-details')[0].classList.contains('source-workspace-error-region')",
            false,
        )
        ->assertScript(
            "Array.from(document.querySelectorAll('input')).some((input) => input.value === 'missing-person')",
            true,
        )
        ->assertNoJavaScriptErrors();
});

it('routes metadata value errors to Source details instead of Mentions and Claims', function (): void {
    $source = app(CreateSource::class)->handle(
        SourceType::generic(),
        metadata: new SourceMetadata(['record_number' => 1]),
    );
    $page = openSourceWorkspaceForBrowserTest($source->id->value);

    setSourceWorkspaceBrowserRowFields(
        $page,
        collectionPath: 'data.metadata',
        matchField: 'key',
        matchValue: 'record_number',
        fields: ['value' => 'abc'],
    );

    $page->assertScript(
        "Array.from(document.querySelectorAll('input')).some((input) => input.value === 'abc')",
        true,
    );

    submitSourceWorkspaceBrowserForm($page)
        ->assertSee('Pole metadanych nr 1 wymaga prawidłowej liczby całkowitej.')
        ->assertVisible('[data-source-workspace-save-errors]')
        ->assertScript(
            "document.querySelectorAll('details.source-workspace-details')[0].classList.contains('source-workspace-error-region')",
            true,
        )
        ->assertScript(
            "document.querySelector('[data-mentions-claims-editor]').classList.contains('source-workspace-error-region')",
            false,
        )
        ->assertScript(
            "Array.from(document.querySelectorAll('input')).some((input) => input.value === 'abc')",
            true,
        )
        ->assertNoJavaScriptErrors();
});

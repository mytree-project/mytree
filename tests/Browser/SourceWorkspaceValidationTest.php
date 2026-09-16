<?php

declare(strict_types=1);

use App\Application\Acquisition\CreateSource;
use App\Application\Settings\Application\ApplicationSettings;
use App\Application\Settings\Application\UpdateApplicationSettings;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\SourceType;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Pest\Browser\Api\Webpage;
use Tests\TestCase;

function openSourceWorkspaceForBrowserTest(string $sourceId): Webpage
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

/** @param array<mixed> $value */
function setSourceWorkspaceBrowserState(Webpage $page, string $property, array $value): void
{
    $propertyJson = json_encode($property, JSON_THROW_ON_ERROR);
    $valueJson = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $page->script(<<<JS
        (async () => {
            const workspace = document.querySelector('[data-source-workspace]');
            const root = workspace.closest('[wire\\:id]');
            const component = Livewire.find(root.getAttribute('wire:id'));
            await component.set({$propertyJson}, {$valueJson});
        })()
        JS);
}

function submitSourceWorkspaceBrowserForm(Webpage $page): Webpage
{
    return $page->click('form.source-acquisition-form button[type="submit"]');
}

it('routes Mention JSON syntax errors to Mentions and Claims and keeps entered state', function (): void {
    $source = app(CreateSource::class)->handle(SourceType::generic());
    $page = openSourceWorkspaceForBrowserTest($source->id->value);

    setSourceWorkspaceBrowserState($page, 'evidenceData.mentions', [[
        'id' => null,
        'kind' => MentionKind::PERSON,
        'local_key' => 'person_valentin',
        'role' => 'declarant',
        'display_label' => 'Valentin Wiśniewski',
        'raw_data_json' => '{"broken":',
    ]]);

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
    $page = openSourceWorkspaceForBrowserTest($source->id->value);

    setSourceWorkspaceBrowserState($page, 'evidenceData.fields', [[
        'claim_id' => null,
        'presentation_origin' => null,
        'field_key' => PredicateKey::PersonOccupation->value,
        'subject_local_key' => 'missing-person',
        'object_local_key' => null,
        'value_raw' => 'rolnik',
        'transcription_certainty' => 'unspecified',
        'interpretation_certainty' => 'unspecified',
    ]]);

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
    $source = app(CreateSource::class)->handle(SourceType::generic());
    $page = openSourceWorkspaceForBrowserTest($source->id->value);

    setSourceWorkspaceBrowserState($page, 'data.metadata', [[
        'key' => 'record_number',
        'type' => 'integer',
        'value' => 'abc',
    ]]);

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
